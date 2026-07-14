<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                        new OA\Property(property: 'message', type: 'string',  example: 'Réservations.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'bookings',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                                                new OA\Property(property: 'uuid',              type: 'string',  format: 'uuid'),
                                            new OA\Property(property: 'status',            type: 'string',  enum: ['pending', 'accepted', 'rejected', 'cancelled']),
                                            new OA\Property(property: 'seats_booked',      type: 'integer'),
                                            new OA\Property(property: 'passenger_name',    type: 'string'),
                                            new OA\Property(property: 'passenger_phone',   type: 'string',  nullable: true),
                                            new OA\Property(property: 'trip_uuid',         type: 'string',  format: 'uuid'),
                                            new OA\Property(property: 'origin',            type: 'string'),
                                            new OA\Property(property: 'destination',       type: 'string'),
                                            new OA\Property(property: 'departure_time',    type: 'string',  format: 'date-time'),
                                            new OA\Property(property: 'pickup_city',           type: 'string',  example: 'Cotonou'),
                                            new OA\Property(property: 'pickup_neighborhood',  type: 'string',  example: 'Akpakpa'),
                                            new OA\Property(property: 'pickup_address',        type: 'string',  example: 'Face pharmacie du centre'),
                                            new OA\Property(property: 'pickup_latitude',        type: 'number',  format: 'float', example: 6.3654),
                                            new OA\Property(property: 'pickup_longitude',       type: 'number',  format: 'float', example: 2.4183),
                                            new OA\Property(property: 'dropoff_city',           type: 'string',  example: 'Parakou'),
                                            new OA\Property(property: 'dropoff_neighborhood',   type: 'string',  example: 'Zongo'),
                                            new OA\Property(property: 'dropoff_address',        type: 'string',  example: 'Carrefour étoile rouge'),
                                            new OA\Property(property: 'dropoff_latitude',        type: 'number',  format: 'float', example: 9.3370),
                                            new OA\Property(property: 'dropoff_longitude',       type: 'number',  format: 'float', example: 2.6280),
                                            new OA\Property(property: 'passenger_distance_km',  type: 'number',  format: 'float', example: 127.4),
                                            new OA\Property(property: 'calculated_price',        type: 'integer', example: 950),
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

        $bookings = Booking::with(['passenger.profile', 'trip'])
            ->whereIn('trip_id', $tripIds)
            ->whereIn('status', ['pending', 'accepted'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Booking $b) {
                $profile = $b->passenger?->profile;
                $name    = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: 'Passager';

                return [
                    'uuid'             => $b->uuid,
                    'status'           => $b->status,
                    'seats_booked'     => $b->seats_booked,
                    'passenger_name'   => $name,
                    'passenger_phone'  => $b->passenger?->phone ?? null,
                    'trip_uuid'        => $b->trip?->uuid,
                    'origin'           => $b->trip?->departure_city,
                    'destination'      => $b->trip?->arrival_city,
                    'departure_time'   => $b->trip?->departure_time,
                    'pickup_city'           => $b->pickup_city,
                    'pickup_neighborhood'   => $b->pickup_neighborhood,
                    'pickup_address'        => $b->pickup_address,
                    'pickup_latitude'       => $b->pickup_latitude,
                    'pickup_longitude'      => $b->pickup_longitude,
                    'dropoff_city'          => $b->dropoff_city,
                    'dropoff_neighborhood'  => $b->dropoff_neighborhood,
                    'dropoff_address'       => $b->dropoff_address,
                    'dropoff_latitude'      => $b->dropoff_latitude,
                    'dropoff_longitude'     => $b->dropoff_longitude,
                    'passenger_distance_km' => $b->passenger_distance_km,
                    'calculated_price'      => $b->calculated_price,
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

        $booking->update(['status' => 'accepted']);

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

        $booking->update(['status' => 'rejected']);

        $this->notifyPassenger($booking, 'rejected');

        return $this->apiResponse(true, 'Réservation refusée.');
    }

    // -------------------------------------------------------------------------

    private function notifyPassenger(Booking $booking, string $action): void
    {
        try {
            $label = $action === 'accepted' ? 'accepté' : 'refusé';
            DB::table('notifications')->insert([
                'id'              => (string) Str::uuid(),
                'type'            => 'booking_' . $action,
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $booking->passenger_id,
                'data'            => json_encode([
                    'title'        => 'Réservation ' . $label,
                    'body'         => 'Votre réservation pour le trajet ' .
                                     ($booking->trip?->departure_city ?? '') . ' → ' .
                                     ($booking->trip?->arrival_city ?? '') . ' a été ' . $label . '.',
                    'booking_uuid' => $booking->uuid,
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
