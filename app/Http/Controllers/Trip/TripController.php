<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Notifications\TripCompleted;
use App\Notifications\TripStarted;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * Gestion des trajets — endpoints publics + actions conducteur.
 *
 * POST /api/trips/{uuid}/location  — push GPS toutes les ~5s depuis le device conducteur
 *                                    + détection de proximité → notifications FCM passagers
 * POST /api/trips/{uuid}/start     — démarre le trajet (status pending → active)
 * POST /api/trips/{uuid}/end       — termine le trajet (status active → completed)
 * GET  /api/trips/{uuid}/tracking  — position publique pour les passagers
 */
class TripController extends Controller
{
    // Rayon en mètres en dessous duquel on notifie le passager
    private const APPROACH_RADIUS_M = 300;

    // =========================================================================
    //  GET /api/trips  — liste publique
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $query = Trip::with(['user.profile', 'vehicle'])
            ->where('is_published', true)
            ->where('is_flagged', false)
            ->where('status', 'pending')
            ->where('departure_time', '>', now())
            ->orderBy('departure_time');

        if ($request->filled('from')) {
            $query->where('departure_city', 'like', '%' . $request->from . '%');
        }
        if ($request->filled('to')) {
            $query->where('arrival_city', 'like', '%' . $request->to . '%');
        }
        if ($request->filled('date')) {
            $query->whereDate('departure_time', $request->date);
        }
        if ($request->filled('seats')) {
            $query->where('available_seats', '>=', (int) $request->seats);
        }

        $trips = $query->paginate($request->input('per_page', 20));

