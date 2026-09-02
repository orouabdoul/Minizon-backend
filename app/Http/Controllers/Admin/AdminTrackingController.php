<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripIncident;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Suivi en temps réel & historique des trajets — Back-Office Admin.
 *
 * GET  /api/admin/tracking/trips                      → liste paginée tous statuts + filtres avancés
 * GET  /api/admin/tracking/live                       → positions GPS uniquement (polling 5s)
 * GET  /api/admin/tracking/stats                      → KPIs enrichis
 * GET  /api/admin/tracking/incidents                  → tous les incidents (filtrables)
 * GET  /api/admin/tracking/{uuid}                     → détail complet + passagers + points GPS
 * POST /api/admin/tracking/{uuid}/incident            → signaler incident
 * PATCH /api/admin/tracking/{uuid}/incident/resolve   → résoudre incident
 * PATCH /api/admin/tracking/{uuid}/flag               → flaguer/déflaguer un trajet
 * POST  /api/admin/tracking/{uuid}/notify-driver      → envoyer notification FCM au conducteur
 */
class AdminTrackingController extends Controller
{
    // =========================================================================
    //  GET /api/admin/tracking/trips
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/tracking/trips',
        operationId: 'adminTrackingList',
        summary: 'Suivi — liste paginée de tous les trajets',
        description: <<<'MD'
Retourne tous les trajets avec filtres avancés : statut, incidents, pannes, retards,
tris par jour / mois / année. Inclut position GPS actuelle et incident actif.
MD,
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status',   in: 'query', schema: new OA\Schema(type: 'string', enum: ['all','active','pending','completed','cancelled'], default: 'all')),
            new OA\Parameter(name: 'filter',   in: 'query', schema: new OA\Schema(type: 'string', enum: ['all','actif','incident','panne','en_retard','flagged'], default: 'all')),
            new OA\Parameter(name: 'date',     in: 'query', schema: new OA\Schema(type: 'string', format: 'date',    description: 'Filtre par jour exact (YYYY-MM-DD)')),
            new OA\Parameter(name: 'month',    in: 'query', schema: new OA\Schema(type: 'string', pattern: '^\d{4}-\d{2}$', description: 'Filtre par mois (YYYY-MM)')),
            new OA\Parameter(name: 'year',     in: 'query', schema: new OA\Schema(type: 'integer', example: 2026, description: 'Filtre par année')),
            new OA\Parameter(name: 'search',   in: 'query', schema: new OA\Schema(type: 'string',  description: 'Recherche conducteur, ville départ/arrivée, ID trajet')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 30)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des trajets',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'trips',    type: 'array', items: new OA\Items(ref: '#/components/schemas/TrackedTrip')),
                                new OA\Property(property: 'total',    type: 'integer'),
                                new OA\Property(property: 'page',     type: 'integer'),
                                new OA\Property(property: 'per_page', type: 'integer'),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function activeTrips(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 30), 100);
        $status  = $request->input('status', 'all');
        $filter  = $request->input('filter', 'all');

        $query = Trip::with([
            'user.profile',
            'vehicle',
            'bookings'      => fn ($q) => $q->where('status', 'accepted'),
            'activeIncident',
        ])->orderByDesc('departure_time');

        // ── Filtre statut ──────────────────────────────────────────────────────
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // ── Filtre date/mois/année ─────────────────────────────────────────────
        if ($request->filled('date')) {
            $query->whereDate('departure_time', $request->input('date'));
        } elseif ($request->filled('month')) {
            [$y, $m] = explode('-', $request->input('month'));
            $query->whereYear('departure_time', $y)->whereMonth('departure_time', $m);
        } elseif ($request->filled('year')) {
            $query->whereYear('departure_time', (int) $request->input('year'));
        }

        // ── Recherche texte ────────────────────────────────────────────────────
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('departure_city', 'like', "%{$s}%")
                  ->orWhere('arrival_city',  'like', "%{$s}%")
                  ->orWhereRaw('UPPER(LEFT(uuid,8)) = ?', [strtoupper($s)])
                  ->orWhereHas('user.profile', fn ($p) =>
                      $p->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name',  'like', "%{$s}%")
                  );
            });
        }

        // ── Filtre sémantique (après base query) ───────────────────────────────
        if ($filter === 'incident') {
            $query->whereHas('incidents', fn ($q) => $q->whereNull('resolved_at'));
        } elseif ($filter === 'panne') {
            $query->whereHas('incidents', fn ($q) => $q->where('type', 'panne')->whereNull('resolved_at'));
        } elseif ($filter === 'en_retard') {
            $nowStr = now()->format('Y-m-d H:i:s');
            // Trajet encore actif APRÈS l'heure d'arrivée estimée
            $query->where('status', 'active')
                  ->where(function ($q) use ($nowStr) {
                      $q->where(fn ($x) =>
                          $x->whereNotNull('estimated_arrival_time')
                            ->where('estimated_arrival_time', '<', $nowStr)
                      )->orWhere(fn ($x) =>
                          $x->whereNull('estimated_arrival_time')
                            ->whereNotNull('started_at')
                            ->whereNotNull('estimated_duration_minutes')
                            ->whereRaw('started_at + INTERVAL estimated_duration_minutes MINUTE < ?', [$nowStr])
                      );
                  });
        } elseif ($filter === 'actif') {
            $query->where('status', 'active')
                  ->whereDoesntHave('incidents', fn ($q) => $q->whereNull('resolved_at'));
        } elseif ($filter === 'flagged') {
            $query->where('is_flagged', true);
        }

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())
            ->map(fn (Trip $t) => $this->formatTrackedTrip($t));

        return $this->apiResponse(true, 'Trajets.', [
            'trips'    => $items,
            'total'    => $paginated->total(),
            'page'     => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
        ]);
    }

    // =========================================================================
    //  GET /api/admin/tracking/live  — positions GPS uniquement (polling 5s)
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/tracking/live',
        operationId: 'adminTrackingLive',
        summary: 'Positions GPS temps réel (polling 5s)',
        description: <<<'MD'
