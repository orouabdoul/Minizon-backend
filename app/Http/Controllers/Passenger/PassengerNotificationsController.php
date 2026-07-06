<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

/**
 * Page "Notifications" — boîte de notifications typées du passager.
 *
 * Filtre par catégorie (all / unread / reservations / trips / payments)
 * et retourne des objets pré-formatés pour AppNotification Flutter.
 *
 * Les actions de marquage global restent compatibles avec NotificationController :
 *   POST /api/notifications/read-all
 *   POST /api/notifications/{id}/read
 * mais des alias passenger/ sont également exposés pour la cohérence de préfixe.
 */
class PassengerNotificationsController extends Controller
{
    // ── Catégories de filtre Flutter ──────────────────────────────────────────
    private const CATEGORIES = [
        ['key' => 'all',          'label' => 'Toutes'],
        ['key' => 'unread',       'label' => 'Non lues'],
        ['key' => 'reservations', 'label' => 'Réservations'],
        ['key' => 'trips',        'label' => 'Trajets'],
        ['key' => 'payments',     'label' => 'Paiements'],
    ];

    // ── Types par catégorie ───────────────────────────────────────────────────
    private const CATEGORY_MAP = [
        'reservations' => [
            'booking_request', 'booking_accepted', 'booking_rejected', 'booking_cancelled',
        ],
        'trips' => [
            'trip_started', 'trip_completed', 'trip_cancelled', 'trip_reminder', 'trip_updated',
        ],
        'payments' => [
            'payment_success', 'payment_failed', 'payment_pending', 'payment_refunded',
        ],
    ];

    // ── Icône Material + couleur ARGB par type ────────────────────────────────
    private const ICON_MAP = [
        'booking_request'   => ['icon' => 'schedule_rounded',             'bg' => 0xFF3B82F6],
        'booking_accepted'  => ['icon' => 'check_circle_rounded',         'bg' => 0xFF10B981],
        'booking_rejected'  => ['icon' => 'cancel_rounded',               'bg' => 0xFFEF4444],
        'booking_cancelled' => ['icon' => 'cancel_rounded',               'bg' => 0xFFEF4444],
        'trip_started'      => ['icon' => 'directions_car_rounded',       'bg' => 0xFF00A86B],
        'trip_completed'    => ['icon' => 'flag_rounded',                  'bg' => 0xFF10B981],
        'trip_cancelled'    => ['icon' => 'cancel_rounded',               'bg' => 0xFFEF4444],
        'trip_reminder'     => ['icon' => 'notifications_active_rounded', 'bg' => 0xFFF59E0B],
        'trip_updated'      => ['icon' => 'update_rounded',               'bg' => 0xFF3B82F6],
        'payment_success'   => ['icon' => 'payments_rounded',             'bg' => 0xFF10B981],
        'payment_failed'    => ['icon' => 'money_off_rounded',            'bg' => 0xFFEF4444],
        'payment_pending'   => ['icon' => 'schedule_rounded',             'bg' => 0xFFF59E0B],
        'payment_refunded'  => ['icon' => 'replay_rounded',               'bg' => 0xFF3B82F6],
        'review_received'   => ['icon' => 'star_rounded',                 'bg' => 0xFFF59E0B],
        'dispute_opened'    => ['icon' => 'report_rounded',               'bg' => 0xFFEF4444],
        'default'           => ['icon' => 'notifications_rounded',        'bg' => 0xFF6B7280],
    ];

    // ── Labels d'action par type ──────────────────────────────────────────────
    private const ACTION_LABELS = [
        'booking_accepted'  => 'Voir le trajet',
        'booking_rejected'  => 'Voir les détails',
        'booking_cancelled' => 'Rechercher un trajet',
        'trip_started'      => 'Suivre en direct',
        'trip_completed'    => 'Évaluer le conducteur',
        'trip_reminder'     => 'Voir le trajet',
        'payment_success'   => 'Voir le reçu',
        'payment_failed'    => 'Réessayer',
        'payment_refunded'  => 'Voir le remboursement',
        'review_received'   => 'Voir l\'avis',
        'dispute_opened'    => 'Répondre',
    ];

