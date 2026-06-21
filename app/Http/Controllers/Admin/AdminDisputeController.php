<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\TripValidation;
use App\Notifications\DisputeStatusChanged;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '⚖️ Admin — Litiges', description: 'Supervision et arbitrage des litiges passager/conducteur (Back-Office)')]
class AdminDisputeController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function priorityLabel(string $reasonType): string
    {
        return match ($reasonType) {
            'scam'             => 'Critique',
            'bad_behavior'     => 'Élevée',
            'driver_absent'    => 'Moyenne',
            'passenger_absent' => 'Faible',
            default            => 'Faible',
        };
    }

    private function typeLabel(string $reasonType): string
    {
        return match ($reasonType) {
            'scam'                             => 'Paiement',
            'bad_behavior'                     => 'Comportement',
            'driver_absent', 'passenger_absent' => 'Annulation',
            default                             => 'Autre',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending'                                    => 'Ouvert',
            'investigating'                              => 'En cours',
            'resolved_refunded', 'resolved_paid_to_driver' => 'Résolu',
            default                                      => 'Clôturé',
        };
    }

    private function party(\App\Models\User $user, string $role, bool $withCount = false): array
    {
        $profile = $user->profile;
        $name    = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''))
            ?: ($user->phone ?? '—');
        $parts     = explode(' ', $name, 2);
        $shortName = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1)) ?: '??';

        $tripCount = 0;
        if ($withCount) {
            $tripCount = $role === 'Conducteur'
                ? $user->trips()->count()
                : $user->bookings()->count();
        }

        return [
            'avatar'    => $this->fileUrl($profile?->selfie_front),
            'name'      => $name,
            'shortName' => $shortName,
            'role'      => $role,
            'rating'    => round((float) ($user->reviewsReceived?->avg('rating') ?? 0), 1),
            'tripCount' => $tripCount,
        ];
    }

    private function baseQuery()
    {
        return Dispute::query()->with([
            'booking.trip.user.profile',
            'booking.trip.user.reviewsReceived',
            'booking.passenger.profile',
            'booking.passenger.reviewsReceived',
            'booking.payment',
            'assignedAdmin.profile',
        ]);
    }

    private function format(Dispute $d): array
    {
        $booking   = $d->booking;
        $trip      = $booking?->trip;
        $driver    = $trip?->user;
        $passenger = $booking?->passenger;
        $payment   = $booking?->payment;

        return [
            'id'            => $d->id,
            'disputeId'     => 'DIS-' . str_pad((string) $d->id, 8, '0', STR_PAD_LEFT),
            'createdAt'     => $d->created_at?->format('d/m/Y H:i') ?? '—',
            'createdAgo'    => $d->created_at?->diffForHumans() ?? '—',

            'type'     => $this->typeLabel($d->reason_type),
            'priority' => $this->priorityLabel($d->reason_type),
            'status'   => $this->statusLabel($d->status),
            'motif'    => $d->description,

            'reservationId' => $booking ? 'RES-' . strtoupper(substr($booking->uuid, 0, 8)) : '—',
            'trajet'        => ($trip?->departure_city ?? '—') . ' → ' . ($trip?->arrival_city ?? '—'),
            'amount'        => $payment
                ? number_format($payment->gross_amount, 0, ',', ' ') . ' FCFA'
                : '—',

            'conductor' => $driver    ? $this->party($driver,    'Conducteur') : null,
            'passenger' => $passenger ? $this->party($passenger, 'Passager')   : null,
        ];
    }

    private function buildTimeline(Dispute $d): array
    {
        $events = [];

        $events[] = [
            'label' => 'Litige ouvert',
            'time'  => $d->created_at?->format('d/m/Y H:i'),
            'done'  => true,
            'quote' => mb_strlen($d->description) > 120
                ? mb_substr($d->description, 0, 120) . '...'
                : $d->description,
        ];

        if ($d->proof_path) {
            $events[] = [
                'label'           => 'Preuve transmise',
                'time'            => $d->created_at?->format('d/m/Y H:i'),
                'done'            => true,
                'hasAttachments'  => true,
                'attachmentCount' => 1,
            ];
        }

        if ($d->assigned_admin_id) {
            $adminName = trim(
                ($d->assignedAdmin?->profile?->first_name ?? '') . ' ' .
                ($d->assignedAdmin?->profile?->last_name  ?? '')
            ) ?: 'Admin';
            $events[] = [
                'label' => 'Pris en charge par ' . $adminName,
                'time'  => $d->updated_at?->format('d/m/Y H:i'),
                'done'  => true,
            ];
        }

        if ($d->status === 'investigating') {
            $events[] = [
                'label' => 'Investigation en cours',
                'time'  => $d->updated_at?->format('d/m/Y H:i'),
                'done'  => true,
            ];
        }

        if ($d->status === 'resolved_refunded') {
            $events[] = [
                'label' => 'Décision : Remboursement du passager',
                'time'  => $d->resolved_at?->format('d/m/Y H:i'),
                'done'  => true,
                'note'  => $d->admin_decision_notes,
            ];
        }

        if ($d->status === 'resolved_paid_to_driver') {
            $events[] = [
                'label' => 'Décision : Fonds libérés au conducteur',
                'time'  => $d->resolved_at?->format('d/m/Y H:i'),
                'done'  => true,
                'note'  => $d->admin_decision_notes,
            ];
        }

        return $events;
    }

    // =========================================================================
    //  METRICS  GET /api/admin/disputes/metrics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/disputes/metrics',
        operationId: 'adminDisputeMetrics',
        summary: '[ADMIN] Métriques des litiges',
        description: 'Retourne les compteurs utilisés pour les onglets (Tous / Ouverts / Critiques) et les KPIs globaux de la page Gestion des Litiges.',
        tags: ['⚖️ Admin — Litiges'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Métriques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Métriques litiges récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'all',          type: 'integer', example: 145, description: 'Total de litiges'),
                                new OA\Property(property: 'open',         type: 'integer', example: 23,  description: 'Litiges ouverts (pending + investigating)'),
                                new OA\Property(property: 'critical',     type: 'integer', example: 8,   description: 'Litiges critiques (type escroquerie)'),
                                new OA\Property(property: 'pending',      type: 'integer', example: 15,  description: 'En attente de prise en charge'),
                                new OA\Property(property: 'investigating', type: 'integer', example: 8,  description: 'En cours d\'investigation'),
                                new OA\Property(property: 'resolved',     type: 'integer', example: 122, description: 'Résolus (remboursé ou payé)'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function metrics(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $all          = Dispute::count();
        $pending      = Dispute::where('status', 'pending')->count();
        $investigating = Dispute::where('status', 'investigating')->count();
        $resolved     = Dispute::whereIn('status', ['resolved_refunded', 'resolved_paid_to_driver'])->count();
        $critical     = Dispute::where('reason_type', 'scam')->count();

        return $this->apiResponse(true, 'Métriques litiges récupérées.', [
            'all'          => $all,
            'open'         => $pending + $investigating,
            'critical'     => $critical,
            'pending'      => $pending,
            'investigating' => $investigating,
            'resolved'     => $resolved,
        ]);
    }

    // =========================================================================
    //  INDEX  GET /api/admin/disputes
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/disputes',
        operationId: 'adminDisputeIndex',
        summary: '[ADMIN] Liste paginée des litiges',
        description: 'Retourne tous les litiges avec filtres combinables : onglet (tous/ouverts/critiques), type, statut, priorité et recherche textuelle.',
        tags: ['⚖️ Admin — Litiges'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 10, maximum: 100)),
            new OA\Parameter(
                name: 'tab', in: 'query',
                description: 'Onglet actif : all (tous), open (ouverts), critical (critiques)',
                schema: new OA\Schema(type: 'string', enum: ['all', 'open', 'critical'], default: 'all')
            ),
            new OA\Parameter(
                name: 'search', in: 'query',
                description: 'Recherche par description, nom ou téléphone des parties',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'type', in: 'query',
                description: 'Filtre par type de litige',
                schema: new OA\Schema(type: 'string', enum: ['Paiement', 'Annulation', 'Comportement', 'Retard', 'Autre'])
            ),
            new OA\Parameter(
                name: 'status', in: 'query',
                description: 'Filtre par statut affiché',
                schema: new OA\Schema(type: 'string', enum: ['Ouvert', 'En cours', 'Résolu', 'Clôturé'])
            ),
            new OA\Parameter(
                name: 'priority', in: 'query',
                description: 'Filtre par priorité',
                schema: new OA\Schema(type: 'string', enum: ['Critique', 'Élevée', 'Moyenne', 'Faible'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des litiges',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Litiges récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',        type: 'integer', example: 145),
                                new OA\Property(property: 'per_page',     type: 'integer', example: 10),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page',    type: 'integer', example: 15),
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id',          type: 'integer', example: 42),
                                            new OA\Property(property: 'disputeId',   type: 'string',  example: 'DIS-00000042'),
                                            new OA\Property(property: 'createdAt',   type: 'string',  example: '14/06/2025 09:30'),
                                            new OA\Property(property: 'createdAgo',  type: 'string',  example: 'il y a 3 heures'),
                                            new OA\Property(property: 'type',        type: 'string',  enum: ['Paiement', 'Annulation', 'Comportement', 'Retard', 'Autre']),
                                            new OA\Property(property: 'priority',    type: 'string',  enum: ['Critique', 'Élevée', 'Moyenne', 'Faible']),
                                            new OA\Property(property: 'status',      type: 'string',  enum: ['Ouvert', 'En cours', 'Résolu', 'Clôturé']),
                                            new OA\Property(property: 'motif',       type: 'string',  example: 'Le conducteur n\'est pas venu.'),
                                            new OA\Property(property: 'reservationId', type: 'string', example: 'RES-A3B4C5D6'),
                                            new OA\Property(property: 'trajet',      type: 'string',  example: 'Cotonou → Porto-Novo'),
                                            new OA\Property(property: 'amount',      type: 'string',  example: '3 000 FCFA'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $perPage  = min((int) $request->input('per_page', 10), 100);
        $tab      = $request->input('tab', 'all');
        $search   = trim($request->input('search', ''));
        $type     = $request->input('type', '');
        $status   = $request->input('status', '');
        $priority = $request->input('priority', '');

        $query = $this->baseQuery();

        // Onglet
        match ($tab) {
            'open'     => $query->whereIn('status', ['pending', 'investigating']),
            'critical' => $query->where('reason_type', 'scam'),
            default    => null,
        };

        // Filtre statut
        if ($status !== '') {
            $dbStatuses = match ($status) {
                'Ouvert'   => ['pending'],
                'En cours' => ['investigating'],
                'Résolu'   => ['resolved_refunded', 'resolved_paid_to_driver'],
                default    => [],
            };
            if ($dbStatuses) {
                $query->whereIn('status', $dbStatuses);
            }
        }

        // Filtre type → reason_type
        if ($type !== '') {
            $reasonTypes = match ($type) {
                'Paiement'     => ['scam'],
                'Comportement' => ['bad_behavior'],
                'Annulation'   => ['driver_absent', 'passenger_absent'],
                default        => [],
            };
            if ($reasonTypes) {
                $query->whereIn('reason_type', $reasonTypes);
            }
        }

        // Filtre priorité → reason_type
        if ($priority !== '') {
            $reasonType = match ($priority) {
                'Critique' => 'scam',
                'Élevée'   => 'bad_behavior',
                'Moyenne'  => 'driver_absent',
                'Faible'   => 'passenger_absent',
                default    => null,
            };
            if ($reasonType) {
                $query->where('reason_type', $reasonType);
            }
        }

        // Recherche textuelle
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('booking.trip.user.profile', fn ($q) =>
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name',  'like', "%{$search}%")
                  )
                  ->orWhereHas('booking.passenger.profile', fn ($q) =>
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name',  'like', "%{$search}%")
                  )
                  ->orWhereHas('booking.trip.user', fn ($q) =>
                      $q->where('phone', 'like', "%{$search}%")
                  )
                  ->orWhereHas('booking.passenger', fn ($q) =>
                      $q->where('phone', 'like', "%{$search}%")
                  );
            });
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->apiResponse(true, 'Litiges récupérés.', [
            'data'         => $paginated->map(fn ($d) => $this->format($d))->values(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  SHOW  GET /api/admin/disputes/{id}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/disputes/{id}',
        operationId: 'adminDisputeShow',
        summary: '[ADMIN] Détail d\'un litige',
        description: 'Retourne toutes les informations d\'un litige : parties (conducteur + passager avec notes et histos), trajet, montant bloqué et chronologie de l\'enquête.',
        tags: ['⚖️ Admin — Litiges'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID du litige'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Litige trouvé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Litige récupéré.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id',            type: 'integer', example: 42),
                                new OA\Property(property: 'disputeId',     type: 'string',  example: 'DIS-00000042'),
                                new OA\Property(property: 'createdAt',     type: 'string',  example: '14/06/2025 09:30'),
                                new OA\Property(property: 'createdAgo',    type: 'string',  example: 'il y a 3 heures'),
                                new OA\Property(property: 'type',          type: 'string',  enum: ['Paiement', 'Annulation', 'Comportement', 'Retard', 'Autre']),
                                new OA\Property(property: 'priority',      type: 'string',  enum: ['Critique', 'Élevée', 'Moyenne', 'Faible']),
                                new OA\Property(property: 'status',        type: 'string',  enum: ['Ouvert', 'En cours', 'Résolu', 'Clôturé']),
                                new OA\Property(property: 'motif',         type: 'string',  example: 'Le conducteur n\'est jamais arrivé.'),
                                new OA\Property(property: 'reservationId', type: 'string',  example: 'RES-A3B4C5D6'),
                                new OA\Property(property: 'trajet',        type: 'string',  example: 'Cotonou → Porto-Novo'),
                                new OA\Property(property: 'amount',        type: 'string',  example: '3 000 FCFA'),
                                new OA\Property(
                                    property: 'conductor',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'avatar',    type: 'string',  nullable: true),
                                        new OA\Property(property: 'name',      type: 'string',  example: 'Kofi Mensah'),
                                        new OA\Property(property: 'shortName', type: 'string',  example: 'KM'),
                                        new OA\Property(property: 'role',      type: 'string',  example: 'Conducteur'),
                                        new OA\Property(property: 'rating',    type: 'number',  example: 4.7),
                                        new OA\Property(property: 'tripCount', type: 'integer', example: 45),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'passenger',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'avatar',    type: 'string',  nullable: true),
                                        new OA\Property(property: 'name',      type: 'string',  example: 'Aminata Sow'),
                                        new OA\Property(property: 'shortName', type: 'string',  example: 'AS'),
                                        new OA\Property(property: 'role',      type: 'string',  example: 'Passager'),
                                        new OA\Property(property: 'rating',    type: 'number',  example: 4.2),
                                        new OA\Property(property: 'tripCount', type: 'integer', example: 12),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'investigationEvents',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'label',           type: 'string',  example: 'Litige ouvert'),
                                            new OA\Property(property: 'time',            type: 'string',  example: '14/06/2025 09:30'),
                                            new OA\Property(property: 'done',            type: 'boolean', example: true),
                                            new OA\Property(property: 'quote',           type: 'string',  nullable: true),
                                            new OA\Property(property: 'note',            type: 'string',  nullable: true),
                                            new OA\Property(property: 'hasAttachments',  type: 'boolean', nullable: true),
                                            new OA\Property(property: 'attachmentCount', type: 'integer', nullable: true),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Litige introuvable'),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $dispute = $this->baseQuery()->find($id);

        if (! $dispute) {
            return $this->apiResponse(false, 'Litige introuvable.', [], 404);
        }

        $data              = $this->format($dispute);
        $driver            = $dispute->booking?->trip?->user;
        $passenger         = $dispute->booking?->passenger;
        $data['conductor'] = $driver    ? $this->party($driver,    'Conducteur', true) : null;
        $data['passenger'] = $passenger ? $this->party($passenger, 'Passager',   true) : null;
        $data['investigationEvents'] = $this->buildTimeline($dispute);

        return $this->apiResponse(true, 'Litige récupéré.', $data);
    }

    // =========================================================================
    //  ASSIGN  POST /api/admin/disputes/{id}/assign
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/disputes/{id}/assign',
        operationId: 'adminDisputeAssign',
        summary: '[ADMIN] Prendre en charge un litige',
        description: 'L\'administrateur s\'assigne le litige et le passe en statut `investigating`. Notifie le plaignant.',
        tags: ['⚖️ Admin — Litiges'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID du litige'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Litige assigné — statut passé à En cours'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Litige introuvable'),
            new OA\Response(response: 422, description: 'Litige déjà pris en charge ou résolu'),
        ]
    )]
    public function assign(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $dispute = Dispute::with(['reporter'])->find($id);

        if (! $dispute) {
            return $this->apiResponse(false, 'Litige introuvable.', [], 404);
        }

        if (! $dispute->isPending()) {
            return $this->apiResponse(false, "Ce litige est déjà en statut « {$this->statusLabel($dispute->status)} ».", [], 422);
        }

        $dispute->update([
            'status'            => 'investigating',
            'assigned_admin_id' => $request->user()->id,
        ]);

        $dispute->reporter->notify(new DisputeStatusChanged($dispute->fresh()));

        return $this->apiResponse(true, 'Litige pris en charge.', $this->format(
            $this->baseQuery()->find($id)
        ));
    }

    // =========================================================================
    //  REFUND  POST /api/admin/disputes/{id}/refund
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/disputes/{id}/refund',
        operationId: 'adminDisputeRefund',
        summary: '[ADMIN] Rembourser le passager',
        description: 'Clôture le litige en faveur du passager : rembourse le paiement escrow, annule la libération automatique. Seul un litige non encore résolu peut être remboursé.',
        tags: ['⚖️ Admin — Litiges'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID du litige'),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Le conducteur n\'a pas effectué le trajet selon les preuves fournies.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Remboursement effectué — litige clôturé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Litige résolu — passager remboursé.'),
                        new OA\Property(property: 'body', type: 'object', description: 'Objet litige mis à jour (status = Résolu)'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Litige introuvable'),
            new OA\Response(response: 422, description: 'Litige déjà résolu'),
        ]
    )]
    public function refund(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $dispute = Dispute::with(['booking.payment', 'reporter'])->find($id);

        if (! $dispute) {
            return $this->apiResponse(false, 'Litige introuvable.', [], 404);
        }

        if ($dispute->isResolved()) {
            return $this->apiResponse(false, 'Ce litige est déjà résolu.', [], 422);
        }

        DB::transaction(function () use ($dispute, $request) {
            $payment    = $dispute->booking?->payment;
            $validation = TripValidation::where('booking_id', $dispute->booking_id)->first();

            $payment?->update(['status' => 'refunded']);
            $validation?->update(['status' => 'disputed']);
            $dispute->booking?->update(['payment_status' => 'refunded']);

            $dispute->update([
                'status'               => 'resolved_refunded',
                'admin_decision_notes' => $request->input('notes'),
                'assigned_admin_id'    => $request->user()->id,
                'resolved_at'          => now(),
            ]);
        });

        $dispute->reporter->notify(new DisputeStatusChanged($dispute->fresh()));

        return $this->apiResponse(true, 'Litige résolu — passager remboursé.', $this->format(
            $this->baseQuery()->find($id)
        ));
    }

    // =========================================================================
    //  PAY DRIVER  POST /api/admin/disputes/{id}/pay-driver
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/disputes/{id}/pay-driver',
        operationId: 'adminDisputePayDriver',
        summary: '[ADMIN] Payer le conducteur',
        description: 'Clôture le litige en faveur du conducteur : libère les fonds escrow vers le conducteur, marque le trajet comme validé. Seul un litige non encore résolu peut être traité.',
        tags: ['⚖️ Admin — Litiges'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID du litige'),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Le trajet a bien eu lieu, témoignages concordants.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Paiement effectué — litige clôturé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Litige résolu — fonds libérés au conducteur.'),
                        new OA\Property(property: 'body', type: 'object', description: 'Objet litige mis à jour (status = Résolu)'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Litige introuvable'),
            new OA\Response(response: 422, description: 'Litige déjà résolu'),
        ]
    )]
    public function payDriver(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $dispute = Dispute::with(['booking.payment', 'reporter'])->find($id);

        if (! $dispute) {
            return $this->apiResponse(false, 'Litige introuvable.', [], 404);
        }

        if ($dispute->isResolved()) {
            return $this->apiResponse(false, 'Ce litige est déjà résolu.', [], 422);
        }

        DB::transaction(function () use ($dispute, $request) {
            $payment    = $dispute->booking?->payment;
            $validation = TripValidation::where('booking_id', $dispute->booking_id)->first();

            $payment?->update(['status' => 'success']);
            $validation?->update(['status' => 'released', 'released_at' => now()]);
            $dispute->booking?->update(['payment_status' => 'released_to_driver']);

            $dispute->update([
                'status'               => 'resolved_paid_to_driver',
                'admin_decision_notes' => $request->input('notes'),
                'assigned_admin_id'    => $request->user()->id,
                'resolved_at'          => now(),
            ]);
        });

        $dispute->reporter->notify(new DisputeStatusChanged($dispute->fresh()));

        return $this->apiResponse(true, 'Litige résolu — fonds libérés au conducteur.', $this->format(
            $this->baseQuery()->find($id)
        ));
    }
}
