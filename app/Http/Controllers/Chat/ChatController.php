<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ChatController extends Controller
{
    // =========================================================================
    //  GET /api/conversations
    //  Liste des conversations de l'utilisateur connecté
    // =========================================================================

    #[OA\Get(
        path: '/api/conversations',
        operationId: 'conversationIndex',
        summary: 'Mes conversations',
        tags: ['💬 Chat'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des conversations',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Conversations.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'items',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'uuid',         type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'other_name',   type: 'string', example: 'Koffi Adjovi'),
                                            new OA\Property(property: 'last_message', type: 'string', nullable: true, example: 'Je suis en route'),
                                            new OA\Property(property: 'last_time',    type: 'string', nullable: true, example: '09:15'),
                                            new OA\Property(property: 'unread_count', type: 'integer', example: 2),
                                            new OA\Property(property: 'trip_route',   type: 'string', nullable: true, example: 'Cotonou → Parakou'),
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
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $conversations = Conversation::whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with(['participants.profile', 'lastMessage', 'trip'])
            ->orderByDesc('updated_at')
            ->get();

        $items = $conversations->map(fn ($c) => $this->formatConversation($c, $userId));

        return $this->apiResponse(true, 'Conversations.', ['items' => $items]);
    }

    // =========================================================================
    //  POST /api/bookings/{uuid}/conversation
    //  Ouvrir / récupérer la conversation d'une réservation
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/conversation',
        operationId: 'conversationGetOrCreate',
        summary: 'Ouvrir ou récupérer la conversation d\'une réservation',
        description: 'Crée la conversation conducteur–passager si elle n\'existe pas encore, ou retourne l\'UUID de la conversation existante.',
        tags: ['💬 Chat'],
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
            new OA\Response(response: 403, description: 'Accès refusé',          content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function getOrCreate(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with('trip')->where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $userId      = $request->user()->id;
        $driverId    = $booking->trip?->user_id;
        $passengerId = $booking->passenger_id;

        if ($userId !== $driverId && $userId !== $passengerId) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversation = Conversation::firstOrCreate(
            ['booking_id' => $booking->id],
            ['trip_id'    => $booking->trip_id]
        );

        // Attacher les deux participants si manquants
        $existing = $conversation->participants()->pluck('user_id')->toArray();
        $toAttach = array_diff(array_filter([$driverId, $passengerId]), $existing);
        if (! empty($toAttach)) {
            $conversation->participants()->attach($toAttach);
        }

        return $this->apiResponse(true, 'Conversation prête.', [
            'conversation_uuid' => $conversation->uuid,
        ]);
    }

    // =========================================================================
    //  GET /api/conversations/{uuid}/messages
    //  Messages paginés d'une conversation (scroll infini)
    // =========================================================================

    #[OA\Get(
        path: '/api/conversations/{uuid}/messages',
        operationId: 'conversationMessages',
        summary: 'Messages paginés d\'une conversation',
        tags: ['💬 Chat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid',      in: 'path',  required: true,  schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 50)),
            new OA\Parameter(name: 'before_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Curseur pour charger les messages plus anciens'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Messages'),
            new OA\Response(response: 403, description: 'Accès refusé',           content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function messages(Request $request, string $uuid): JsonResponse
    {
        $userId       = $request->user()->id;
        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        if (! $conversation->hasParticipant($userId)) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $perPage  = min((int) $request->input('per_page', 20), 50);
        $beforeId = (int) $request->input('before_id', 0);

        $query = $conversation->messages()->with('sender.profile')->orderByDesc('id');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $raw     = $query->limit($perPage)->get()->reverse()->values();
        $hasMore = $conversation->messages()
            ->where('id', '<', $raw->first()?->id ?? PHP_INT_MAX)
            ->exists();

        return $this->apiResponse(true, 'Messages.', [
            'items'          => $raw->map(fn ($m) => $this->formatMessage($m, $userId))->values(),
            'has_more'       => $hasMore,
            'next_before_id' => $raw->first()?->id,
        ]);
    }

    // =========================================================================
    //  POST /api/conversations/{uuid}/messages
    //  Envoyer un message texte ou un fichier (image / document)
    // =========================================================================

    #[OA\Post(
        path: '/api/conversations/{uuid}/messages',
        operationId: 'conversationSend',
        summary: 'Envoyer un message (texte ou fichier)',
        description: "Envoie un message texte, un fichier (image ou document), ou les deux.\n\nFormats acceptés :\n- **Images** : jpeg, png, webp, gif (max 5 Mo)\n- **Documents** : pdf, doc, docx (max 10 Mo)\n\n**Content-Type : `multipart/form-data`** — requis dès qu'un fichier est joint.",
        tags: ['💬 Chat'],
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
                            new OA\Property(
                                property: 'body',
                                type: 'string',
                                description: 'Texte du message (requis si pas de fichier)',
                                example: 'Je serai là dans 5 minutes.',
                                nullable: true,
                            ),
                            new OA\Property(
                                property: 'attachment',
                                type: 'string',
                                format: 'binary',
                                description: 'Fichier à joindre — image (jpeg/png/webp/gif, max 5 Mo) ou document (pdf/doc/docx, max 10 Mo)',
                                nullable: true,
                            ),
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
                                new OA\Property(property: 'id',   type: 'integer'),
                                new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'kind', type: 'string', enum: ['outgoing']),
                                new OA\Property(property: 'body', type: 'string', nullable: true),
                                new OA\Property(property: 'time', type: 'string', example: '09:15'),
                                new OA\Property(
                                    property: 'attachment',
                                    nullable: true,
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'url',  type: 'string', example: 'https://…/chat/conv-uuid/file.jpg'),
                                        new OA\Property(property: 'type', type: 'string', enum: ['image', 'document']),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé',            content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Message vide ou fichier invalide', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function send(Request $request, string $uuid): JsonResponse
    {
        $userId       = $request->user()->id;
        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        if (! $conversation->hasParticipant($userId)) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $validated = $request->validate([
            'body'       => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpeg,png,webp,gif,pdf,doc,docx'],
        ]);

        $hasText = ! empty(trim($validated['body'] ?? ''));
        $hasFile = $request->hasFile('attachment');

        if (! $hasText && ! $hasFile) {
            return $this->apiResponse(false, 'Le message ne peut pas être vide.', [], 422);
        }

        $attachmentPath = null;
        $attachmentType = null;

        if ($hasFile) {
            $file           = $request->file('attachment');
            $mime           = $file->getMimeType() ?? '';
            $attachmentType = str_starts_with($mime, 'image/') ? 'image' : 'document';
            $ext            = $file->getClientOriginalExtension();
            $filename       = Str::uuid() . '.' . $ext;
            $attachmentPath = $file->storeAs('chat/' . $conversation->uuid, $filename, 'public');
        }

        $msg = DB::transaction(function () use ($conversation, $userId, $validated, $attachmentPath, $attachmentType, $hasText) {
            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $userId,
                'body'            => $hasText ? trim($validated['body']) : null,
                'attachment_path' => $attachmentPath,
                'attachment_type' => $attachmentType,
            ]);

            $conversation->touch();

            return $msg;
        });

        return $this->apiResponse(true, 'Message envoyé.', $this->formatMessage($msg, $userId), 201);
    }

    // =========================================================================
    //  POST /api/conversations/{uuid}/read
    //  Marquer tous les messages reçus comme lus
    // =========================================================================

    #[OA\Post(
        path: '/api/conversations/{uuid}/read',
        operationId: 'conversationMarkRead',
        summary: 'Marquer les messages comme lus',
        tags: ['💬 Chat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Messages marqués comme lus'),
            new OA\Response(response: 404, description: 'Conversation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function markRead(Request $request, string $uuid): JsonResponse
    {
        $userId       = $request->user()->id;
        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation || ! $conversation->hasParticipant($userId)) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->apiResponse(true, 'Messages marqués comme lus.');
    }

    // =========================================================================
    //  PATCH /api/messages/{uuid}
    //  Modifier le texte d'un message (expéditeur uniquement)
    // =========================================================================

    #[OA\Patch(
        path: '/api/messages/{uuid}',
        operationId: 'messageEdit',
        summary: 'Modifier un message',
        description: "Modifie le texte d'un message. Seul l'expéditeur peut modifier son propre message. Un message composé uniquement d'un fichier (sans texte) ne peut pas être édité.",
        tags: ['💬 Chat'],
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
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Message modifié.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid',       type: 'string', format: 'uuid'),
                                new OA\Property(property: 'body',       type: 'string'),
                                new OA\Property(property: 'edited_at',  type: 'string', example: '2026-07-12T10:30:00+01:00'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé — pas l\'expéditeur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Message introuvable',               content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Corps vide ou message sans texte',  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
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

        // Un message sans texte (fichier seul) ne peut pas être édité
        if ($message->body === null && $message->attachment_path) {
            return $this->apiResponse(false, 'Un message sans texte ne peut pas être modifié.', [], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $newBody = trim($validated['body']);

        if ($newBody === '') {
            return $this->apiResponse(false, 'Le message ne peut pas être vide.', [], 422);
        }

        $message->update(['body' => $newBody]);

        return $this->apiResponse(true, 'Message modifié.', [
            'uuid'      => $message->uuid,
            'body'      => $message->body,
            'edited_at' => $message->updated_at->toIso8601String(),
        ]);
    }

    // =========================================================================
    //  DELETE /api/messages/{uuid}
    //  Supprimer un message (expéditeur uniquement)
    // =========================================================================

    #[OA\Delete(
        path: '/api/messages/{uuid}',
        operationId: 'messageDelete',
        summary: 'Supprimer un message',
        description: 'Supprime définitivement un message. Seul l\'expéditeur peut supprimer son propre message. Si un fichier est attaché, il est également supprimé du stockage.',
        tags: ['💬 Chat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Message supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé — pas l\'expéditeur', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Message introuvable',               content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
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

        if ($message->attachment_path) {
            Storage::disk('public')->delete($message->attachment_path);
        }

        $message->delete();

        return $this->apiResponse(true, 'Message supprimé.');
    }

    // =========================================================================
    //  ADMIN — Modération
    // =========================================================================

    public function adminIndex(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversations = Conversation::with(['participants.profile', 'lastMessage', 'trip'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return $this->apiResponse(true, 'Conversations.', [
            'items' => collect($conversations->items())->map(
                fn ($c) => $this->formatConversation($c, 0)
            ),
            'total' => $conversations->total(),
            'page'  => $conversations->currentPage(),
        ]);
    }

    public function adminMessages(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        $messages = $conversation->messages()
            ->with('sender.profile')
            ->orderBy('id')
            ->get()
            ->map(fn ($m) => $this->formatMessage($m, 0));

        return $this->apiResponse(true, 'Messages.', ['items' => $messages]);
    }

    public function adminDeleteMessage(Request $request, string $uuid, int $id): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
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

        return $this->apiResponse(true, 'Message supprimé.');
    }

    public function adminDelete(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $conversation = Conversation::where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        // Supprimer les fichiers stockés
        $conversation->messages()
            ->whereNotNull('attachment_path')
            ->each(fn ($m) => Storage::disk('public')->delete($m->attachment_path));

        $conversation->delete();

        return $this->apiResponse(true, 'Conversation supprimée.');
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatMessage(Message $msg, int $myUserId): array
    {
        $tz         = 'Africa/Porto-Novo';
        $attachment = null;

        if ($msg->attachment_path) {
            $attachment = [
                'url'  => Storage::disk('public')->url($msg->attachment_path),
                'type' => $msg->attachment_type ?? 'image',
            ];
        }

        return [
            'id'         => $msg->id,
            'uuid'       => $msg->uuid,
            'kind'       => $myUserId > 0 && $msg->sender_id === $myUserId ? 'outgoing' : 'incoming',
            'body'       => $msg->body,
            'time'       => $msg->created_at->setTimezone($tz)->format('H:i'),
            'read_at'    => $msg->read_at?->toIso8601String(),
            'attachment' => $attachment,
        ];
    }

    private function formatConversation(Conversation $conv, int $myUserId): array
    {
        $other = $myUserId > 0
            ? $conv->participants->first(fn ($p) => $p->id !== $myUserId)
            : $conv->participants->first();

        $profile = $other?->profile;
        $last    = $conv->lastMessage;
        $tz      = 'Africa/Porto-Novo';

        $lastText = $last?->body
            ?? ($last?->attachment_path
                ? ($last->attachment_type === 'image' ? '📷 Photo' : '📄 Document')
                : null);

        return [
            'uuid'         => $conv->uuid,
            'other_name'   => $profile
                ? trim("{$profile->first_name} {$profile->last_name}")
                : ($other?->phone ?? '—'),
            'last_message' => $lastText,
            'last_time'    => $last?->created_at->setTimezone($tz)->format('H:i'),
            'unread_count' => $myUserId > 0 ? $conv->unreadCountFor($myUserId) : 0,
            'trip_route'   => $conv->trip
                ? ($conv->trip->departure_city . ' → ' . $conv->trip->arrival_city)
                : null,
        ];
    }
}
