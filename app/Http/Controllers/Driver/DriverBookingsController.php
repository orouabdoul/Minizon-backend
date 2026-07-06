<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
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
                                            new OA\Property(property: 'uuid',             type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'status',           type: 'string', enum: ['pending', 'accepted', 'rejected', 'cancelled']),
                                            new OA\Property(property: 'seats_booked',     type: 'integer'),
                                            new OA\Property(property: 'passenger_name',   type: 'string'),
                                            new OA\Property(property: 'passenger_phone',  type: 'string', nullable: true),
                                            new OA\Property(property: 'trip_uuid',        type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'origin',           type: 'string'),
                                            new OA\Property(property: 'destination',      type: 'string'),
                                            new OA\Property(property: 'departure_time',   type: 'string', format: 'date-time'),
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
                    'uuid'            => $b->uuid,
                    'status'          => $b->status,
                    'seats_booked'    => $b->seats_booked,
                    'passenger_name'  => $name,
                    'passenger_phone' => $b->passenger?->phone ?? null,
                    'trip_uuid'       => $b->trip?->uuid,
                    'origin'          => $b->trip?->origin,
                    'destination'     => $b->trip?->destination,
                    'departure_time'  => $b->trip?->departure_time,
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
            Notification::create([
                'user_id' => $booking->passenger_id,
                'type'    => 'booking_' . $action,
                'title'   => 'Réservation ' . $label,
                'body'    => 'Votre réservation pour le trajet ' .
                             ($booking->trip?->origin ?? '') . ' → ' .
                             ($booking->trip?->destination ?? '') . ' a été ' . $label . '.',
                'data'    => json_encode(['booking_uuid' => $booking->uuid]),
            ]);
        } catch (\Throwable) {
            // non-bloquant
        }
    }
}
