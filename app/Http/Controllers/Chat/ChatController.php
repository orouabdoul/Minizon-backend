<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\NewMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ChatController extends Controller
{
    // =========================================================================
    //  GET /api/conversations
    //  Liste toutes les conversations de l'utilisateur connecté
    // =========================================================================

    #[OA\Get(
        path: '/api/conversations',
        operationId: 'conversationsIndex',
        summary: 'Mes conversations',
        description: 'Retourne toutes les conversations de l\'utilisateur connecté, triées par activité récente. Chaque entrée inclut le dernier message et le nombre de messages non lus.',
        tags: ['💬 Messagerie'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des conversations'),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $conversations = Conversation::with([
            'participants.profile',
            'lastMessage.sender.profile',
            'booking',
            'trip',
        ])
        ->whereHas('participants', fn ($q) => $q->where('users.id', $userId))
        ->withCount([
            'messages as unread_count' => fn ($q) => $q
                ->where('sender_id', '!=', $userId)
                ->whereNull('read_at'),
        ])
        ->orderByDesc('updated_at')
        ->get()
        ->map(fn ($conv) => $this->formatConversation($conv, $userId));

        return $this->apiResponse(true, 'Conversations récupérées.', $conversations);
    }

    // =========================================================================
    //  POST /api/bookings/{uuid}/conversation
    //  Crée ou retourne la conversation liée à une réservation acceptée
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/conversation',
        operationId: 'conversationGetOrCreate',
        summary: 'Ouvrir la conversation d\'une réservation',
        description: 'Crée (ou retourne si elle existe déjà) la conversation entre le conducteur et le passager pour une réservation **acceptée**. Idempotent : appeler plusieurs fois retourne toujours la même conversation.',
        tags: ['💬 Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de la réservation'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conversation récupérée ou créée'),
            new OA\Response(response: 403, description: 'Accès refusé ou réservation non acceptée', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable',                  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function getOrCreate(Request $request, string $bookingUuid): JsonResponse
    {
        $booking = Booking::with('trip')->where('uuid', $bookingUuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $user        = $request->user();
        $isDriver    = $booking->trip->user_id === $user->id;
        $isPassenger = $booking->passenger_id === $user->id;

        if (! $isDriver && ! $isPassenger) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        if ($booking->status !== 'accepted') {
            return $this->apiResponse(
                false,
                'La conversation n\'est disponible que pour les réservations acceptées.',
                [],
                422
            );
        }

        $conversation = Conversation::firstOrCreate(
            ['booking_id' => $booking->id],
            ['trip_id' => $booking->trip_id]
        );

        // Attache conducteur + passager si pas encore participants
        $conversation->participants()->syncWithoutDetaching([
            $booking->trip->user_id,
            $booking->passenger_id,
        ]);

        $conversation->load(['participants.profile', 'lastMessage.sender.profile']);

        $unreadCount = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return $this->apiResponse(true, 'Conversation récupérée.', array_merge(
            $conversation->toArray(),
            ['unread_count' => $unreadCount]
        ));
    }

    // =========================================================================
    //  GET /api/conversations/{uuid}/messages
    //  Messages paginés (scroll infini — les plus récents d'abord)
    //  ?before_id={id}&per_page=20
    // =========================================================================

    #[OA\Get(
        path: '/api/conversations/{uuid}/messages',
        operationId: 'conversationMessages',
        summary: 'Historique des messages (scroll infini)',
        description: 'Retourne les messages d\'une conversation, du plus récent au plus ancien. Pour charger les messages plus anciens, passer `before_id` = ID du plus ancien message déjà chargé.',
        tags: ['💬 Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid',      in: 'path',  required: true,  schema: new OA\Schema(type: 'string',  format: 'uuid'), description: 'UUID de la conversation'),
            new OA\Parameter(name: 'before_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Charger les messages antérieurs à cet ID (pagination cursor)'),
            new OA\Parameter(name: 'per_page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 50), description: 'Nombre de messages par page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Messages récupérés'),
            new OA\Response(response: 403, description: 'Accès refusé',       content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function messages(Request $request, string $uuid): JsonResponse
    {
        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        if (! $this->isParticipant($conversation, $request->user()->id)) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $perPage  = min((int) $request->input('per_page', 20), 50);
        $beforeId = (int) $request->input('before_id', 0);

        $query = $conversation->messages()
            ->with('sender.profile')
            ->orderByDesc('id');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->limit($perPage)->get()->reverse()->values();

        $hasMore = $beforeId > 0
            ? $conversation->messages()->where('id', '<', $messages->first()?->id ?? $beforeId)->exists()
            : $conversation->messages()->where('id', '<', $messages->first()?->id ?? PHP_INT_MAX)->exists();

        return $this->apiResponse(true, 'Messages récupérés.', [
            'messages'  => $messages->map(fn ($m) => $this->formatMessage($m, $request->user()->id)),
            'has_more'  => $hasMore,
            'next_before_id' => $messages->first()?->id,
        ]);
    }

    // =========================================================================
    //  POST /api/conversations/{uuid}/messages
    //  Envoyer un message (texte et/ou image)
    // =========================================================================

    #[OA\Post(
        path: '/api/conversations/{uuid}/messages',
        operationId: 'conversationSend',
        summary: 'Envoyer un message',
        description: 'Envoie un message texte et/ou une image dans une conversation. Envoie une notification push FCM au destinataire. Requête `multipart/form-data` si image jointe.',
        tags: ['💬 Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'body',       type: 'string',  example: 'Je suis en route !',   nullable: true, description: 'Texte du message (max 2000 caractères)'),
                        new OA\Property(property: 'attachment', type: 'string',  format: 'binary',                nullable: true, description: 'Image jpg/png/webp/gif — max 5 Mo'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Message envoyé'),
            new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Accès refusé',      content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function send(Request $request, string $uuid): JsonResponse
    {
        $conversation = Conversation::with('participants.profile')->where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        if (! $this->isParticipant($conversation, $request->user()->id)) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'body'       => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        if (! $request->filled('body') && ! $request->hasFile('attachment')) {
            return $this->apiResponse(false, 'Le message doit contenir du texte ou une image.', [], 422);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('chat/attachments', 'public');
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $request->user()->id,
            'body'            => $request->body,
            'attachment_path' => $attachmentPath,
        ]);

        // Mise à jour du timestamp de la conversation (pour le tri)
        $conversation->touch();

        // Notification FCM au destinataire
        $recipient = $conversation->participants
            ->first(fn ($p) => $p->id !== $request->user()->id);

        if ($recipient) {
            $recipient->notify(new NewMessage($message->load('sender.profile'), $conversation));
        }

        return $this->apiResponse(
            true,
            'Message envoyé.',
            $this->formatMessage($message->load('sender.profile'), $request->user()->id),
            201
        );
    }

    // =========================================================================
    //  POST /api/conversations/{uuid}/read
    //  Marquer tous les messages non lus comme lus
    // =========================================================================

    #[OA\Post(
        path: '/api/conversations/{uuid}/read',
        operationId: 'conversationRead',
        summary: 'Marquer les messages comme lus',
        description: 'Marque tous les messages reçus non lus de cette conversation comme lus. À appeler lorsque l\'utilisateur ouvre la conversation.',
        tags: ['💬 Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Messages marqués comme lus'),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function markRead(Request $request, string $uuid): JsonResponse
    {
        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        if (! $this->isParticipant($conversation, $request->user()->id)) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $updated = $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->apiResponse(true, "{$updated} message(s) marqué(s) comme lu(s).", [
            'marked_read' => $updated,
        ]);
    }

    // =========================================================================
    //  ADMIN — Toutes les conversations (supervision)
    //  GET /api/admin/conversations
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/conversations',
        operationId: 'adminConversationsIndex',
        summary: '[ADMIN] Supervision des conversations',
        description: 'Liste toutes les conversations de la plateforme avec leur dernier message et nombre de messages. Accès réservé aux administrateurs.',
        tags: ['💬 Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée des conversations'),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function adminIndex(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        $conversations = Conversation::with([
            'participants.profile',
            'lastMessage.sender.profile',
            'booking',
            'trip',
        ])
        ->withCount('messages')
        ->orderByDesc('updated_at')
        ->paginate($perPage);

        return $this->apiResponse(true, 'Conversations récupérées (admin).', $conversations);
    }

    // =========================================================================
    //  ADMIN — Messages d'une conversation
    //  GET /api/admin/conversations/{uuid}/messages
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/conversations/{uuid}/messages',
        operationId: 'adminConversationMessages',
        summary: '[ADMIN] Lire une conversation complète',
        description: 'Accès admin à tous les messages d\'une conversation. Utilisé pour la modération et le support.',
        tags: ['💬 Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid',      in: 'path',  required: true,  schema: new OA\Schema(type: 'string',  format: 'uuid')),
            new OA\Parameter(name: 'before_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Pagination cursor'),
            new OA\Parameter(name: 'per_page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Messages + infos participants'),
            new OA\Response(response: 403, description: 'Accès refusé',            content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function adminMessages(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversation = Conversation::with(['participants.profile', 'trip', 'booking'])
            ->where('uuid', $uuid)
            ->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        $perPage  = min((int) $request->input('per_page', 50), 100);
        $beforeId = (int) $request->input('before_id', 0);

        $query = $conversation->messages()->with('sender.profile')->orderByDesc('id');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->limit($perPage)->get()->reverse()->values();

        return $this->apiResponse(true, 'Messages récupérés (admin).', [
            'conversation' => [
                'uuid'         => $conversation->uuid,
                'participants' => $conversation->participants->map(fn ($p) => [
                    'uuid'  => $p->uuid,
                    'phone' => $p->phone,
                    'name'  => $p->profile
                        ? trim("{$p->profile->first_name} {$p->profile->last_name}")
                        : $p->phone,
                ]),
                'trip' => $conversation->trip ? [
                    'uuid'           => $conversation->trip->uuid,
                    'departure_city' => $conversation->trip->departure_city,
                    'arrival_city'   => $conversation->trip->arrival_city,
                ] : null,
            ],
            'messages' => $messages->map(fn ($m) => $this->formatMessage($m, 0)),
            'has_more' => $beforeId > 0
                ? $conversation->messages()->where('id', '<', $messages->first()?->id ?? $beforeId)->exists()
                : false,
            'next_before_id' => $messages->first()?->id,
        ]);
    }

    // =========================================================================
    //  ADMIN — Supprimer un message (modération)
    //  DELETE /api/admin/conversations/{uuid}/messages/{id}
    // =========================================================================

    #[OA\Delete(
        path: '/api/admin/conversations/{uuid}/messages/{id}',
        operationId: 'adminDeleteMessage',
        summary: '[ADMIN] Supprimer un message',
        description: 'Supprime définitivement un message (modération). Si le message contient une pièce jointe, le fichier est également supprimé du stockage.',
        tags: ['💬 Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de la conversation'),
            new OA\Parameter(name: 'id',   in: 'path', required: true, schema: new OA\Schema(type: 'integer'),                description: 'ID du message'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Message supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé',            content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Message introuvable',     content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function adminDeleteMessage(Request $request, string $uuid, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        $message = $conversation->messages()->find($id);

        if (! $message) {
            return $this->apiResponse(false, 'Message introuvable.', [], 404);
        }

        if ($message->attachment_path) {
            Storage::disk('public')->delete($message->attachment_path);
        }

        $message->delete();

        return $this->apiResponse(true, 'Message supprimé.', []);
    }

    // =========================================================================
    //  ADMIN — Fermer / supprimer une conversation
    //  DELETE /api/admin/conversations/{uuid}
    // =========================================================================

    #[OA\Delete(
        path: '/api/admin/conversations/{uuid}',
        operationId: 'adminDeleteConversation',
        summary: '[ADMIN] Supprimer une conversation',
        description: 'Supprime définitivement une conversation et tous ses messages (y compris les pièces jointes). Action irréversible.',
        tags: ['💬 Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conversation supprimée'),
            new OA\Response(response: 403, description: 'Accès refusé',            content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function adminDelete(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        // Supprimer les pièces jointes
        $conversation->messages()
            ->whereNotNull('attachment_path')
            ->each(function ($msg) {
                Storage::disk('public')->delete($msg->attachment_path);
            });

        $conversation->delete();

        return $this->apiResponse(true, 'Conversation supprimée.', []);
    }

    // =========================================================================
    //  Helpers privés
    // =========================================================================

    private function isParticipant(Conversation $conversation, int $userId): bool
    {
        if ($conversation->relationLoaded('participants')) {
            return $conversation->participants->contains('id', $userId);
        }
        return $conversation->participants()->where('users.id', $userId)->exists();
    }

    private function formatConversation(Conversation $conv, int $userId): array
    {
        $other = $conv->participants->first(fn ($p) => $p->id !== $userId);

        return [
            'uuid'          => $conv->uuid,
            'booking_id'    => $conv->booking_id,
            'trip'          => $conv->trip ? [
                'uuid'             => $conv->trip->uuid,
                'departure_city'   => $conv->trip->departure_city,
                'arrival_city'     => $conv->trip->arrival_city,
                'departure_time'   => $conv->trip->departure_time,
            ] : null,
            'other_user'    => $other ? [
                'uuid'       => $other->uuid,
                'name'       => $other->profile
                    ? trim("{$other->profile->first_name} {$other->profile->last_name}")
                    : $other->phone,
                'phone'      => $other->phone,
            ] : null,
            'last_message'  => $conv->lastMessage ? $this->formatMessage($conv->lastMessage, $userId) : null,
            'unread_count'  => $conv->unread_count ?? 0,
            'updated_at'    => $conv->updated_at,
        ];
    }

    private function formatMessage(Message $message, int $myUserId): array
    {
        return [
            'id'              => $message->id,
            'uuid'            => $message->uuid,
            'body'            => $message->body,
            'attachment_url'  => $message->attachment_path
                ? Storage::url($message->attachment_path)
                : null,
            'is_mine'         => $message->sender_id === $myUserId,
            'sender'          => $message->sender ? [
                'uuid' => $message->sender->uuid,
                'name' => $message->sender->profile
                    ? trim("{$message->sender->profile->first_name} {$message->sender->profile->last_name}")
                    : $message->sender->phone,
            ] : null,
            'read_at'         => $message->read_at,
            'created_at'      => $message->created_at,
        ];
    }
}
