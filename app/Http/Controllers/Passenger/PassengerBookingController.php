<?php

namespace App\Http\Controllers\Passenger;

use App\Helpers\GeoHelper;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Trip;
use FedaPay\FedaPay;
use FedaPay\Transaction as FedaTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class PassengerBookingController extends Controller
{
    private const COMMISSION_RATE = 0.10;

    // =========================================================================
    //  POST /api/trips/{uuid}/bookings
    //  Créer une réservation
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/bookings',
        operationId: 'passengerBookingStore',
        summary: 'Réserver un trajet',
        description: "Crée une réservation pour le passager authentifié. Retourne l'UUID de la réservation à transmettre à l'étape de paiement.\n\n**Flow :**\n1. Appeler cet endpoint → `booking_uuid`\n2. `POST /api/bookings/{uuid}/pay` → initier le paiement Mobile Money\n3. Naviguer vers `WaitingApprovalView`",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'seats_booked',
                    'pickup_city', 'pickup_neighborhood', 'pickup_address', 'pickup_latitude', 'pickup_longitude',
                    'dropoff_city', 'dropoff_neighborhood', 'dropoff_address', 'dropoff_latitude', 'dropoff_longitude',
                ],
                properties: [
                    new OA\Property(property: 'seats_booked',          type: 'integer', minimum: 1, example: 1),
                    new OA\Property(property: 'pickup_city',           type: 'string',  example: 'Cotonou'),
                    new OA\Property(property: 'pickup_neighborhood',   type: 'string',  example: 'Akpakpa'),
                    new OA\Property(property: 'pickup_address',        type: 'string',  example: 'Face pharmacie du centre'),
                    new OA\Property(property: 'pickup_latitude',       type: 'number',  format: 'float', example: 6.3654),
                    new OA\Property(property: 'pickup_longitude',      type: 'number',  format: 'float', example: 2.4183),
                    new OA\Property(property: 'dropoff_city',          type: 'string',  example: 'Parakou'),
                    new OA\Property(property: 'dropoff_neighborhood',  type: 'string',  example: 'Zongo'),
                    new OA\Property(property: 'dropoff_address',       type: 'string',  example: 'Carrefour étoile rouge'),
                    new OA\Property(property: 'dropoff_latitude',      type: 'number',  format: 'float', example: 9.3370),
                    new OA\Property(property: 'dropoff_longitude',     type: 'number',  format: 'float', example: 2.6280),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Réservation créée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Réservation créée.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'booking_uuid',           type: 'string',  format: 'uuid'),
                                new OA\Property(property: 'booking_mode',           type: 'string',  enum: ['instant', 'approval'], example: 'approval'),
                                new OA\Property(property: 'price_total',            type: 'integer', example: 1500, description: 'Prix total conducteur (seats × price_per_seat)'),
                                new OA\Property(property: 'calculated_price',       type: 'integer', example: 950,  description: 'Prix automatique calculé selon la distance du passager (XOF)'),
                                new OA\Property(property: 'passenger_distance_km',  type: 'number',  format: 'float', example: 127.4, description: 'Distance passager calculée par Haversine (km)'),
                                new OA\Property(property: 'trip_distance_km',       type: 'number',  format: 'float', example: 420.0, description: 'Distance totale du trajet principal (km)'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Trajet introuvable',                          content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Réservation déjà existante sur ce trajet',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Places insuffisantes ou trajet non réservable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'seats_booked'         => ['required', 'integer', 'min:1', 'max:10'],
            'pickup_city'          => ['required', 'string', 'max:100'],
            'pickup_neighborhood'  => ['required', 'string', 'max:100'],
            'pickup_address'       => ['required', 'string', 'max:500'],
            'pickup_latitude'      => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude'     => ['required', 'numeric', 'between:-180,180'],
            'dropoff_city'         => ['required', 'string', 'max:100'],
            'dropoff_neighborhood' => ['required', 'string', 'max:100'],
            'dropoff_address'      => ['required', 'string', 'max:500'],
            'dropoff_latitude'     => ['required', 'numeric', 'between:-90,90'],
            'dropoff_longitude'    => ['required', 'numeric', 'between:-180,180'],
        ]);

        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if (! $trip->is_published || $trip->status !== 'pending') {
            return $this->apiResponse(false, 'Ce trajet n\'est plus disponible à la réservation.', [], 422);
        }

        if ($trip->user_id === $request->user()->id) {
            return $this->apiResponse(false, 'Vous ne pouvez pas réserver votre propre trajet.', [], 422);
        }

        $seatsRequested = (int) $validated['seats_booked'];

        if ($trip->available_seats < $seatsRequested) {
            return $this->apiResponse(false, "Seulement {$trip->available_seats} place(s) disponible(s) sur ce trajet.", [], 422);
        }

        // Doublon — une réservation active existe déjà
        $existing = Booking::where('trip_id', $trip->id)
            ->where('passenger_id', $request->user()->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->first();

        if ($existing) {
            return $this->apiResponse(false, 'Vous avez déjà une réservation active pour ce trajet.', [
                'booking_uuid' => $existing->uuid,
            ], 409);
        }

        // ── Calcul de la distance et du prix automatique ─────────────────────
        $passengerDistanceKm = GeoHelper::haversineKm(
            $validated['pickup_latitude'],  $validated['pickup_longitude'],
            $validated['dropoff_latitude'], $validated['dropoff_longitude']
        );

        $tripDistanceKm = GeoHelper::haversineKm(
            (float) $trip->departure_latitude, (float) $trip->departure_longitude,
            (float) $trip->arrival_latitude,   (float) $trip->arrival_longitude
        );

        $calculatedPrice = GeoHelper::calculatePassengerPrice(
            $passengerDistanceKm,
            $tripDistanceKm,
            (int) $trip->price_per_seat
        );

        $booking = DB::transaction(function () use ($trip, $request, $validated, $seatsRequested, $passengerDistanceKm, $calculatedPrice) {
            $booking = Booking::create([
                'trip_id'              => $trip->id,
                'passenger_id'         => $request->user()->id,
                'seats_booked'         => $seatsRequested,
                'pickup_city'          => $validated['pickup_city'],
                'pickup_neighborhood'  => $validated['pickup_neighborhood'],
                'pickup_address'       => $validated['pickup_address'],
                'pickup_latitude'      => $validated['pickup_latitude'],
                'pickup_longitude'     => $validated['pickup_longitude'],
                'dropoff_city'         => $validated['dropoff_city'],
                'dropoff_neighborhood' => $validated['dropoff_neighborhood'],
                'dropoff_address'      => $validated['dropoff_address'],
                'dropoff_latitude'     => $validated['dropoff_latitude'],
                'dropoff_longitude'    => $validated['dropoff_longitude'],
                'passenger_distance_km'=> round($passengerDistanceKm, 2),
                'calculated_price'     => $calculatedPrice,
                'status'               => 'pending',
                'payment_status'       => 'unpaid',
            ]);

            // Bloquer les places immédiatement pour éviter la surréservation
            $trip->decrement('available_seats', $seatsRequested);

            return $booking;
        });

        // Notifier le conducteur d'une nouvelle demande
        $this->notifyDriver($trip, $booking);

        return $this->apiResponse(true, 'Réservation créée.', [
            'booking_uuid'          => $booking->uuid,
            'booking_mode'          => $trip->booking_mode ?? 'approval',
            'price_total'           => (int) $trip->price_per_seat * $seatsRequested,
            'calculated_price'      => $calculatedPrice,
            'passenger_distance_km' => round($passengerDistanceKm, 2),
            'trip_distance_km'      => round($tripDistanceKm, 2),
        ], 201);
    }

    // =========================================================================
    //  POST /api/bookings/{uuid}/pay
    //  Initier le paiement Mobile Money (FedaPay)
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/pay',
        operationId: 'passengerBookingPay',
        summary: 'Initier le paiement Mobile Money',
        description: 'Crée le paiement en escrow via FedaPay (MTN / Moov / Celtiis). La réservation passe en `escrow_locked` après succès.',
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['provider', 'phone_number'],
                properties: [
                    new OA\Property(property: 'provider',     type: 'string',  enum: ['mtn', 'moov', 'celtiis'], example: 'mtn'),
                    new OA\Property(property: 'phone_number', type: 'string',  example: '97000000', description: 'Numéro local béninois (8 chiffres sans indicatif)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paiement initié — escrow en attente de validation conducteur',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Paiement initié.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'payment_uuid', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'booking_uuid', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'amount',       type: 'integer', example: 1500),
                                new OA\Property(property: 'status',       type: 'string',  example: 'pending'),
                                new OA\Property(property: 'payment_url',  type: 'string',  example: 'https://checkout.fedapay.com/payment-page/...', description: 'URL FedaPay à ouvrir en WebView Flutter pour que le passager valide sur son téléphone Mobile Money.'),
                                new OA\Property(property: 'fedapay_id',   type: 'integer', nullable: true, example: 12345),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Réservation introuvable',        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Paiement déjà effectué',         content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Réservation non éligible au paiement', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function pay(Request $request, string $uuid): JsonResponse
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

        if ($booking->status === 'rejected' || $booking->status === 'cancelled') {
            return $this->apiResponse(false, 'Cette réservation a été annulée ou refusée.', [], 422);
        }

        $trip        = $booking->trip;
        $grossAmount = (int) $trip->price_per_seat * (int) $booking->seats_booked;
        $commission  = (int) round($grossAmount * self::COMMISSION_RATE);
        $netAmount   = $grossAmount - $commission;

        // ── Profil du passager pour FedaPay ──────────────────────────────────
        $passenger = $request->user()->load('profile');
        $profile   = $passenger->profile;
        $firstName = $profile?->first_name ?? 'Passager';
        $lastName  = $profile?->last_name  ?? '';
        $email     = $profile?->email      ?? ($passenger->phone . '@minizon.app');

        // ── Initialiser FedaPay ───────────────────────────────────────────────
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

        // ── Générer le token de paiement FedaPay (URL checkout Mobile Money) ──
        try {
            $tokenObj   = $fedaTx->generateToken();
            $paymentUrl = $tokenObj->url;
        } catch (\FedaPay\Error\Base $e) {
            Log::error('FedaPay generateToken failed', [
                'booking_uuid'  => $booking->uuid,
                'fedapay_id'    => $fedaTx->id ?? null,
                'http_status'   => $e->getHttpStatus(),
                'feda_message'  => $e->getErrorMessage(),
                'feda_errors'   => $e->getErrors(),
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

        // ── Persister le paiement en base (pending jusqu'au webhook) ─────────
        $txnRef = 'TXN-' . strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));

        $payment = DB::transaction(function () use (
            $booking, $validated, $grossAmount, $commission, $netAmount, $request, $fedaTx, $txnRef
        ) {
            $payment = Payment::create([
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

            // Le booking passe en escrow_locked seulement après confirmation webhook
            // Pour l'instant on garde unpaid — le webhook mettra à jour

            return $payment;
        });

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
    //  POST /api/bookings/{uuid}/cancel
    //  Annuler une réservation (depuis le passager)
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/cancel',
        operationId: 'passengerBookingCancel',
        summary: 'Annuler une réservation',
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réservation annulée'),
            new OA\Response(response: 404, description: 'Réservation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Annulation impossible',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with('trip')
            ->where('uuid', $uuid)
            ->where('passenger_id', $request->user()->id)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if (in_array($booking->status, ['cancelled', 'rejected'], true)) {
            return $this->apiResponse(false, 'Cette réservation est déjà annulée.', [], 422);
        }

        if ($booking->trip?->status === 'completed') {
            return $this->apiResponse(false, 'Impossible d\'annuler un trajet déjà terminé.', [], 422);
        }

        DB::transaction(function () use ($booking) {
            // Remettre les places disponibles si la réservation était acceptée
            if ($booking->status === 'accepted' && $booking->trip) {
                $booking->trip->increment('available_seats', $booking->seats_booked);
            }

            $booking->update(['status' => 'cancelled']);
        });

        $this->notifyDriver($booking->trip, $booking, cancelled: true);

        return $this->apiResponse(true, 'Réservation annulée.');
    }

    // -------------------------------------------------------------------------

    private function notifyDriver(?Trip $trip, Booking $booking, bool $cancelled = false): void
    {
        if (! $trip) return;

        try {
            $title = $cancelled ? 'Réservation annulée' : 'Nouvelle demande de réservation';
            $body  = $cancelled
                ? "Un passager a annulé sa réservation pour {$trip->departure_city} → {$trip->arrival_city}."
                : "Un passager souhaite réserver {$booking->seats_booked} place(s) pour {$trip->departure_city} → {$trip->arrival_city}.";

            DB::table('notifications')->insert([
                'id'              => (string) Str::uuid(),
                'type'            => $cancelled ? 'booking_cancelled' : 'booking_request',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $trip->user_id,
                'data'            => json_encode([
                    'title'        => $title,
                    'body'         => $body,
                    'booking_uuid' => $booking->uuid,
                    'trip_uuid'    => $trip->uuid,
                ]),
                'read_at'    => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // non-bloquant
        }
    }
}
