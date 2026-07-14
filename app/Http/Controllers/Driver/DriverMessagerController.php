<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Page "Messagerie" — boîte de réception du conducteur.
 *
 * Retourne les threads formatés directement en MessengerThread Flutter :
 * couleurs ARGB prêtes pour Color(int), labels traduits, temps relatif.
 *
 * Ouvrir une conversation : POST /api/driver/bookings/{uuid}/conversation
 * Envoyer un message      : POST /api/driver/conversations/{uuid}/messages
 */
class DriverMessagerController extends Controller
{
    // ── Filtres disponibles ────────────────────────────────────────────────────
    private const FILTERS = [
        ['key' => 'all',       'label' => 'Tous'],
        ['key' => 'unread',    'label' => 'Non lus'],
        ['key' => 'active',    'label' => 'En cours'],
        ['key' => 'completed', 'label' => 'Terminés'],
    ];

    // ── Couleurs ARGB (décimales) — utilisées directement dans Color(int) Flutter ──
    private const COLOR_GREEN_BG   = 0x1A10B981; // vert 10 % opacité
    private const COLOR_GREEN_TEXT = 0xFF10B981;
    private const COLOR_BLUE_BG    = 0x1A3B82F6;
    private const COLOR_BLUE_TEXT  = 0xFF3B82F6;
    private const COLOR_GRAY_BG    = 0x1A6B7280;
    private const COLOR_GRAY_TEXT  = 0xFF6B7280;
    private const COLOR_RED_BG     = 0x1AEF4444;
    private const COLOR_RED_TEXT   = 0xFFEF4444;
    private const COLOR_ORANGE_BG  = 0x1AF59E0B;
    private const COLOR_ORANGE_TEXT = 0xFFF59E0B;
    private const COLOR_WHITE      = 0xFFFFFFFF;

