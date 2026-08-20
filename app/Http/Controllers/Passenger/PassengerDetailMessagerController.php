<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Trip;
use App\Services\FcmService;
use App\Traits\HandlesConversationChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Page "Conversation" — vue détaillée pour le passager.
 */
class PassengerDetailMessagerController extends Controller
{
    use HandlesConversationChat;

    // =========================================================================
    //  GET /api/passenger/conversations/{uuid}/thread
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/conversations/{uuid}/thread',
        operationId: 'passengerConversationThread',
        summary: 'Contexte + messages d\'une conversation (passager)',
        description: "Retourne en un seul appel :\n- **`thread`** : contexte de l'interlocuteur et du trajet pour le header de la page\n- **`messages`** : liste paginée de `DetailMessage` avec `kind` pré-calculé (`incoming` / `outgoing` / `reminder`). Un `reminder` virtuel est injecté en tête si le trajet démarre dans les 24h.\n- Pagination curseur via `before_id` + `per_page`.\n- Sync incrémentale via `since_id` (polling).\n\nEnvoyer un message : **POST /api/conversations/{uuid}/messages** (ChatController).\nMarquer comme lu : **POST /api/conversations/{uuid}/read** (ChatController).",
        tags: ['👤 Passenger — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid',      in: 'path',  required: true,  schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 50)),
            new OA\Parameter(name: 'before_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Cursor pour charger les messages plus anciens (scroll infini)'),
            new OA\Parameter(name: 'since_id',  in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Sync incrémentale : retourne uniquement les messages après cet ID'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Thread + messages',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Thread chargé.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'thread',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'uuid',         type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'booking_uuid', type: 'string', format: 'uuid', nullable: true),
                                        new OA\Property(
                                            property: 'other_user',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'uuid',       type: 'string'),
                                                new OA\Property(property: 'name',       type: 'string', example: 'Moussa Alabi'),
                                                new OA\Property(property: 'phone',      type: 'string', nullable: true),
                                                new OA\Property(property: 'avatar_url', type: 'string'),
                                                new OA\Property(property: 'is_online',  type: 'boolean', description: 'Placeholder — toujours false sans WebSocket'),
                                            ]
                                        ),
                                        new OA\Property(
                                            property: 'trip',
                                            nullable: true,
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'uuid',                 type: 'string', format: 'uuid'),
                                                new OA\Property(property: 'route',                type: 'string', example: 'Cotonou → Abomey-Calavi'),
                                                new OA\Property(property: 'status',               type: 'string', example: 'active'),
                                                new OA\Property(property: 'status_label',         type: 'string', example: 'En cours'),
                                                new OA\Property(property: 'departure_time_label', type: 'string', example: 'Demain 08:30'),
                                                new OA\Property(property: 'available_seats',      type: 'integer', example: 3),
                                            ]
                                        ),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'messages',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/DetailMessage')
                                ),
                                new OA\Property(property: 'has_more',       type: 'boolean', example: false),
                                new OA\Property(property: 'next_before_id', type: 'integer', nullable: true),
                                new OA\Property(property: 'latest_id',      type: 'integer', nullable: true, description: 'ID du dernier message — à passer en since_id au prochain poll'),
                                new OA\Property(property: 'send_endpoint',  type: 'string', example: 'POST /api/conversations/{uuid}/messages'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function thread(Request $request, string $uuid): JsonResponse
    {
        $userId = $request->user()->id;

        $conversation = Conversation::with([
            'participants.profile',
            'participants.role',
            'booking',
            'trip',
        ])->where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        if (! $conversation->hasParticipant($userId)) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        // ── Contexte du thread (toujours calculé) ─────────────────────────────
        $other       = $conversation->participants->first(fn ($p) => $p->id !== $userId);
        $profile     = $other?->profile;
        $isAdminConv = $conversation->type === 'support';
        $avatarUrl   = (! $isAdminConv && $profile?->selfie_front)
            ? Storage::disk('public')->url($profile->selfie_front)
            : null;
        $otherName   = $isAdminConv
            ? 'Admin Minizon'
            : ($profile ? trim("{$profile->first_name} {$profile->last_name}") : ($other?->phone ?? '—'));

        $threadContext = [
            'uuid'                  => $conversation->uuid,
            'booking_uuid'          => $isAdminConv ? '' : ($conversation->booking?->uuid ?? ''),
            'is_admin_conversation' => $isAdminConv,
            'other_user'            => [
                'uuid'       => $isAdminConv ? '' : ($other?->uuid ?? ''),
                'name'       => $otherName,
                'phone'      => $isAdminConv ? '' : (($conversation->booking?->status !== 'accepted') ? '' : ($other?->phone ?? '')),
                'avatar_url' => $avatarUrl,
                'is_online'  => $isAdminConv ? false : ($other?->is_online ?? false),
            ],
            'trip' => $isAdminConv ? null : $this->formatTripContext($conversation->trip),
        ];

        $sendEndpoint = 'POST /api/passenger/conversations/' . $conversation->uuid . '/messages';

        // ── Sync incrémentale : since_id ──────────────────────────────────────
        $sinceId  = (int) $request->input('since_id', 0);
        $beforeId = (int) $request->input('before_id', 0);
        $perPage  = min((int) $request->input('per_page', 20), 50);

        if ($sinceId > 0) {
            $raw = $conversation->messages()
                ->with('sender.profile')
                ->where('id', '>', $sinceId)
                ->orderBy('id')
                ->limit(100)
                ->get();

            $this->markDelivered($conversation, $userId, $raw);

            return $this->apiResponse(true, 'Thread chargé.', [
                'thread'        => $threadContext,
                'messages'      => $raw->map(fn (Message $m) => $this->formatDetailMessage($m, $userId))->values(),
                'has_more'      => false,
                'latest_id'     => $raw->last()?->id ?? $sinceId,
                'send_endpoint' => $sendEndpoint,
            ]);
        }

        // ── Historique paginé : before_id ────────────────────────────────────
        $query = $conversation->messages()->with('sender.profile')->orderByDesc('id');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $rawMessages = $query->limit($perPage)->get()->reverse()->values();

        $hasMore = $conversation->messages()
            ->where('id', '<', $rawMessages->first()?->id ?? PHP_INT_MAX)
            ->exists();

        $this->markDelivered($conversation, $userId, $rawMessages);

        $messages = $rawMessages->map(fn (Message $m) => $this->formatDetailMessage($m, $userId))->values()->all();

        // Rappel de départ injecté uniquement sur la première page
        if ($beforeId === 0) {
            $reminder = $this->buildReminderIfNeeded($conversation->trip);
            if ($reminder !== null) {
                array_unshift($messages, $reminder);
            }
        }

        return $this->apiResponse(true, 'Thread chargé.', [
            'thread'         => $threadContext,
            'messages'       => $messages,
            'has_more'       => $hasMore,
            'next_before_id' => $rawMessages->first()?->id,
            'latest_id'      => $rawMessages->last()?->id,
            'send_endpoint'  => $sendEndpoint,
        ]);
    }

    // =========================================================================
    //  POST /api/passenger/bookings/{uuid}/conversation
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/bookings/{uuid}/conversation',
        operationId: 'passengerConversationGetOrCreate',
        summary: 'Ouvrir ou récupérer la conversation d\'une réservation (passager)',
        description: 'Retourne l\'UUID de la conversation existante entre passager et conducteur, ou en crée une nouvelle (style WhatsApp — une seule conversation par paire).',
        tags: ['👤 Passenger — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'UUID de la conversation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Conversation prête.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'conversation_uuid', type: 'string', format: 'uuid'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé',           content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]

    // =========================================================================
    //  GET /api/passenger/conversations/{uuid}/messages
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/conversations/{uuid}/messages',
        operationId: 'passengerConversationMessages',
        summary: 'Messages paginés d\'une conversation (passager)',
        tags: ['👤 Passenger — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid',      in: 'path',  required: true,  schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 50)),
            new OA\Parameter(name: 'before_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Curseur pour charger les messages plus anciens'),
            new OA\Parameter(name: 'since_id',  in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Sync incrémentale : retourne uniquement les messages après cet ID'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Messages',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Messages.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'items',          type: 'array', items: new OA\Items(ref: '#/components/schemas/DetailMessage')),
                                new OA\Property(property: 'has_more',       type: 'boolean'),
                                new OA\Property(property: 'next_before_id', type: 'integer', nullable: true),
                                new OA\Property(property: 'latest_id',      type: 'integer', nullable: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé',           content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]

    // =========================================================================
    //  POST /api/passenger/conversations/{uuid}/messages
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/conversations/{uuid}/messages',
        operationId: 'passengerConversationSend',
        summary: 'Envoyer un message (passager)',
        description: "Envoie un message texte, un fichier (image ou document), ou les deux.\n\nFormats acceptés :\n- **Images** : jpeg, png, webp, gif (max 5 Mo)\n- **Documents** : pdf, doc, docx (max 10 Mo)\n\n**Content-Type : `multipart/form-data`** — requis dès qu'un fichier est joint.",
        tags: ['👤 Passenger — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'body',       type: 'string', nullable: true, description: 'Texte du message', example: 'Je serai là dans 5 minutes.'),
                            new OA\Property(property: 'attachment', type: 'string', format: 'binary', nullable: true, description: 'Fichier à joindre (image, document ou audio)'),
                        ]
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Message envoyé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Message envoyé.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id',       type: 'integer'),
                                new OA\Property(property: 'uuid',     type: 'string', format: 'uuid'),
                                new OA\Property(property: 'kind',     type: 'string', enum: ['outgoing']),
                                new OA\Property(property: 'body',     type: 'string', nullable: true),
                                new OA\Property(property: 'time',     type: 'string', example: '09:15'),
                                new OA\Property(property: 'raw_date', type: 'string', format: 'date', example: '2026-07-14'),
                                new OA\Property(property: 'attachment', type: 'object', nullable: true,
                                    properties: [
                                        new OA\Property(property: 'url',  type: 'string'),
                                        new OA\Property(property: 'type', type: 'string', enum: ['image', 'document', 'audio']),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé',                   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Conversation introuvable',        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Message vide ou fichier invalide', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]

    // =========================================================================
    //  POST /api/passenger/conversations/{uuid}/read
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/conversations/{uuid}/read',
        operationId: 'passengerConversationMarkRead',
        summary: 'Marquer les messages comme lus (passager)',
        tags: ['👤 Passenger — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Messages marqués comme lus'),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    private function oaPlaceholderChat(): void {}

    // =========================================================================
    //  PATCH /api/passenger/messages/{uuid}
    // =========================================================================

    #[OA\Patch(
        path: '/api/passenger/messages/{uuid}',
        operationId: 'passengerMessageEdit',
        summary: 'Modifier un message (passager)',
        description: "Modifie le texte d'un message. Seul l'expéditeur peut modifier son propre message.",
        tags: ['👤 Passenger — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['body'],
                properties: [
                    new OA\Property(property: 'body', type: 'string', maxLength: 4000, example: 'Message corrigé.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Message modifié',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success',  type: 'boolean', example: true),
                        new OA\Property(property: 'message',  type: 'string',  example: 'Message modifié.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid',      type: 'string', format: 'uuid'),
                                new OA\Property(property: 'body',      type: 'string'),
                                new OA\Property(property: 'edited_at', type: 'string', example: '2026-07-14T09:15:00+01:00'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé — pas l\'expéditeur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Message introuvable',              content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Corps vide ou message sans texte', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function editMessage(Request $request, string $uuid): JsonResponse
    {
        $message = Message::where('uuid', $uuid)->first();

        if (! $message) {
            return $this->apiResponse(false, 'Message introuvable.', [], 404);
        }

        if ($message->sender_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Vous ne pouvez modifier que vos propres messages.', [], 403);
        }

        if ($message->body === null && $message->attachment_path) {
            return $this->apiResponse(false, 'Un message sans texte ne peut pas être modifié.', [], 422);
        }

        $validated = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $newBody   = trim($validated['body']);

        if ($newBody === '') {
            return $this->apiResponse(false, 'Le message ne peut pas être vide.', [], 422);
        }

        $message->update(['body' => $newBody]);

        // Push FCM → destinataire voit le message modifié en temps réel
        $message->load('conversation.participants');
        $conv   = $message->conversation;
        $others = $conv->participants->filter(fn ($u) => $u->id !== $request->user()->id);
        $tokens = $others->pluck('fcm_token')->filter()->values()->all();
        if (! empty($tokens)) {
            app(FcmService::class)->sendToMultiple($tokens, '', '', [
                'type'              => 'message_edited',
                'conversation_uuid' => $conv->uuid,
                'message_uuid'      => $message->uuid,
                'new_body'          => $message->body,
            ]);
        }

        return $this->apiResponse(true, 'Message modifié.', [
            'uuid'      => $message->uuid,
            'body'      => $message->body,
            'is_edited' => true,
            'edited_at' => $message->updated_at->toIso8601String(),
        ]);
    }

    // =========================================================================
    //  DELETE /api/passenger/messages/{uuid}
    // =========================================================================

    #[OA\Delete(
        path: '/api/passenger/messages/{uuid}',
        operationId: 'passengerMessageDelete',
        summary: 'Supprimer un message (passager)',
        description: 'Supprime définitivement un message. Seul l\'expéditeur peut supprimer son propre message. Si un fichier est attaché, il est également supprimé du stockage.',
        tags: ['👤 Passenger — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Message supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé — pas l\'expéditeur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Message introuvable',              content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function deleteMessage(Request $request, string $uuid): JsonResponse
    {
        $message = Message::where('uuid', $uuid)->first();

        if (! $message) {
            return $this->apiResponse(false, 'Message introuvable.', [], 404);
        }

        if ($message->sender_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Vous ne pouvez supprimer que vos propres messages.', [], 403);
        }

        // Push FCM AVANT le soft-delete
        $message->load('conversation.participants');
        $conv    = $message->conversation;
        $msgUuid = $message->uuid;
        $others  = $conv->participants->filter(fn ($u) => $u->id !== $request->user()->id);
        $tokens  = $others->pluck('fcm_token')->filter()->values()->all();

        $message->delete(); // soft delete — fichier conservé en stockage

        if (! empty($tokens)) {
            app(FcmService::class)->sendToMultiple($tokens, '', '', [
                'type'              => 'message_deleted',
                'conversation_uuid' => $conv->uuid,
                'message_uuid'      => $msgUuid,
            ]);
        }

        return $this->apiResponse(true, 'Message supprimé.');
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatDetailMessage(Message $msg, int $myUserId): array
    {
        $tz         = 'Africa/Porto-Novo';
        $attachType = $msg->attachment_type ?? 'document';

        $attachment = null;
        if ($msg->attachment_path) {
            $attachment = [
                'url'  => Storage::disk('public')->url($msg->attachment_path),
                'type' => $attachType,
            ];
        }

        $messageType = $msg->body && $msg->attachment_path
            ? 'mixed'
            : ($msg->attachment_path ? $attachType : 'text');

        $textContent = $msg->body;

        return [
            'id'               => $msg->id,
            'kind'             => $msg->sender_id === $myUserId ? 'outgoing' : 'incoming',
            'message'          => $textContent,
            'body'             => $textContent,
            'message_type'     => $messageType,
            'is_voice_message' => $attachType === 'audio' && $msg->attachment_path !== null,
            'is_delivered'     => $msg->delivered_at !== null,
            'is_read'          => $msg->read_at !== null,
            'is_edited'        => $msg->updated_at->gt($msg->created_at),
            'delivered_at'     => $msg->delivered_at?->toIso8601String(),
            'read_at'          => $msg->read_at?->toIso8601String(),
            'time'             => $msg->created_at->setTimezone($tz)->format('H:i'),
            'raw_date'         => $msg->created_at->setTimezone($tz)->format('Y-m-d'),
            'reply_to_id'      => $msg->reply_to_id,
            'title'            => null,
            'subtitle'         => null,
            'action_label'     => null,
            'attachment'       => $attachment,
        ];
    }

    private function buildReminderIfNeeded(?Trip $trip): ?array
    {
        if (! $trip || ! in_array($trip->status, ['pending', 'active'])) return null;

        $now       = now();
        $departsAt = $trip->departure_time;

        if ($departsAt->diffInHours($now, false) > 2 || $departsAt->isBefore($now->copy()->subHours(2))) {
            return null;
        }

        $route      = $trip->departure_city . ' → ' . $trip->arrival_city;
        $timeStr    = $departsAt->setTimezone('Africa/Porto-Novo')->format('H:i');
        $hoursUntil = (int) $now->diffInHours($departsAt, false);

        $label = $hoursUntil <= 0
            ? "Votre trajet {$route} commence maintenant !"
            : ($hoursUntil < 2
                ? "Rappel : votre trajet {$route} démarre dans {$hoursUntil}h ({$timeStr})."
                : "Rappel : votre trajet {$route} démarre à {$timeStr}.");

        return [
            'id'           => null,
            'kind'         => 'reminder',
            'message'      => $label,
            'time'         => 'Système',
            'raw_date'     => null,
            'title'        => null,
            'subtitle'     => null,
            'action_label' => null,
            'attachment'   => null,
        ];
    }

    private function formatTripContext(?Trip $trip): ?array
    {
        if (! $trip) return null;

        $statusLabels = [
            'pending'   => 'En attente',
            'active'    => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
        ];

        $tz        = 'Africa/Porto-Novo';
        $departsAt = $trip->departure_time->setTimezone($tz);
        $now       = now()->setTimezone($tz);
        $diffDays  = (int) $now->diffInDays($departsAt, false);
        $timeLabel = $departsAt->format('H:i');

        $dateLabel = match (true) {
            $diffDays === 0  => "Aujourd'hui {$timeLabel}",
            $diffDays === 1  => "Demain {$timeLabel}",
            $diffDays === -1 => "Hier {$timeLabel}",
            $diffDays > 1   => $departsAt->translatedFormat('D. d/m') . " {$timeLabel}",
            default          => $departsAt->format('d/m') . " {$timeLabel}",
        };

        return [
            'uuid'                 => $trip->uuid,
            'route'                => $trip->departure_city . ' → ' . $trip->arrival_city,
            'status'               => $trip->status,
            'status_label'         => $statusLabels[$trip->status] ?? $trip->status,
            'departure_time_label' => $dateLabel,
            'available_seats'      => $trip->available_seats ?? 0,
        ];
    }
}
