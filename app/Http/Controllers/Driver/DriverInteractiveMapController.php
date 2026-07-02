<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Carte interactive du trajet" — navigation en cours.
 *
 * Ce contrôleur gère les données statiques et les actions de la carte.
 * Les mises à jour en temps réel (position GPS) restent sur :
 *   POST /api/trips/{uuid}/location  (TripController::updateLocation)
 *
 * Statuts des arrêts :
 *   - pending    : pas encore atteint
 *   - approaching: premier arrêt non terminé (next stop)
 *   - done       : passager pris en charge (picked_up_at != null)
 */
class DriverInteractiveMapController extends Controller
{
    // =========================================================================
    //  GET /api/driver/trips/{uuid}/map
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/trips/{uuid}/map',
        operationId: 'driverTripMap',
        summary: 'Données de la carte interactive du trajet en cours',
        description: "Retourne en un seul appel tout ce dont la page \"Carte interactive\" a besoin : position conducteur, arrêts ordonnés avec statuts (pending/approaching/done), polyline de route simplifiée, distance, ETA et estimation carburant. Appelé à l'entrée sur la page et à chaque recalcul. La télémétrie GPS temps-réel continue de passer par **POST /api/trips/{uuid}/location**.",
        tags: ['🚗 Driver — Interactive Map'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données carte',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Carte du trajet.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'driver_position',
                                    type: 'object',
                                    description: 'Dernière position GPS connue du conducteur',
                                    properties: [
                                        new OA\Property(property: 'lat', type: 'number', example: 6.3703),
                                        new OA\Property(property: 'lng', type: 'number', example: 2.3912),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'stops',
                                    type: 'array',
                                    description: 'Arrêts ordonnés (pickups puis dropoff)',
                                    items: new OA\Items(ref: '#/components/schemas/MapStop')
                                ),
                                new OA\Property(
                                    property: 'route_polyline',
                                    type: 'array',
                                    description: 'Points de la polyligne (départ → pickups → arrivée)',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'lat', type: 'number'),
                                            new OA\Property(property: 'lng', type: 'number'),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'route_distance', type: 'string', example: '413 km'),
                                new OA\Property(property: 'route_eta',      type: 'string', example: '5h30'),
                                new OA\Property(property: 'route_fuel',     type: 'string', example: '~33L'),
                                new OA\Property(property: 'current_stop_index', type: 'integer', example: 0, description: 'Index (0-based) du prochain arrêt à atteindre'),
                                new OA\Property(property: 'completed_stops',    type: 'integer', example: 1),
                                new OA\Property(property: 'total_stops',        type: 'integer', example: 4),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Ce trajet ne vous appartient pas ou n\'est pas actif', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function mapData(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::with([
            'bookings' => fn ($q) => $q
                ->where('status', 'accepted')
                ->with('passenger.profile')
                ->orderBy('created_at'),
        ])->where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }
        if ($trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Ce trajet ne vous appartient pas.', [], 403);
        }
        if ($trip->status !== 'active') {
            return $this->apiResponse(false, 'Le trajet n\'est pas en cours (statut : ' . $trip->status . ').', [], 403);
        }

        [$stops, $polyline] = $this->buildStopsAndPolyline($trip);

        $distanceKm    = $this->haversineKm(
            $trip->departure_latitude, $trip->departure_longitude,
            $trip->arrival_latitude,   $trip->arrival_longitude,
        );
        $routeDistance = $distanceKm !== null ? number_format($distanceKm, 0, '.', '') . ' km' : '— km';
        $routeEta      = $this->formatDuration($trip->estimated_duration_minutes) ?? '—';
        $routeFuel     = $distanceKm !== null ? '~' . number_format($distanceKm * 0.08, 0) . 'L' : '—';

        $completedCount    = collect($stops)->where('status', 'done')->count();
        $currentStopIndex  = $completedCount; // 0-based index of next stop

