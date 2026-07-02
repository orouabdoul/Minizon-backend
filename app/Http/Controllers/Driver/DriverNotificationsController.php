<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

/**
 * Page "Notifications" — boîte de notifications typées du conducteur.
 *
 * Filtre par catégorie (all / unread / reservations / payments / trips)
 * et retourne des objets pré-formatés pour DriverNotificationModel Flutter.
 *
 * Les actions de marquage restent compatibles avec NotificationController :
 *   POST /api/notifications/read-all
 *   POST /api/notifications/{id}/read
 * mais des alias driver/ sont également exposés pour la cohérence de préfixe.
 */
class DriverNotificationsController extends Controller
{
    // ── Correspondance type → catégorie de filtre Flutter ─────────────────────
    private const CATEGORY_MAP = [
        'reservations' => [
            'booking_request', 'booking_accepted', 'booking_rejected',
            'booking_cancelled', 'new_booking',
        ],
        'payments' => [
            'payment_received', 'payment_pending', 'payment_failed',
            'payment_released', 'withdrawal_processed', 'withdrawal_requested',
        ],
        'trips' => [
            'trip_started', 'trip_completed', 'trip_cancelled',
            'trip_reminder', 'trip_updated',
        ],
    ];

    // ── Correspondance type → icône Material (name) + couleur ARGB ────────────
    private const ICON_MAP = [
        'booking_request'       => ['icon' => 'person_add_rounded',          'bg' => 0xFF3B82F6], // bleu
        'booking_accepted'      => ['icon' => 'check_circle_rounded',        'bg' => 0xFF10B981], // vert
        'booking_rejected'      => ['icon' => 'cancel_rounded',              'bg' => 0xFFEF4444], // rouge
        'booking_cancelled'     => ['icon' => 'cancel_rounded',              'bg' => 0xFFEF4444],
        'new_booking'           => ['icon' => 'person_add_rounded',          'bg' => 0xFF3B82F6],
        'payment_received'      => ['icon' => 'payments_rounded',            'bg' => 0xFF10B981],
        'payment_pending'       => ['icon' => 'schedule_rounded',            'bg' => 0xFFF59E0B], // orange
        'payment_failed'        => ['icon' => 'money_off_rounded',           'bg' => 0xFFEF4444],
        'payment_released'      => ['icon' => 'account_balance_wallet_outlined', 'bg' => 0xFF10B981],
        'withdrawal_processed'  => ['icon' => 'savings_rounded',             'bg' => 0xFF10B981],
        'withdrawal_requested'  => ['icon' => 'schedule_rounded',            'bg' => 0xFFF59E0B],
        'trip_started'          => ['icon' => 'directions_car_rounded',      'bg' => 0xFF00A86B], // primary
        'trip_completed'        => ['icon' => 'flag_rounded',                'bg' => 0xFF10B981],
        'trip_cancelled'        => ['icon' => 'cancel_rounded',              'bg' => 0xFFEF4444],
        'trip_reminder'         => ['icon' => 'notifications_active_rounded','bg' => 0xFFF59E0B],
        'trip_updated'          => ['icon' => 'update_rounded',              'bg' => 0xFF3B82F6],
        'review_received'       => ['icon' => 'star_rounded',                'bg' => 0xFFF59E0B],
        'dispute_opened'        => ['icon' => 'report_rounded',              'bg' => 0xFFEF4444],
        'default'               => ['icon' => 'notifications_rounded',       'bg' => 0xFF6B7280],
    ];

    // ── Labels d'action par type ───────────────────────────────────────────────
    private const ACTION_LABELS = [
        'booking_request'      => 'Voir la demande',
        'booking_accepted'     => 'Voir le trajet',
        'booking_cancelled'    => 'Voir les détails',
        'payment_received'     => 'Voir mes gains',
        'payment_released'     => 'Retirer mes fonds',
        'withdrawal_processed' => 'Voir mes retraits',
        'trip_completed'       => 'Voir le résumé',
        'trip_reminder'        => 'Voir le trajet',
        'review_received'      => 'Voir l\'avis',
        'dispute_opened'       => 'Répondre',
    ];

