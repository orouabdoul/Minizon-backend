<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '🔔 Admin — Notifications', description: 'Supervision du centre de notifications back-office')]
class AdminNotificationController extends Controller
{
    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

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
        $diff = $d->diffInMinutes(now());
        if ($diff < 60)  return $diff . 'min';
        $diff = $d->diffInHours(now());
        if ($diff < 24)  return $diff . 'h';
        $diff = $d->diffInDays(now());
        if ($diff < 30)  return $diff . 'j';
        return $d->diffInMonths(now()) . 'mois';
    }

    private function refEntity(?string $refType, ?string $refId): ?string
    {
        if (! $refType || ! $refId) return null;
        return match ($refType) {
            'booking' => 'RES-' . strtoupper(substr($refId, 0, 8)),
            'payment' => 'PAY-' . strtoupper(substr($refId, 0, 8)),
            'dispute' => 'DIS-' . str_pad($refId, 8, '0', STR_PAD_LEFT),
            'trip'    => 'TRIP-' . strtoupper(substr($refId, 0, 8)),
            'user'    => 'USR-' . strtoupper(substr($refId, 0, 8)),
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

    private function format(AdminNotification $n): array
    {
        $user = $n->user;
        return [
            'id'         => $n->uuid,
            'notifId'    => 'NOTIF-' . strtoupper(substr($n->uuid, 0, 8)),
            'type'       => $n->type,
            'priority'   => $this->priorityLabel($n->priority),
            'status'     => $this->statusLabel($n->status),
            'title'      => $n->title,
            'description'=> $n->description,
            'date'       => $n->created_at->format('d/m/Y'),
            'time'       => $n->created_at->format('H:i'),
            'createdAgo' => $this->createdAgo($n->created_at),
            'dateGroup'  => $this->dateGroup($n->created_at),
            'refEntity'  => $this->refEntity($n->ref_type, $n->ref_id),
            'refLabel'   => $this->refLabel($n->ref_type),
            'userName'   => $user?->name,
            'userAvatar' => $user?->profile_photo_url ?? null,
        ];
    }

    // -----------------------------------------------------------------------
    // ENDPOINTS
    // -----------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/notifications/metrics',
        summary: 'Compteurs pour les onglets du centre de notifications',
        security: [['sanctum' => []]],
        tags: ['🔔 Admin — Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Compteurs par onglet',
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
    public function metrics(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'all'    => AdminNotification::count(),
            'unread' => AdminNotification::where('status', 'unread')->count(),
            'urgent' => AdminNotification::where('priority', 'urgent')->count(),
            'system' => AdminNotification::where('type', 'system')->count(),
        ]);
    }

    #[OA\Get(
        path: '/api/admin/notifications',
        summary: 'Liste paginée des notifications admin avec filtres',
        security: [['sanctum' => []]],
        tags: ['🔔 Admin — Notifications'],
        parameters: [
            new OA\Parameter(name: 'tab',    in: 'query', schema: new OA\Schema(type: 'string', enum: ['all', 'unread', 'urgent', 'system'])),
            new OA\Parameter(name: 'type',   in: 'query', schema: new OA\Schema(type: 'string', enum: ['system', 'user', 'payment', 'dispute', 'driver'])),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des notifications admin'),
        ]
    )]
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = AdminNotification::with('user')->latest();

        // Filtres onglets
        match ($request->input('tab')) {
            'unread' => $q->where('status', 'unread'),
            'urgent' => $q->where('priority', 'urgent'),
            'system' => $q->where('type', 'system'),
            default  => null,
        };

        // Filtre type
        if ($type = $request->input('type')) {
            $q->where('type', $type);
        }

        // Recherche fulltext sur titre + description
        if ($search = $request->input('search')) {
            $q->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $paginated = $q->paginate($perPage);

        return response()->json([
            'data'         => collect($paginated->items())->map(fn ($n) => $this->format($n)),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
        ]);
    }

    #[OA\Post(
        path: '/api/admin/notifications/{uuid}/read',
        summary: 'Marquer une notification comme lue',
        security: [['sanctum' => []]],
        tags: ['🔔 Admin — Notifications'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification marquée comme lue'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    public function markAsRead(string $uuid): \Illuminate\Http\JsonResponse
    {
        $notif = AdminNotification::where('uuid', $uuid)->firstOrFail();

        if ($notif->status === 'unread') {
            $notif->update([
                'status'  => 'read',
                'read_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Notification marquée comme lue.', 'data' => $this->format($notif->fresh())]);
    }

    #[OA\Post(
        path: '/api/admin/notifications/read-all',
        summary: 'Marquer toutes les notifications non lues comme lues',
        security: [['sanctum' => []]],
        tags: ['🔔 Admin — Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Toutes les notifications marquées comme lues'),
        ]
    )]
    public function markAllRead(): \Illuminate\Http\JsonResponse
    {
        $count = AdminNotification::where('status', 'unread')
            ->update(['status' => 'read', 'read_at' => now()]);

        return response()->json(['message' => "{$count} notification(s) marquée(s) comme lues."]);
    }

    #[OA\Post(
        path: '/api/admin/notifications/{uuid}/handle',
        summary: 'Marquer une notification comme traitée',
        security: [['sanctum' => []]],
        tags: ['🔔 Admin — Notifications'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification marquée comme traitée'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    public function markAsHandled(string $uuid): \Illuminate\Http\JsonResponse
    {
        $notif = AdminNotification::where('uuid', $uuid)->firstOrFail();

        $notif->update([
            'status'     => 'handled',
            'handled_at' => now(),
            'read_at'    => $notif->read_at ?? now(),
        ]);

        return response()->json(['message' => 'Notification marquée comme traitée.', 'data' => $this->format($notif->fresh())]);
    }

    #[OA\Delete(
        path: '/api/admin/notifications/{uuid}',
        summary: 'Supprimer une notification',
        security: [['sanctum' => []]],
        tags: ['🔔 Admin — Notifications'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification supprimée'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    public function destroy(string $uuid): \Illuminate\Http\JsonResponse
    {
        $notif = AdminNotification::where('uuid', $uuid)->firstOrFail();
        $notif->delete();

        return response()->json(['message' => 'Notification supprimée.']);
    }
}
