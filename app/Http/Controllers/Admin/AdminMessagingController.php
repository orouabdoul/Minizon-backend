<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Messagerie directe Admin ↔ Conducteur (back-office).
 *
 * Distinct de AdminConversationController (modération driver-passenger).
 * Les conversations admin-driver n'ont pas de trip_id ni booking_id.
 */
class AdminMessagingController extends Controller
{
    // =========================================================================
    //  GET /api/admin/messaging/conversations
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/messaging/conversations',
        operationId: 'adminMessagingList',
        summary: 'Liste des conversations admin ↔ conducteur',
        description: 'Retourne toutes les conversations directes entre l\'admin et des conducteurs, triées par non-lus puis par date. Filtre optionnel par statut conducteur.',
        tags: ['👑 Admin — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                description: 'Filtrer par statut conducteur',
                schema: new OA\Schema(type: 'string', enum: ['tous', 'en_ligne', 'en_trajet', 'hors_ligne'], default: 'tous')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des conversations',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'conversations',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/AdminConversation')
                                ),
                                new OA\Property(property: 'totalUnread', type: 'integer', example: 4),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function conversations(Request $request): JsonResponse
    {
        $adminId    = auth()->id();
        $statusFilter = $request->input('status', 'tous');

        // Conversations admin-driver : sans trip ni booking
        $conversations = Conversation::with([
            'participants.profile',
            'participants.role',
            'lastMessage',
        ])
        ->whereNull('trip_id')
        ->whereNull('booking_id')
        ->whereHas('participants', fn ($q) => $q->where('users.id', $adminId))
        ->whereHas('participants', fn ($q) => $q->whereHas('role', fn ($r) => $r->where('name', 'driver')))
        ->orderByDesc('updated_at')
        ->get();

        // On résout les statuts driver en une seule requête groupée
        $driverIds   = $conversations->flatMap(fn ($c) => $c->participants->pluck('id'))->unique()->diff([$adminId]);
        $activeTrips = Trip::whereIn('user_id', $driverIds)
            ->where('status', 'active')
            ->with('activeIncident')
            ->get()
            ->keyBy('user_id');

        $drivers = User::whereIn('id', $driverIds)->get()->keyBy('id');

        $result = $conversations
            ->map(fn (Conversation $conv) => $this->formatConversation(
                $conv, $adminId, $activeTrips, $drivers
            ))
            ->when($statusFilter !== 'tous', fn ($col) => $col->filter(
                fn ($c) => $c['driverStatus'] === $statusFilter
            ))
            ->values();

        $totalUnread = $result->sum('unreadCount');

        return $this->apiResponse(true, 'Conversations.', [
            'conversations' => $result,
            'totalUnread'   => $totalUnread,
        ]);
    }

    // =========================================================================
    //  GET /api/admin/messaging/conversations/{uuid}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/messaging/conversations/{uuid}',
        operationId: 'adminMessagingShow',
        summary: 'Ouvrir une conversation — messages + marquer lu',
        tags: ['👑 Admin — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conversation et ses messages'),
            new OA\Response(response: 404, description: 'Conversation introuvable'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $adminId = auth()->id();

        $conversation = Conversation::with([
            'participants.profile',
            'participants.role',
            'messages',
            'lastMessage',
        ])->where('uuid', $uuid)->firstOrFail();

        // Marquer les messages du conducteur comme lus
        $conversation->messages()
            ->where('sender_id', '!=', $adminId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $conversation->messages->map(fn (Message $m) => [
            'id'      => $m->uuid,
            'content' => $m->body,
            'sender'  => $m->sender_id === $adminId ? 'admin' : 'driver',
            'sentAt'  => $m->created_at->toIso8601String(),
            'status'  => $m->read_at ? 'lu' : 'envoyé',
        ]);

        $driverUser  = $conversation->participants->first(fn ($u) => $u->id !== $adminId);
        $activeTrips = Trip::where('user_id', $driverUser?->id)->where('status', 'active')->with('activeIncident')->get()->keyBy('user_id');
        $drivers     = collect([$driverUser?->id => $driverUser]);

        return $this->apiResponse(true, 'Conversation.', [
            'conversation' => $this->formatConversation($conversation, $adminId, $activeTrips, $drivers),
            'messages'     => $messages,
        ]);
    }

    // =========================================================================
    //  POST /api/admin/messaging/conversations/{uuid}/messages
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/messaging/conversations/{uuid}/messages',
        operationId: 'adminMessagingSend',
        summary: 'Envoyer un message à un conducteur',
        tags: ['👑 Admin — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', maxLength: 2000, example: 'Votre passager vous attend.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Message envoyé'),
            new OA\Response(response: 404, description: 'Conversation introuvable'),
        ]
    )]
    public function sendMessage(Request $request, string $uuid): JsonResponse
    {
        $adminId = auth()->id();

        $conversation = Conversation::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $adminId,
            'body'            => $validated['content'],
        ]);

        $conversation->touch();

        return $this->apiResponse(true, 'Message envoyé.', [
            'message' => [
                'id'      => $message->uuid,
                'content' => $message->body,
                'sender'  => 'admin',
                'sentAt'  => $message->created_at->toIso8601String(),
                'status'  => 'envoyé',
            ],
        ], 201);
    }

    // =========================================================================
    //  POST /api/admin/messaging/broadcast
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/messaging/broadcast',
        operationId: 'adminMessagingBroadcast',
        summary: 'Diffuser un message à plusieurs conducteurs',
        description: 'Envoie le message dans la conversation admin-driver de chaque conducteur correspondant au filtre. Crée la conversation si elle n\'existe pas encore.',
        tags: ['👑 Admin — Messagerie'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content', 'target'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', maxLength: 2000, example: 'Nouveau point de ramassage disponible.'),
                    new OA\Property(property: 'target',  type: 'string', enum: ['tous', 'en_ligne', 'en_trajet'], example: 'en_ligne'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Broadcast effectué',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success',  type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'sent_to', type: 'integer', example: 12),
                                new OA\Property(property: 'target',  type: 'string',  example: 'en_ligne'),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function broadcast(Request $request): JsonResponse
    {
        $adminId = auth()->id();

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'target'  => 'required|in:tous,en_ligne,en_trajet',
        ]);

        $driversQuery = User::whereHas('role', fn ($q) => $q->where('name', 'driver'))
            ->where('is_blocked', false);

        if ($validated['target'] === 'en_trajet') {
            $driversQuery->whereHas('trips', fn ($q) => $q->where('status', 'active'));
        } elseif ($validated['target'] === 'en_ligne') {
            // en_ligne = is_online true (mis à jour par le device du conducteur)
            $driversQuery->where('is_online', true)
                ->whereDoesntHave('trips', fn ($q) => $q->where('status', 'active'));
        }

        $drivers   = $driversQuery->get();
        $sentCount = 0;

        foreach ($drivers as $driver) {
            $conv = $this->findOrCreateConversation($adminId, $driver->id);

            Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $adminId,
                'body'            => $validated['content'],
            ]);

            $conv->touch();
            $sentCount++;
        }

        return $this->apiResponse(true, "Message diffusé à {$sentCount} conducteur(s).", [
            'sent_to' => $sentCount,
            'target'  => $validated['target'],
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function findOrCreateConversation(int $adminId, int $driverId): Conversation
    {
        $existing = Conversation::whereNull('trip_id')
            ->whereNull('booking_id')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $adminId))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $driverId))
            ->first();

        if ($existing) {
            return $existing;
        }

        $conv = Conversation::create([]);
        $conv->participants()->attach([$adminId, $driverId]);

        return $conv;
    }

    private function formatConversation(
        Conversation $conv,
        int $adminId,
        \Illuminate\Support\Collection $activeTrips,
        \Illuminate\Support\Collection $drivers
    ): array {
        $driver  = $conv->participants->first(fn ($u) => $u->id !== $adminId)
                ?? $drivers->get($conv->participants->pluck('id')->diff([$adminId])->first());
        $profile = $driver?->profile;

        $driverName = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
        if (empty($driverName)) {
            $driverName = $driver?->phone ?? 'Conducteur';
        }

        $avatarUrl = $profile?->selfie_front
            ? asset('storage/' . $profile->selfie_front)
            : 'https://ui-avatars.com/api/?name=' . urlencode($driverName) . '&background=00A86B&color=fff';

        // Statut conducteur
        $activeTrip   = $activeTrips->get($driver?->id);
        $driverStatus = match (true) {
            $activeTrip !== null      => 'en_trajet',
            (bool) $driver?->is_online => 'en_ligne',
            default                   => 'hors_ligne',
        };

        // Priorité depuis l'incident actif du trajet
        $priority = '';
        if ($activeTrip?->activeIncident) {
            $incidentType = $activeTrip->activeIncident->type;
            // 'autre' ne remonte pas comme badge priorité frontend
            $priority = in_array($incidentType, ['panne', 'urgence']) ? $incidentType : '';
        }

        $last        = $conv->lastMessage;
        $unreadCount = $conv->messages()
            ->where('sender_id', '!=', $adminId)
            ->whereNull('read_at')
            ->count();

        return [
            'id'            => $conv->uuid,
            'driverName'    => $driverName,
            'driverAvatar'  => $avatarUrl,
            'driverPhone'   => $driver?->phone ?? '',
            'driverStatus'  => $driverStatus,
            'activeTripId'  => $activeTrip ? strtoupper(substr($activeTrip->uuid, 0, 8)) : null,
            'unreadCount'   => $unreadCount,
            'lastMessage'   => $last?->body ?? '',
            'lastMessageAt' => ($last?->created_at ?? $conv->created_at)->toIso8601String(),
            'priority'      => $priority,
        ];
    }
}

// ── OpenAPI schema ─────────────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'AdminConversation',
    properties: [
        new OA\Property(property: 'id',            type: 'string', format: 'uuid'),
        new OA\Property(property: 'driverName',    type: 'string',  example: 'Koffi Mensah'),
        new OA\Property(property: 'driverAvatar',  type: 'string',  format: 'uri'),
        new OA\Property(property: 'driverPhone',   type: 'string',  example: '+22997000000'),
        new OA\Property(property: 'driverStatus',  type: 'string',  enum: ['en_ligne', 'en_trajet', 'hors_ligne']),
        new OA\Property(property: 'activeTripId',  type: 'string',  nullable: true, example: 'CF304AE1'),
        new OA\Property(property: 'unreadCount',   type: 'integer', example: 2),
        new OA\Property(property: 'lastMessage',   type: 'string',  example: 'Bonjour, problème sur l\'autoroute'),
        new OA\Property(property: 'lastMessageAt', type: 'string',  format: 'date-time'),
        new OA\Property(property: 'priority',      type: 'string',  enum: ['', 'panne', 'urgence', 'retard'], nullable: true),
    ]
)]
class _AdminConversationSchema {}
