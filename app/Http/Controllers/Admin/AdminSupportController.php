<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '🎧 Admin — Support', description: 'Gestion des tickets support utilisateurs (Back-Office)')]
class AdminSupportController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'high'   => 'Haute',
            'low'    => 'Basse',
            default  => 'Moyenne',
        };
    }

    private function channelLabel(string $channel): string
    {
        return match ($channel) {
            'app'   => 'App Mobile',
            'phone' => 'Téléphone',
            'email' => 'Email',
            'chat'  => 'Chat',
            default => 'Autre',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'new'         => 'Nouveau',
            'in_progress' => 'En cours',
            'resolved'    => 'Résolu',
            'closed'      => 'Clôturé',
            default       => 'Nouveau',
        };
    }

    private function timeElapsed(\Carbon\Carbon $createdAt): string
    {
        $minutes = (int) $createdAt->diffInMinutes(now());
        if ($minutes < 60)   return $minutes . 'min';
        $hours = (int) $createdAt->diffInHours(now());
        if ($hours < 24)     return $hours . 'h';
        $days = (int) $createdAt->diffInDays(now());
        if ($days < 30)      return $days . 'j';
        return $createdAt->diffInMonths(now()) . 'mois';
    }

    private function baseQuery()
    {
        return SupportTicket::query()->with([
            'user.profile',
            'agent.profile',
        ]);
    }

    private function format(SupportTicket $t): array
    {
        $user         = $t->user;
        $userProfile  = $user?->profile;
        $agent        = $t->agent;
        $agentProfile = $agent?->profile;

        $userName = trim(($userProfile?->first_name ?? '') . ' ' . ($userProfile?->last_name ?? ''))
            ?: ($user?->phone ?? '—');
        $agentName = $agent
            ? (trim(($agentProfile?->first_name ?? '') . ' ' . ($agentProfile?->last_name ?? '')) ?: ($agent->phone ?? '—'))
            : null;

        return [
            'id'          => $t->uuid,
            'ticketId'    => 'TKT-' . strtoupper(substr($t->uuid, 0, 8)),
            'createdAt'   => $t->created_at?->format('d/m/Y H:i') ?? '—',
            'date'        => $t->created_at?->format('d/m/Y') ?? '—',
            'time'        => $t->created_at?->format('H:i') ?? '—',
            'timeElapsed' => $t->created_at ? $this->timeElapsed($t->created_at) : '—',

            'userName'    => $userName,
            'userAvatar'  => $this->fileUrl($userProfile?->selfie_front),
            'userEmail'   => $user?->email ?? '—',

            'subject'     => $t->subject,
            'description' => mb_strlen($t->description) > 100
                ? mb_substr($t->description, 0, 100) . '...'
                : $t->description,

            'priority'    => $this->priorityLabel($t->priority),
            'channel'     => $this->channelLabel($t->channel),
            'status'      => $this->statusLabel($t->status),

            'agentName'   => $agentName,
            'agentAvatar' => $agentName ? $this->fileUrl($agentProfile?->selfie_front) : null,
        ];
    }

    // =========================================================================
    //  METRICS  GET /api/admin/support/metrics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/support/metrics',
        operationId: 'adminSupportMetrics',
        summary: '[ADMIN] Métriques des tickets support',
        description: 'Retourne les compteurs utilisés pour les KPIs de la page Support : total, nouveaux, en cours, résolus et clôturés.',
        tags: ['🎧 Admin — Support'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Métriques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Métriques support récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',       type: 'integer', example: 230, description: 'Total de tickets'),
                                new OA\Property(property: 'open',        type: 'integer', example: 45,  description: 'Tickets ouverts (new + in_progress)'),
                                new OA\Property(property: 'new',         type: 'integer', example: 18,  description: 'Nouveaux tickets (non assignés)'),
                                new OA\Property(property: 'in_progress', type: 'integer', example: 27,  description: 'Tickets en cours de traitement'),
                                new OA\Property(property: 'resolved',    type: 'integer', example: 170, description: 'Tickets résolus'),
                                new OA\Property(property: 'closed',      type: 'integer', example: 15,  description: 'Tickets clôturés'),
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

        $total      = SupportTicket::count();
        $new        = SupportTicket::where('status', 'new')->count();
        $inProgress = SupportTicket::where('status', 'in_progress')->count();
        $resolved   = SupportTicket::where('status', 'resolved')->count();
        $closed     = SupportTicket::where('status', 'closed')->count();

        return $this->apiResponse(true, 'Métriques support récupérées.', [
            'total'       => $total,
            'open'        => $new + $inProgress,
            'new'         => $new,
            'in_progress' => $inProgress,
            'resolved'    => $resolved,
            'closed'      => $closed,
        ]);
    }

    // =========================================================================
    //  INDEX  GET /api/admin/support
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/support',
        operationId: 'adminSupportIndex',
        summary: '[ADMIN] Liste paginée des tickets support',
        description: 'Retourne tous les tickets support avec filtres combinables : statut, priorité, agent assigné, date et recherche textuelle.',
        tags: ['🎧 Admin — Support'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 10, maximum: 100)),
            new OA\Parameter(
                name: 'search', in: 'query',
                description: 'Recherche par sujet, description, nom ou email utilisateur',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'status', in: 'query',
                description: 'Filtre par statut',
                schema: new OA\Schema(type: 'string', enum: ['Nouveau', 'En cours', 'Résolu', 'Clôturé'])
            ),
            new OA\Parameter(
                name: 'priority', in: 'query',
                description: 'Filtre par priorité',
                schema: new OA\Schema(type: 'string', enum: ['Haute', 'Moyenne', 'Basse'])
            ),
            new OA\Parameter(
                name: 'agent', in: 'query',
                description: 'UUID de l\'agent assigné (ou "unassigned" pour les non assignés)',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'date', in: 'query',
                description: 'Filtrer par date de création (YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2025-06-15')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des tickets',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Tickets récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',        type: 'integer', example: 230),
                                new OA\Property(property: 'per_page',     type: 'integer', example: 10),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page',    type: 'integer', example: 23),
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id',          type: 'string',  example: 'uuid-xxx',     description: 'UUID du ticket'),
                                            new OA\Property(property: 'ticketId',    type: 'string',  example: 'TKT-A3B4C5D6', description: 'Identifiant lisible'),
                                            new OA\Property(property: 'date',        type: 'string',  example: '14/06/2025'),
                                            new OA\Property(property: 'time',        type: 'string',  example: '09:30'),
                                            new OA\Property(property: 'timeElapsed', type: 'string',  example: '2h',           description: 'Durée depuis ouverture'),
                                            new OA\Property(property: 'userName',    type: 'string',  example: 'Aminata Sow'),
                                            new OA\Property(property: 'userAvatar',  type: 'string',  nullable: true),
                                            new OA\Property(property: 'userEmail',   type: 'string',  example: 'aminata@example.com'),
                                            new OA\Property(property: 'subject',     type: 'string',  example: 'Paiement non reçu'),
                                            new OA\Property(property: 'description', type: 'string',  example: 'Mon paiement a été débité mais...'),
                                            new OA\Property(property: 'priority',    type: 'string',  enum: ['Haute', 'Moyenne', 'Basse']),
                                            new OA\Property(property: 'channel',     type: 'string',  enum: ['App Mobile', 'Téléphone', 'Email', 'Chat', 'Autre']),
                                            new OA\Property(property: 'status',      type: 'string',  enum: ['Nouveau', 'En cours', 'Résolu', 'Clôturé']),
                                            new OA\Property(property: 'agentName',   type: 'string',  nullable: true, example: 'Admin Kofi'),
                                            new OA\Property(property: 'agentAvatar', type: 'string',  nullable: true),
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
        $search   = trim($request->input('search', ''));
        $status   = $request->input('status', '');
        $priority = $request->input('priority', '');
        $agent    = $request->input('agent', '');
        $date     = $request->input('date', '');

        $query = $this->baseQuery();

        if ($status !== '') {
            $dbStatus = match ($status) {
                'Nouveau'   => 'new',
                'En cours'  => 'in_progress',
                'Résolu'    => 'resolved',
                'Clôturé'   => 'closed',
                default     => null,
            };
            if ($dbStatus) $query->where('status', $dbStatus);
        }

        if ($priority !== '') {
            $dbPriority = match ($priority) {
                'Haute'   => 'high',
                'Basse'   => 'low',
                default   => 'medium',
            };
            $query->where('priority', $dbPriority);
        }

        if ($agent === 'unassigned') {
            $query->whereNull('agent_id');
        } elseif ($agent !== '') {
            $query->whereHas('agent', fn ($q) => $q->where('uuid', $agent));
        }

        if ($date !== '') {
            $query->whereDate('created_at', $date);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('subject',     'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($q) =>
                      $q->where('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('profile', fn ($q) =>
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name',  'like', "%{$search}%")
                        )
                  );
            });
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->apiResponse(true, 'Tickets récupérés.', [
            'data'         => $paginated->map(fn ($t) => $this->format($t))->values(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  AGENTS  GET /api/admin/support/agents
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/support/agents',
        operationId: 'adminSupportAgents',
        summary: '[ADMIN] Liste des agents support',
        description: 'Retourne la liste des administrateurs pouvant être assignés à un ticket. Utilisé pour peupler le filtre agent dans le front-end.',
        tags: ['🎧 Admin — Support'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des agents',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Agents récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'value', type: 'string', example: 'uuid-xxx', description: 'UUID de l\'agent (à passer dans le filtre agent=)'),
                                    new OA\Property(property: 'label', type: 'string', example: 'Admin Kofi'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function agents(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $agents = User::where('role_id', function ($q) {
            $q->select('id')->from('roles')->where('name', 'admin');
        })
        ->with('profile')
        ->get()
        ->map(function (User $u) {
            $profile = $u->profile;
            $label   = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''))
                ?: ($u->phone ?? $u->email ?? '—');
            return ['value' => $u->uuid, 'label' => $label];
        });

        return $this->apiResponse(true, 'Agents récupérés.', $agents);
    }

    // =========================================================================
    //  RESOLVE  POST /api/admin/support/{uuid}/resolve
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/support/{uuid}/resolve',
        operationId: 'adminSupportResolve',
        summary: '[ADMIN] Résoudre un ticket support',
        description: 'Marque le ticket comme résolu et enregistre l\'agent qui le traite. Impossible si le ticket est déjà résolu ou clôturé.',
        tags: ['🎧 Admin — Support'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID du ticket'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ticket résolu',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Ticket résolu.'),
                        new OA\Property(property: 'body', type: 'object', description: 'Ticket mis à jour (status = Résolu)'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Ticket introuvable'),
            new OA\Response(response: 422, description: 'Ticket déjà résolu ou clôturé'),
        ]
    )]
    public function resolve(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $ticket = $this->baseQuery()->where('uuid', $uuid)->first();

        if (! $ticket) {
            return $this->apiResponse(false, 'Ticket introuvable.', [], 404);
        }

        if ($ticket->isResolved()) {
            return $this->apiResponse(false, 'Ce ticket est déjà ' . $this->statusLabel($ticket->status) . '.', [], 422);
        }

        $ticket->update([
            'status'      => 'resolved',
            'agent_id'    => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return $this->apiResponse(true, 'Ticket résolu.', $this->format(
            $this->baseQuery()->where('uuid', $uuid)->first()
        ));
    }

    // =========================================================================
    //  STORE  POST /api/admin/support (création via back-office)
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/support',
        operationId: 'adminSupportStore',
        summary: '[ADMIN] Créer un ticket support',
        description: 'Crée un ticket support depuis le back-office (bouton "Nouveau Ticket"). L\'agent peut ouvrir un ticket au nom d\'un utilisateur.',
        tags: ['🎧 Admin — Support'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_uuid', 'subject', 'description', 'priority', 'channel'],
                properties: [
                    new OA\Property(property: 'user_uuid',    type: 'string',  format: 'uuid', description: 'UUID de l\'utilisateur concerné'),
                    new OA\Property(property: 'subject',      type: 'string',  example: 'Paiement non reçu'),
                    new OA\Property(property: 'description',  type: 'string',  example: 'Le client signale ne pas avoir reçu le remboursement sous 48h.'),
                    new OA\Property(property: 'priority',     type: 'string',  enum: ['Haute', 'Moyenne', 'Basse'], example: 'Haute'),
                    new OA\Property(property: 'channel',      type: 'string',  enum: ['App Mobile', 'Téléphone', 'Email', 'Chat', 'Autre'], example: 'Téléphone'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ticket créé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Ticket créé.'),
                        new OA\Property(property: 'body', type: 'object', description: 'Ticket nouvellement créé'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $data = $request->validate([
            'user_uuid'   => ['required', 'string', 'exists:users,uuid'],
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'priority'    => ['required', 'in:Haute,Moyenne,Basse'],
            'channel'     => ['required', 'in:App Mobile,Téléphone,Email,Chat,Autre'],
        ]);

        $user = User::where('uuid', $data['user_uuid'])->first();

        $dbPriority = match ($data['priority']) {
            'Haute'  => 'high',
            'Basse'  => 'low',
            default  => 'medium',
        };

        $dbChannel = match ($data['channel']) {
            'App Mobile' => 'app',
            'Téléphone'  => 'phone',
            'Email'      => 'email',
            'Chat'       => 'chat',
            default      => 'other',
        };

        $ticket = SupportTicket::create([
            'user_id'     => $user->id,
            'subject'     => $data['subject'],
            'description' => $data['description'],
            'priority'    => $dbPriority,
            'channel'     => $dbChannel,
            'status'      => 'new',
            'agent_id'    => $request->user()->id,
        ]);

        return $this->apiResponse(true, 'Ticket créé.', $this->format(
            $this->baseQuery()->find($ticket->id)
        ), 201);
    }
}