    // =========================================================================
    //  GET /api/driver/notifications
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/notifications',
        operationId: 'driverNotifications',
        summary: 'Notifications du conducteur — vue paginée et filtrée',
        description: "Retourne les notifications de l'utilisateur connecté, formatées pour `DriverNotificationModel` Flutter. Supporte le filtre par catégorie (`filter`) et la pagination curseur.\n\n**Catégories de filtre :**\n- `all` — toutes\n- `unread` — non lues uniquement\n- `reservations` — booking_request, booking_accepted, booking_rejected, booking_cancelled\n- `payments` — payment_received, payment_released, withdrawal_*\n- `trips` — trip_started, trip_completed, trip_cancelled, trip_reminder\n\n**Marquer comme lu :** `POST /api/notifications/{id}/read` ou `POST /api/driver/notifications/{id}/read`\n**Tout marquer :** `POST /api/driver/notifications/read-all`",
        tags: ['🔔 Driver — Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'filter', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['all', 'unread', 'reservations', 'payments', 'trips'], default: 'all')
            ),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 30, maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notifications',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Notifications.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'unread_count',     type: 'integer', example: 4),
                                new OA\Property(property: 'filter_counts',    type: 'object',  description: 'Nombre de notifications par catégorie (pour les badges des chips)'),
                                new OA\Property(
                                    property: 'notifications',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/DriverNotificationItem')
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
        $user    = $request->user();
        $filter  = $request->query('filter', 'all');
        $perPage = min((int) $request->input('per_page', 30), 100);

        // ── Récupération + filtrage par catégorie ──────────────────────────────
        $allNotifs = $user->notifications()->latest()->take(200)->get();

        $filtered = match ($filter) {
            'unread'       => $allNotifs->whereNull('read_at'),
            'reservations' => $allNotifs->filter(fn ($n) => $this->inCategory('reservations', $n->data['type'] ?? '')),
            'payments'     => $allNotifs->filter(fn ($n) => $this->inCategory('payments', $n->data['type'] ?? '')),
            'trips'        => $allNotifs->filter(fn ($n) => $this->inCategory('trips', $n->data['type'] ?? '')),
            default        => $allNotifs,
        };

        $notifications = $filtered
            ->take($perPage)
            ->map(fn ($n) => $this->formatNotification($n));

        // ── Compteurs pour les badges des filter chips ──────────────────────────
        $filterCounts = [
            'all'          => $allNotifs->count(),
            'unread'       => $allNotifs->whereNull('read_at')->count(),
            'reservations' => $allNotifs->filter(fn ($n) => $this->inCategory('reservations', $n->data['type'] ?? ''))->count(),
            'payments'     => $allNotifs->filter(fn ($n) => $this->inCategory('payments', $n->data['type'] ?? ''))->count(),
            'trips'        => $allNotifs->filter(fn ($n) => $this->inCategory('trips', $n->data['type'] ?? ''))->count(),
        ];

        return $this->apiResponse(true, 'Notifications.', [
            'unread_count'  => $user->unreadNotifications()->count(),
            'filter_counts' => $filterCounts,
            'notifications' => $notifications->values(),
        ]);
    }

    // =========================================================================
    //  POST /api/driver/notifications/read-all
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/notifications/read-all',
        operationId: 'driverNotificationsReadAll',
        summary: 'Tout marquer comme lu (alias driver)',
        tags: ['🔔 Driver — Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Toutes marquées comme lues'),
        ]
    )]
    public function markAllRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->apiResponse(true, "{$count} notification(s) marquée(s) comme lues.");
    }

    // =========================================================================
    //  POST /api/driver/notifications/{id}/read
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/notifications/{id}/read',
        operationId: 'driverNotificationRead',
        summary: 'Marquer une notification comme lue (alias driver)',
        tags: ['🔔 Driver — Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Marquée comme lue'),
            new OA\Response(response: 404, description: 'Introuvable'),
        ]
    )]
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return $this->apiResponse(false, 'Notification introuvable.', [], 404);
        }

        $notification->markAsRead();

        return $this->apiResponse(true, 'Notification marquée comme lue.');
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'DriverNotificationItem',
        description: 'Notification formatée pour DriverNotificationModel Flutter',
        properties: [
            new OA\Property(property: 'id',                    type: 'string',  format: 'uuid'),
            new OA\Property(property: 'type',                  type: 'string',  example: 'booking_request', description: 'Type technique — utilisé par Flutter pour mapper l\'icône et la navigation'),
            new OA\Property(property: 'category',              type: 'string',  enum: ['reservations', 'payments', 'trips', 'other']),
            new OA\Property(property: 'title',                 type: 'string',  example: 'Nouvelle demande de réservation'),
            new OA\Property(property: 'body',                  type: 'string',  example: 'Koffi Mensah souhaite réserver 1 place sur votre trajet Cotonou → Parakou.'),
            new OA\Property(property: 'icon_name',             type: 'string',  example: 'person_add_rounded', description: 'Nom du MaterialIcon Flutter — ex: Icons.person_add_rounded'),
            new OA\Property(property: 'icon_background_color', type: 'integer', example: 4281729782, description: 'Couleur ARGB 32 bits — Color(iconBackground)'),
            new OA\Property(property: 'is_read',               type: 'boolean', example: false),
            new OA\Property(property: 'time',                  type: 'string',  example: 'il y a 2h'),
            new OA\Property(property: 'action_label',          type: 'string',  nullable: true, example: 'Voir la demande'),
            new OA\Property(
                property: 'action_data',
                type: 'object',
                nullable: true,
                description: 'Données de navigation — contient les UUIDs nécessaires à la route Flutter',
                properties: [
                    new OA\Property(property: 'trip_uuid',    type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'booking_uuid', type: 'string', format: 'uuid', nullable: true),
                ]
            ),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS
    // =========================================================================

    private function formatNotification(\Illuminate\Notifications\DatabaseNotification $n): array
    {
        $type   = $n->data['type']   ?? 'default';
        $icon   = self::ICON_MAP[$type]    ?? self::ICON_MAP['default'];
        $cat    = $this->categoryOf($type);

        return [
            'id'                    => $n->id,
            'type'                  => $type,
            'category'              => $cat,
            'title'                 => $n->data['title'] ?? 'Notification',
            'body'                  => $n->data['body']  ?? '',
            'icon_name'             => $icon['icon'],
            'icon_background_color' => $icon['bg'],
            'is_read'               => ! is_null($n->read_at),
            'time'                  => $this->relativeTime($n->created_at),
            'action_label'          => self::ACTION_LABELS[$type] ?? null,
            'action_data'           => [
                'trip_uuid'    => $n->data['trip_uuid']    ?? null,
                'booking_uuid' => $n->data['booking_uuid'] ?? null,
            ],
        ];
    }

    private function inCategory(string $category, string $type): bool
    {
        return in_array($type, self::CATEGORY_MAP[$category] ?? [], true);
    }

    private function categoryOf(string $type): string
    {
        foreach (self::CATEGORY_MAP as $cat => $types) {
            if (in_array($type, $types, true)) return $cat;
        }
        return 'other';
    }

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
