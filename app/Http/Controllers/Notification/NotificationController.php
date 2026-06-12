<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Gestion des notifications in-app stockées en base de données.
 * L'app mobile utilise ces endpoints pour afficher la cloche et l'historique.
 */
class NotificationController extends Controller
{
    #[OA\Get(
        path: '/api/notifications',
        operationId: 'notificationsIndex',
        summary: 'Mes notifications (toutes)',
        description: 'Retourne toutes les notifications de l\'utilisateur connecté, triées de la plus récente. Les non lues apparaissent en premier.',
        tags: ['🔔 Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'unread_only', in: 'query', required: false,
                description: 'Si "1", retourne uniquement les notifications non lues.',
                schema: new OA\Schema(type: 'string', enum: ['0', '1'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notifications récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success',       type: 'boolean', example: true),
                        new OA\Property(property: 'message',       type: 'string'),
                        new OA\Property(property: 'unread_count',  type: 'integer', example: 3),
                        new OA\Property(property: 'body',          type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $request->boolean('unread_only')
            ? $user->unreadNotifications()
            : $user->notifications();

        $notifications = $query->latest()->take(100)->get()->map(fn ($n) => [
            'id'         => $n->id,
            'type'       => $n->data['type']  ?? null,
            'title'      => $n->data['title'] ?? null,
            'body'       => $n->data['body']  ?? null,
            'data'       => $n->data,
            'read'       => ! is_null($n->read_at),
            'created_at' => $n->created_at,
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Notifications récupérées.',
            'unread_count' => $user->unreadNotifications()->count(),
            'body'         => $notifications,
        ]);
    }

    #[OA\Post(
        path: '/api/notifications/{id}/read',
        operationId: 'notificationMarkRead',
        summary: 'Marquer une notification comme lue',
        tags: ['🔔 Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Marquée comme lue'),
            new OA\Response(response: 404, description: 'Notification introuvable'),
        ]
    )]
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json(['success' => false, 'message' => 'Notification introuvable.'], 404);
        }

        $notification->markAsRead();

        return response()->json(['success' => true, 'message' => 'Notification marquée comme lue.']);
    }

    #[OA\Post(
        path: '/api/notifications/read-all',
        operationId: 'notificationMarkAllRead',
        summary: 'Tout marquer comme lu',
        description: 'Marque toutes les notifications non lues comme lues d\'un coup.',
        tags: ['🔔 Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Toutes marquées comme lues'),
        ]
    )]
    public function markAllRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "{$count} notification(s) marquée(s) comme lues.",
        ]);
    }

    #[OA\Delete(
        path: '/api/notifications/{id}',
        operationId: 'notificationDelete',
        summary: 'Supprimer une notification',
        tags: ['🔔 Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Supprimée'),
            new OA\Response(response: 404, description: 'Introuvable'),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json(['success' => false, 'message' => 'Notification introuvable.'], 404);
        }

        $notification->delete();

        return response()->json(['success' => true, 'message' => 'Notification supprimée.']);
    }
}
