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
    private const SERVICE_FEE_RATE  = 0.05;  // +5%  ajouté au passager
    private const DRIVER_COMMISSION = 0.10;  // -10% prélevé sur la part conducteur

    // =========================================================================
    //  POST /api/trips/{uuid}/bookings
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
                                new OA\Property(property: 'price_total',            type: 'integer', example: 630,  description: 'Montant total à payer (base + 5% service fee)'),
                                new OA\Property(property: 'calculated_price',       type: 'integer', example: 600,  description: 'Prix proraté par place selon distance (XOF)'),
                                new OA\Property(property: 'service_fee',            type: 'integer', example: 30,   description: 'Frais de service Minizon 5% ajoutés au passager (XOF)'),
                                new OA\Property(property: 'passenger_distance_km',  type: 'number',  format: 'float', example: 127.4),
                                new OA\Property(property: 'trip_distance_km',       type: 'number',  format: 'float', example: 420.0),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Trajet introuvable',                             content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Réservation déjà existante sur ce trajet',      content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
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

        $existing = Booking::where('trip_id', $trip->id)
            ->where('passenger_id', $request->user()->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->first();

        if ($existing) {
            return $this->apiResponse(false, 'Vous avez déjà une réservation active pour ce trajet.', [
                'booking_uuid' => $existing->uuid,
            ], 409);
        }

        // ── Calcul distance & prix ────────────────────────────────────────────
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

        $base       = $calculatedPrice * $seatsRequested;
        $serviceFee = (int) round($base * self::SERVICE_FEE_RATE);
        $totalPrice = $base + $serviceFee;

        $booking = DB::transaction(function () use (
            $trip, $request, $validated, $seatsRequested,
            $passengerDistanceKm, $calculatedPrice, $serviceFee, $totalPrice
        ) {
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
                'service_fee'          => $serviceFee,
                'total_price'          => $totalPrice,
                'status'               => 'pending',
                'payment_status'       => 'unpaid',
            ]);

            $trip->decrement('available_seats', $seatsRequested);

            return $booking;
        });

        $this->notifyDriver($trip, $booking);

        return $this->apiResponse(true, 'Réservation créée.', [
            'booking_uuid'          => $booking->uuid,
            'booking_mode'          => $trip->booking_mode ?? 'approval',
            'price_total'           => $totalPrice,
            'calculated_price'      => $calculatedPrice,
            'service_fee'           => $serviceFee,
            'passenger_distance_km' => round($passengerDistanceKm, 2),
            'trip_distance_km'      => round($tripDistanceKm, 2),
        ], 201);
    }

    // =========================================================================
    //  POST /api/bookings/{uuid}/pay
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/pay',
        operationId: 'passengerBookingPay',
        summary: 'Initier le paiement (MoMo ou Carte bancaire)',
        description: "Crée le paiement en escrow via FedaPay. Supports :\n- **MTN / Moov / Celtiis** → `phone_number` requis, paiement push MoMo\n- **card** → `phone_number` non requis, paiement via page checkout FedaPay (WebView)\n\nDans tous les cas, `payment_url` est retourné — ouvrir en WebView Flutter pour finaliser.",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['provider'],
                properties: [
                    new OA\Property(
                        property: 'provider',
                        type: 'string',
                        enum: ['mtn', 'moov', 'celtiis', 'card'],
                        example: 'mtn',
                        description: 'Mode de paiement. `card` = carte bancaire via checkout FedaPay.'
                    ),
                    new OA\Property(
                        property: 'phone_number',
                        type: 'string',
                        nullable: true,
                        example: '97000000',
                        description: 'Requis pour mtn/moov/celtiis. Non requis pour card. Format : 8–12 chiffres sans indicatif.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paiement initié',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Paiement initié.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'payment_uuid',    type: 'string',  format: 'uuid'),
                                new OA\Property(property: 'booking_uuid',    type: 'string',  format: 'uuid'),
                                new OA\Property(property: 'amount',          type: 'integer', example: 630, description: 'Montant total débité au passager (XOF)'),
                                new OA\Property(property: 'provider',        type: 'string',  example: 'mtn'),
                                new OA\Property(property: 'status',          type: 'string',  example: 'pending'),
                                new OA\Property(property: 'payment_url',     type: 'string',  example: 'https://checkout.fedapay.com/payment-page/...', description: 'Ouvrir en WebView Flutter. Valable pour tous les providers.'),
                                new OA\Property(property: 'fedapay_id',      type: 'integer', nullable: true, example: 12345),
                                new OA\Property(property: 'requires_webview',type: 'boolean', example: true, description: 'Toujours true — le passager complète le paiement sur la page FedaPay.'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Réservation introuvable',              content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Paiement déjà effectué',               content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Réservation non éligible au paiement', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function pay(Request $request, string $uuid): JsonResponse
    {
        $isMomo = in_array($request->input('provider'), ['mtn', 'moov', 'celtiis'], true);

        $validated = $request->validate([
            'provider'     => ['required', 'string', 'in:mtn,moov,celtiis,card'],
            'phone_number' => $isMomo
                ? ['required', 'string', 'regex:/^[0-9]{8,12}$/']
                : ['nullable', 'string'],
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

        $trip = $booking->trip;

        // base = prix proraté × places (sans frais)
        $base             = (int) $booking->calculated_price * (int) $booking->seats_booked;
        // Passager paie base + 5% service fee
        $grossAmount      = $booking->total_price ?: ($base + ($booking->service_fee ?? 0));
        // Minizon prélève 10% sur la part conducteur
        $driverCommission = (int) round($base * self::DRIVER_COMMISSION);
        // Conducteur reçoit 90% du base
        $netAmount        = $base - $driverCommission;
        // Commission totale Minizon = service_fee (5% passager) + 10% conducteur
        $commissionAmount = ($booking->service_fee ?? 0) + $driverCommission;

        // ── Profil du passager pour FedaPay ──────────────────────────────────
        $passenger = $request->user()->load('profile');
        $profile   = $passenger->profile;
        $firstName = $profile?->first_name ?? 'Passager';
        $lastName  = $profile?->last_name  ?? '';
        $email     = $profile?->email      ?? ($passenger->phone . '@minizon.app');

        FedaPay::setApiKey(config('fedapay.secret_key'));
        FedaPay::setEnvironment(config('fedapay.environment'));

        // Données client FedaPay — phone_number uniquement pour MoMo
        $customer = [
            'firstname' => $firstName,
            'lastname'  => $lastName,
            'email'     => $email,
        ];
        if ($isMomo && ! empty($validated['phone_number'])) {
            $customer['phone_number'] = [
                'number'  => $validated['phone_number'],
                'country' => 'bj',
            ];
        }

        try {
            $fedaTx = FedaTransaction::create([
                'description'  => "Réservation Minizon — {$trip->departure_city} → {$trip->arrival_city}",
                'amount'       => $grossAmount,
                'currency'     => ['iso' => 'XOF'],
                'callback_url' => config('fedapay.callback_url'),
                'customer'     => $customer,
            ]);
        } catch (\Throwable $e) {
            Log::error('FedaPay transaction create failed', ['booking_uuid' => $booking->uuid, 'error' => $e->getMessage()]);
            return $this->apiResponse(false, 'Impossible d\'initier le paiement. Réessayez.', [], 502);
        }

        try {
            $tokenObj   = $fedaTx->generateToken();
            $paymentUrl = $tokenObj->url;
        } catch (\FedaPay\Error\Base $e) {
            Log::error('FedaPay generateToken failed', [
                'booking_uuid' => $booking->uuid,
                'fedapay_id'   => $fedaTx->id ?? null,
                'http_status'  => $e->getHttpStatus(),
                'feda_message' => $e->getErrorMessage(),
                'feda_errors'  => $e->getErrors(),
            ]);
            return $this->apiResponse(false, 'Impossible de générer le lien de paiement. Réessayez.', [
                'detail' => $e->getErrorMessage() ?: $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            Log::error('FedaPay generateToken unexpected error', ['booking_uuid' => $booking->uuid, 'error' => $e->getMessage()]);
            return $this->apiResponse(false, 'Erreur inattendue lors de la génération du paiement.', [], 502);
        }

        $txnRef = 'TXN-' . strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));

        $payment = DB::transaction(function () use (
            $booking, $validated, $grossAmount, $commissionAmount, $netAmount, $request, $fedaTx, $txnRef
        ) {
            return Payment::create([
                'booking_id'            => $booking->id,
                'user_id'               => $request->user()->id,
                'provider'              => $validated['provider'],
                'phone_number'          => $validated['phone_number'],
                'gross_amount'          => $grossAmount,
                'commission_amount'     => $commissionAmount,
                'net_amount'            => $netAmount,
                'status'                => 'pending',
                'idempotency_key'       => 'booking_' . $booking->id . '_' . time(),
                'transaction_reference' => $txnRef,
                'provider_reference'    => (string) ($fedaTx->id ?? ''),
            ]);
        });

        return $this->apiResponse(true, 'Paiement initié. Complétez le paiement sur la page sécurisée.', [
            'payment_uuid'    => $payment->uuid,
            'booking_uuid'    => $booking->uuid,
            'amount'          => $grossAmount,
            'provider'        => $validated['provider'],
            'status'          => 'pending',
            'payment_url'     => $paymentUrl,
            'fedapay_id'      => $fedaTx->id ?? null,
            'requires_webview'=> true,
        ]);
    }

    // =========================================================================
    //  POST /api/bookings/{uuid}/cancel
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
            if ($booking->status === 'accepted' && $booking->trip) {
                $booking->trip->increment('available_seats', $booking->seats_booked);
            }
            $booking->update(['status' => 'cancelled']);
        });

        $this->notifyDriver($booking->trip, $booking, cancelled: true);

        return $this->apiResponse(true, 'Réservation annulée.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
        } catch (\Throwable) {}
    }
}
