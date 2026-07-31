<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TripValidation;
use FedaPay\FedaPay;
use FedaPay\Transaction as FedaTransaction;
use FedaPay\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private const COMMISSION_RATE = 0.10;

    // =========================================================================
    //  POST /api/bookings/{uuid}/pay
    //  Initier le paiement Mobile Money (FedaPay)
    // =========================================================================

    public function initiate(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'provider'     => ['required', 'string', 'in:mtn,moov,celtiis'],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]{8,12}$/'],
        ]);

        $booking = Booking::with(['trip', 'payment'])
            ->where('uuid', $uuid)
            ->where('passenger_id', $request->user()->id)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->payment_status === 'escrow_locked') {
            return $this->apiResponse(false, 'Le paiement a déjà été effectué pour cette réservation.', [
                'payment_uuid' => $booking->payment?->uuid,
            ], 409);
        }

        if (in_array($booking->status, ['rejected', 'cancelled'], true)) {
            return $this->apiResponse(false, 'Cette réservation a été annulée ou refusée.', [], 422);
        }

        $trip = $booking->trip;

        // ── Montant basé sur le prix proraté (distance passager) × places ─────
        // calculated_price = GeoHelper::calculatePassengerPrice() enregistré à la réservation
        $grossAmount = (int) $booking->calculated_price * (int) $booking->seats_booked;
        $commission  = (int) round($grossAmount * self::COMMISSION_RATE);
        $netAmount   = $grossAmount - $commission;

        // ── Profil passager pour FedaPay ──────────────────────────────────────
        $passenger = $request->user()->load('profile');
        $profile   = $passenger->profile;
        $firstName = $profile?->first_name ?? 'Passager';
        $lastName  = $profile?->last_name  ?? '';
        $email     = $profile?->email      ?? ($passenger->phone . '@minizon.app');

        // ── Initialiser le SDK FedaPay ────────────────────────────────────────
        FedaPay::setApiKey(config('fedapay.secret_key'));
        FedaPay::setEnvironment(config('fedapay.environment'));

        // ── Créer la transaction FedaPay ──────────────────────────────────────
        try {
            $fedaTx = FedaTransaction::create([
                'description'  => "Réservation Minizon — {$trip->departure_city} → {$trip->arrival_city}",
                'amount'       => $grossAmount,
                'currency'     => ['iso' => 'XOF'],
                'callback_url' => config('fedapay.callback_url'),
                'customer'     => [
                    'firstname'    => $firstName,
                    'lastname'     => $lastName,
                    'email'        => $email,
                    'phone_number' => [
                        'number'  => $validated['phone_number'],
                        'country' => 'bj',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('FedaPay transaction create failed', [
                'booking_uuid' => $booking->uuid,
                'error'        => $e->getMessage(),
            ]);
            return $this->apiResponse(false, 'Impossible d\'initier le paiement. Réessayez.', [], 502);
        }

        // ── Générer le token de paiement (URL checkout WebView Flutter) ────────
        try {
            $tokenObj   = $fedaTx->generateToken();
            $paymentUrl = $tokenObj->url;
        } catch (\FedaPay\Error\Base $e) {
            Log::error('FedaPay generateToken failed', [
                'booking_uuid' => $booking->uuid,
                'fedapay_id'   => $fedaTx->id ?? null,
                'http_status'  => $e->getHttpStatus(),
                'feda_message' => $e->getErrorMessage(),
            ]);
            return $this->apiResponse(false, 'Impossible de générer le lien de paiement. Réessayez.', [
                'detail' => $e->getErrorMessage() ?: $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            Log::error('FedaPay generateToken unexpected error', [
                'booking_uuid' => $booking->uuid,
                'error'        => $e->getMessage(),
            ]);
            return $this->apiResponse(false, 'Erreur inattendue lors de la génération du paiement.', [], 502);
        }

        // ── Persister le paiement (pending jusqu'à confirmation webhook) ───────
        $txnRef = 'TXN-' . strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));

        $payment = DB::transaction(function () use (
            $booking, $validated, $grossAmount, $commission, $netAmount, $request, $fedaTx, $txnRef
        ) {
            // Supprimer l'éventuel paiement pending précédent pour cette réservation
            Payment::where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->delete();

            return Payment::create([
                'booking_id'            => $booking->id,
                'user_id'               => $request->user()->id,
                'provider'              => $validated['provider'],
                'phone_number'          => $validated['phone_number'],
                'gross_amount'          => $grossAmount,
                'commission_amount'     => $commission,
                'net_amount'            => $netAmount,
                'status'                => 'pending',
                'idempotency_key'       => 'booking_' . $booking->id . '_' . time(),
                'transaction_reference' => $txnRef,
                'provider_reference'    => (string) ($fedaTx->id ?? ''),
            ]);
        });

        Log::info('FedaPay payment initiated', [
            'booking_uuid' => $booking->uuid,
            'payment_uuid' => $payment->uuid,
            'fedapay_id'   => $fedaTx->id,
            'amount'       => $grossAmount,
        ]);

        return $this->apiResponse(true, 'Paiement initié. Complétez le paiement sur la page sécurisée.', [
            'payment_uuid' => $payment->uuid,
            'booking_uuid' => $booking->uuid,
            'amount'       => $grossAmount,
            'status'       => 'pending',
            'payment_url'  => $paymentUrl,
            'fedapay_id'   => $fedaTx->id ?? null,
        ]);
    }

    // =========================================================================
    //  POST /api/payments/webhook/fedapay  (public — sécurisé par signature HMAC)
    //  FedaPay appelle cet endpoint quand le statut d'une transaction change
    //  (paiement PIN confirmé, décliné, etc.)
    // =========================================================================

    public function webhook(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('X-Fedapay-Signature', '');
        $secret    = config('fedapay.webhook_secret');

        // ── Vérification de signature ──────────────────────────────────────────
        if ($secret) {
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $secret);
            } catch (\FedaPay\Error\SignatureVerification $e) {
                Log::warning('FedaPay webhook: signature invalide', [
                    'sig_header' => $sigHeader,
                    'error'      => $e->getMessage(),
                ]);
                return response('Signature invalide', 400);
            } catch (\UnexpectedValueException $e) {
                Log::warning('FedaPay webhook: payload JSON invalide', ['error' => $e->getMessage()]);
                return response('Payload invalide', 400);
            }
        } else {
            // Pas de secret configuré — parser directement (sandbox / dev)
            Log::warning('FedaPay webhook: FEDAPAY_WEBHOOK_SECRET non configuré — signature non vérifiée');
            $data = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
                return response('Payload invalide', 400);
            }
            $event = (object) $data;
        }

        $eventType = $event->type       ?? null;
        $objectId  = $event->object_id  ?? null;

        Log::info('FedaPay webhook reçu', ['type' => $eventType, 'object_id' => $objectId]);

        // Ignorer les événements non liés aux transactions
        if (! str_starts_with((string) $eventType, 'transaction.')) {
            return response('OK', 200);
        }

        // ── Retrouver le paiement par l'ID FedaPay (provider_reference) ────────
        $payment = Payment::with('booking')
            ->where('provider_reference', (string) $objectId)
            ->first();

        if (! $payment) {
            Log::warning('FedaPay webhook: paiement introuvable', [
                'fedapay_id' => $objectId,
                'event_type' => $eventType,
            ]);
            // Retourner 200 pour éviter les relances FedaPay
            return response('OK', 200);
        }

        // ── Traitement selon le type d'événement ──────────────────────────────
        DB::transaction(function () use ($payment, $eventType) {
            $booking = $payment->booking;

            if ($eventType === 'transaction.approved') {
                // Paiement confirmé par PIN → verrouiller les fonds en escrow
                $payment->update(['status' => 'locked']);

                if ($booking) {
                    $booking->update(['payment_status' => 'escrow_locked']);

                    // Créer l'enregistrement d'escrow (si pas déjà existant)
                    TripValidation::firstOrCreate(
                        ['booking_id' => $booking->id],
                        [
                            'trip_id'             => $booking->trip_id,
                            'passenger_confirmed' => false,
                            'auto_release_at'     => now()->addHours(24),
                            'status'              => 'waiting',
                        ]
                    );

                    Log::info('FedaPay: paiement verrouillé en escrow', [
                        'payment_uuid' => $payment->uuid,
                        'booking_uuid' => $booking->uuid,
                        'amount'       => $payment->gross_amount,
                    ]);
                }
            } elseif (in_array($eventType, ['transaction.declined', 'transaction.cancelled'], true)) {
                // Paiement refusé ou annulé
                $payment->update(['status' => 'failed']);

                Log::info('FedaPay: paiement échoué', [
                    'payment_uuid' => $payment->uuid,
                    'event_type'   => $eventType,
                ]);
            }
        });

        return response('OK', 200);
    }

    // =========================================================================
    //  GET /api/payments/{uuid}
    //  Polling du statut (toutes les 3 s côté Flutter)
    // =========================================================================

    public function status(Request $request, string $uuid): JsonResponse
    {
        $payment = Payment::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $payment) {
            return $this->apiResponse(false, 'Paiement introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Statut du paiement.', [
            'payment_uuid'    => $payment->uuid,
            'status'          => $payment->status,
            'gross_amount'    => $payment->gross_amount,
            'provider'        => $payment->provider,
            'transaction_ref' => $payment->transaction_reference,
            'booking_uuid'    => $payment->booking?->uuid,
        ]);
    }

    // =========================================================================
    //  POST /api/bookings/{uuid}/confirm-arrival
    //  Passager confirme son arrivée → libère l'escrow immédiatement
    // =========================================================================

    public function confirmArrival(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with(['payment', 'tripValidation'])
            ->where('uuid', $uuid)
            ->where('passenger_id', $request->user()->id)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->payment_status !== 'escrow_locked') {
            return $this->apiResponse(false, 'Aucun paiement en escrow à libérer pour cette réservation.', [], 422);
        }

        DB::transaction(function () use ($booking) {
            $now = now();

            $booking->update(['passenger_confirmed_at' => $now]);

            if ($booking->tripValidation) {
                $booking->tripValidation->update([
                    'passenger_confirmed'    => true,
                    'passenger_confirmed_at' => $now,
                    'status'                 => 'released',
                ]);
            }

            if ($booking->payment) {
                $booking->payment->update(['status' => 'success']);
            }

            $booking->update(['payment_status' => 'released_to_driver']);
        });

        return $this->apiResponse(true, 'Arrivée confirmée. Les fonds ont été libérés au conducteur.');
    }
}