    // =========================================================================
    //  GET /api/driver/messager
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/messager',
        operationId: 'driverMessager',
        summary: 'Boîte de réception du conducteur',
        description: "Retourne les filtres disponibles et la liste des conversations formatées en `MessengerThread` Flutter. Toutes les valeurs de couleur sont des entiers ARGB 32 bits passables directement à `Color(int)`. Le champ `filter` (all|unread|active|completed) est optionnel ; la recherche textuelle est gérée côté client.\n\nPour ouvrir un thread : **POST /api/bookings/{uuid}/conversation** puis naviguer vers la vue de chat.",
        tags: ['💬 Driver — Messagerie'],
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
                                    description: 'Chips de filtre à afficher dans la _FilterRow',
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
                                new OA\Property(property: 'total_unread', type: 'integer', example: 3),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function inbox(Request $request): JsonResponse
    {
        $user     = $request->user();
        $userId   = $user->id;
        $filter   = $request->query('filter', 'all');

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

        // ── Filtres ───────────────────────────────────────────────────────────
        match ($filter) {
            'unread' => $query->whereHas('messages', fn ($q) => $q
                ->where('sender_id', '!=', $userId)
                ->whereNull('read_at')
            ),
            'active'    => $query->whereHas('trip', fn ($q) => $q->where('status', 'active')),
            'completed' => $query->whereHas('trip', fn ($q) => $q->where('status', 'completed')),
            default     => null,
        };

        $conversations = $query->get();

        $threads     = $conversations->map(fn ($conv) => $this->formatThread($conv, $userId))->values();
        $totalUnread = $conversations->sum('unread_count');

        return $this->apiResponse(true, 'Messagerie.', [
            'filters'       => self::FILTERS,
            'threads'       => $threads,
            'total_unread'  => $totalUnread,
        ]);
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'MessengerThread',
        description: 'Thread de messagerie — format Flutter MessengerThread',
        properties: [
            new OA\Property(property: 'uuid',                    type: 'string',  format: 'uuid'),
            new OA\Property(property: 'booking_uuid',            type: 'string',  format: 'uuid', nullable: true),
            new OA\Property(property: 'trip_uuid',               type: 'string',  format: 'uuid', nullable: true),
            new OA\Property(property: 'avatar_url',              type: 'string',  description: 'Photo de profil ou selfie KYC. Chaîne vide si absent.'),
            new OA\Property(property: 'badge',                   type: 'string',  example: '3',   description: 'Compteur de messages non lus (chaîne vide si 0)'),
            new OA\Property(property: 'badge_color',             type: 'integer', example: 4293124164, description: 'ARGB int — Color(badgeColor)'),
            new OA\Property(property: 'name',                    type: 'string',  example: 'Koffi Mensah'),
            new OA\Property(property: 'time',                    type: 'string',  example: 'il y a 2h'),
            new OA\Property(property: 'preview',                 type: 'string',  example: 'Je suis en route !'),
            new OA\Property(property: 'status_background_color', type: 'integer', example: 436274561, description: 'ARGB int — Color(statusBackgroundColor)'),
            new OA\Property(property: 'status_label',            type: 'string',  example: 'En cours'),
            new OA\Property(property: 'status_label_color',      type: 'integer', example: 4279520129, description: 'ARGB int — Color(statusLabelColor)'),
            new OA\Property(property: 'is_unread',               type: 'boolean', example: true),
            new OA\Property(property: 'role_label',              type: 'string',  example: 'Passager'),
            new OA\Property(property: 'role_label_color',        type: 'integer', example: 4281729782, description: 'ARGB int — Color(roleLabelColor)'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatThread(Conversation $conv, int $userId): array
    {
        $unreadCount = (int) ($conv->unread_count ?? 0);

        // ── Interlocuteur ────────────────────────────────────────────────────
        $other   = $conv->participants->first(fn ($p) => $p->id !== $userId);
        $profile = $other?->profile;
        $name    = $profile
            ? trim("{$profile->first_name} {$profile->last_name}")
            : ($other?->phone ?? '—');

        // ── Avatar ───────────────────────────────────────────────────────────
        $avatarUrl = '';
        if ($profile?->selfie_front) {
            $avatarUrl = Storage::disk('public')->url($profile->selfie_front);
        }

        // ── Aperçu du dernier message ────────────────────────────────────────
        $lastMsg = $conv->lastMessage;
        $preview = '—';
        if ($lastMsg) {
            $isMe    = $lastMsg->sender_id === $userId;
            $prefix  = $isMe ? 'Vous : ' : '';
            $preview = $lastMsg->attachment_path && ! $lastMsg->body
                ? $prefix . ($lastMsg->attachment_type === 'document' ? '📄 Document' : '📷 Image')
                : $prefix . ($lastMsg->body ?? '—');
        }

        // ── Statut du trajet ─────────────────────────────────────────────────
        [$statusLabel, $statusBg, $statusText] = $this->tripStatusColors($conv->trip?->status);

        // ── Rôle de l'interlocuteur ──────────────────────────────────────────
        $isDriver      = $conv->trip && $other?->id === $conv->trip->user_id;
        $roleLabel     = $isDriver ? 'Conducteur' : 'Passager';
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

    /** Retourne [label, bg ARGB int, text ARGB int] pour un statut de trajet. */
    private function tripStatusColors(?string $status): array
    {
        return match ($status) {
            'active'    => ['En cours',    self::COLOR_GREEN_BG,   self::COLOR_GREEN_TEXT],
            'pending'   => ['En attente',  self::COLOR_BLUE_BG,    self::COLOR_BLUE_TEXT],
            'completed' => ['Terminé',     self::COLOR_GRAY_BG,    self::COLOR_GRAY_TEXT],
            'cancelled' => ['Annulé',      self::COLOR_RED_BG,     self::COLOR_RED_TEXT],
            default     => ['—',           self::COLOR_GRAY_BG,    self::COLOR_GRAY_TEXT],
        };
    }

    /**
     * Temps relatif en français à partir d'une date Carbon.
     *
     * < 1 min    → "À l'instant"
     * < 60 min   → "il y a Xmin"
     * < 24 h     → "il y a Xh"
     * < 7 jours  → nom du jour abrégé ("Lun.", "Mar.", …)
     * ≥ 7 jours  → "jj/mm"
     */
    private function relativeTime(?Carbon $date): string
    {
        if ($date === null) return '—';

        $now     = now()->setTimezone('Africa/Porto-Novo');
        $then    = $date->setTimezone('Africa/Porto-Novo');
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