        return $this->apiResponse(true, 'Trajets disponibles.', [
            'trips'        => collect($trips->items())->map(fn ($t) => $this->formatTrip($t)),
            'current_page' => $trips->currentPage(),
            'last_page'    => $trips->lastPage(),
            'total'        => $trips->total(),
        ]);
    }

    // =========================================================================
    //  GET /api/trips/{uuid}  — détail public
    // =========================================================================

    public function show(string $uuid): JsonResponse
    {
        $trip = Trip::with(['user.profile', 'vehicle.vehicleType'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->apiResponse(true, 'Détail du trajet.', ['trip' => $this->formatTrip($trip)]);
    }

    // =========================================================================
    //  HELPER — sérialisation formatée d'un trajet (heure Bénin + champs calculés)
    // =========================================================================

    private function formatTrip(\App\Models\Trip $trip): array
    {
        $tz      = 'Africa/Porto-Novo';
        $depTime = $trip->departure_time?->setTimezone($tz);
        $arrTime = $trip->estimated_arrival_time?->setTimezone($tz);

        $durationLabel = null;
        if ($trip->estimated_duration_minutes) {
            $h = intdiv($trip->estimated_duration_minutes, 60);
            $m = $trip->estimated_duration_minutes % 60;
            $durationLabel = $h > 0 ? "{$h}h" . ($m > 0 ? " {$m}min" : '') : "{$m}min";
        }

        $profile = $trip->user?->profile;
        $vehicle = $trip->vehicle;

        $driverName = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: 'Conducteur';

        return [
            'uuid'            => $trip->uuid,
            'status'          => $trip->status,
            'is_published'    => $trip->is_published,
            'booking_mode'    => $trip->booking_mode,
            'cancellation_policy' => $trip->cancellation_policy,

            // ── Géographie départ ──────────────────────────────────────────
            'departure_city'             => $trip->departure_city,
            'departure_arrondissement'   => $trip->departure_arrondissement,
            'departure_neighborhood'     => $trip->departure_neighborhood,
            'departure_point'            => $trip->departure_point,
            'departure_latitude'         => $trip->departure_latitude,
            'departure_longitude'        => $trip->departure_longitude,

            // ── Géographie arrivée ─────────────────────────────────────────
            'arrival_city'               => $trip->arrival_city,
            'arrival_arrondissement'     => $trip->arrival_arrondissement,
            'arrival_neighborhood'       => $trip->arrival_neighborhood,
            'arrival_point'              => $trip->arrival_point,
            'arrival_latitude'           => $trip->arrival_latitude,
            'arrival_longitude'          => $trip->arrival_longitude,

            // ── Horaires (heure Bénin) ─────────────────────────────────────
            'departure_time'             => $depTime?->toIso8601String(),
            'departure_time_formatted'   => $depTime?->format('H:i'),
            'departure_date_formatted'   => $depTime?->translatedFormat('D. d/m'),
            'departure_datetime_label'   => $depTime?->translatedFormat('D. d/m \à H\hi'),
            'estimated_arrival_time'     => $arrTime?->toIso8601String(),
            'estimated_arrival_formatted'=> $arrTime ? '~' . $arrTime->format('H:i') : null,

            // ── Métriques trajet ───────────────────────────────────────────
            'distance_km'                => $trip->distance_km,
            'estimated_duration_minutes' => $trip->estimated_duration_minutes,
            'duration_label'             => $durationLabel,

            // ── Prix & places ──────────────────────────────────────────────
            'price_per_seat'    => $trip->price_per_seat,
            'total_seats'       => $trip->total_seats,
            'available_seats'   => $trip->available_seats,
            'max_per_booking'   => $trip->max_per_booking,

            // ── Contenu ────────────────────────────────────────────────────
            'description'       => $trip->description,
            'preferences'       => $trip->preferences ?? [],
            'waypoints'         => $trip->waypoints   ?? [],

            // ── Conducteur ─────────────────────────────────────────────────
            'driver_name'       => $driverName,
            'driver_initials'   => $this->driverInitials($driverName),

            // ── Véhicule ───────────────────────────────────────────────────
            'vehicle'           => $vehicle ? trim("{$vehicle->brand} {$vehicle->model}") : null,
            'vehicle_plate'     => $vehicle?->license_plate,
            'vehicle_label'     => $vehicle ? "{$vehicle->brand} {$vehicle->model} · {$vehicle->license_plate}" : null,
        ];
    }

    private function driverInitials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn ($w) => strtoupper($w[0]))
            ->join('');
    }

    // =========================================================================
    //  POST /api/trips  — création (conducteur approuvé)
    // =========================================================================

    public function store(Request $request): JsonResponse
    {
        // Délégué au DriverAddTripController — utiliser POST /api/driver/trips
        return $this->apiResponse(false, 'Utilisez POST /api/driver/trips pour créer un trajet.', [], 410);
    }

    // =========================================================================
    //  PUT /api/trips/{uuid}  — mise à jour
    // =========================================================================

    public function update(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if ($trip->isActive() || $trip->isCompleted()) {
            return $this->apiResponse(false, 'Impossible de modifier un trajet en cours ou terminé.', [], 422);
        }

        $validated = $request->validate([
            'departure_time'             => 'sometimes|date|after:now',
            'price_per_seat'             => 'sometimes|integer|min:100',
            'available_seats'            => 'sometimes|integer|min:1',
            'description'                => 'nullable|string|max:500',
            'preferences'                => 'nullable|array',
        ]);

        $trip->update($validated);

        return $this->apiResponse(true, 'Trajet mis à jour.', ['trip' => $trip->fresh()]);
    }

    // =========================================================================
    //  DELETE /api/trips/{uuid}
    // =========================================================================

    public function destroy(string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if ($trip->isActive()) {
            return $this->apiResponse(false, 'Impossible de supprimer un trajet en cours.', [], 422);
        }

        $trip->delete();

        return $this->apiResponse(true, 'Trajet supprimé.');
    }

    // =========================================================================
    //  POST /api/trips/{uuid}/start  — démarrage officiel du trajet
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/start',
        operationId: 'tripStart',
        summary: 'Démarrer un trajet',
        tags: ['🚗 Driver — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trajet démarré'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ]
    )]
    public function startTrip(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if (! $trip->isPending()) {
            return $this->apiResponse(false, 'Le trajet ne peut pas être démarré dans son état actuel.', [], 422);
        }

        $trip->update([
            'status'     => 'active',
            'started_at' => now(),
        ]);

        // Notifier tous les passagers avec une réservation acceptée
        try {
            $passengers = Booking::with('passenger')
                ->where('trip_id', $trip->id)
                ->where('status', 'accepted')
                ->get()
                ->pluck('passenger')
                ->filter();

            foreach ($passengers as $passenger) {
                $passenger->notify(new TripStarted($trip));
            }
        } catch (\Throwable $e) {
            Log::warning('TripController startTrip: notif passagers échouée', ['error' => $e->getMessage()]);
        }

        return $this->apiResponse(true, 'Trajet démarré.', [
            'uuid'       => $trip->uuid,
            'status'     => 'active',
            'started_at' => $trip->started_at?->toIso8601String(),
        ]);
    }

    // =========================================================================
    //  POST /api/trips/{uuid}/end  — fin de trajet
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/end',
        operationId: 'tripEnd',
        summary: 'Terminer un trajet',
        tags: ['🚗 Driver — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trajet terminé'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Trajet non actif'),
        ]
    )]
    public function endTrip(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if (! $trip->isActive()) {
            return $this->apiResponse(false, 'Le trajet n\'est pas en cours.', [], 422);
        }

        $trip->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Notifier les passagers et le conducteur
        try {
            $passengers = Booking::with('passenger')
                ->where('trip_id', $trip->id)
                ->where('status', 'accepted')
                ->get()
                ->pluck('passenger')
                ->filter();

            foreach ($passengers as $passenger) {
                $passenger->notify(new TripCompleted($trip, 'passenger'));
            }

            // Notifier le conducteur pour la libération des fonds sous 24h
            $trip->user?->notify(new TripCompleted($trip, 'driver'));
        } catch (\Throwable $e) {
            Log::warning('TripController endTrip: notif échouée', ['error' => $e->getMessage()]);
        }

        return $this->apiResponse(true, 'Trajet terminé.', [
            'uuid'         => $trip->uuid,
            'status'       => 'completed',
            'completed_at' => $trip->completed_at?->toIso8601String(),
        ]);
    }

    // =========================================================================
    //  POST /api/trips/{uuid}/location  — push GPS conducteur (polling ~5s)
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/location',
        operationId: 'tripUpdateLocation',
        summary: 'Mettre à jour la position GPS du conducteur',
        description: "Appelé par le device conducteur toutes les **5 secondes** pendant un trajet actif.\n\nMet à jour `current_latitude`, `current_longitude`, `current_speed` et `location_updated_at` sur le trajet.\n\n**Détection de proximité :** si le conducteur est à moins de 300m d'un point de prise en charge (pickup) d'un passager, une notification FCM **\"🚗 Votre conducteur approche !\"** est envoyée automatiquement à ce passager (une seule fois par arrêt par trajet, sonnerie activée).\n\nRetourne `approaching_stops` — liste des arrêts dont la notification vient d'être déclenchée.",
        tags: ['🚗 Driver — Trajets', '🚗 Driver — Interactive Map'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['latitude', 'longitude'],
                properties: [
                    new OA\Property(property: 'latitude',  type: 'number', format: 'float', example: 7.1234),
                    new OA\Property(property: 'longitude', type: 'number', format: 'float', example: 2.3456),
                    new OA\Property(property: 'speed',     type: 'number', format: 'float', example: 72.5, nullable: true, description: 'Vitesse en km/h (optionnel)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Position enregistrée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Position mise à jour.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'lat',   type: 'number', example: 7.1234),
                                new OA\Property(property: 'lng',   type: 'number', example: 2.3456),
                                new OA\Property(property: 'speed', type: 'number', nullable: true),
                                new OA\Property(
                                    property: 'approaching_stops',
                                    type: 'array',
                                    description: 'Arrêts dont la notification FCM vient d\'être envoyée (vide si aucun)',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'booking_uuid', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'distance_m',   type: 'integer', example: 245),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Non autorisé ou trajet non actif'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function updateLocation(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if (! $trip->isActive()) {
            return $this->apiResponse(false, 'La position ne peut être mise à jour que pendant un trajet actif.', [], 403);
        }

        $validated = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed'     => 'nullable|numeric|min:0|max:300',
        ]);

        $trip->update([
            'current_latitude'    => $validated['latitude'],
            'current_longitude'   => $validated['longitude'],
            'current_speed'       => $validated['speed'] ?? null,
            'location_updated_at' => now(),
        ]);

        // Détection de proximité → notifications FCM passagers
        $approaching = $this->checkProximityNotifications(
            $trip,
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        return $this->apiResponse(true, 'Position mise à jour.', [
            'lat'              => (float) $validated['latitude'],
            'lng'              => (float) $validated['longitude'],
            'speed'            => isset($validated['speed']) ? (float) $validated['speed'] : null,
            'approaching_stops'=> $approaching,
        ]);
    }

    // =========================================================================
    //  GET /api/trips/{uuid}/tracking  — position publique (passager)
    // =========================================================================

    #[OA\Get(
        path: '/api/trips/{uuid}/tracking',
        operationId: 'tripGetTracking',
        summary: 'Position en temps réel du conducteur (accès public)',
        tags: ['👤 Passenger — Réservations'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Position du conducteur'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    public function getTracking(string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if (! $trip->isActive()) {
            return $this->apiResponse(false, 'Ce trajet n\'est pas en cours.', [], 422);
        }

        $staleThreshold = now()->subMinutes(2);
        $isStale = $trip->location_updated_at === null
            || $trip->location_updated_at->lt($staleThreshold);

        return $this->apiResponse(true, 'Position du conducteur.', [
            'lat'                 => $trip->current_latitude,
            'lng'                 => $trip->current_longitude,
            'speed_kmh'           => $trip->current_speed,
            'location_updated_at' => $trip->location_updated_at?->toIso8601String(),
            'is_stale'            => $isStale,
            'status'              => $trip->status,
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    /**
     * Vérifie si le conducteur est dans le rayon d'approche (300m) d'un pickup.
     * Envoie une notification FCM au passager une seule fois par arrêt (cache 2h).
     *
     * @return array  Liste des arrêts dont la notification vient d'être déclenchée.
     */
    private function checkProximityNotifications(Trip $trip, float $driverLat, float $driverLng): array
    {
        $approaching = [];

        // Uniquement les pickups non encore terminés avec des coordonnées GPS
        $pendingBookings = Booking::where('trip_id', $trip->id)
            ->where('status', 'accepted')
            ->whereNull('picked_up_at')
            ->whereNotNull('pickup_latitude')
            ->whereNotNull('pickup_longitude')
            ->with('passenger:id,fcm_token,phone')
            ->get();

        foreach ($pendingBookings as $booking) {
            $distM    = $this->metersApart(
                $driverLat, $driverLng,
                (float) $booking->pickup_latitude,
                (float) $booking->pickup_longitude
            );
            $cacheKey = "approach_notified_{$trip->id}_{$booking->id}";

            if ($distM <= self::APPROACH_RADIUS_M && ! Cache::has($cacheKey)) {
                $fcmToken = $booking->passenger?->fcm_token;
                if ($fcmToken) {
                    app(FcmService::class)->send(
                        $fcmToken,
                        '🚗 Votre conducteur approche !',
                        'Préparez-vous, le conducteur est à ' . round($distM) . 'm de votre point de prise en charge.',
                        [
                            'type'         => 'driver_approaching',
                            'trip_uuid'    => $trip->uuid,
                            'booking_uuid' => $booking->uuid,
                            'distance_m'   => (string) round($distM),
                            'sound'        => 'approach_sound',
                        ]
                    );
                }
                // Marquer comme notifié pour 2h (durée max d'un trajet)
                Cache::put($cacheKey, true, now()->addMinutes(120));

                $approaching[] = [
                    'booking_uuid' => $booking->uuid,
                    'distance_m'   => (int) round($distM),
                ];
            }
        }

        return $approaching;
    }

    /** Distance en mètres entre deux points GPS (Haversine). */
    private function metersApart(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R  = 6_371_000; // rayon Terre en mètres
        $dL = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a  = sin($dL / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dl / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }
}
