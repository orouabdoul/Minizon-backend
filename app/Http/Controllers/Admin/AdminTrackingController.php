<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Suivi en temps réel des trajets actifs — Back-Office Admin.
 *
 * Endpoints :
 *   GET  /api/admin/tracking           — liste des trajets actifs avec positions GPS
 *   GET  /api/admin/tracking/stats     — KPIs (actifs, incidents, conducteurs, aujourd'hui)
 *   POST /api/admin/tracking/{uuid}/incident        — signaler un incident
 *   PATCH /api/admin/tracking/{uuid}/incident/resolve — résoudre l'incident actif
 */
class AdminTrackingController extends Controller
{
    // =========================================================================
    //  GET /api/admin/tracking
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/tracking',
        operationId: 'adminTrackingList',
        summary: 'Suivi temps réel — liste des trajets actifs',
        description: 'Retourne tous les trajets avec status=active, enrichis de la position GPS actuelle du conducteur et de l\'incident actif éventuel. Polling recommandé toutes les 15 secondes.',
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'filter',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['all', 'actif', 'incident'], default: 'all')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des trajets tracés',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Trajets actifs.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'trips',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/TrackedTrip')
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function activeTrips(Request $request): JsonResponse
    {
        $filter = $request->input('filter', 'all');

        $trips = Trip::with([
            'user.profile',
            'vehicle',
            'bookings'   => fn ($q) => $q->where('status', 'accepted'),
            'activeIncident',
        ])
        ->where('status', 'active')
        ->get();

        if ($filter === 'incident') {
            $trips = $trips->filter(fn ($t) => $t->activeIncident !== null);
        } elseif ($filter === 'actif') {
            $trips = $trips->filter(fn ($t) => $t->activeIncident === null);
        }

        $result = $trips->values()->map(fn (Trip $t) => $this->formatTrackedTrip($t));

        return $this->apiResponse(true, 'Trajets actifs.', ['trips' => $result]);
    }

    // =========================================================================
    //  GET /api/admin/tracking/stats
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/tracking/stats',
        operationId: 'adminTrackingStats',
        summary: 'KPIs du suivi temps réel',
        description: 'Retourne les compteurs de la barre de statistiques : trajets actifs, incidents en cours, conducteurs en ligne, trajets du jour.',
        tags: ['👑 Admin — Suivi temps réel'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'KPIs tracking',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'activeTrips',    type: 'integer', example: 5),
                                new OA\Property(property: 'incidents',      type: 'integer', example: 1),
                                new OA\Property(property: 'driversOnline',  type: 'integer', example: 5),
                                new OA\Property(property: 'tripsToday',     type: 'integer', example: 12),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function stats(): JsonResponse
    {
        $activeTrips = Trip::where('status', 'active')->count();

        $incidents = TripIncident::whereNull('resolved_at')
            ->whereHas('trip', fn ($q) => $q->where('status', 'active'))
            ->count();

        // Conducteurs en ligne = conducteurs ayant un trajet actif
        $driversOnline = Trip::where('status', 'active')
            ->distinct('user_id')
            ->count('user_id');

        $tripsToday = Trip::whereDate('created_at', today())->count();

        return $this->apiResponse(true, 'Stats suivi.', [
            'activeTrips'   => $activeTrips,
            'incidents'     => $incidents,
            'driversOnline' => $driversOnline,
            'tripsToday'    => $tripsToday,
        ]);
    }

    // =========================================================================
    //  POST /api/admin/tracking/{uuid}/incident
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/tracking/{uuid}/incident',
        operationId: 'adminReportIncident',
        summary: 'Signaler un incident sur un trajet actif',
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
                    new OA\Property(property: 'type',  type: 'string', enum: ['panne', 'urgence', 'autre'], example: 'panne'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Crevaison signalée par le conducteur'),
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

        // Un seul incident actif à la fois
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
        responses: [
            new OA\Response(response: 200, description: 'Incident résolu'),
            new OA\Response(response: 404, description: 'Aucun incident actif trouvé'),
        ]
    )]
    public function resolveIncident(string $uuid): JsonResponse
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
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        return $this->apiResponse(true, 'Incident marqué comme résolu.', [
            'incident_uuid' => $incident->uuid,
            'resolved_at'   => $incident->resolved_at->toIso8601String(),
        ]);
    }

    // =========================================================================
    //  GET /api/admin/tracking/{uuid}  — détail d'un trajet tracé
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/tracking/{uuid}',
        operationId: 'adminTrackingDetail',
        summary: 'Détail d\'un trajet tracé',
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
            'bookings' => fn ($q) => $q->with('passenger.profile')->where('status', 'accepted'),
            'incidents' => fn ($q) => $q->orderBy('created_at', 'desc'),
        ])->where('uuid', $uuid)->firstOrFail();

        $passengers = $trip->bookings->map(fn ($b) => [
            'name'                => trim(($b->passenger?->profile?->first_name ?? '') . ' ' . ($b->passenger?->profile?->last_name ?? '')),
            'phone'               => $b->passenger?->phone,
            'seats'               => $b->seats_booked,
            'pickup_city'         => $b->pickup_city,
            'pickup_neighborhood' => $b->pickup_neighborhood,
            'pickup_address'      => $b->pickup_address,
            'dropoff_city'        => $b->dropoff_city,
            'dropoff_neighborhood'=> $b->dropoff_neighborhood,
            'dropoff_address'     => $b->dropoff_address,
        ]);

        $incidentHistory = $trip->incidents->map(fn ($i) => [
            'uuid'        => $i->uuid,
            'type'        => $i->type,
            'notes'       => $i->notes,
            'resolved'    => $i->isResolved(),
            'reported_at' => $i->created_at->toIso8601String(),
            'resolved_at' => $i->resolved_at?->toIso8601String(),
        ]);

        return $this->apiResponse(true, 'Détail du trajet.', [
            'trip'             => $this->formatTrackedTrip($trip->load('activeIncident')),
            'passengers'       => $passengers,
            'incident_history' => $incidentHistory,
        ]);
    }

    // =========================================================================
    //  HELPER PRIVÉ
    // =========================================================================

    private function formatTrackedTrip(Trip $t): array
    {
        $profile  = $t->user?->profile;
        $incident = $t->activeIncident;

        $driverName = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
        if (empty($driverName)) $driverName = 'Conducteur';

        $passengerCount = $t->bookings?->sum('seats_booked') ?? 0;

        // ETA approximée : heure départ + durée estimée
        $eta = '—';
        if ($t->estimated_arrival_time) {
            $eta = $t->estimated_arrival_time
                ->setTimezone('Africa/Porto-Novo')
                ->format('H:i');
        } elseif ($t->started_at && $t->estimated_duration_minutes) {
            $eta = $t->started_at
                ->addMinutes($t->estimated_duration_minutes)
                ->setTimezone('Africa/Porto-Novo')
                ->format('H:i');
        }

        // Avatar via storage ou placeholder
        $avatarUrl = $profile?->selfie_front
            ? asset('storage/' . $profile->selfie_front)
            : 'https://ui-avatars.com/api/?name=' . urlencode($driverName) . '&background=00A86B&color=fff';

        return [
            'id'               => $t->uuid,
            'tripId'           => strtoupper(substr($t->uuid, 0, 8)),
            'from'             => $t->departure_city,
            'to'               => $t->arrival_city,
            'driverName'       => $driverName,
            'driverPhone'      => $t->user?->phone ?? '',
            'driverAvatar'     => $avatarUrl,
            'passengerCount'   => $passengerCount,
            'estimatedArrival' => $eta,
            'status'           => $incident ? 'incident' : 'actif',
            'position'         => [
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
}

// ── OpenAPI schema ─────────────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'TrackedTrip',
    properties: [
        new OA\Property(property: 'id',               type: 'string', format: 'uuid'),
        new OA\Property(property: 'tripId',           type: 'string', example: 'CF304AE1'),
        new OA\Property(property: 'from',             type: 'string', example: 'Cotonou'),
        new OA\Property(property: 'to',               type: 'string', example: 'Parakou'),
        new OA\Property(property: 'driverName',       type: 'string', example: 'Koffi Mensah'),
        new OA\Property(property: 'driverPhone',      type: 'string', example: '+22997000000'),
        new OA\Property(property: 'driverAvatar',     type: 'string', format: 'uri'),
        new OA\Property(property: 'passengerCount',   type: 'integer', example: 3),
        new OA\Property(property: 'estimatedArrival', type: 'string', example: '14:30'),
        new OA\Property(property: 'status',           type: 'string', enum: ['actif', 'incident']),
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
