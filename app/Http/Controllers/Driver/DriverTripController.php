<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DriverTripController extends Controller
{
    // =========================================================================
    //  GET /api/driver/trips  — liste filtrée avec compteurs et passagers
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/trips',
        operationId: 'driverTripList',
        summary: 'Mes trajets (conducteur)',
        description: "Retourne tous les trajets du conducteur avec filtres par statut, compteurs par onglet et la liste des passagers acceptés (initiales + nom) pour chaque trajet. Utilisé pour alimenter la page Trajets de l'app mobile.",
        tags: ['🚗 Driver — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                description: 'Filtre par statut. Valeurs : `all` (défaut), `pending`, `active`, `completed`, `cancelled`.',
                schema: new OA\Schema(type: 'string', enum: ['all', 'pending', 'active', 'completed', 'cancelled'], default: 'all')
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Trajets du conducteur',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Mes trajets.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'filter_counts',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'all',       type: 'integer', example: 12),
                                        new OA\Property(property: 'pending',   type: 'integer', example: 3),
                                        new OA\Property(property: 'active',    type: 'integer', example: 1),
                                        new OA\Property(property: 'completed', type: 'integer', example: 7),
                                        new OA\Property(property: 'cancelled', type: 'integer', example: 1),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'trips',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/DriverTripCard')
                                ),
                                new OA\Property(
                                    property: 'pagination',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'current_page', type: 'integer'),
                                        new OA\Property(property: 'last_page',    type: 'integer'),
                                        new OA\Property(property: 'per_page',     type: 'integer'),
                                        new OA\Property(property: 'total',        type: 'integer'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Compte non approuvé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $user     = $request->user();
        $statuses = ['pending', 'active', 'completed', 'cancelled'];
        $filter   = $request->input('status', 'all');

        // ── Compteurs par statut ───────────────────────────────────────────────
        $counts     = Trip::where('user_id', $user->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->toArray();
        $filterCounts = ['all' => array_sum($counts)];
        foreach ($statuses as $s) {
            $filterCounts[$s] = $counts[$s] ?? 0;
        }

        // ── Requête filtrée ───────────────────────────────────────────────────
        $query = Trip::with(['bookings.passenger.profile'])
            ->where('user_id', $user->id);

        if ($filter !== 'all' && in_array($filter, $statuses)) {
            $query->where('status', $filter);
        }

        $paginated = $query->orderByDesc('departure_time')->paginate(15);

        $trips = $paginated->getCollection()->map(fn (Trip $trip) => $this->serializeCard($trip));

        return $this->apiResponse(true, 'Mes trajets.', [
            'filter_counts' => $filterCounts,
            'trips'         => $trips,
            'pagination'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // =========================================================================
    //  GET /api/driver/trips/{uuid}/passengers
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/trips/{uuid}/passengers',
        operationId: 'driverTripPassengers',
        summary: 'Passagers d\'un trajet (conducteur)',
        description: "Liste des passagers acceptés pour un trajet donné : nom complet, téléphone, places réservées, statut de la réservation et statut de paiement.",
        tags: ['🚗 Driver — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des passagers',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Passagers du trajet.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'trip_uuid',    type: 'string', format: 'uuid'),
                                new OA\Property(property: 'trip_route',   type: 'string', example: 'Cotonou → Bohicon'),
                                new OA\Property(property: 'total_booked', type: 'integer'),
                                new OA\Property(
                                    property: 'passengers',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'booking_uuid',  type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'full_name',     type: 'string', example: 'Koffi Mensah'),
                                            new OA\Property(property: 'initials',      type: 'string', example: 'KM'),
                                            new OA\Property(property: 'phone',         type: 'string'),
                                            new OA\Property(property: 'seats_booked',  type: 'integer'),
                                            new OA\Property(property: 'booking_status', type: 'string', enum: ['pending', 'accepted', 'rejected', 'cancelled']),
                                            new OA\Property(property: 'payment_status', type: 'string', nullable: true),
                                            new OA\Property(property: 'booked_at',     type: 'string', format: 'date-time'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'pending_requests',
                                    type: 'array',
                                    description: 'Demandes en attente de réponse du conducteur',
                                    items: new OA\Items(type: 'object')
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function passengers(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::with(['bookings.passenger.profile'])
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable ou accès refusé.', [], 404);
        }

        $accepted = [];
        $pending  = [];

        foreach ($trip->bookings as $booking) {
            $passenger = $booking->passenger;
            $profile   = $passenger?->profile;
            $fullName  = $profile?->fullName() ?: ($passenger?->phone ?? '—');
            $initials  = $this->initials($fullName);

            $item = [
                'booking_uuid'   => $booking->uuid,
                'full_name'      => $fullName,
                'initials'       => $initials,
                'phone'          => $passenger?->phone,
                'seats_booked'   => $booking->seats_booked,
                'booking_status' => $booking->status,
                'payment_status' => $booking->payment_status,
                'booked_at'      => $booking->created_at,
            ];

            if ($booking->status === 'pending') {
                $pending[] = $item;
            } elseif ($booking->status === 'accepted') {
                $accepted[] = $item;
            }
        }

        $totalBooked = array_sum(array_column($accepted, 'seats_booked'));

        return $this->apiResponse(true, 'Passagers du trajet.', [
            'trip_uuid'        => $trip->uuid,
            'trip_route'       => $trip->route(),
            'total_booked'     => $totalBooked,
            'passengers'       => $accepted,
            'pending_requests' => $pending,
        ]);
    }

    // =========================================================================
    //  POST /api/driver/trips/{uuid}/cancel
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/trips/{uuid}/cancel',
        operationId: 'driverTripCancel',
        summary: 'Annuler un trajet',
        description: "Passe le statut du trajet à `cancelled`. Seuls les trajets `pending` peuvent être annulés. Les réservations associées passent également en `cancelled`.",
        tags: ['🚗 Driver — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trajet annulé'),
            new OA\Response(response: 403, description: 'Accès refusé ou annulation impossible', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if (! in_array($trip->status, ['pending'])) {
            return $this->apiResponse(false, 'Impossible d\'annuler un trajet ' . $trip->status . '.', [], 403);
        }

        $trip->update(['status' => 'cancelled']);

        // Annuler toutes les réservations en attente ou acceptées
        Booking::where('trip_id', $trip->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->update(['status' => 'cancelled']);

        return $this->apiResponse(true, 'Trajet annulé.', ['uuid' => $trip->uuid, 'status' => 'cancelled']);
    }

    // =========================================================================
    //  SCHEMA OA (placeholder pour la doc)
    // =========================================================================

    #[OA\Schema(
        schema: 'DriverTripCard',
        description: 'Carte trajet sur la page Trajets conducteur',
        properties: [
            new OA\Property(property: 'uuid',         type: 'string', format: 'uuid'),
            new OA\Property(property: 'status',        type: 'string', enum: ['pending', 'active', 'completed', 'cancelled']),
            new OA\Property(property: 'status_label',  type: 'string', example: 'À venir'),
            // Géographie
            new OA\Property(property: 'origin',            type: 'string', example: 'Cotonou, Akpakpa'),
            new OA\Property(property: 'origin_point',      type: 'string', nullable: true, example: 'Carrefour Étoile Rouge'),
            new OA\Property(property: 'destination',       type: 'string', example: 'Parakou, Centre-ville'),
            new OA\Property(property: 'destination_point', type: 'string', nullable: true, example: 'Gare routière'),
            // Timing
            new OA\Property(property: 'departure_time',             type: 'string', format: 'date-time'),
            new OA\Property(property: 'departure_time_label',       type: 'string', example: 'Dim. 07:00'),
            new OA\Property(property: 'estimated_arrival_time',     type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'estimated_duration_minutes', type: 'integer', nullable: true),
            // Capacité
            new OA\Property(property: 'seats_total',     type: 'integer', example: 4),
            new OA\Property(property: 'seats_available', type: 'integer', example: 2),
            new OA\Property(property: 'seats_booked',    type: 'integer', example: 2),
            new OA\Property(property: 'max_per_booking', type: 'integer', example: 2),
            // Finance
            new OA\Property(property: 'price_per_seat',      type: 'integer', example: 3500),
            new OA\Property(property: 'price_label',          type: 'string',  example: '3 500 XOF'),
            new OA\Property(property: 'driver_net_per_seat',  type: 'integer', example: 3150),
            new OA\Property(property: 'commission_rate',      type: 'integer', example: 10),
            // Mode & politique
            new OA\Property(property: 'booking_mode',        type: 'string', enum: ['instant', 'approval']),
            new OA\Property(property: 'cancellation_policy', type: 'string', enum: ['flexible', 'moderate', 'strict']),
            // Contenu
            new OA\Property(property: 'published_ago',  type: 'string',  example: 'il y a 2h'),
            new OA\Property(property: 'description',    type: 'string',  nullable: true),
            new OA\Property(property: 'preferences',    type: 'array',   items: new OA\Items(type: 'string')),
            new OA\Property(property: 'waypoints',      type: 'array',   items: new OA\Items(type: 'object')),
            // Récurrence
            new OA\Property(property: 'is_recurring',   type: 'boolean'),
            new OA\Property(property: 'recurring_days', type: 'array', items: new OA\Items(type: 'string')),
            // État
            new OA\Property(property: 'is_published', type: 'boolean'),
            new OA\Property(property: 'is_flagged',   type: 'boolean'),
            // Passagers & note
            new OA\Property(property: 'note', type: 'string', nullable: true),
            new OA\Property(
                property: 'passengers',
                type: 'array',
                items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'initials', type: 'string', example: 'KM'),
                        new OA\Property(property: 'name',     type: 'string', example: 'Koffi Mensah'),
                    ]
                )
            ),
            // Actions
            new OA\Property(
                property: 'primary_action',
                type: 'object',
                properties: [
                    new OA\Property(property: 'label',   type: 'string',  example: 'Démarrer'),
                    new OA\Property(property: 'enabled', type: 'boolean', example: true),
                    new OA\Property(property: 'action',  type: 'string',  enum: ['start', 'end', 'view', 'none']),
                ]
            ),
            new OA\Property(property: 'can_edit',   type: 'boolean'),
            new OA\Property(property: 'can_cancel', type: 'boolean'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function serializeCard(Trip $trip): array
    {
        $acceptedBookings = $trip->bookings->where('status', 'accepted');
        $pendingBookings  = $trip->bookings->where('status', 'pending');
        $seatsBooked      = $acceptedBookings->sum('seats_booked');

        // Passagers (initiales) pour les avatars
        $passengers = $acceptedBookings->map(function (Booking $b) {
            $profile  = $b->passenger?->profile;
            $fullName = $profile?->fullName() ?: ($b->passenger?->phone ?? '?');
            return [
                'initials' => $this->initials($fullName),
                'name'     => $fullName,
            ];
        })->values()->all();

        // Note contextuelle
        $note = null;
        if ($pendingBookings->count() > 0 && in_array($trip->status, ['pending', 'active'])) {
            $n    = $pendingBookings->count();
            $note = $n === 1 ? '1 demande en attente de réponse' : "{$n} demandes en attente de réponse";
        }

        // Action principale
        $primaryAction = match ($trip->status) {
            'pending'   => ['label' => 'Démarrer', 'enabled' => true,  'action' => 'start'],
            'active'    => ['label' => 'Terminer', 'enabled' => true,  'action' => 'end'],
            'completed' => ['label' => 'Terminé',  'enabled' => false, 'action' => 'view'],
            default     => ['label' => 'Annulé',   'enabled' => false, 'action' => 'none'],
        };

        return [
            'uuid'                 => $trip->uuid,
            'status'               => $trip->status,
            'status_label'         => $this->statusLabel($trip->status),

            // Géographie
            'origin'               => $trip->departure_city . ', ' . $trip->departure_neighborhood,
            'origin_point'         => $trip->departure_point,
            'destination'          => $trip->arrival_city . ', ' . $trip->arrival_neighborhood,
            'destination_point'    => $trip->arrival_point,

            // Timing
            'departure_time'             => $trip->departure_time,
            'departure_time_label'       => $trip->departure_time->translatedFormat('D. H\hi'),
            'estimated_arrival_time'     => $trip->estimated_arrival_time,
            'estimated_duration_minutes' => $trip->estimated_duration_minutes,

            // Capacité
            'seats_total'          => $trip->total_seats,
            'seats_available'      => $trip->available_seats,
            'seats_booked'         => (int) $seatsBooked,
            'max_per_booking'      => $trip->max_per_booking,

            // Finance
            'price_per_seat'       => $trip->price_per_seat,
            'price_label'          => number_format($trip->price_per_seat, 0, ',', ' ') . ' XOF',
            'driver_net_per_seat'  => $trip->driverEarnings(1),
            'commission_rate'      => $trip->commission_rate,

            // Mode & politique
            'booking_mode'         => $trip->booking_mode,
            'cancellation_policy'  => $trip->cancellation_policy,

            // Contenu
            'published_ago'        => $trip->created_at->diffForHumans(null, true),
            'description'          => $trip->description,
            'preferences'          => $trip->preferences ?? [],
            'waypoints'            => $trip->waypoints ?? [],

            // Récurrence
            'is_recurring'         => (bool) $trip->is_recurring,
            'recurring_days'       => $trip->recurring_days ?? [],

            // État
            'is_published'         => (bool) $trip->is_published,
            'is_flagged'           => (bool) $trip->is_flagged,

            // Passagers & note
            'note'                 => $note,
            'passengers'           => $passengers,

            // Actions
            'primary_action'       => $primaryAction,
            'can_edit'             => $trip->status === 'pending',
            'can_cancel'           => $trip->status === 'pending',
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending'   => 'À venir',
            'active'    => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            default     => $status,
        };
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), 2);
        $first = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
        $last  = mb_strtoupper(mb_substr($parts[1] ?? '', 0, 1));
        return $first . $last ?: '??';
    }
}
