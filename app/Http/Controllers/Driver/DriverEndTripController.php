<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Fin du trajet" — résumé post-course pour le conducteur.
 *
 * La confirmation effective (status → completed + création des TripValidation)
 * est gérée par l'endpoint existant :
 *   POST /api/trips/{uuid}/end  (TripController::endTrip)
 */
class DriverEndTripController extends Controller
{
    // =========================================================================
    //  GET /api/driver/trips/{uuid}/end-summary
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/trips/{uuid}/end-summary',
        operationId: 'driverTripEndSummary',
        summary: 'Résumé de fin de trajet',
        description: "Retourne en un seul appel tout ce dont la page \"Trajet terminé\" a besoin : résumé financier (brut / commission / net), durée réelle, confirmations passagers en temps réel et date de disponibilité des fonds (+24h). Utilisable avant (statut `active`) et après (statut `completed`) la confirmation.\n\nLe bouton **\"Confirmer la fin du trajet\"** doit appeler **POST /api/trips/{uuid}/end**.",
        tags: ['🚗 Driver — End Trip'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Résumé de fin de trajet',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Résumé du trajet.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'trip_route',         type: 'string',  example: 'Cotonou → Parakou'),
                                new OA\Property(property: 'real_duration',      type: 'string',  nullable: true, example: '4h15', description: 'Durée réelle (started_at→completed_at) ou estimée si pas encore terminé'),
                                new OA\Property(property: 'distance_km',        type: 'number',  nullable: true, example: 413.5),
                                new OA\Property(property: 'passengers_count',   type: 'integer', example: 3),
                                new OA\Property(property: 'gross_revenue',      type: 'number',  example: 9000),
                                new OA\Property(property: 'commission',         type: 'number',  example: 900),
                                new OA\Property(property: 'commission_rate',    type: 'integer', example: 10),
                                new OA\Property(property: 'net_revenue',        type: 'number',  example: 8100),
                                new OA\Property(property: 'confirmed_count',    type: 'integer', example: 2, description: 'Passagers ayant confirmé l\'arrivée'),
                                new OA\Property(property: 'all_confirmed',      type: 'boolean', example: false),
                                new OA\Property(property: 'available_date',     type: 'string',  example: 'le 03/07/2026', description: 'Date de disponibilité des fonds (auto_release_at ou now+24h)'),
                                new OA\Property(
                                    property: 'confirmations',
                                    type: 'array',
                                    description: 'Une entrée par réservation acceptée',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'booking_uuid',   type: 'string',  format: 'uuid'),
                                            new OA\Property(property: 'name',           type: 'string',  example: 'Koffi Mensah'),
                                            new OA\Property(property: 'initial',        type: 'string',  example: 'KM'),
                                            new OA\Property(property: 'has_confirmed',  type: 'boolean', example: false),
                                            new OA\Property(property: 'seats',          type: 'integer', example: 1),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'trip_status',  type: 'string', enum: ['active', 'completed'], example: 'active'),
                                new OA\Property(property: 'end_endpoint', type: 'string', example: 'POST /api/trips/{uuid}/end'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Ce trajet ne vous appartient pas ou statut incompatible', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function summary(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $trip = Trip::with([
            'bookings' => fn ($q) => $q
                ->where('status', 'accepted')
                ->with(['passenger.profile', 'tripValidation']),
        ])->where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if ($trip->user_id !== $user->id) {
            return $this->apiResponse(false, 'Ce trajet ne vous appartient pas.', [], 403);
        }

        if (! in_array($trip->status, ['active', 'completed'])) {
            return $this->apiResponse(false, 'Ce trajet n\'a pas encore démarré (statut : ' . $trip->status . ').', [], 403);
        }

        // ── Finances ──────────────────────────────────────────────────────────
        $passengersCount = (int) $trip->bookings->sum('seats_booked');
        $commissionRate  = $trip->commission_rate ?? 10;
        $grossRevenue    = $trip->price_per_seat * $passengersCount;
        $commission      = (int) round($grossRevenue * $commissionRate / 100);
        $netRevenue      = $grossRevenue - $commission;

        // ── Durée réelle ──────────────────────────────────────────────────────
        $realDuration = null;
        if ($trip->started_at && $trip->completed_at) {
            $realDuration = $this->formatDuration((int) $trip->started_at->diffInMinutes($trip->completed_at));
        } elseif ($trip->started_at) {
            $realDuration = $this->formatDuration((int) $trip->started_at->diffInMinutes(now()));
        } elseif ($trip->estimated_duration_minutes) {
            $realDuration = $this->formatDuration($trip->estimated_duration_minutes);
        }

        // ── Distance ─────────────────────────────────────────────────────────
        $distanceKm = $this->haversineKm(
            $trip->departure_latitude,  $trip->departure_longitude,
            $trip->arrival_latitude,    $trip->arrival_longitude,
        );

        // ── Confirmations passagers ──────────────────────────────────────────
        $confirmations = $trip->bookings->map(function ($booking) {
            $profile    = $booking->passenger?->profile;
            $validation = $booking->tripValidation;
            $name       = $profile?->fullName() ?: ($booking->passenger?->phone ?? '—');
            $parts      = explode(' ', trim($name));
            $initial    = mb_strtoupper(
                mb_substr($parts[0] ?? '', 0, 1)
                . mb_substr($parts[1] ?? '', 0, 1)
            );

            return [
                'booking_uuid'  => $booking->uuid,
                'name'          => $name,
                'initial'       => $initial ?: '?',
                'has_confirmed' => (bool) $validation?->passenger_confirmed,
                'seats'         => $booking->seats_booked,
            ];
        })->values()->all();

        $confirmedCount = collect($confirmations)->where('has_confirmed', true)->count();
        $allConfirmed   = $trip->bookings->count() > 0 && $confirmedCount === $trip->bookings->count();

        // ── Date de disponibilité des fonds ──────────────────────────────────
        $firstValidation = $trip->bookings
            ->map(fn ($b) => $b->tripValidation)
            ->filter()
            ->sortBy('auto_release_at')
            ->first();

        $availableAt   = $firstValidation?->auto_release_at ?? now()->addHours(24);
        $availableDate = 'le ' . $availableAt->setTimezone('Africa/Porto-Novo')->format('d/m/Y');

        return $this->apiResponse(true, 'Résumé du trajet.', [
            'trip_route'       => $trip->departure_city . ' → ' . $trip->arrival_city,
            'real_duration'    => $realDuration,
            'distance_km'      => $distanceKm,
            'passengers_count' => $passengersCount,
            'gross_revenue'    => $grossRevenue,
            'commission'       => $commission,
            'commission_rate'  => $commissionRate,
            'net_revenue'      => $netRevenue,
            'confirmed_count'  => $confirmedCount,
            'all_confirmed'    => $allConfirmed,
            'available_date'   => $availableDate,
            'confirmations'    => $confirmations,
            'trip_status'      => $trip->status,
            'end_endpoint'     => 'POST /api/trips/' . $trip->uuid . '/end',
        ]);
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    private function formatDuration(?int $minutes): ?string
    {
        if ($minutes === null || $minutes < 0) return null;
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $m > 0 ? "{$h}h{$m}" : "{$h}h00";
    }

    private function haversineKm(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) return null;
        $R  = 6371;
        $dL = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lon2 - $lon1);
        $a  = sin($dL / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dl / 2) ** 2;
        return round(2 * $R * asin(sqrt($a)), 1);
    }
}
