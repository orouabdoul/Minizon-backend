<?php

namespace App\Http\Controllers\Driver;

use App\Helpers\GeoHelper;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BoardingEvent;
use App\Models\Trip;
use App\Notifications\BoardingRequestNotification;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Carte interactive du trajet" — navigation en cours.
 *
 * Ce contrôleur gère les données statiques et les actions de la carte.
 * Les mises à jour GPS temps-réel passent par :
 *   POST /api/trips/{uuid}/location  (TripController::updateLocation)
 * Ce même endpoint déclenche les notifications d'approche FCM vers les passagers.
 *
 * Statuts des arrêts :
 *   - pending    : pas encore atteint
 *   - approaching: prochain arrêt non terminé (next stop)
 *   - done       : passager pris en charge (picked_up_at != null)
 */
class DriverInteractiveMapController extends Controller
{
    private const FUEL_L_PER_KM = 0.08; // 8L/100km moyenne Bénin

    // =========================================================================
    //  GET /api/driver/trips/{uuid}/map
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/trips/{uuid}/map',
        operationId: 'driverTripMap',
        summary: 'Données de la carte interactive du trajet en cours',
        description: "Retourne en un seul appel tout ce dont la page \"Carte interactive\" a besoin : position conducteur, arrêts ordonnés avec statuts (pending/approaching/done), polyline de route simplifiée, distance, ETA et estimation carburant.\n\n**Arrêts :** chaque passager génère 2 arrêts — un `pickup` (prise en charge) et un `dropoff` (dépose). Les coordonnées GPS sont celles enregistrées par le passager lors de sa réservation.\n\n**Mises à jour temps-réel :** la télémétrie GPS continue de passer par **POST /api/trips/{uuid}/location** (toutes les 5s). Ce même endpoint déclenche les notifications FCM d'approche vers les passagers.",
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
                                    description: 'Arrêts ordonnés : pickups (un par passager) puis dropoffs (un par passager)',
                                    items: new OA\Items(ref: '#/components/schemas/MapStop')
                                ),
                                new OA\Property(
                                    property: 'route_polyline',
                                    type: 'array',
                                    description: 'Points de la polyligne (départ → pickups → arrivée finale)',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'lat', type: 'number'),
                                            new OA\Property(property: 'lng', type: 'number'),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'route_distance',      type: 'string',  example: '413 km'),
                                new OA\Property(property: 'route_eta',           type: 'string',  example: '5h30'),
                                new OA\Property(property: 'route_fuel',          type: 'string',  example: '~33L'),
                                new OA\Property(property: 'current_stop_index',  type: 'integer', example: 0, description: 'Index (0-based) du prochain arrêt pickup à atteindre'),
                                new OA\Property(property: 'completed_pickups',   type: 'integer', example: 1, description: 'Nombre de passagers déjà pris en charge'),
                                new OA\Property(property: 'total_pickups',       type: 'integer', example: 3, description: 'Nombre total de passagers à prendre en charge'),
                                new OA\Property(property: 'completed_stops',     type: 'integer', example: 1, description: 'Alias de completed_pickups (compatibilité)'),
                                new OA\Property(property: 'total_stops',         type: 'integer', example: 6, description: 'Total d\'arrêts affichés (pickups + dropoffs)'),
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

        [$stops, $polyline, $completedPickups, $totalPickups] = $this->buildStopsAndPolyline($trip);

        [$depLat, $depLng] = GeoHelper::bestCoords(
            $trip->departure_latitude, $trip->departure_longitude,
            $trip->departure_city ?? '',
            $trip->departure_arrondissement ?? null,
            $trip->departure_neighborhood   ?? null,
        );
        [$arrLatCoord, $arrLngCoord] = GeoHelper::bestCoords(
            $trip->arrival_latitude, $trip->arrival_longitude,
            $trip->arrival_city ?? '',
            $trip->arrival_arrondissement ?? null,
            $trip->arrival_neighborhood   ?? null,
        );
        $distanceKm    = $trip->distance_km ?? $this->haversineKm($depLat, $depLng, $arrLatCoord, $arrLngCoord);
        $routeDistance = $distanceKm !== null ? number_format($distanceKm, 0, '.', '') . ' km' : '— km';
        $routeEta      = $this->formatDuration($trip->estimated_duration_minutes) ?? '—';
        $routeFuel     = $distanceKm !== null ? '~' . number_format($distanceKm * self::FUEL_L_PER_KM, 0) . 'L' : '—';

        return $this->apiResponse(true, 'Carte du trajet.', [
            'driver_position'   => [
                'lat' => $trip->current_latitude  ?? $depLat,
                'lng' => $trip->current_longitude ?? $depLng,
            ],
            'stops'             => $stops,
            'route_polyline'    => $polyline,
            'route_distance'    => $routeDistance,
            'route_eta'         => $routeEta,
            'route_fuel'        => $routeFuel,
            'current_stop_index'=> $completedPickups,
            'completed_pickups' => $completedPickups,
            'total_pickups'     => $totalPickups,
            'completed_stops'   => $completedPickups,
            'total_stops'       => count($stops),
        ]);
    }

    // =========================================================================
    //  POST /api/driver/trips/{uuid}/stops/{bookingUuid}/done
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/trips/{uuid}/stops/{bookingUuid}/done',
        operationId: 'driverMarkStopDone',
        summary: 'Marquer un arrêt (pickup) comme terminé',
        description: "Enregistre la prise en charge d'un passager en remplissant `picked_up_at` sur la réservation.\n\nEnvoie automatiquement une notification FCM au passager : **\"✅ Vous êtes à bord !\"**\n\nPour terminer le trajet (dernier arrêt / dépose finale), utilisez **POST /api/trips/{uuid}/end**.",
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
                                new OA\Property(property: 'stop_id',          type: 'string',  format: 'uuid'),
                                new OA\Property(property: 'picked_up_at',     type: 'string',  format: 'date-time'),
                                new OA\Property(property: 'next_stop_index',  type: 'integer', example: 2, description: 'Index du prochain arrêt pickup à atteindre'),
                                new OA\Property(property: 'all_picked_up',    type: 'boolean', description: 'true = tous les passagers sont à bord, navigation vers la dépose'),
                                new OA\Property(property: 'notification_sent',type: 'boolean', description: 'true = notification FCM envoyée au passager'),
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
            ->with('passenger')
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }
        if ($booking->picked_up_at !== null) {
            return $this->apiResponse(false, 'Ce passager a déjà été pris en charge.', [], 403);
        }

        $booking->update(['picked_up_at' => now()]);

        // Notification FCM au passager : "vous êtes à bord"
        $notificationSent = false;
        $fcmToken = $booking->passenger?->fcm_token;
        if ($fcmToken) {
            $driverName = $request->user()->profile?->fullName()
                ?? $request->user()->phone;
            $notificationSent = app(FcmService::class)->send(
                $fcmToken,
                '✅ Vous êtes à bord !',
                'Le conducteur ' . $driverName . ' vous a pris en charge. Bon voyage !',
                [
                    'type'         => 'passenger_picked_up',
                    'trip_uuid'    => $trip->uuid,
                    'booking_uuid' => $booking->uuid,
                    'sound'        => 'boarding_sound',
                ]
            );
        }

        // Compter les pickups restants après ce marquage
        $doneCount  = $trip->bookings()
            ->where('status', 'accepted')
            ->whereNotNull('picked_up_at')
            ->count();
        $totalCount = $trip->bookings()
            ->where('status', 'accepted')
            ->count();

        return $this->apiResponse(true, 'Passager pris en charge.', [
            'stop_id'           => $booking->uuid,
            'picked_up_at'      => $booking->picked_up_at,
            'next_stop_index'   => $doneCount,
            'all_picked_up'     => $doneCount >= $totalCount,
            'notification_sent' => $notificationSent,
        ]);
    }

    // =========================================================================
    //  POST /api/driver/trips/{uuid}/stops/{bookingUuid}/boarding-request
    //  Conducteur déclenche la demande d'embarquement → FCM au passager
    // =========================================================================

    public function boardingRequest(Request $request, string $uuid, string $bookingUuid): JsonResponse
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
            ->with(['passenger.profile', 'trip'])
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }
        if ($booking->picked_up_at !== null) {
            return $this->apiResponse(false, 'Ce passager a déjà été pris en charge.', [], 403);
        }

        $driverLat = $request->input('driver_lat') ? (float) $request->input('driver_lat') : null;
        $driverLng = $request->input('driver_lng') ? (float) $request->input('driver_lng') : null;

        $expiresAt = now()->addMinutes(2)->toIso8601String();

        // Notifier le passager
        $notified = false;
        try {
            $booking->passenger?->notify(
                new BoardingRequestNotification($booking, $driverLat, $driverLng, $expiresAt)
            );
            $notified = true;
        } catch (\Throwable) {}

        BoardingEvent::log(
            bookingUuid:  $booking->uuid,
            tripUuid:     $trip->uuid,
            eventType:    'request_sent',
            driverId:     $request->user()->id,
            passengerId:  $booking->passenger_id,
            driverLat:    $driverLat,
            driverLng:    $driverLng,
        );

        return $this->apiResponse(true, 'Demande d\'embarquement envoyée.', [
            'expires_at'   => $expiresAt,
            'notified'     => $notified,
            'booking_uuid' => $booking->uuid,
        ]);
    }

    // =========================================================================
    //  POST /api/driver/trips/{uuid}/stops/{bookingUuid}/verify-code
    //  Conducteur saisit le code 4 chiffres du passager (fallback hors réseau)
    // =========================================================================

    public function verifyCode(Request $request, string $uuid, string $bookingUuid): JsonResponse
    {
        $validated = $request->validate([
            'code'       => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'driver_lat' => ['nullable', 'numeric'],
            'driver_lng' => ['nullable', 'numeric'],
        ]);

        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip || $trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Trajet introuvable ou accès refusé.', [], 403);
        }
        if (! in_array($trip->status, ['pending', 'active'])) {
            return $this->apiResponse(false, 'Le trajet n\'est pas disponible (statut : ' . $trip->status . ').', [], 403);
        }

        $booking = Booking::where('uuid', $bookingUuid)
            ->where('trip_id', $trip->id)
            ->where('status', 'accepted')
            ->with('passenger')
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }
        if ($booking->picked_up_at !== null) {
            return $this->apiResponse(false, 'Ce passager a déjà été pris en charge.', [], 403);
        }

        if ((string) $booking->boarding_code !== $validated['code']) {
            return $this->apiResponse(false, 'Code incorrect.', [], 422);
        }

        $driverLat = isset($validated['driver_lat']) ? (float) $validated['driver_lat'] : null;
        $driverLng = isset($validated['driver_lng']) ? (float) $validated['driver_lng'] : null;

        $booking->update([
            'picked_up_at'          => now(),
            'boarding_method'       => 'code',
            'driver_lat_at_boarding'=> $driverLat,
            'driver_lng_at_boarding'=> $driverLng,
        ]);

        // Auto-démarrage du trajet au premier embarquement par code
        if ($trip->status === 'pending') {
            $trip->update(['status' => 'active', 'started_at' => now()]);
        }

        BoardingEvent::log(
            bookingUuid:  $booking->uuid,
            tripUuid:     $trip->uuid,
            eventType:    'code_verified',
            driverId:     $request->user()->id,
            passengerId:  $booking->passenger_id,
            driverLat:    $driverLat,
            driverLng:    $driverLng,
        );

        // Notification FCM au passager
        $fcmToken = $booking->passenger?->fcm_token;
        if ($fcmToken) {
            $driverName = $request->user()->profile?->fullName() ?? $request->user()->phone;
            app(FcmService::class)->send(
                $fcmToken,
                '✅ Vous êtes à bord !',
                'Le conducteur ' . $driverName . ' vous a pris en charge (code). Bon voyage !',
                [
                    'type'         => 'passenger_picked_up',
                    'trip_uuid'    => $trip->uuid,
                    'booking_uuid' => $booking->uuid,
                    'method'       => 'code',
                ]
            );
        }

        $doneCount  = $trip->bookings()->where('status', 'accepted')->whereNotNull('picked_up_at')->count();
        $totalCount = $trip->bookings()->where('status', 'accepted')->count();

        return $this->apiResponse(true, 'Code vérifié. Passager pris en charge.', [
            'stop_id'         => $booking->uuid,
            'picked_up_at'    => $booking->picked_up_at->toIso8601String(),
            'next_stop_index' => $doneCount,
            'all_picked_up'   => $doneCount >= $totalCount,
        ]);
    }

    // =========================================================================
    //  POST /api/driver/trips/{uuid}/recalculate
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/trips/{uuid}/recalculate',
        operationId: 'driverRecalculateRoute',
        summary: 'Recalculer / optimiser l\'itinéraire',
        description: "Retourne l'itinéraire réoptimisé : les arrêts pickup non terminés sont triés par proximité avec la position actuelle du conducteur (Haversine). Les ETAs sont recalculées en conséquence. Lève le bandeau de confirmation côté Flutter (`showOptimizationBanner = true`).",
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
                                new OA\Property(property: 'optimized',          type: 'boolean', example: true),
                                new OA\Property(property: 'stops',              type: 'array',   items: new OA\Items(ref: '#/components/schemas/MapStop')),
                                new OA\Property(property: 'route_polyline',     type: 'array',   items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'route_distance',     type: 'string',  example: '408 km'),
                                new OA\Property(property: 'route_eta',          type: 'string',  example: '5h20'),
                                new OA\Property(property: 'route_fuel',         type: 'string',  example: '~33L'),
                                new OA\Property(property: 'current_stop_index', type: 'integer', example: 1),
                                new OA\Property(property: 'completed_pickups',  type: 'integer', example: 1, description: 'Passagers pris en charge avant le recalcul'),
                                new OA\Property(property: 'total_pickups',      type: 'integer', example: 3, description: 'Total de passagers à prendre en charge'),
                                new OA\Property(property: 'completed_stops',    type: 'integer', example: 1),
                                new OA\Property(property: 'total_stops',        type: 'integer', example: 6, description: 'Total arrêts (pickups + dropoffs)'),
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

        $driverLat = $trip->current_latitude  ?? $trip->departure_latitude;
        $driverLng = $trip->current_longitude ?? $trip->departure_longitude;

        $acceptedBookings = $trip->bookings;

        // Garder l'ordre des arrêts terminés, trier les pending par proximité GPS passager
        $done    = $acceptedBookings->whereNotNull('picked_up_at')->sortBy('picked_up_at');
        $pending = $acceptedBookings->whereNull('picked_up_at')->sortBy(
            fn ($b) => $this->haversineKm(
                $driverLat, $driverLng,
                $b->pickup_latitude  ?? $trip->departure_latitude,
                $b->pickup_longitude ?? $trip->departure_longitude,
            ) ?? PHP_INT_MAX
        );

        $trip->setRelation('bookings', $done->merge($pending)->values());

        [$stops, $polyline, $completedPickups, $totalPickups] = $this->buildStopsAndPolyline($trip);

        [$depLatR, $depLngR] = GeoHelper::bestCoords(
            $trip->departure_latitude, $trip->departure_longitude,
            $trip->departure_city ?? '',
            $trip->departure_arrondissement ?? null,
            $trip->departure_neighborhood   ?? null,
        );
        [$arrLatR, $arrLngR] = GeoHelper::bestCoords(
            $trip->arrival_latitude, $trip->arrival_longitude,
            $trip->arrival_city ?? '',
            $trip->arrival_arrondissement ?? null,
            $trip->arrival_neighborhood   ?? null,
        );
        $distanceKm    = $trip->distance_km ?? $this->haversineKm($depLatR, $depLngR, $arrLatR, $arrLngR);
        $routeDistance = $distanceKm !== null ? number_format($distanceKm, 0, '.', '') . ' km' : '— km';
        $routeEta      = $this->formatDuration($trip->estimated_duration_minutes) ?? '—';
        $routeFuel     = $distanceKm !== null ? '~' . number_format($distanceKm * self::FUEL_L_PER_KM, 0) . 'L' : '—';

        return $this->apiResponse(true, 'Itinéraire optimisé.', [
            'optimized'          => true,
            'stops'              => $stops,
            'route_polyline'     => $polyline,
            'route_distance'     => $routeDistance,
            'route_eta'          => $routeEta,
            'route_fuel'         => $routeFuel,
            'current_stop_index' => $completedPickups,
            'completed_pickups'  => $completedPickups,
            'total_pickups'      => $totalPickups,
            'completed_stops'    => $completedPickups,
            'total_stops'        => count($stops),
        ]);
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'MapStop',
        description: 'Arrêt de la carte interactive — pickup (prise en charge) ou dropoff (dépose)',
        properties: [
            new OA\Property(property: 'id',             type: 'string',  description: 'UUID réservation pour pickups, "{uuid}_dropoff" pour dépose'),
            new OA\Property(property: 'type',           type: 'string',  enum: ['pickup', 'dropoff']),
            new OA\Property(property: 'status',         type: 'string',  enum: ['pending', 'approaching', 'done']),
            new OA\Property(property: 'passenger_name', type: 'string',  example: 'Koffi Mensah'),
            new OA\Property(property: 'passenger_phone',type: 'string',  nullable: true, example: '+22961234567'),
            new OA\Property(property: 'address',        type: 'string',  example: 'Akpakpa, Cotonou'),
            new OA\Property(property: 'eta',            type: 'string',  example: '07:15'),
            new OA\Property(
                property: 'latlng',
                type: 'object',
                nullable: true,
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
     * Construit la liste des arrêts et la polyline.
     *
     * Arrêts retournés :
     *   [pickup_1, pickup_2, ..., dropoff_1, dropoff_2, ...]
     *
     * @return array{0: array, 1: array, 2: int, 3: int}
     *   [stops, polyline, completedPickups, totalPickups]
     */
    private function buildStopsAndPolyline(Trip $trip): array
    {
        $pickupStops  = [];
        $dropoffStops = [];
        $polyline     = [];
        $tz           = 'Africa/Porto-Novo';

        // Point de départ — résoudre depuis géo si GPS absent
        [$depLat, $depLng] = GeoHelper::bestCoords(
            $trip->departure_latitude,
            $trip->departure_longitude,
            $trip->departure_city ?? '',
            $trip->departure_arrondissement ?? null,
            $trip->departure_neighborhood   ?? null,
        );
        if ($depLat && $depLng) {
            $polyline[] = ['lat' => $depLat, 'lng' => $depLng];
        }
        $waypointsForRoute = $depLat && $depLng ? [['lat' => $depLat, 'lng' => $depLng]] : [];

        $departsAt    = $trip->departure_time
            ? $trip->departure_time->setTimezone($tz)
            : now()->setTimezone($tz);
        $pickupOffset = 0;
        $doneCount    = 0;

        foreach ($trip->bookings as $booking) {
            $isDone  = $booking->picked_up_at !== null;
            if ($isDone) $doneCount++;

            $profile = $booking->passenger?->profile;
            $name    = $profile?->fullName() ?: ($booking->passenger?->phone ?? '—');
            $phone   = $booking->passenger?->phone;

            // ── Pickup stop — GPS précis via commune+arrondissement+quartier ───
            [$pickupLat, $pickupLng] = GeoHelper::bestCoords(
                $booking->pickup_latitude,
                $booking->pickup_longitude,
                $booking->pickup_city ?? $trip->departure_city ?? '',
                $booking->pickup_arrondissement ?? $trip->departure_arrondissement ?? null,
                $booking->pickup_neighborhood   ?? $trip->departure_neighborhood   ?? null,
            );
            $pickupAddr = $booking->pickup_address
                ?? ($booking->pickup_neighborhood
                    ? "{$booking->pickup_neighborhood}, {$booking->pickup_city}"
                    : "{$trip->departure_neighborhood}, {$trip->departure_city}");

            $pickupEta = $departsAt->copy()->addMinutes($pickupOffset)->format('H:i');

            $pickupStops[] = [
                'id'              => $booking->uuid,
                'type'            => 'pickup',
                'status'          => 'pending', // résolu après la boucle
                'passenger_name'  => $name,
                'passenger_phone' => $phone,
                'address'         => $pickupAddr,
                'eta'             => $pickupEta,
                'latlng'          => ($pickupLat && $pickupLng)
                    ? ['lat' => $pickupLat, 'lng' => $pickupLng]
                    : null,
            ];

            if ($pickupLat && $pickupLng) {
                $polyline[]          = ['lat' => $pickupLat, 'lng' => $pickupLng];
                $waypointsForRoute[] = ['lat' => $pickupLat, 'lng' => $pickupLng];
            }

            // ── Dropoff stop — GPS précis via commune+arrondissement+quartier ──
            [$dropoffLat, $dropoffLng] = GeoHelper::bestCoords(
                $booking->dropoff_latitude,
                $booking->dropoff_longitude,
                $booking->dropoff_city ?? $trip->arrival_city ?? '',
                $booking->dropoff_arrondissement ?? $trip->arrival_arrondissement ?? null,
                $booking->dropoff_neighborhood   ?? $trip->arrival_neighborhood   ?? null,
            );
            $dropoffAddr = $booking->dropoff_address
                ?? ($booking->dropoff_neighborhood
                    ? "{$booking->dropoff_neighborhood}, {$booking->dropoff_city}"
                    : "{$trip->arrival_neighborhood}, {$trip->arrival_city}"
                        . ($trip->arrival_point ? ' — ' . $trip->arrival_point : ''));

            // ETA dépose = ETA départ + durée estimée trajet
            $dropoffEta = $trip->estimated_arrival_time
                ? $trip->estimated_arrival_time->setTimezone($tz)->format('H:i')
                : ($trip->estimated_duration_minutes
                    ? $departsAt->copy()->addMinutes($trip->estimated_duration_minutes)->format('H:i')
                    : '—');

            $dropoffStops[] = [
                'id'              => $booking->uuid . '_dropoff',
                'type'            => 'dropoff',
                'status'          => 'pending', // résolu après la boucle
                'passenger_name'  => $name,
                'passenger_phone' => $phone,
                'address'         => $dropoffAddr,
                'eta'             => $dropoffEta,
                'latlng'          => ($dropoffLat && $dropoffLng)
                    ? ['lat' => $dropoffLat, 'lng' => $dropoffLng]
                    : null,
            ];

            $pickupOffset += 5;
        }

        // Point d'arrivée — résoudre depuis géo si GPS absent
        [$arrLat, $arrLng] = GeoHelper::bestCoords(
            $trip->arrival_latitude,
            $trip->arrival_longitude,
            $trip->arrival_city ?? '',
            $trip->arrival_arrondissement ?? null,
            $trip->arrival_neighborhood   ?? null,
        );
        if ($arrLat && $arrLng) {
            $polyline[]          = ['lat' => $arrLat, 'lng' => $arrLng];
            $waypointsForRoute[] = ['lat' => $arrLat, 'lng' => $arrLng];
        }

        // Remplacer la polyline lignes-droites par la vraie route routière
        $routePolyline = GeoHelper::buildRoutePolyline($waypointsForRoute);
        // Si la route réelle est disponible, l'utiliser ; sinon garder les points directs
        if (count($routePolyline) > count($waypointsForRoute)) {
            $polyline = $routePolyline;
        }

        // ── Résolution des statuts pickups ────────────────────────────────────
        $firstPendingPickup = false;
        foreach ($pickupStops as &$stop) {
            $booking = $trip->bookings->firstWhere('uuid', $stop['id']);
            if ($booking?->picked_up_at !== null) {
                $stop['status'] = 'done';
            } elseif (! $firstPendingPickup) {
                $stop['status']       = 'approaching';
                $firstPendingPickup   = true;
            } else {
                $stop['status'] = 'pending';
            }
        }
        unset($stop);

        // ── Résolution des statuts dropoffs ───────────────────────────────────
        // Si tous les pickups sont terminés → le premier dropoff est "approaching"
        $allPickupsDone         = ! $firstPendingPickup;
        $firstPendingDropoff    = false;
        foreach ($dropoffStops as &$stop) {
            if ($allPickupsDone && ! $firstPendingDropoff) {
                $stop['status']       = 'approaching';
                $firstPendingDropoff  = true;
            } else {
                $stop['status'] = 'pending';
            }
        }
        unset($stop);

        $totalPickups = count($pickupStops);
        $stops        = array_merge($pickupStops, $dropoffStops);

        return [$stops, $polyline, $doneCount, $totalPickups];
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
        return $m > 0 ? sprintf('%dh%02d', $h, $m) : "{$h}h00";
    }
}
