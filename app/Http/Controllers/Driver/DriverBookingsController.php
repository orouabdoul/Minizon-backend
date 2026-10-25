<?php

namespace App\Http\Controllers\Driver;

use App\Helpers\GeoHelper;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Trip;
use App\Notifications\BookingStatusChanged;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DriverBookingsController extends Controller
{
    // =========================================================================
    //  GET /api/driver/bookings
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/bookings',
        operationId: 'driverBookingsList',
        summary: 'Liste des réservations reçues (conducteur)',
        description: 'Retourne toutes les réservations en attente ou acceptées pour les trajets du conducteur authentifié.',
        tags: ['🚗 Driver — Réservations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des réservations',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Réservations.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'bookings',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'status', type: 'string', enum: ['pending', 'accepted', 'rejected', 'cancelled']),
                                            new OA\Property(property: 'seats_booked', type: 'integer'),
                                            new OA\Property(property: 'passenger_name', type: 'string'),
                                            new OA\Property(property: 'passenger_phone', type: 'string', nullable: true),
                                            new OA\Property(property: 'trip_uuid', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'origin', type: 'string'),
                                            new OA\Property(property: 'destination', type: 'string'),
                                            new OA\Property(property: 'departure_time', type: 'string', format: 'date-time'),
                                            // ── Prise en charge — commune → arrondissement → quartier → point précis ──
                                            new OA\Property(property: 'pickup_city', type: 'string', example: 'Cotonou'),
                                            new OA\Property(property: 'pickup_arrondissement', type: 'string', example: '6ème Arrondissement', nullable: true),
                                            new OA\Property(property: 'pickup_neighborhood', type: 'string', example: 'Akpakpa', nullable: true),
                                            new OA\Property(property: 'pickup_address', type: 'string', example: 'Face pharmacie du centre'),
                                            new OA\Property(property: 'pickup_latitude', type: 'number', format: 'float', example: 6.3654),
                                            new OA\Property(property: 'pickup_longitude', type: 'number', format: 'float', example: 2.4183),
                                            // ── Dépôt — commune → arrondissement → quartier → point précis ──
                                            new OA\Property(property: 'dropoff_city', type: 'string', example: 'Parakou'),
                                            new OA\Property(property: 'dropoff_arrondissement', type: 'string', example: '1er Arrondissement', nullable: true),
                                            new OA\Property(property: 'dropoff_neighborhood', type: 'string', example: 'Zongo', nullable: true),
                                            new OA\Property(property: 'dropoff_address', type: 'string', example: 'Carrefour étoile rouge'),
                                            new OA\Property(property: 'dropoff_latitude', type: 'number', format: 'float', example: 9.3370),
                                            new OA\Property(property: 'dropoff_longitude', type: 'number', format: 'float', example: 2.6280),
                                            new OA\Property(property: 'passenger_distance_km', type: 'number', format: 'float', example: 127.4),
                                            new OA\Property(property: 'calculated_price', type: 'integer', example: 950),
                                            new OA\Property(property: 'payment_status', type: 'string', enum: ['unpaid', 'escrow_locked', 'released_to_driver', 'refunded'], example: 'escrow_locked'),
                                            new OA\Property(property: 'is_paid', type: 'boolean', example: true),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function driverBookings(): JsonResponse
    {
        $driver = auth()->user();

        $tripIds = Trip::where('user_id', $driver->id)
            ->pluck('id');

        $bookings = Booking::with(['passenger.profile', 'trip', 'conversation'])
            ->whereIn('trip_id', $tripIds)
            ->whereIn('status', ['pending', 'accepted'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Booking $b) {
                $profile = $b->passenger?->profile;
                $name = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? '')) ?: 'Passager';

                $passengerId   = $b->passenger_id;
                $avgRating     = Review::where('reviewee_id', $passengerId)
                    ->where('status', 'visible')
                    ->avg('rating');
                $tripsCount    = Booking::where('passenger_id', $passengerId)
                    ->whereIn('status', ['accepted', 'completed'])
                    ->count();

                // Prix proraté : recalcul on-the-fly si les distances sont disponibles.
                // Corrige les anciennes réservations où calculated_price était stocké
                // au tarif plein (car trip.distance_km était inconnu à la création).
                $tripKm    = (float) ($b->trip?->distance_km ?? 0);
                $passKm    = (float) ($b->passenger_distance_km ?? 0);
                $tarif     = (int) ($b->trip?->price_per_seat ?? 0);
                $unitPrice = ($tripKm > 0 && $passKm > 0 && $tarif > 0)
                    ? GeoHelper::calculatePassengerPrice($passKm, $tripKm, $tarif)
                    : (int) ($b->calculated_price ?? $tarif);
                $sub       = $unitPrice * (int) $b->seats_booked;
                $fee       = (int) ($b->service_fee ?? (int) round($sub * 0.05));
                $total     = $b->total_price ?? ($sub + $fee);

                return [
                    'uuid' => $b->uuid,
                    'status' => $b->status,
                    'seats_booked' => $b->seats_booked,
                    'passenger_name' => $name,
                    'passenger_phone' => $b->passenger?->phone ?? null,
                    'passenger_rating' => $avgRating ? round((float) $avgRating, 1) : 0.0,
                    'passenger_trips_count' => $tripsCount,
                    'passenger_is_verified' => (bool) ($b->passenger?->is_verified ?? false),
                    'created_at' => $b->created_at?->toIso8601String(),
                    'conversation_uuid' => $b->conversation?->uuid,
                    'trip_uuid' => $b->trip?->uuid,
                    'origin' => $b->trip?->departure_city,
                    'destination' => $b->trip?->arrival_city,
                    'departure_time' => $b->trip?->departure_time,
                    // ── Prise en charge ──
                    'pickup_city' => $b->pickup_city,
                    'pickup_arrondissement' => $b->pickup_arrondissement,
                    'pickup_neighborhood' => $b->pickup_neighborhood,
                    'pickup_address' => $b->pickup_address,
                    'pickup_latitude' => $b->pickup_latitude,
                    'pickup_longitude' => $b->pickup_longitude,
                    // ── Dépôt ──
                    'dropoff_city' => $b->dropoff_city,
                    'dropoff_arrondissement' => $b->dropoff_arrondissement,
                    'dropoff_neighborhood' => $b->dropoff_neighborhood,
                    'dropoff_address' => $b->dropoff_address,
                    'dropoff_latitude' => $b->dropoff_latitude,
                    'dropoff_longitude' => $b->dropoff_longitude,
                    'passenger_distance_km' => $b->passenger_distance_km,
                    'calculated_price' => $unitPrice,
                    'price_subtotal'   => $sub,
                    'service_fee'      => $fee,
                    'total_price'      => $total,
                    'payment_status' => $b->payment_status,
                    'is_paid' => $b->payment_status === 'escrow_locked',
                ];
            });

        return $this->apiResponse(true, 'Réservations.', ['bookings' => $bookings]);
    }

    // =========================================================================
    //  POST /api/bookings/{uuid}/accept
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/accept',
        operationId: 'driverBookingAccept',
        summary: 'Accepter une réservation',
        tags: ['🚗 Driver — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réservation acceptée'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    public function accept(string $uuid): JsonResponse
    {
        $booking = Booking::with('trip', 'passenger')
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($booking->trip?->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Action non autorisée.', [], 403);
        }

        if (! $booking->isPending()) {
            return $this->apiResponse(false, 'Cette réservation ne peut plus être acceptée.', [], 422);
        }

        try {
            DB::transaction(function () use ($booking) {
                $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
                if (! $lockedBooking->isPending()) {
                    throw new \LogicException('Cette réservation ne peut plus être acceptée.');
                }

                $lockedBooking->update(['status' => 'accepted']);
            });
        } catch (\LogicException $e) {
            return $this->apiResponse(false, $e->getMessage(), [], 422);
        }

        $this->notifyPassenger($booking, 'accepted');

        return $this->apiResponse(true, 'Réservation acceptée.');
    }

    // =========================================================================
    //  POST /api/bookings/{uuid}/reject
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/reject',
        operationId: 'driverBookingReject',
        summary: 'Refuser une réservation',
        tags: ['🚗 Driver — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réservation refusée'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    public function reject(string $uuid): JsonResponse
    {
        $booking = Booking::with('trip', 'passenger')
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($booking->trip?->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Action non autorisée.', [], 403);
        }

        if (! $booking->isPending()) {
            return $this->apiResponse(false, 'Cette réservation ne peut plus être refusée.', [], 422);
        }

        try {
            DB::transaction(function () use ($booking) {
                $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
                if (! $lockedBooking->isPending()) {
                    throw new \LogicException('Cette réservation ne peut plus être refusée.');
                }

                $lockedTrip = Trip::whereKey($lockedBooking->trip_id)->lockForUpdate()->firstOrFail();
                $lockedTrip->increment('available_seats', $lockedBooking->seats_booked);
                $lockedBooking->update(['status' => 'rejected']);
            });
        } catch (\LogicException $e) {
            return $this->apiResponse(false, $e->getMessage(), [], 422);
        }

        $this->notifyPassenger($booking, 'rejected');

        return $this->apiResponse(true, 'Réservation refusée.');
    }

    // =========================================================================
    //  POST /api/driver/bookings/{uuid}/review  — noter un passager
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/bookings/{uuid}/review',
        operationId: 'driverRatePassenger',
        summary: 'Conducteur note un passager après le trajet',
        description: 'Crée un avis du conducteur sur le passager. Idempotent : un seul avis par booking. Le trajet doit être `completed`.',
        tags: ['🚗 Driver — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'UUID de la réservation (booking)'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['rating'],
                properties: [
                    new OA\Property(property: 'rating',  type: 'integer', minimum: 1, maximum: 5, example: 4),
                    new OA\Property(property: 'comment', type: 'string',  maxLength: 300, nullable: true, example: 'Passager ponctuel et respectueux.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Avis enregistré (idempotent — déjà existant)'),
            new OA\Response(response: 201, description: 'Avis créé'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
            new OA\Response(response: 409, description: 'Le trajet n\'est pas encore terminé'),
        ]
    )]
    public function ratePassenger(Request $request, string $uuid): JsonResponse
    {
        $driver  = $request->user();
        $booking = Booking::with(['trip', 'passenger'])->where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $trip = $booking->trip;

        if (! $trip || $trip->user_id !== $driver->id) {
            return $this->apiResponse(false, 'Action non autorisée.', [], 403);
        }

        if ($trip->status !== 'completed') {
            return $this->apiResponse(false, 'Le trajet doit être terminé pour noter un passager.', [], 409);
        }

        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:300'],
        ]);

        // Idempotence : un seul avis du conducteur sur ce passager pour ce trajet
        $existing = Review::where('trip_id', $trip->id)
            ->where('reviewer_id', $driver->id)
            ->where('reviewee_id', $booking->passenger_id)
            ->first();

        if ($existing) {
            return $this->apiResponse(true, 'Avis déjà enregistré.', ['already_exists' => true]);
        }

        Review::create([
            'trip_id'     => $trip->id,
            'reviewer_id' => $driver->id,
            'reviewee_id' => $booking->passenger_id,
            'rating'      => $validated['rating'],
            'comment'     => $validated['comment'] ?? null,
            'status'      => 'visible',
        ]);

        // Notifier le passager (non bloquant)
        try {
            $fcmToken   = $booking->passenger?->fcm_token;
            $driverName = $driver->profile?->first_name ?? 'Votre conducteur';
            if ($fcmToken) {
                app(FcmService::class)->send(
                    $fcmToken,
                    '⭐ ' . $driverName . ' vous a noté',
                    'Consultez votre profil pour voir votre note.',
                    ['type' => 'driver_review_new', 'trip_uuid' => $trip->uuid]
                );
            }
        } catch (\Throwable) {
            // non-bloquant
        }

        return $this->apiResponse(true, 'Avis enregistré.', [], 201);
    }

    // -------------------------------------------------------------------------

    private function notifyPassenger(Booking $booking, string $action): void
    {
        if (! $booking->passenger) {
            return;
        }

        try {
            // BookingStatusChanged gère DB + FCM push
            $booking->passenger->notify(new BookingStatusChanged($booking));
        } catch (\Throwable) {
            // non-bloquant
        }
    }
}
