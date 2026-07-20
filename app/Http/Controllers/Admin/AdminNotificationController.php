<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '🔔 Admin — Notifications', description: 'Centre de notifications back-office + push vers les utilisateurs')]
class AdminNotificationController extends Controller
{
    // =========================================================================
    //  GET /api/admin/notifications/metrics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/notifications/metrics',
        operationId: 'adminNotifMetrics',
        summary: 'KPIs + compteurs par onglet',
        description: 'Retourne les compteurs utilisés par les cartes KPI et les badges d\'onglets de la NotificationsScreen.',
        tags: ['🔔 Admin — Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Compteurs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'all',    type: 'integer', example: 45),
                        new OA\Property(property: 'unread', type: 'integer', example: 12),
                        new OA\Property(property: 'urgent', type: 'integer', example: 3),
                        new OA\Property(property: 'system', type: 'integer', example: 8),
                    ]
                )
            ),
        ]
    )]
    public function metrics(): JsonResponse
    {
        return response()->json([
            'all'    => AdminNotification::count(),
            'unread' => AdminNotification::where('status', 'unread')->count(),
            'urgent' => AdminNotification::where('priority', 'urgent')->count(),
            'system' => AdminNotification::where('type', 'system')->count(),
        ]);
    }

    // =========================================================================
    //  GET /api/admin/notifications
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/notifications',
        operationId: 'adminNotifIndex',
        summary: 'Liste filtrée des notifications admin',
        tags: ['🔔 Admin — Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'tab',      in: 'query', schema: new OA\Schema(type: 'string', enum: ['all', 'unread', 'urgent', 'system'], default: 'all')),
            new OA\Parameter(name: 'type',     in: 'query', schema: new OA\Schema(type: 'string', enum: ['system', 'user', 'payment', 'dispute', 'driver'])),
            new OA\Parameter(name: 'search',   in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des notifications'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $q = AdminNotification::with('user.profile')->latest();

        // Filtre onglet
        match ($request->input('tab')) {
            'unread' => $q->where('status', 'unread'),
            'urgent' => $q->where('priority', 'urgent'),
            'system' => $q->where('type', 'system'),
            default  => null,
        };

        // Filtre type
        if ($request->filled('type')) {
            $q->where('type', $request->input('type'));
        }

        // Recherche
        if ($request->filled('search')) {
            $s = $request->input('search');
            $q->where(function ($sub) use ($s) {
                $sub->where('title', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $perPage   = min((int) $request->input('per_page', 50), 200);
        $paginated = $q->paginate($perPage);

        return response()->json([
            'data'         => collect($paginated->items())->map(fn ($n) => $this->format($n)),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
        ]);
    }

    // =========================================================================
    //  POST /api/admin/notifications/{uuid}/read
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/notifications/{uuid}/read',
        operationId: 'adminNotifMarkRead',
        summary: 'Marquer une notification comme lue',
        tags: ['🔔 Admin — Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification marquée comme lue'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    public function markAsRead(string $uuid): JsonResponse
    {
        $notif = AdminNotification::where('uuid', $uuid)->firstOrFail();

        if ($notif->status === 'unread') {
            $notif->update(['status' => 'read', 'read_at' => now()]);
        }

        return response()->json(['message' => 'Notification marquée comme lue.', 'data' => $this->format($notif->fresh()->load('user.profile'))]);
    }

    // =========================================================================
    //  POST /api/admin/notifications/read-all
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/notifications/read-all',
        operationId: 'adminNotifMarkAllRead',
        summary: 'Marquer toutes les notifications non lues comme lues',
        tags: ['🔔 Admin — Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Toutes les notifications marquées comme lues'),
        ]
    )]
    public function markAllRead(): JsonResponse
    {
        $count = AdminNotification::where('status', 'unread')
            ->update(['status' => 'read', 'read_at' => now()]);

        return response()->json(['message' => "{$count} notification(s) marquée(s) comme lues."]);
    }

    // =========================================================================
    //  POST /api/admin/notifications/{uuid}/handle
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/notifications/{uuid}/handle',
        operationId: 'adminNotifHandle',
        summary: 'Marquer une notification comme traitée',
        tags: ['🔔 Admin — Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification marquée comme traitée'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    public function markAsHandled(string $uuid): JsonResponse
    {
        $notif = AdminNotification::where('uuid', $uuid)->firstOrFail();

        $notif->update([
            'status'     => 'handled',
            'handled_at' => now(),
            'read_at'    => $notif->read_at ?? now(),
        ]);

        return response()->json(['message' => 'Notification marquée comme traitée.', 'data' => $this->format($notif->fresh()->load('user.profile'))]);
    }

    // =========================================================================
    //  DELETE /api/admin/notifications/{uuid}
    // =========================================================================

    #[OA\Delete(
        path: '/api/admin/notifications/{uuid}',
        operationId: 'adminNotifDelete',
        summary: 'Supprimer une notification',
        tags: ['🔔 Admin — Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification supprimée'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        AdminNotification::where('uuid', $uuid)->firstOrFail()->delete();

        return response()->json(['message' => 'Notification supprimée.']);
    }

    // =========================================================================
    //  POST /api/admin/notifications/send
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/notifications/send',
        operationId: 'adminNotifSend',
        summary: 'Envoyer une notification push aux utilisateurs',
        description: 'Diffuse une notification FCM vers les appareils des utilisateurs ciblés (all / drivers / passengers). Requiert FCM_SERVER_KEY dans les variables d\'environnement.',
        tags: ['🔔 Admin — Notifications'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'body', 'target', 'type'],
                properties: [
                    new OA\Property(property: 'title',  type: 'string', maxLength: 65,  example: 'Maintenance planifiée'),
                    new OA\Property(property: 'body',   type: 'string', maxLength: 200, example: 'La plateforme sera indisponible de 02h00 à 04h00.'),
                    new OA\Property(property: 'target', type: 'string', enum: ['all', 'drivers', 'passengers'], example: 'all'),
                    new OA\Property(property: 'type',   type: 'string', enum: ['info', 'warning', 'promo', 'system'], example: 'info'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Push envoyé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success',    type: 'boolean', example: true),
                        new OA\Property(property: 'sent_count', type: 'integer', example: 42),
                        new OA\Property(property: 'target',     type: 'string',  example: 'drivers'),
                    ]
                )
            ),
        ]
    )]
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'  => 'required|string|max:65',
            'body'   => 'required|string|max:200',
            'target' => 'required|in:all,drivers,passengers',
            'type'   => 'required|in:info,warning,promo,system',
        ]);

        // Cibler les utilisateurs avec un token FCM actif
        $usersQuery = User::whereNotNull('fcm_token')
            ->where('notifications_enabled', true)
            ->where('is_blocked', false);

        match ($validated['target']) {
            'drivers'    => $usersQuery->whereHas('role', fn ($q) => $q->where('name', 'driver')),
            'passengers' => $usersQuery->whereHas('role', fn ($q) => $q->where('name', 'passenger')),
            default      => null,
        };

        $tokens    = $usersQuery->pluck('fcm_token')->filter()->unique()->values()->toArray();
        $sentCount = 0;
        $fcmKey    = env('FCM_SERVER_KEY');

        if ($fcmKey && ! empty($tokens)) {
            foreach (array_chunk($tokens, 1000) as $batch) {
                $res = Http::withHeaders([
                    'Authorization' => 'key=' . $fcmKey,
                    'Content-Type'  => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'registration_ids' => $batch,
                    'notification' => [
                        'title' => $validated['title'],
                        'body'  => $validated['body'],
                        'sound' => 'default',
                    ],
                    'data' => ['type' => $validated['type']],
                ]);

                if ($res->successful()) {
                    $sentCount += count($batch);
                }
            }
        } else {
            // FCM non configuré ou aucun token — on simule pour le dev
            $sentCount = count($tokens);
        }

        // Journal d'audit
        AuditLog::record(
            action:      'notification.broadcast',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'export_donnees',
            severity:    'info',
            description: "Push diffusé à {$sentCount} appareil(s) : « {$validated['title']} »",
            targetName:  $validated['target'],
            userAgent:   $request->userAgent(),
        );

        return response()->json([
            'success'    => true,
            'message'    => "Notification envoyée à {$sentCount} appareil(s).",
            'sent_count' => $sentCount,
            'target'     => $validated['target'],
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function format(AdminNotification $n): array
    {
        $profile = $n->user?->profile;

        $name = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
        if (empty($name)) {
            $name = $n->user?->phone ?? null;
        }

        $avatar = null;
        if ($name) {
            $avatar = $profile?->selfie_front
                ? asset('storage/' . $profile->selfie_front)
                : 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=00A86B&color=fff';
        }

        return [
            'id'          => $n->uuid,
            'notifId'     => 'NOTIF-' . strtoupper(substr($n->uuid, 0, 8)),
            'type'        => $n->type,
            'priority'    => $this->priorityLabel($n->priority),
            'status'      => $this->statusLabel($n->status),
            'title'       => $n->title,
            'description' => $n->description,
            'date'        => $n->created_at->format('d/m/Y'),
            'time'        => $n->created_at->format('H:i'),
            'createdAgo'  => $this->createdAgo($n->created_at),
            'dateGroup'   => $this->dateGroup($n->created_at),
            'refEntity'   => $this->refEntity($n->ref_type, $n->ref_id),
            'refLabel'    => $this->refLabel($n->ref_type),
            'userName'    => $name,
            'userAvatar'  => $avatar,
        ];
    }

    private function priorityLabel(string $p): string
    {
        return match ($p) {
            'urgent' => 'Urgente',
            'high'   => 'Haute',
            'normal' => 'Normale',
            default  => 'Basse',
        };
    }

    private function statusLabel(string $s): string
    {
        return match ($s) {
            'unread'  => 'Non lue',
            'read'    => 'Lue',
            'handled' => 'Traitée',
            default   => 'Non lue',
        };
    }

    private function dateGroup(\Carbon\Carbon $d): string
    {
        if ($d->isToday())     return "Aujourd'hui";
        if ($d->isYesterday()) return 'Hier';
        return 'Cette semaine';
    }

    private function createdAgo(\Carbon\Carbon $d): string
    {
        $diff = now()->diffInMinutes($d);
        if ($diff < 60)  return $diff . 'min';
        $diff = now()->diffInHours($d);
        if ($diff < 24)  return $diff . 'h';
        $diff = now()->diffInDays($d);
        if ($diff < 30)  return $diff . 'j';
        return now()->diffInMonths($d) . 'mois';
    }

    private function refEntity(?string $refType, ?string $refId): ?string
    {
        if (! $refType || ! $refId) return null;
        return match ($refType) {
            'booking' => 'RES-'  . strtoupper(substr($refId, 0, 8)),
            'payment' => 'PAY-'  . strtoupper(substr($refId, 0, 8)),
            'dispute' => 'DIS-'  . str_pad($refId, 8, '0', STR_PAD_LEFT),
            'trip'    => 'TRIP-' . strtoupper(substr($refId, 0, 8)),
            'user'    => 'USR-'  . strtoupper(substr($refId, 0, 8)),
            default   => $refId,
        };
    }

    private function refLabel(?string $refType): ?string
    {
        if (! $refType) return null;
        return match ($refType) {
            'booking' => 'Voir la réservation',
            'payment' => 'Voir le paiement',
            'dispute' => 'Voir le litige',
            'trip'    => 'Voir le trajet',
            'user'    => "Voir l'utilisateur",
            default   => 'Voir le détail',
        };
    }
}
