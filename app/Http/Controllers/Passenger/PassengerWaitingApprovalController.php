<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "En attente de confirmation" — polling du statut conducteur.
 *
 * Affiché immédiatement après la création d'une réservation.
 * Le Flutter controller appelle cet endpoint en boucle (~3 s) jusqu'à ce que
 * le statut change.
 *
 * Statuts retournés → enum Dart WaitingStatus :
 *   pending   ← booking.status == 'pending'  ET délai non expiré
 *   timeout   ← booking.status == 'pending'  ET délai expiré
 *   accepted  ← booking.status == 'accepted'
 *   rejected  ← booking.status == 'rejected'
 *
 * Timeout : APPROVAL_TIMEOUT_SECONDS (défaut 300 s = 5 min).
 * L'annulation utilise l'endpoint existant : POST /api/bookings/{uuid}/cancel.
 */
class PassengerWaitingApprovalController extends Controller
{
    public const APPROVAL_TIMEOUT_SECONDS = 300;

    // =========================================================================
    //  GET /api/passenger/bookings/{uuid}/approval-status
    //  Endpoint de polling — appelé toutes les ~3 s par le Flutter
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/bookings/{uuid}/approval-status',
        operationId: 'passengerApprovalStatus',
        summary: 'Statut d\'approbation d\'une réservation (polling)',
        description: "Endpoint léger pour la page `WaitingApprovalView`. Appelé en boucle par le Flutter (toutes les ~3 s) pour détecter la réponse du conducteur.\n\nRetourne le statut dérivé (`pending`, `accepted`, `rejected`, `timeout`), les infos du trajet pour la carte et les métadonnées du countdown (`timeout_at`, `total_timeout_seconds`).\n\n**Timeout** : `" . self::APPROVAL_TIMEOUT_SECONDS . " s` — si le conducteur ne répond pas dans ce délai, le statut devient `timeout`.",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statut de la réservation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Statut de la réservation.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'booking_uuid', type: 'string', format: 'uuid'),
                                new OA\Property(
                                    property: 'status',
                                    type: 'string',
                                    enum: ['pending', 'accepted', 'rejected', 'timeout'],
                                    example: 'pending',
                                    description: 'Correspond aux cases de l\'enum Dart `WaitingStatus`.'
                                ),
                                new OA\Property(property: 'reserved_seats',          type: 'integer', example: 1),
                                new OA\Property(property: 'total_timeout_seconds',   type: 'integer', example: 300, description: 'Durée totale du délai en secondes — pour calculer progressFraction.'),
                                new OA\Property(property: 'timeout_at',              type: 'string',  format: 'date-time', nullable: true, description: 'ISO 8601 — instant d\'expiration du délai. Null si statut ≠ pending.'),
                                new OA\Property(property: 'seconds_remaining',       type: 'integer', nullable: true, example: 247, description: 'Secondes restantes avant timeout. Null si statut ≠ pending.'),
                                new OA\Property(
                                    property: 'ride',
                                    type: 'object',
                                    description: 'Informations du trajet pour la carte de résumé.',
                                    properties: [
                                        new OA\Property(property: 'origin',         type: 'string', example: 'Cotonou'),
                                        new OA\Property(property: 'destination',    type: 'string', example: 'Abomey-Calavi'),
                                        new OA\Property(property: 'departure_time', type: 'string', example: '14h30'),
                                        new OA\Property(property: 'driver_name',    type: 'string', example: 'Koffi Adjovi'),
                                        new OA\Property(property: 'rating',         type: 'string', example: '4.8'),
                                        new OA\Property(property: 'price',          type: 'string', example: '1 500 FCFA'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé',           content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function status(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with(['trip.user.profile', 'trip.vehicle'])
            ->where('uuid', $uuid)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->passenger_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $tz   = 'Africa/Porto-Novo';
        $trip = $booking->trip;

        // ── Statut dérivé ─────────────────────────────────────────────────────
        $flutterStatus  = $this->deriveStatus($booking);
        $timeoutAt      = null;
        $secondsRemaining = null;

        if ($flutterStatus === 'pending') {
            $deadline       = $booking->created_at->addSeconds(self::APPROVAL_TIMEOUT_SECONDS);
            $timeoutAt      = $deadline->setTimezone($tz)->toIso8601String();
            $secondsRemaining = max(0, (int) now()->diffInSeconds($deadline, false));
        }

        // ── Infos trajet pour la carte ────────────────────────────────────────
        $driver  = $trip?->user;
        $profile = $driver?->profile;

        $driverFirstName = $profile?->first_name ?? '';
        $driverLastName  = $profile?->last_name  ?? '';
        $driverName      = trim("$driverFirstName $driverLastName") ?: 'Conducteur';

        // Rating calculé depuis les avis reçus (requête légère)
        $rating = $driver
            ? round((float) $driver->reviewsReceived()->avg('rating') ?? 0, 1)
            : 0.0;

        $pricePerSeat = (int) ($trip?->price_per_seat ?? 0);
        $seats        = (int) $booking->seats_booked;
        $totalPrice   = number_format($pricePerSeat * $seats, 0, ',', ' ') . ' FCFA';

        $depTime = $trip?->departure_time?->setTimezone($tz);

        return $this->apiResponse(true, 'Statut de la réservation.', [
            'booking_uuid'         => $booking->uuid,
            'status'               => $flutterStatus,
            'reserved_seats'       => $seats,
            'total_timeout_seconds'=> self::APPROVAL_TIMEOUT_SECONDS,
            'timeout_at'           => $timeoutAt,
            'seconds_remaining'    => $secondsRemaining,
            'ride'                 => [
                'origin'         => $trip?->departure_city ?? '—',
                'destination'    => $trip?->arrival_city   ?? '—',
                'departure_time' => $depTime?->format('H\hi') ?? '—',
                'driver_name'    => $driverName,
                'rating'         => $rating > 0 ? (string) $rating : '—',
                'price'          => $totalPrice,
            ],
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function deriveStatus(Booking $booking): string
    {
        return match ($booking->status) {
            'accepted' => 'accepted',
            'rejected' => 'rejected',
            'pending'  => $booking->created_at->addSeconds(self::APPROVAL_TIMEOUT_SECONDS)->isPast()
                ? 'timeout'
                : 'pending',
            default    => 'pending',
        };
    }
}
