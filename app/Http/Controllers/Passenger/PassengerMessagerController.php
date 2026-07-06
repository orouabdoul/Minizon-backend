<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Page "Messagerie" — boîte de réception du passager.
 *
 * Retourne les threads formatés en MessengerThread Flutter.
 * Même format que DriverMessagerController ; le rôle de l'interlocuteur
 * est déterminé dynamiquement (conducteur si autre participant = trip.user_id).
 *
 * Ouvrir une conversation : POST /api/bookings/{uuid}/conversation (ChatController)
 * Envoyer un message      : POST /api/conversations/{uuid}/messages (ChatController)
 */
class PassengerMessagerController extends Controller
{
    private const FILTERS = [
        ['key' => 'all',       'label' => 'Tous'],
        ['key' => 'unread',    'label' => 'Non lus'],
        ['key' => 'active',    'label' => 'En cours'],
        ['key' => 'completed', 'label' => 'Terminés'],
    ];

    private const COLOR_GREEN_BG    = 0x1A10B981;
    private const COLOR_GREEN_TEXT  = 0xFF10B981;
    private const COLOR_BLUE_BG     = 0x1A3B82F6;
    private const COLOR_BLUE_TEXT   = 0xFF3B82F6;
    private const COLOR_GRAY_BG     = 0x1A6B7280;
    private const COLOR_GRAY_TEXT   = 0xFF6B7280;
    private const COLOR_RED_BG      = 0x1AEF4444;
    private const COLOR_RED_TEXT    = 0xFFEF4444;

    // =========================================================================
    //  GET /api/passenger/messager
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/messager',
        operationId: 'passengerMessager',
        summary: 'Boîte de réception du passager',
        description: "Retourne les filtres disponibles et la liste des conversations formatées en `MessengerThread` Flutter. Toutes les valeurs de couleur sont des entiers ARGB 32 bits passables directement à `Color(int)`. Le champ `filter` (all|unread|active|completed) est optionnel ; la recherche textuelle est gérée côté client.\n\nPour ouvrir un thread : **POST /api/bookings/{uuid}/conversation** puis naviguer vers la vue de chat.",
        tags: ['👤 Passenger — Messagerie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'filter', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['all', 'unread', 'active', 'completed'], default: 'all')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Threads de messagerie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Messagerie.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'filters',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',   type: 'string', example: 'all'),
                                            new OA\Property(property: 'label', type: 'string', example: 'Tous'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'threads',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/MessengerThread')
                                ),
                                new OA\Property(property: 'total_unread', type: 'integer', example: 2),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function inbox(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $filter = $request->query('filter', 'all');

        $query = Conversation::with([
            'participants.profile',
            'lastMessage.sender',
            'booking',
            'trip',
        ])
        ->whereHas('participants', fn ($q) => $q->where('users.id', $userId))
        ->withCount([
            'messages as unread_count' => fn ($q) => $q
                ->where('sender_id', '!=', $userId)
                ->whereNull('read_at'),
        ])
        ->orderByDesc('updated_at');

        match ($filter) {
            'unread'    => $query->having('unread_count', '>', 0),
            'active'    => $query->whereHas('trip', fn ($q) => $q->where('status', 'active')),
            'completed' => $query->whereHas('trip', fn ($q) => $q->where('status', 'completed')),
            default     => null,
        };

        $conversations = $query->get();

        $threads     = $conversations->map(fn ($conv) => $this->formatThread($conv, $userId))->values();
        $totalUnread = $conversations->sum('unread_count');

        return $this->apiResponse(true, 'Messagerie.', [
            'filters'      => self::FILTERS,
            'threads'      => $threads,
            'total_unread' => $totalUnread,
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatThread(Conversation $conv, int $userId): array
    {
        $unreadCount = (int) ($conv->unread_count ?? 0);

        $other   = $conv->participants->first(fn ($p) => $p->id !== $userId);
        $profile = $other?->profile;
        $name    = $profile
            ? trim("{$profile->first_name} {$profile->last_name}")
            : ($other?->phone ?? '—');

        $avatarUrl = '';
        if ($profile?->selfie_front) {
            $avatarUrl = Storage::disk('public')->url($profile->selfie_front);
        }

        $lastMsg = $conv->lastMessage;
        $preview = '—';
        if ($lastMsg) {
            $isMe    = $lastMsg->sender_id === $userId;
            $prefix  = $isMe ? 'Vous : ' : '';
            $preview = $lastMsg->attachment_path && ! $lastMsg->body
                ? $prefix . '📷 Image'
                : $prefix . ($lastMsg->body ?? '—');
        }

        [$statusLabel, $statusBg, $statusText] = $this->tripStatusColors($conv->trip?->status);

        $isDriver       = $conv->trip && $other?->id === $conv->trip->user_id;
        $roleLabel      = $isDriver ? 'Conducteur' : 'Passager';
        $roleLabelColor = $isDriver ? self::COLOR_GREEN_TEXT : self::COLOR_BLUE_TEXT;

        return [
            'uuid'                    => $conv->uuid,
            'booking_uuid'            => $conv->booking?->uuid,
            'trip_uuid'               => $conv->trip?->uuid,
            'avatar_url'              => $avatarUrl,
            'badge'                   => $unreadCount > 0 ? (string) $unreadCount : '',
            'badge_color'             => self::COLOR_RED_TEXT,
            'name'                    => $name,
            'time'                    => $this->relativeTime($conv->updated_at),
            'preview'                 => $preview,
            'status_background_color' => $statusBg,
            'status_label'            => $statusLabel,
            'status_label_color'      => $statusText,
            'is_unread'               => $unreadCount > 0,
            'role_label'              => $roleLabel,
            'role_label_color'        => $roleLabelColor,
        ];
    }

    private function tripStatusColors(?string $status): array
    {
        return match ($status) {
            'active'    => ['En cours',   self::COLOR_GREEN_BG, self::COLOR_GREEN_TEXT],
            'pending'   => ['En attente', self::COLOR_BLUE_BG,  self::COLOR_BLUE_TEXT],
            'completed' => ['Terminé',    self::COLOR_GRAY_BG,  self::COLOR_GRAY_TEXT],
            'cancelled' => ['Annulé',     self::COLOR_RED_BG,   self::COLOR_RED_TEXT],
            default     => ['—',          self::COLOR_GRAY_BG,  self::COLOR_GRAY_TEXT],
        };
    }

    private function relativeTime(?Carbon $date): string
    {
        if ($date === null) return '—';

        $now     = now()->setTimezone('Africa/Porto-Novo');
        $then    = $date->copy()->setTimezone('Africa/Porto-Novo');
        $diffMin = (int) $now->diffInMinutes($then);
        $diffH   = (int) $now->diffInHours($then);
        $diffD   = (int) $now->diffInDays($then);

        if ($diffMin < 1)  return "À l'instant";
        if ($diffMin < 60) return "il y a {$diffMin}min";
        if ($diffH < 24)   return "il y a {$diffH}h";
        if ($diffD < 7) {
            $days = ['Dim.', 'Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.'];
            return $days[$then->dayOfWeek];
        }
        return $then->format('d/m');
    }
}