Retourne uniquement les données GPS des trajets actifs. Réponse volontairement légère
pour un polling côté client toutes les 5 secondes sans surcharger le réseau.
MD,
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Positions GPS',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success',     type: 'boolean'),
                        new OA\Property(property: 'server_time', type: 'string', format: 'date-time'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'positions',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'uuid',               type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'lat',                type: 'number', format: 'float', nullable: true),
                                            new OA\Property(property: 'lng',                type: 'number', format: 'float', nullable: true),
                                            new OA\Property(property: 'speed',              type: 'number', format: 'float', nullable: true),
                                            new OA\Property(property: 'status',             type: 'string'),
                                            new OA\Property(property: 'has_incident',       type: 'boolean'),
                                            new OA\Property(property: 'is_late',            type: 'boolean'),
                                            new OA\Property(property: 'location_updated_at',type: 'string', format: 'date-time', nullable: true),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function live(): JsonResponse
    {
        $trips = Trip::with('activeIncident')
            ->where('status', 'active')
            ->select([
                'id', 'uuid', 'status',
                'current_latitude', 'current_longitude', 'current_speed',
                'location_updated_at',
                'estimated_arrival_time',
                'started_at', 'estimated_duration_minutes',
                'is_flagged',
            ])
            ->get();

        $positions = $trips->map(function (Trip $t) {
            // Détection retard
            $isLate = false;
            if ($t->estimated_arrival_time) {
                $isLate = $t->estimated_arrival_time->isPast();
            } elseif ($t->started_at && $t->estimated_duration_minutes > 0) {
                $isLate = $t->started_at->addMinutes($t->estimated_duration_minutes)->isPast();
            }

            return [
                'uuid'                => $t->uuid,
                'lat'                 => $t->current_latitude,
                'lng'                 => $t->current_longitude,
                'speed'               => $t->current_speed,
                'status'              => $t->activeIncident ? 'incident' : 'actif',
                'has_incident'        => $t->activeIncident !== null,
                'incident_type'       => $t->activeIncident?->type,
                'is_late'             => $isLate,
                'is_flagged'          => (bool) $t->is_flagged,
                'location_updated_at' => $t->location_updated_at?->toIso8601String(),
            ];
        });

        return $this->apiResponse(true, 'Positions GPS.', [
            'server_time' => $now->toIso8601String(),
            'positions'   => $positions,
        ]);
    }

    // =========================================================================
    //  GET /api/admin/tracking/stats
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/tracking/stats',
        operationId: 'adminTrackingStats',
        summary: 'KPIs enrichis du suivi temps réel',
        description: 'Retourne les compteurs de la barre de statistiques avec ventilation par statut et tendance du jour.',
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'KPIs tracking',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'active_trips',     type: 'integer', example: 5),
                                new OA\Property(property: 'pending_trips',    type: 'integer', example: 8),
                                new OA\Property(property: 'incidents',        type: 'integer', example: 1),
                                new OA\Property(property: 'pannes',           type: 'integer', example: 1),
                                new OA\Property(property: 'late_trips',       type: 'integer', example: 2),
                                new OA\Property(property: 'drivers_online',   type: 'integer', example: 5),
                                new OA\Property(property: 'trips_today',      type: 'integer', example: 12),
                                new OA\Property(property: 'completed_today',  type: 'integer', example: 7),
                                new OA\Property(property: 'cancelled_today',  type: 'integer', example: 1),
                                new OA\Property(property: 'flagged_trips',    type: 'integer', example: 0),
                                new OA\Property(property: 'passengers_today', type: 'integer', example: 28),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function stats(): JsonResponse
    {
        $nowStr  = now()->format('Y-m-d H:i:s');
        $todayStr = today()->toDateString();

        $activeTrips  = Trip::where('status', 'active')->count();
        $pendingTrips = Trip::where('status', 'pending')->count();

        $incidents = TripIncident::whereNull('resolved_at')
            ->whereHas('trip', fn ($q) => $q->where('status', 'active'))
            ->count();

        $pannes = TripIncident::where('type', 'panne')
            ->whereNull('resolved_at')
            ->whereHas('trip', fn ($q) => $q->where('status', 'active'))
            ->count();

        // Trajets actifs dépassant leur heure d'arrivée estimée
        $lateTrips = Trip::where('status', 'active')
            ->where(function ($q) use ($nowStr) {
                $q->where(fn ($x) =>
                    $x->whereNotNull('estimated_arrival_time')
                      ->where('estimated_arrival_time', '<', $nowStr)
                )->orWhere(fn ($x) =>
                    $x->whereNull('estimated_arrival_time')
                      ->whereNotNull('started_at')
                      ->whereNotNull('estimated_duration_minutes')
                      ->whereRaw(
                          'started_at + INTERVAL estimated_duration_minutes MINUTE < ?',
                          [$nowStr]
                      )
                );
            })->count();

        $driversOnline  = Trip::where('status', 'active')->distinct('user_id')->count('user_id');
        $tripsToday     = Trip::whereDate('departure_time', $todayStr)->count();
        $completedToday = Trip::where('status', 'completed')->whereDate('completed_at', $todayStr)->count();
        $cancelledToday = Trip::where('status', 'cancelled')->whereDate('updated_at', $todayStr)->count();
        $flaggedTrips   = Trip::where('is_flagged', true)->count();

        $passengersToday = Booking::where('status', 'accepted')
            ->whereHas('trip', fn ($q) => $q->whereDate('departure_time', $todayStr))
            ->sum('seats_booked');

        return $this->apiResponse(true, 'Stats suivi.', [
            'active_trips'     => $activeTrips,
            'pending_trips'    => $pendingTrips,
            'incidents'        => $incidents,
            'pannes'           => $pannes,
            'late_trips'       => $lateTrips,
            'drivers_online'   => $driversOnline,
            'trips_today'      => $tripsToday,
            'completed_today'  => $completedToday,
            'cancelled_today'  => $cancelledToday,
            'flagged_trips'    => $flaggedTrips,
            'passengers_today' => (int) $passengersToday,
        ]);
    }

    // =========================================================================
    //  GET /api/admin/tracking/incidents
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/tracking/incidents',
        operationId: 'adminTrackingIncidents',
        summary: 'Liste de tous les incidents (actifs + historique)',
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'type',      in: 'query', schema: new OA\Schema(type: 'string', enum: ['all','panne','urgence','autre'], default: 'all')),
            new OA\Parameter(name: 'resolved',  in: 'query', schema: new OA\Schema(type: 'string', enum: ['all','yes','no'], default: 'all')),
            new OA\Parameter(name: 'date',      in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'month',     in: 'query', schema: new OA\Schema(type: 'string', pattern: '^\d{4}-\d{2}$')),
            new OA\Parameter(name: 'year',      in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page',  in: 'query', schema: new OA\Schema(type: 'integer', default: 30)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des incidents'),
        ]
    )]
    public function incidents(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 30), 100);

        $query = TripIncident::with(['trip.user.profile', 'reporter.profile'])
            ->orderByDesc('created_at');

        if ($request->filled('type') && $request->input('type') !== 'all') {
            $query->where('type', $request->input('type'));
        }

        $resolved = $request->input('resolved', 'all');
        if ($resolved === 'yes') {
            $query->whereNotNull('resolved_at');
        } elseif ($resolved === 'no') {
            $query->whereNull('resolved_at');
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        } elseif ($request->filled('month')) {
            [$y, $m] = explode('-', $request->input('month'));
            $query->whereYear('created_at', $y)->whereMonth('created_at', $m);
        } elseif ($request->filled('year')) {
            $query->whereYear('created_at', (int) $request->input('year'));
        }

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(function (TripIncident $i) {
            $trip    = $i->trip;
            $profile = $trip?->user?->profile;
            $driver  = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: '—';

            $repProfile = $i->reporter?->profile;
            $reporter   = trim(($repProfile?->first_name ?? '') . ' ' . ($repProfile?->last_name ?? '')) ?: 'Conducteur';

            return [
                'uuid'        => $i->uuid,
                'type'        => $i->type,
                'notes'       => $i->notes,
                'resolved'    => $i->isResolved(),
                'reported_at' => $i->created_at->toIso8601String(),
                'resolved_at' => $i->resolved_at?->toIso8601String(),
                'reporter'    => $reporter,
                'trip'        => $trip ? [
                    'uuid'  => $trip->uuid,
                    'id'    => strtoupper(substr($trip->uuid, 0, 8)),
                    'route' => $trip->route(),
                ] : null,
                'driver' => $driver,
                'driver_phone' => $trip?->user?->phone,
            ];
        });

        return $this->apiResponse(true, 'Incidents.', [
            'incidents' => $items,
            'total'     => $paginated->total(),
            'page'      => $paginated->currentPage(),
            'per_page'  => $paginated->perPage(),
        ]);
    }

    // =========================================================================
    //  GET /api/admin/tracking/{uuid}  — détail complet d'un trajet
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/tracking/{uuid}',
        operationId: 'adminTrackingDetail',
        summary: 'Détail complet d\'un trajet',
        description: 'Retourne toutes les informations du trajet : GPS, passagers avec points de prise/dépôt GPS, historique incidents, timeline.',
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail du trajet'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $trip = Trip::with([
            'user.profile',
            'vehicle.vehicleType',
            'bookings'  => fn ($q) => $q->with('passenger.profile')->orderBy('created_at'),
            'incidents' => fn ($q) => $q->with('reporter.profile')->orderByDesc('created_at'),
            'activeIncident',
        ])->where('uuid', $uuid)->firstOrFail();

        $now = now();

        // ── Passagers avec points de prise et dépôt ───────────────────────────
        $passengers = $trip->bookings->map(fn (Booking $b) => [
            'uuid'          => $b->uuid,
            'name'          => trim(($b->passenger?->profile?->first_name ?? '') . ' ' . ($b->passenger?->profile?->last_name ?? '')) ?: '—',
            'phone'         => $b->passenger?->phone,
            'seats'         => $b->seats_booked,
            'picked_up'     => $b->picked_up_at !== null,
            'picked_up_at'  => $b->picked_up_at?->toIso8601String(),
            'payment_status'=> $b->payment_status,
            'pickup' => [
                'city'         => $b->pickup_city,
                'neighborhood' => $b->pickup_neighborhood,
                'address'      => $b->pickup_address,
                'lat'          => $b->pickup_latitude,
                'lng'          => $b->pickup_longitude,
            ],
            'dropoff' => [
                'city'         => $b->dropoff_city,
                'neighborhood' => $b->dropoff_neighborhood,
                'address'      => $b->dropoff_address,
                'lat'          => $b->dropoff_latitude,
                'lng'          => $b->dropoff_longitude,
            ],
        ]);

        // ── Historique incidents ───────────────────────────────────────────────
        $incidentHistory = $trip->incidents->map(function (TripIncident $i) {
            $repProfile = $i->reporter?->profile;
            $reporter   = trim(($repProfile?->first_name ?? '') . ' ' . ($repProfile?->last_name ?? '')) ?: 'Système';
            return [
                'uuid'        => $i->uuid,
                'type'        => $i->type,
                'notes'       => $i->notes,
                'resolved'    => $i->isResolved(),
                'reporter'    => $reporter,
                'reported_at' => $i->created_at->toIso8601String(),
                'resolved_at' => $i->resolved_at?->toIso8601String(),
            ];
        });

        // ── Timeline du trajet ─────────────────────────────────────────────────
        $timeline = $this->buildTimeline($trip);

        // ── Détection retard ───────────────────────────────────────────────────
        $isLate = false;
        if ($trip->status === 'active') {
            if ($trip->estimated_arrival_time) {
                $isLate = $trip->estimated_arrival_time->isPast();
            } elseif ($trip->started_at && $trip->estimated_duration_minutes > 0) {
                $isLate = $trip->started_at->addMinutes($trip->estimated_duration_minutes)->isPast();
            }
        }

        // ── GPS — arrêts intermédiaires (waypoints) ────────────────────────────
        $waypoints = collect($trip->waypoints ?? [])->map(fn ($w) => [
            'city'    => $w['city']    ?? null,
            'address' => $w['address'] ?? null,
            'lat'     => $w['lat']     ?? null,
            'lng'     => $w['lng']     ?? null,
        ])->values();

        $detail = array_merge($this->formatTrackedTrip($trip), [
            'is_late'           => $isLate,
            'departure_point'   => [
                'label' => $trip->departure_point ?? $trip->departure_city,
                'lat'   => $trip->departure_latitude,
                'lng'   => $trip->departure_longitude,
            ],
            'arrival_point'     => [
                'label' => $trip->arrival_point ?? $trip->arrival_city,
                'lat'   => $trip->arrival_latitude,
                'lng'   => $trip->arrival_longitude,
            ],
            'waypoints'         => $waypoints,
            'distance_km'       => $trip->distance_km,
            'price_per_seat'    => $trip->price_per_seat,
            'total_seats'       => $trip->total_seats,
            'available_seats'   => $trip->available_seats,
            'is_flagged'        => (bool) $trip->is_flagged,
            'moderation_note'   => $trip->moderation_note,
            'started_at'        => $trip->started_at?->toIso8601String(),
            'completed_at'      => $trip->completed_at?->toIso8601String(),
            'vehicle'           => $trip->vehicle ? [
                'plate'  => $trip->vehicle->plate_number,
                'brand'  => $trip->vehicle->brand,
                'model'  => $trip->vehicle->model,
                'color'  => $trip->vehicle->color,
                'type'   => $trip->vehicle->vehicleType?->name,
            ] : null,
        ]);

        return $this->apiResponse(true, 'Détail du trajet.', [
            'trip'             => $detail,
            'passengers'       => $passengers,
            'incident_history' => $incidentHistory,
            'timeline'         => $timeline,
        ]);
    }

    // =========================================================================
    //  POST /api/admin/tracking/{uuid}/incident
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/tracking/{uuid}/incident',
        operationId: 'adminReportIncident',
        summary: 'Signaler un incident sur un trajet',
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type'],
                properties: [
                    new OA\Property(property: 'type',  type: 'string', enum: ['panne', 'urgence', 'autre']),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, maxLength: 500),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Incident créé'),
            new OA\Response(response: 409, description: 'Un incident actif existe déjà'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    public function reportIncident(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        $existing = TripIncident::where('trip_id', $trip->id)
            ->whereNull('resolved_at')
            ->first();

        if ($existing) {
            return $this->apiResponse(false, 'Un incident actif existe déjà pour ce trajet.', [
                'incident_uuid' => $existing->uuid,
                'type'          => $existing->type,
            ], 409);
        }

        $validated = $request->validate([
            'type'  => 'required|in:panne,urgence,autre',
            'notes' => 'nullable|string|max:500',
        ]);

        $incident = TripIncident::create([
            'trip_id'     => $trip->id,
            'type'        => $validated['type'],
            'notes'       => $validated['notes'] ?? null,
            'reported_by' => auth()->id(),
        ]);

        // Notifier le conducteur par FCM
        $driverToken = $trip->user?->fcm_token;
        if ($driverToken) {
            app(FcmService::class)->sendToMultiple(
                [$driverToken],
                'Incident signalé',
                'L\'administration a signalé un incident sur votre trajet.',
                ['type' => 'trip_incident', 'trip_uuid' => $trip->uuid, 'incident_type' => $incident->type]
            );
        }

        return $this->apiResponse(true, 'Incident signalé.', [
            'incident_uuid' => $incident->uuid,
            'type'          => $incident->type,
            'notes'         => $incident->notes,
            'resolved'      => false,
        ], 201);
    }

    // =========================================================================
    //  PATCH /api/admin/tracking/{uuid}/incident/resolve
    // =========================================================================

    #[OA\Patch(
        path: '/api/admin/tracking/{uuid}/incident/resolve',
        operationId: 'adminResolveIncident',
        summary: 'Résoudre l\'incident actif d\'un trajet',
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'resolution_note', type: 'string', nullable: true, maxLength: 500),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Incident résolu'),
            new OA\Response(response: 404, description: 'Aucun incident actif trouvé'),
        ]
    )]
    public function resolveIncident(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        $incident = TripIncident::where('trip_id', $trip->id)
            ->whereNull('resolved_at')
            ->latest()
            ->first();

        if (! $incident) {
            return $this->apiResponse(false, 'Aucun incident actif pour ce trajet.', [], 404);
        }

        $incident->update([
            'resolved_at'     => now(),
            'resolved_by'     => auth()->id(),
        ]);

        // Notifier le conducteur
        $driverToken = $trip->user?->fcm_token;
        if ($driverToken) {
            app(FcmService::class)->sendToMultiple(
                [$driverToken],
                'Incident résolu',
                'L\'incident sur votre trajet a été marqué comme résolu.',
                ['type' => 'incident_resolved', 'trip_uuid' => $trip->uuid]
            );
        }

        return $this->apiResponse(true, 'Incident marqué comme résolu.', [
            'incident_uuid' => $incident->uuid,
            'resolved_at'   => $incident->resolved_at->toIso8601String(),
        ]);
    }

    // =========================================================================
    //  PATCH /api/admin/tracking/{uuid}/flag
    // =========================================================================

    #[OA\Patch(
        path: '/api/admin/tracking/{uuid}/flag',
        operationId: 'adminFlagTrip',
        summary: 'Flaguer / déflaguer un trajet (modération)',
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['flag'],
                properties: [
                    new OA\Property(property: 'flag',             type: 'boolean', example: true),
                    new OA\Property(property: 'moderation_note',  type: 'string',  nullable: true, maxLength: 500),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Trajet mis à jour'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    public function flagTrip(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'flag'            => 'required|boolean',
            'moderation_note' => 'nullable|string|max:500',
        ]);

        $trip->update([
            'is_flagged'      => $validated['flag'],
            'moderation_note' => $validated['moderation_note'] ?? $trip->moderation_note,
        ]);

        $action = $validated['flag'] ? 'flagué' : 'déflagué';

        return $this->apiResponse(true, "Trajet {$action}.", [
            'uuid'            => $trip->uuid,
            'is_flagged'      => (bool) $trip->is_flagged,
            'moderation_note' => $trip->moderation_note,
        ]);
    }

    // =========================================================================
    //  POST /api/admin/tracking/{uuid}/notify-driver
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/tracking/{uuid}/notify-driver',
        operationId: 'adminNotifyDriver',
        summary: 'Envoyer une notification FCM au conducteur d\'un trajet',
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['message'],
                properties: [
                    new OA\Property(property: 'title',   type: 'string',  nullable: true, example: 'Message de l\'administration'),
                    new OA\Property(property: 'message', type: 'string',  example: 'Veuillez contacter le support.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Notification envoyée'),
            new OA\Response(response: 400, description: 'Conducteur sans token FCM'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    public function notifyDriver(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::with('user')->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'title'   => 'nullable|string|max:100',
            'message' => 'required|string|max:500',
        ]);

        $token = $trip->user?->fcm_token;
        if (! $token) {
            return $this->apiResponse(false, 'Ce conducteur n\'a pas de token FCM enregistré.', [], 400);
        }

        app(FcmService::class)->sendToMultiple(
            [$token],
            $validated['title'] ?? 'Message de l\'administration',
            $validated['message'],
            ['type' => 'admin_alert', 'trip_uuid' => $trip->uuid]
        );

        return $this->apiResponse(true, 'Notification envoyée au conducteur.');
    }

    // =========================================================================
    //  POST /api/admin/tracking/{uuid}/notify-passengers
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/tracking/{uuid}/notify-passengers',
        operationId: 'adminNotifyPassengers',
        summary: 'Envoyer une notification FCM à tous les passagers d\'un trajet',
        description: 'Notifie tous les passagers acceptés d\'un trajet (retard, urgence, information). Chaque passager reçoit un push FCM et une notification DB.',
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['message'],
                properties: [
                    new OA\Property(property: 'title',   type: 'string', nullable: true, example: 'Retard du trajet'),
                    new OA\Property(property: 'message', type: 'string', example: 'Votre trajet a un retard de 20 minutes. Merci de votre patience.'),
                    new OA\Property(property: 'type',    type: 'string', nullable: true, enum: ['admin_alert', 'trip_delay', 'trip_emergency'], default: 'admin_alert'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Notifications envoyées'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    public function notifyPassengers(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::with([
            'bookings' => fn ($q) => $q->where('status', 'accepted')->with('passenger'),
        ])->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'title'   => 'nullable|string|max:100',
            'message' => 'required|string|max:500',
            'type'    => 'nullable|string|in:admin_alert,trip_delay,trip_emergency',
        ]);

        $title   = $validated['title'] ?? 'Information Minizon';
        $msgBody = $validated['message'];
        $type    = $validated['type'] ?? 'admin_alert';

        $tokens  = [];
        $notified = 0;

        foreach ($trip->bookings as $booking) {
            $passenger = $booking->passenger;
            if (! $passenger) continue;

            // Notification DB
            try {
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => $type,
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $passenger->id,
                    'data'            => json_encode([
                        'type'      => $type,
                        'title'     => $title,
                        'body'      => $msgBody,
                        'trip_uuid' => $trip->uuid,
                    ]),
                    'read_at'    => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable) {}

            if ($passenger->fcm_token) {
                $tokens[] = $passenger->fcm_token;
            }
            $notified++;
        }

        if (! empty($tokens)) {
            app(FcmService::class)->sendToMultiple(
                $tokens,
                $title,
                $msgBody,
                ['type' => $type, 'trip_uuid' => $trip->uuid]
            );
        }

        return $this->apiResponse(true, "Notification envoyée à {$notified} passager(s).", [
            'notified_count' => $notified,
            'tokens_sent'    => count($tokens),
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatTrackedTrip(Trip $t): array
    {
        $profile  = $t->user?->profile;
        $incident = $t->activeIncident;

        $driverName = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
        if (empty($driverName)) $driverName = 'Conducteur';

        $passengerCount = $t->bookings?->sum('seats_booked') ?? 0;

        // ETA
        $eta = null;
        if ($t->estimated_arrival_time) {
            $eta = $t->estimated_arrival_time->setTimezone('Africa/Porto-Novo')->format('H:i');
        } elseif ($t->started_at && $t->estimated_duration_minutes) {
            $eta = $t->started_at->addMinutes($t->estimated_duration_minutes)
                      ->setTimezone('Africa/Porto-Novo')->format('H:i');
        }

        $avatarUrl = $profile?->selfie_front
            ? asset('storage/' . $profile->selfie_front)
            : 'https://ui-avatars.com/api/?name=' . urlencode($driverName) . '&background=00A86B&color=fff';

        // Statut enrichi
        $tripStatus = match ($t->status) {
            'active'    => $incident ? 'incident' : 'actif',
            'pending'   => 'en_attente',
            'completed' => 'terminé',
            'cancelled' => 'annulé',
            default     => $t->status,
        };

        return [
            'uuid'             => $t->uuid,
            'trip_id'          => strtoupper(substr($t->uuid, 0, 8)),
            'from'             => $t->departure_city,
            'to'               => $t->arrival_city,
            'departure_time'   => $t->departure_time?->setTimezone('Africa/Porto-Novo')->toIso8601String(),
            'status'           => $tripStatus,
            'raw_status'       => $t->status,
            'is_flagged'       => (bool) $t->is_flagged,
            'driver_name'      => $driverName,
            'driver_phone'     => $t->user?->phone ?? '',
            'driver_avatar'    => $avatarUrl,
            'passenger_count'  => $passengerCount,
            'total_seats'      => $t->total_seats,
            'estimated_arrival'=> $eta,
            'position' => [
                'lat'   => $t->current_latitude,
                'lng'   => $t->current_longitude,
                'speed' => $t->current_speed,
            ],
            'incident' => $incident ? [
                'uuid'     => $incident->uuid,
                'type'     => $incident->type,
                'notes'    => $incident->notes ?? '',
                'resolved' => false,
            ] : null,
            'started_at'          => $t->started_at?->toIso8601String(),
            'location_updated_at' => $t->location_updated_at?->toIso8601String(),
        ];
    }

    private function buildTimeline(Trip $t): array
    {
        $events = [];

        if ($t->created_at) {
            $events[] = ['event' => 'created',   'label' => 'Trajet créé',          'at' => $t->created_at->toIso8601String()];
        }
        if ($t->published_at) {
            $events[] = ['event' => 'published',  'label' => 'Publié',               'at' => $t->published_at->toIso8601String()];
        }
        if ($t->started_at) {
            $events[] = ['event' => 'started',    'label' => 'Trajet démarré',        'at' => $t->started_at->toIso8601String()];
        }
        foreach ($t->incidents ?? [] as $i) {
            $events[] = [
                'event' => 'incident',
                'label' => 'Incident : ' . $i->type,
                'at'    => $i->created_at->toIso8601String(),
            ];
            if ($i->resolved_at) {
                $events[] = ['event' => 'resolved', 'label' => 'Incident résolu', 'at' => $i->resolved_at->toIso8601String()];
            }
        }
        if ($t->completed_at) {
            $events[] = ['event' => 'completed', 'label' => 'Trajet terminé',        'at' => $t->completed_at->toIso8601String()];
        }

        usort($events, fn ($a, $b) => strcmp($a['at'], $b['at']));

        return $events;
    }
}

// ── OpenAPI schema ─────────────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'TrackedTrip',
    properties: [
        new OA\Property(property: 'uuid',              type: 'string',  format: 'uuid'),
        new OA\Property(property: 'trip_id',           type: 'string',  example: 'CF304AE1'),
        new OA\Property(property: 'from',              type: 'string',  example: 'Cotonou'),
        new OA\Property(property: 'to',                type: 'string',  example: 'Parakou'),
        new OA\Property(property: 'departure_time',    type: 'string',  format: 'date-time', nullable: true),
        new OA\Property(property: 'status',            type: 'string',  enum: ['actif', 'incident', 'en_attente', 'terminé', 'annulé']),
        new OA\Property(property: 'raw_status',        type: 'string',  enum: ['active', 'pending', 'completed', 'cancelled']),
        new OA\Property(property: 'is_flagged',        type: 'boolean', example: false),
        new OA\Property(property: 'driver_name',       type: 'string',  example: 'Koffi Mensah'),
        new OA\Property(property: 'driver_phone',      type: 'string',  example: '+22997000000'),
        new OA\Property(property: 'driver_avatar',     type: 'string',  format: 'uri'),
        new OA\Property(property: 'passenger_count',   type: 'integer', example: 3),
        new OA\Property(property: 'total_seats',       type: 'integer', example: 4),
        new OA\Property(property: 'estimated_arrival', type: 'string',  example: '14:30', nullable: true),
        new OA\Property(
            property: 'position',
            type: 'object',
            properties: [
                new OA\Property(property: 'lat',   type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'lng',   type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'speed', type: 'number', format: 'float', nullable: true),
            ]
        ),
        new OA\Property(
            property: 'incident',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'uuid',     type: 'string', format: 'uuid'),
                new OA\Property(property: 'type',     type: 'string', enum: ['panne', 'urgence', 'autre']),
                new OA\Property(property: 'notes',    type: 'string', nullable: true),
                new OA\Property(property: 'resolved', type: 'boolean'),
            ]
        ),
    ]
)]
class _TrackedTripSchema {}