        return $this->apiResponse(true, 'Carte du trajet.', [
            'driver_position'    => [
                'lat' => $trip->current_latitude  ?? $trip->departure_latitude,
                'lng' => $trip->current_longitude ?? $trip->departure_longitude,
            ],
            'stops'              => $stops,
            'route_polyline'     => $polyline,
            'route_distance'     => $routeDistance,
            'route_eta'          => $routeEta,
            'route_fuel'         => $routeFuel,
            'current_stop_index' => $currentStopIndex,
            'completed_stops'    => $completedCount,
            'total_stops'        => count($stops),
        ]);
    }

    // =========================================================================
    //  POST /api/driver/trips/{uuid}/stops/{bookingUuid}/done
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/trips/{uuid}/stops/{bookingUuid}/done',
        operationId: 'driverMarkStopDone',
        summary: 'Marquer un arrêt comme terminé',
        description: 'Enregistre la prise en charge d\'un passager (pickup) en remplissant `picked_up_at` sur la réservation. Pour le dernier arrêt (dépose finale), utilisez **POST /api/trips/{uuid}/end** pour clôturer le trajet.',
        tags: ['🚗 Driver — Interactive Map'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid',        in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'bookingUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Arrêt marqué comme terminé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Passager pris en charge.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'stop_id',       type: 'string', format: 'uuid'),
                                new OA\Property(property: 'picked_up_at',  type: 'string', format: 'date-time'),
                                new OA\Property(property: 'next_stop_index', type: 'integer', example: 2),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé ou arrêt déjà terminé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable',              content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function markStopDone(Request $request, string $uuid, string $bookingUuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip || $trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Trajet introuvable ou accès refusé.', [], 403);
        }
        if ($trip->status !== 'active') {
            return $this->apiResponse(false, 'Le trajet n\'est pas en cours.', [], 403);
        }

        $booking = Booking::where('uuid', $bookingUuid)
            ->where('trip_id', $trip->id)
            ->where('status', 'accepted')
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }
        if ($booking->picked_up_at !== null) {
            return $this->apiResponse(false, 'Ce passager a déjà été pris en charge.', [], 403);
        }

        $booking->update(['picked_up_at' => now()]);

        // Calcul du prochain index après ce marquage
        $doneCount = $trip->bookings()
            ->where('status', 'accepted')
            ->whereNotNull('picked_up_at')
            ->count();

        return $this->apiResponse(true, 'Passager pris en charge.', [
            'stop_id'          => $booking->uuid,
            'picked_up_at'     => $booking->picked_up_at,
            'next_stop_index'  => $doneCount,
        ]);
    }

    // =========================================================================
    //  POST /api/driver/trips/{uuid}/recalculate
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/trips/{uuid}/recalculate',
        operationId: 'driverRecalculateRoute',
        summary: 'Recalculer / optimiser l\'itinéraire',
        description: 'Retourne l\'itinéraire réoptimisé (arrêts non terminés triés par proximité avec la position actuelle du conducteur). La polyline et les ETAs sont mises à jour en conséquence. Lève le bandeau de confirmation côté Flutter (`showOptimizationBanner = true`).',
        tags: ['🚗 Driver — Interactive Map'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Itinéraire recalculé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Itinéraire optimisé.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'optimized', type: 'boolean', example: true),
                                new OA\Property(property: 'stops',          type: 'array', items: new OA\Items(ref: '#/components/schemas/MapStop')),
                                new OA\Property(property: 'route_polyline', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'route_distance', type: 'string', example: '408 km'),
                                new OA\Property(property: 'route_eta',      type: 'string', example: '5h20'),
                                new OA\Property(property: 'route_fuel',     type: 'string', example: '~33L'),
                                new OA\Property(property: 'current_stop_index', type: 'integer'),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function recalculate(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::with([
            'bookings' => fn ($q) => $q
                ->where('status', 'accepted')
                ->with('passenger.profile')
                ->orderBy('created_at'),
        ])->where('uuid', $uuid)->first();

        if (! $trip || $trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Trajet introuvable ou accès refusé.', [], 403);
        }
        if ($trip->status !== 'active') {
            return $this->apiResponse(false, 'Le trajet n\'est pas en cours.', [], 403);
        }

        // Recalcul : trier les arrêts non terminés par proximité avec le conducteur.
        // Sans API de routage, on utilise Haversine depuis current_position.
        $driverLat = $trip->current_latitude  ?? $trip->departure_latitude;
        $driverLng = $trip->current_longitude ?? $trip->departure_longitude;

        // On sépare bookings done / pending puis re-sort pending par Haversine
        $acceptedBookings = $trip->bookings;

        $done    = $acceptedBookings->whereNotNull('picked_up_at')->sortBy('created_at');
        $pending = $acceptedBookings->whereNull('picked_up_at')->sortBy(
            fn ($b) => $this->haversineKm(
                $driverLat, $driverLng,
                $trip->departure_latitude, $trip->departure_longitude,
            ) ?? PHP_INT_MAX
        );

        // Reconstruire la collection dans le nouvel ordre
        $trip->setRelation('bookings', $done->merge($pending)->values());

        [$stops, $polyline] = $this->buildStopsAndPolyline($trip);

        $distanceKm    = $this->haversineKm(
            $trip->departure_latitude, $trip->departure_longitude,
            $trip->arrival_latitude,   $trip->arrival_longitude,
        );
        $routeDistance = $distanceKm !== null ? number_format($distanceKm, 0, '.', '') . ' km' : '— km';
        $routeEta      = $this->formatDuration($trip->estimated_duration_minutes) ?? '—';
        $routeFuel     = $distanceKm !== null ? '~' . number_format($distanceKm * 0.08, 0) . 'L' : '—';

        $completedCount = collect($stops)->where('status', 'done')->count();

        return $this->apiResponse(true, 'Itinéraire optimisé.', [
            'optimized'          => true,
            'stops'              => $stops,
            'route_polyline'     => $polyline,
            'route_distance'     => $routeDistance,
            'route_eta'          => $routeEta,
            'route_fuel'         => $routeFuel,
            'current_stop_index' => $completedCount,
        ]);
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'MapStop',
        description: 'Arrêt de la carte interactive (pickup ou dropoff final)',
        properties: [
            new OA\Property(property: 'id',             type: 'string',  description: 'UUID de la réservation (ou "{tripUuid}_dropoff" pour la dépose finale)'),
            new OA\Property(property: 'type',           type: 'string',  enum: ['pickup', 'dropoff']),
            new OA\Property(property: 'status',         type: 'string',  enum: ['pending', 'approaching', 'done']),
            new OA\Property(property: 'passenger_name', type: 'string',  example: 'Koffi Mensah'),
            new OA\Property(property: 'address',        type: 'string',  example: 'Akpakpa, Cotonou'),
            new OA\Property(property: 'eta',            type: 'string',  example: '07:15'),
            new OA\Property(
                property: 'latlng',
                type: 'object',
                properties: [
                    new OA\Property(property: 'lat', type: 'number', example: 6.3703),
                    new OA\Property(property: 'lng', type: 'number', example: 2.3912),
                ]
            ),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS
    // =========================================================================

    /**
     * Construit :
     *  - la liste des arrêts (pickups par ordre de réservation + 1 dropoff final)
     *  - la polyline simplifiée (départ → pickups → arrivée)
     *
     * @return array{0: array, 1: array}
     */
    private function buildStopsAndPolyline(Trip $trip): array
    {
        $stops    = [];
        $polyline = [];
        $tz       = 'Africa/Porto-Novo';

        // Point de départ dans la polyline
        if ($trip->departure_latitude && $trip->departure_longitude) {
            $polyline[] = ['lat' => $trip->departure_latitude, 'lng' => $trip->departure_longitude];
        }

        $departsAt    = $trip->departure_time->setTimezone($tz);
        $pickupOffset = 0; // minutes d'offset par rapport à l'heure de départ
        $doneCount    = 0;

        foreach ($trip->bookings as $booking) {
            $isDone = $booking->picked_up_at !== null;
            if ($isDone) $doneCount++;

            $profile = $booking->passenger?->profile;
            $name    = $profile?->fullName() ?: ($booking->passenger?->phone ?? '—');

            // Adresse : quartier du passager ou point de départ du trajet
            $address = ($profile?->neighborhood && $profile?->city)
                ? "{$profile->neighborhood}, {$profile->city}"
                : $trip->departure_city . ', ' . $trip->departure_neighborhood;

            // GPS : pas de GPS passager stocké → on utilise les coords de départ
            $lat = $trip->departure_latitude;
            $lng = $trip->departure_longitude;

            $eta = $departsAt->copy()->addMinutes($pickupOffset)->format('H:i');

            $stops[] = [
                'id'             => $booking->uuid,
                'type'           => 'pickup',
                'status'         => 'pending', // résolu ci-dessous
                'passenger_name' => $name,
                'address'        => $address,
                'eta'            => $eta,
                'latlng'         => ['lat' => $lat, 'lng' => $lng],
            ];

            if ($lat && $lng) {
                $polyline[] = ['lat' => $lat, 'lng' => $lng];
            }

            $pickupOffset += 5;
        }

        // Résolution des statuts
        $firstPendingFound = false;
        foreach ($stops as &$stop) {
            $booking = $trip->bookings->firstWhere('uuid', $stop['id']);
            if ($booking?->picked_up_at !== null) {
                $stop['status'] = 'done';
            } elseif (! $firstPendingFound) {
                $stop['status']    = 'approaching';
                $firstPendingFound = true;
            } else {
                $stop['status'] = 'pending';
            }
        }
        unset($stop);

        // Arrêt final (dépose) — toujours pending jusqu'à la fin du trajet
        $arrivalTime = $trip->estimated_arrival_time
            ?? ($trip->estimated_duration_minutes
                ? $departsAt->copy()->addMinutes($trip->estimated_duration_minutes)
                : null);
        $dropoffEta = $arrivalTime?->setTimezone($tz)->format('H:i') ?? '—';
        $dropoffAddr = $trip->arrival_city . ', ' . $trip->arrival_neighborhood
            . ($trip->arrival_point ? ' — ' . $trip->arrival_point : '');

        $stops[] = [
            'id'             => $trip->uuid . '_dropoff',
            'type'           => 'dropoff',
            'status'         => $firstPendingFound || $doneCount < $trip->bookings->count()
                ? 'pending'
                : 'approaching',
            'passenger_name' => 'Tous les passagers',
            'address'        => $dropoffAddr,
            'eta'            => $dropoffEta,
            'latlng'         => [
                'lat' => $trip->arrival_latitude,
                'lng' => $trip->arrival_longitude,
            ],
        ];

        // Point d'arrivée dans la polyline
        if ($trip->arrival_latitude && $trip->arrival_longitude) {
            $polyline[] = ['lat' => $trip->arrival_latitude, 'lng' => $trip->arrival_longitude];
        }

        return [$stops, $polyline];
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

    private function formatDuration(?int $minutes): ?string
    {
        if ($minutes === null) return null;
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $m > 0 ? "{$h}h{$m}" : "{$h}h00";
    }
}
