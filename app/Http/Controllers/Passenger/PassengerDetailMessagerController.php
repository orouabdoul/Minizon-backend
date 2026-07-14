<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Page "Conversation" — vue détaillée pour le passager.
 *
 * Ce contrôleur ne duplique PAS les actions de chat — elles restent dans ChatController :
 *   POST /api/conversations/{uuid}/messages → envoyer un message
 *   POST /api/conversations/{uuid}/read     → marquer comme lu
 *
 * Retourne en un seul appel le contexte du thread (interlocuteur, infos du trajet)
 * + les messages paginés formatés en DetailMessage Flutter
 * (kind: incoming | outgoing | reminder).
 */
class PassengerDetailMessagerController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/conversations/{uuid}/thread
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/conversations/{uuid}/thread',
        operationId: 'passengerConversationThread',
        summary: 'Contexte + messages d\'une conversation (passager)',
        description: "Retourne en un seul appel :\n- **`thread`** : contexte de l'interlocuteur et du trajet pour le header de la page\n- **`messages`** : liste paginée de `DetailMessage` avec `kind` pré-calculé (`incoming` / `outgoing` / `reminder`). Un `reminder` virtuel est injecté en tête si le trajet démarre dans les 24h.\n- Pagination curseur via `before_id` + `per_page`.\n\nEnvoyer un message : **POST /api/conversations/{uuid}/messages** (ChatController).\nMarquer comme lu : **POST /api/conversations/{uuid}/read** (ChatController).",
        tags: ['👤 Passenger — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid',      in: 'path',  required: true,  schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 50)),
            new OA\Parameter(name: 'before_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Cursor pour charger les messages plus anciens (scroll infini)'),
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
            'booking',
            'trip',
        ])->where('uuid', $uuid)->first();

        if (! $conversation) {
            return $this->apiResponse(false, 'Conversation introuvable.', [], 404);
        }

        if (! $conversation->hasParticipant($userId)) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        // ── Messages paginés ──────────────────────────────────────────────────
        $perPage  = min((int) $request->input('per_page', 20), 50);
        $beforeId = (int) $request->input('before_id', 0);

        $query = $conversation->messages()
            ->with('sender.profile')
            ->orderByDesc('id');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $rawMessages = $query->limit($perPage)->get()->reverse()->values();

        $hasMore = $conversation->messages()
            ->where('id', '<', $rawMessages->first()?->id ?? PHP_INT_MAX)
            ->exists();

        $messages = $rawMessages->map(fn (Message $m) => $this->formatDetailMessage($m, $userId))->values()->all();

        // ── Injection du rappel de départ (première page uniquement) ──────────
        if ($beforeId === 0) {
            $reminder = $this->buildReminderIfNeeded($conversation->trip);
            if ($reminder !== null) {
                array_unshift($messages, $reminder);
            }
        }

        // ── Contexte du thread ────────────────────────────────────────────────
        $other   = $conversation->participants->first(fn ($p) => $p->id !== $userId);
        $profile = $other?->profile;

        $avatarUrl = '';
        if ($profile?->selfie_front) {
            $avatarUrl = Storage::disk('public')->url($profile->selfie_front);
        }

        $threadContext = [
            'uuid'         => $conversation->uuid,
            'booking_uuid' => $conversation->booking?->uuid,
            'other_user'   => [
                'uuid'       => $other?->uuid,
                'name'       => $profile
                    ? trim("{$profile->first_name} {$profile->last_name}")
                    : ($other?->phone ?? '—'),
                'phone'      => $other?->phone,
                'avatar_url' => $avatarUrl,
                'is_online'  => false,
            ],
            'trip' => $this->formatTripContext($conversation->trip),
        ];

        return $this->apiResponse(true, 'Thread chargé.', [
            'thread'         => $threadContext,
            'messages'       => $messages,
            'has_more'       => $hasMore,
            'next_before_id' => $rawMessages->first()?->id,
            'send_endpoint'  => 'POST /api/conversations/' . $conversation->uuid . '/messages',
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatDetailMessage(Message $msg, int $myUserId): array
    {
        $tz         = 'Africa/Porto-Novo';
        $timeLabel  = $msg->created_at->setTimezone($tz)->format('H:i');

        $attachment = null;
        if ($msg->attachment_path) {
            $attachment = [
                'url'  => Storage::disk('public')->url($msg->attachment_path),
                'type' => $msg->attachment_type ?? 'image',
            ];
        }

        return [
            'id'           => $msg->id,
            'kind'         => $msg->sender_id === $myUserId ? 'outgoing' : 'incoming',
            'message'      => $msg->body,
            'time'         => $timeLabel,
            'raw_date'     => $msg->created_at->setTimezone($tz)->format('Y-m-d'),
            'title'        => null,
            'subtitle'     => null,
            'action_label' => null,
            'attachment'   => $attachment,
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

        $route       = $trip->departure_city . ' → ' . $trip->arrival_city;
        $timeStr     = $departsAt->setTimezone('Africa/Porto-Novo')->format('H:i');
        $hoursUntil  = (int) $now->diffInHours($departsAt, false);

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
