<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Trip;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Messagerie directe Admin ↔ Conducteur / Passager (back-office).
 *
 * Conversations sans trip_id ni booking_id.
 * Les conducteurs/passagers voient ces messages dans leur boîte de réception mobile.
 */
#[OA\Tag(name: '👑 Admin — Messagerie', description: 'Centre de messagerie directe Admin ↔ Conducteur/Passager (Back-Office)')]
class AdminMessagingController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function avatarUrl(?string $path, string $name, string $color = '2563EB'): string
    {
        if ($path) {
            return Storage::disk('public')->url($path);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode(trim($name) ?: 'U') . '&background=' . $color . '&color=fff&size=64';
    }

    private function findOrCreateConversation(int $adminId, int $userId): Conversation
    {
        $existing = Conversation::whereNull('trip_id')
            ->whereNull('booking_id')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $adminId))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $userId))
            ->first();

        if ($existing) {
            return $existing;
        }

        $conv = Conversation::create(['trip_id' => null, 'booking_id' => null]);
        $conv->participants()->attach([$adminId, $userId]);

        return $conv;
    }

    private function formatConversation(Conversation $conv, int $adminId): array
    {
        $other   = $conv->participants->first(fn ($u) => $u->id !== $adminId);
        $profile = $other?->profile;

        $name = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
        if (empty($name)) {
            $name = $other?->phone ?? 'Utilisateur';
        }

        $role     = $other?->role?->name ?? 'passenger';
        $roleLabel = match ($role) {
            'driver'    => 'Conducteur',
            'passenger' => 'Passager',
            'admin'     => 'Admin',
            default     => ucfirst($role),
        };
        $avatarColor = $role === 'driver' ? '00A86B' : '2563EB';

        // Statut en ligne (conducteur)
        $driverStatus = 'hors_ligne';
        if ($other) {
            $hasActiveTrip = Trip::where('user_id', $other->id)->where('status', 'active')->exists();
            $driverStatus  = $hasActiveTrip ? 'en_trajet' : ($other->is_online ? 'en_ligne' : 'hors_ligne');
        }

        $last        = $conv->lastMessage;
        $unreadCount = $conv->messages()
            ->where('sender_id', '!=', $adminId)
            ->whereNull('read_at')
            ->count();

        return [
            'id'            => $conv->uuid,
            'userId'        => $other?->uuid,
            'name'          => $name,
            'avatar'        => $this->avatarUrl($profile?->selfie_front, $name, $avatarColor),
            'phone'         => $other?->phone ?? '',
            'role'          => $role,
            'roleLabel'     => $roleLabel,
            'driverStatus'  => $driverStatus,
            'unreadCount'   => $unreadCount,
            'lastMessage'   => $last?->body ?? '',
            'lastMessageAt' => ($last?->created_at ?? $conv->created_at)?->toIso8601String(),
        ];
    }

    // =========================================================================
    //  GET /api/admin/messaging/users?q=&role=driver|passenger|all
    //  Rechercher des utilisateurs pour démarrer une conversation
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/messaging/users',
        operationId: 'adminMessagingUsers',
        summary: '[ADMIN] Rechercher des utilisateurs à contacter',
        description: 'Retourne la liste des conducteurs et/ou passagers avec indication si une conversation directe existe déjà. Utilisé pour l\'auto-complétion "Nouveau message".',
        tags: ['👑 Admin — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q',    in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Recherche par nom ou téléphone'),
            new OA\Parameter(name: 'role', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'driver', 'passenger'], default: 'all')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste d\'utilisateurs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'uuid',             type: 'string',  format: 'uuid'),
                                    new OA\Property(property: 'name',             type: 'string',  example: 'Koffi Mensah'),
                                    new OA\Property(property: 'phone',            type: 'string',  example: '+22997000000'),
                                    new OA\Property(property: 'avatar',           type: 'string'),
                                    new OA\Property(property: 'role',             type: 'string',  example: 'driver'),
                                    new OA\Property(property: 'roleLabel',        type: 'string',  example: 'Conducteur'),
                                    new OA\Property(property: 'conversationUuid', type: 'string',  nullable: true, description: 'UUID conversation existante, null si aucune'),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function searchUsers(Request $request): JsonResponse
    {
        $adminId  = auth()->id();
        $q        = trim($request->input('q', ''));
        $roleFilter = $request->input('role', 'all');

        $query = User::with(['profile', 'role'])
            ->where('is_blocked', false)
            ->whereHas('role', fn ($r) => $roleFilter === 'all'
                ? $r->whereIn('name', ['driver', 'passenger'])
                : $r->where('name', $roleFilter)
            );

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('phone', 'like', "%{$q}%")
                   ->orWhereHas('profile', fn ($p) => $p->where(
                       DB::raw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))"),
                       'like',
                       "%{$q}%"
                   ));
            });
        }

        $users = $query->limit(20)->get();

        // Charger les conversations admin existantes pour ces users
        $userIds = $users->pluck('id');
        $existingConvs = Conversation::whereNull('trip_id')
            ->whereNull('booking_id')
            ->whereHas('participants', fn ($qb) => $qb->where('users.id', $adminId))
            ->whereHas('participants', fn ($qb) => $qb->whereIn('users.id', $userIds))
            ->with('participants:id,uuid')
            ->get();

        // Map userId → conversationUuid
        $convMap = [];
        foreach ($existingConvs as $conv) {
            $otherId = $conv->participants->first(fn ($p) => $p->id !== $adminId)?->id;
            if ($otherId) {
                $convMap[$otherId] = $conv->uuid;
            }
        }

        $result = $users->map(function (User $u) use ($convMap): array {
            $profile = $u->profile;
            $name    = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: $u->phone;
            $role    = $u->role?->name ?? 'passenger';

            return [
                'uuid'             => $u->uuid,
                'name'             => $name,
                'phone'            => $u->phone,
                'avatar'           => $this->avatarUrl($profile?->selfie_front, $name, $role === 'driver' ? '00A86B' : '2563EB'),
                'role'             => $role,
                'roleLabel'        => $role === 'driver' ? 'Conducteur' : 'Passager',
                'conversationUuid' => $convMap[$u->id] ?? null,
            ];
        });

        return $this->apiResponse(true, 'Utilisateurs trouvés.', $result);
    }

    // =========================================================================
    //  POST /api/admin/messaging/start
    //  Démarrer ou retrouver une conversation avec un conducteur ou passager
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/messaging/start',
        operationId: 'adminMessagingStart',
        summary: '[ADMIN] Démarrer une conversation avec un utilisateur',
        description: 'Crée ou retrouve la conversation directe admin ↔ utilisateur (driver ou passenger). Retourne la conversation et les messages existants.',
        tags: ['👑 Admin — Messagerie'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_uuid'],
                properties: [
                    new OA\Property(property: 'user_uuid', type: 'string', format: 'uuid', example: 'abc-123'),
                    new OA\Property(property: 'message',   type: 'string', description: 'Message optionnel à envoyer immédiatement', maxLength: 2000),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Conversation créée ou retrouvée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'conversation', type: 'object'),
                                new OA\Property(property: 'messages',     type: 'array', items: new OA\Items()),
                                new OA\Property(property: 'created',      type: 'boolean', description: 'true si nouvelle conversation'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
            new OA\Response(response: 422, description: 'UUID invalide ou utilisateur admin'),
        ]
    )]
    public function startConversation(Request $request): JsonResponse
    {
        $adminId = auth()->id();

        $validated = $request->validate([
            'user_uuid' => 'required|string|uuid',
            'message'   => 'nullable|string|max:2000',
        ]);

        $targetUser = User::with(['profile', 'role'])
            ->where('uuid', $validated['user_uuid'])
            ->first();

        if (! $targetUser) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        if ($targetUser->role?->name === 'admin') {
            return $this->apiResponse(false, 'Impossible de démarrer une conversation avec un autre administrateur.', [], 422);
        }

        $existed = Conversation::whereNull('trip_id')
            ->whereNull('booking_id')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $adminId))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $targetUser->id))
            ->exists();

        $conv = $this->findOrCreateConversation($adminId, $targetUser->id);

        // Envoyer un message initial si fourni
        if (! empty($validated['message'])) {
            $msg = Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $adminId,
                'body'            => $validated['message'],
            ]);
            $conv->touch();

            // Push FCM au destinataire
            if ($targetUser->fcm_token) {
                app(FcmService::class)->send(
                    $targetUser->fcm_token,
                    'Message de Minizon Admin',
                    $validated['message'],
                    ['type' => 'admin_message', 'conversation_uuid' => $conv->uuid]
                );
            }
        }

        $conv->load(['participants.profile', 'participants.role', 'messages', 'lastMessage']);

        $messages = $conv->messages->map(fn (Message $m) => [
            'id'      => $m->uuid,
            'content' => $m->body,
            'sender'  => $m->sender_id === $adminId ? 'admin' : 'user',
            'sentAt'  => $m->created_at->toIso8601String(),
            'status'  => $m->read_at ? 'lu' : 'envoyé',
        ]);

        return $this->apiResponse(true, $existed ? 'Conversation retrouvée.' : 'Conversation créée.', [
            'conversation' => $this->formatConversation($conv, $adminId),
            'messages'     => $messages,
            'created'      => ! $existed,
        ]);
    }

    // =========================================================================
    //  GET /api/admin/messaging/conversations?role=&search=&status=
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/messaging/conversations',
        operationId: 'adminMessagingList',
        summary: '[ADMIN] Liste des conversations directes (conducteurs & passagers)',
        description: 'Retourne toutes les conversations directes admin ↔ utilisateur, triées par date (plus récente en premier). Filtres combinables : rôle (driver/passenger), recherche textuelle, statut en ligne.',
        tags: ['👑 Admin — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'role',   in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'driver', 'passenger'], default: 'all')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filtre par nom ou téléphone'),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['tous', 'en_ligne', 'en_trajet', 'hors_ligne'], default: 'tous'), description: 'Filtre par statut conducteur (ignoré pour les passagers)'),
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
                                new OA\Property(property: 'conversations', type: 'array', items: new OA\Items()),
                                new OA\Property(property: 'totalUnread',   type: 'integer', example: 4),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function conversations(Request $request): JsonResponse
    {
        $adminId      = auth()->id();
        $roleFilter   = $request->input('role', 'all');
        $statusFilter = $request->input('status', 'tous');
        $search       = trim($request->input('search', ''));

        $query = Conversation::with([
            'participants.profile',
            'participants.role',
            'lastMessage',
        ])
        ->whereNull('trip_id')
        ->whereNull('booking_id')
        ->whereHas('participants', fn ($q) => $q->where('users.id', $adminId))
        ->whereHas('participants', fn ($q) => $q->where('users.id', '!=', $adminId));

        // Filtre par rôle
        if ($roleFilter !== 'all') {
            $query->whereHas('participants', fn ($q) => $q->whereHas('role', fn ($r) => $r->where('name', $roleFilter)));
        } else {
            $query->whereHas('participants', fn ($q) => $q->whereHas('role', fn ($r) => $r->whereIn('name', ['driver', 'passenger'])));
        }

        // Filtre par recherche textuelle (nom ou téléphone de l'interlocuteur)
        if ($search !== '') {
            $query->whereHas('participants', fn ($q) => $q
                ->where('users.id', '!=', $adminId)
                ->where(fn ($qb) => $qb
                    ->where('users.phone', 'like', "%{$search}%")
                    ->orWhereHas('profile', fn ($p) => $p->where(
                        DB::raw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))"),
                        'like',
                        "%{$search}%"
                    ))
                )
            );
        }

        $conversations = $query->orderByDesc('updated_at')->get();

        // Statut en ligne — une requête groupée pour les drivers
        $otherIds    = $conversations->flatMap(fn ($c) => $c->participants->pluck('id'))->unique()->diff([$adminId]);
        $activeTrips = Trip::whereIn('user_id', $otherIds)->where('status', 'active')->pluck('user_id', 'user_id');
        $onlineUsers = User::whereIn('id', $otherIds)->where('is_online', true)->pluck('id', 'id');

        $result = $conversations->map(function (Conversation $conv) use ($adminId, $activeTrips, $onlineUsers): array {
            $data = $this->formatConversation($conv, $adminId);

            // Recalculer driverStatus depuis les collections pré-chargées
            $other = $conv->participants->first(fn ($u) => $u->id !== $adminId);
            if ($other) {
                $data['driverStatus'] = $activeTrips->has($other->id)
                    ? 'en_trajet'
                    : ($onlineUsers->has($other->id) ? 'en_ligne' : 'hors_ligne');
            }

            return $data;
        });

        // Filtre statut côté PHP (uniquement après résolution des statuts)
        if ($statusFilter !== 'tous') {
            $result = $result->filter(fn ($c) => $c['driverStatus'] === $statusFilter);
        }

        $result      = $result->values();
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
        summary: '[ADMIN] Ouvrir une conversation — messages + marquer lu',
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

        // Marquer les messages entrants comme lus
        $conversation->messages()
            ->where('sender_id', '!=', $adminId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $conversation->messages->map(fn (Message $m) => [
            'id'      => $m->uuid,
            'content' => $m->body,
            'sender'  => $m->sender_id === $adminId ? 'admin' : 'user',
            'sentAt'  => $m->created_at->toIso8601String(),
            'status'  => $m->read_at ? 'lu' : 'envoyé',
        ]);

        return $this->apiResponse(true, 'Conversation.', [
            'conversation' => $this->formatConversation($conversation, $adminId),
            'messages'     => $messages,
        ]);
    }

    // =========================================================================
    //  POST /api/admin/messaging/conversations/{uuid}/messages
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/messaging/conversations/{uuid}/messages',
        operationId: 'adminMessagingSend',
        summary: '[ADMIN] Envoyer un message',
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

        $conversation = Conversation::with('participants')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $adminId,
            'body'            => $validated['content'],
        ]);

        $conversation->touch();

        // Push FCM au(x) destinataire(s)
        $recipients = $conversation->participants->filter(fn ($u) => $u->id !== $adminId);
        $fcmTokens  = $recipients->pluck('fcm_token')->filter()->values()->all();

        if (! empty($fcmTokens)) {
            app(FcmService::class)->sendToMultiple(
                $fcmTokens,
                'Minizon Admin',
                $validated['content'],
                ['type' => 'admin_message', 'conversation_uuid' => $conversation->uuid]
            );
        }

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
        summary: '[ADMIN] Diffuser un message à plusieurs utilisateurs',
        description: 'Envoie le message dans la conversation admin-user de chaque utilisateur correspondant au filtre. Crée la conversation si elle n\'existe pas encore. Envoie une notification push FCM à chaque destinataire.',
        tags: ['👑 Admin — Messagerie'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content', 'target'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', maxLength: 2000, example: 'Nouveau point de ramassage disponible.'),
                    new OA\Property(property: 'target',  type: 'string', enum: ['tous_conducteurs', 'tous_passagers', 'tous', 'en_ligne', 'en_trajet'], example: 'en_ligne'),
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
            'target'  => 'required|in:tous_conducteurs,tous_passagers,tous,en_ligne,en_trajet',
        ]);

        $usersQuery = User::where('is_blocked', false);

        match ($validated['target']) {
            'tous_conducteurs' => $usersQuery->whereHas('role', fn ($q) => $q->where('name', 'driver')),
            'tous_passagers'   => $usersQuery->whereHas('role', fn ($q) => $q->where('name', 'passenger')),
            'en_ligne'         => $usersQuery
                ->whereHas('role', fn ($q) => $q->where('name', 'driver'))
                ->where('is_online', true)
                ->whereDoesntHave('trips', fn ($q) => $q->where('status', 'active')),
            'en_trajet'        => $usersQuery
                ->whereHas('role', fn ($q) => $q->where('name', 'driver'))
                ->whereHas('trips', fn ($q) => $q->where('status', 'active')),
            default            => $usersQuery->whereHas('role', fn ($q) => $q->whereIn('name', ['driver', 'passenger'])),
        };

        $users     = $usersQuery->get();
        $sentCount = 0;
        $fcmTokens = [];

        foreach ($users as $user) {
            $conv = $this->findOrCreateConversation($adminId, $user->id);

            Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $adminId,
                'body'            => $validated['content'],
            ]);

            $conv->touch();

            if ($user->fcm_token) {
                $fcmTokens[] = $user->fcm_token;
            }

            $sentCount++;
        }

        // Push FCM groupé
        if (! empty($fcmTokens)) {
            app(FcmService::class)->sendToMultiple(
                $fcmTokens,
                'Minizon Admin',
                $validated['content'],
                ['type' => 'admin_broadcast']
            );
        }

        return $this->apiResponse(true, "Message diffusé à {$sentCount} utilisateur(s).", [
            'sent_to' => $sentCount,
            'target'  => $validated['target'],
        ]);
    }
}