    // =========================================================================
    //  GET /api/passenger/notifications
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/notifications',
        operationId: 'passengerNotifications',
        summary: 'Notifications du passager — vue filtrée',
        description: "Retourne les catégories, les compteurs et la liste des notifications formatées pour `AppNotification` Flutter.\n\n**Catégories :**\n- `all` — toutes\n- `unread` — non lues uniquement\n- `reservations` — booking_accepted, booking_rejected, booking_cancelled\n- `trips` — trip_started, trip_completed, trip_cancelled, trip_reminder\n- `payments` — payment_success, payment_failed, payment_pending\n\n**Supprimer :** `DELETE /api/passenger/notifications/{id}`\n**Tout marquer :** `POST /api/passenger/notifications/read-all`",
        tags: ['👤 Passenger — Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'filter', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['all', 'unread', 'reservations', 'trips', 'payments'], default: 'all')
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
                                new OA\Property(property: 'unread_count',  type: 'integer', example: 2),
                                new OA\Property(
                                    property: 'categories',
                                    type: 'array',
                                    description: 'Chips de filtre à afficher',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',   type: 'string', example: 'all'),
                                            new OA\Property(property: 'label', type: 'string', example: 'Toutes'),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'filter_counts', type: 'object', description: 'Compteurs par catégorie pour les badges'),
                                new OA\Property(
                                    property: 'notifications',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/PassengerNotificationItem')
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $filter  = $request->query('filter', 'all');
        $perPage = min((int) $request->input('per_page', 30), 100);

        $allNotifs = $user->notifications()->latest()->take(200)->get();

        $filtered = match ($filter) {
            'unread'       => $allNotifs->whereNull('read_at'),
            'reservations' => $allNotifs->filter(fn ($n) => $this->inCategory('reservations', $n->data['type'] ?? '')),
            'trips'        => $allNotifs->filter(fn ($n) => $this->inCategory('trips', $n->data['type'] ?? '')),
            'payments'     => $allNotifs->filter(fn ($n) => $this->inCategory('payments', $n->data['type'] ?? '')),
            default        => $allNotifs,
        };

        $notifications = $filtered
            ->take($perPage)
            ->map(fn ($n) => $this->formatNotification($n))
            ->values();

        $filterCounts = [
            'all'          => $allNotifs->count(),
            'unread'       => $allNotifs->whereNull('read_at')->count(),
            'reservations' => $allNotifs->filter(fn ($n) => $this->inCategory('reservations', $n->data['type'] ?? ''))->count(),
            'trips'        => $allNotifs->filter(fn ($n) => $this->inCategory('trips', $n->data['type'] ?? ''))->count(),
            'payments'     => $allNotifs->filter(fn ($n) => $this->inCategory('payments', $n->data['type'] ?? ''))->count(),
        ];

        return $this->apiResponse(true, 'Notifications.', [
            'unread_count'  => $user->unreadNotifications()->count(),
            'categories'    => self::CATEGORIES,
            'filter_counts' => $filterCounts,
            'notifications' => $notifications,
        ]);
    }

    // =========================================================================
    //  POST /api/passenger/notifications/read-all
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/notifications/read-all',
        operationId: 'passengerNotificationsReadAll',
        summary: 'Tout marquer comme lu',
        tags: ['👤 Passenger — Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Toutes marquées comme lues'),
        ]
    )]
    public function markAllRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->apiResponse(true, "{$count} notification(s) marquée(s) comme lues.", [
            'marked_read' => $count,
        ]);
    }

    // =========================================================================
    //  POST /api/passenger/notifications/{id}/read
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/notifications/{id}/read',
        operationId: 'passengerNotificationRead',
        summary: 'Marquer une notification comme lue',
        tags: ['👤 Passenger — Notifications'],
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
    //  DELETE /api/passenger/notifications/{id}
    // =========================================================================

    #[OA\Delete(
        path: '/api/passenger/notifications/{id}',
        operationId: 'passengerNotificationDelete',
        summary: 'Supprimer une notification (swipe-to-dismiss)',
        description: 'Supprime définitivement une notification de l\'utilisateur. Appelé par le widget Dismissible lors du swipe.',
        tags: ['👤 Passenger — Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification supprimée'),
            new OA\Response(response: 404, description: 'Introuvable'),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return $this->apiResponse(false, 'Notification introuvable.', [], 404);
        }

        $notification->delete();

        return $this->apiResponse(true, 'Notification supprimée.');
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'PassengerNotificationItem',
        description: 'Notification formatée pour AppNotification Flutter',
        properties: [
            new OA\Property(property: 'id',                    type: 'string',  format: 'uuid'),
            new OA\Property(property: 'type',                  type: 'string',  example: 'booking_accepted'),
            new OA\Property(property: 'category',              type: 'string',  enum: ['reservations', 'trips', 'payments', 'other']),
            new OA\Property(property: 'title',                 type: 'string',  example: 'Réservation acceptée'),
            new OA\Property(property: 'body',                  type: 'string',  example: 'Moussa Alabi a accepté votre demande de réservation.'),
            new OA\Property(property: 'icon_name',             type: 'string',  example: 'check_circle_rounded'),
            new OA\Property(property: 'icon_background_color', type: 'integer', example: 4279520129, description: 'Couleur ARGB 32 bits — Color(iconBackgroundColor)'),
            new OA\Property(property: 'is_read',               type: 'boolean', example: false),
            new OA\Property(property: 'time',                  type: 'string',  example: 'il y a 2h'),
            new OA\Property(property: 'action_label',          type: 'string',  nullable: true, example: 'Voir le trajet'),
            new OA\Property(
                property: 'action_data',
                nullable: true,
                type: 'object',
                properties: [
                    new OA\Property(property: 'trip_uuid',    type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'booking_uuid', type: 'string', format: 'uuid', nullable: true),
                ]
            ),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatNotification(DatabaseNotification $n): array
    {
        $type = $n->data['type'] ?? 'default';
        $icon = self::ICON_MAP[$type] ?? self::ICON_MAP['default'];

        return [
            'id'                    => $n->id,
            'type'                  => $type,
            'category'              => $this->categoryOf($type),
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
