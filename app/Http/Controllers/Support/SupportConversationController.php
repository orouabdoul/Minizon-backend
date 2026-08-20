<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Ouvre ou retrouve la conversation support (user ↔ Admin Minizon).
 * Utilisé par driver et passager.
 */
class SupportConversationController extends Controller
{
    #[OA\Post(
        path: '/api/{role}/support/conversation',
        operationId: 'startSupportConversation',
        summary: 'Démarrer ou retrouver la conversation support avec Admin Minizon',
        description: "Crée ou retrouve la conversation directe entre l'utilisateur authentifié et l'administrateur Minizon. La conversation apparaîtra dans la boîte de réception de l'utilisateur avec `is_admin_conversation: true`.",
        tags: ['💬 Support'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['driver', 'passenger'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Conversation support prête',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
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
            new OA\Response(response: 503, description: 'Aucun administrateur disponible'),
        ]
    )]
    public function start(Request $request): JsonResponse
    {
        $user  = $request->user();
        $admin = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->first();

        if (! $admin) {
            return $this->apiResponse(false, 'Service support temporairement indisponible.', [], 503);
        }

        $conversation = Conversation::where('type', 'support')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $admin->id))
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'type'       => 'support',
                'trip_id'    => null,
                'booking_id' => null,
            ]);
            $conversation->participants()->attach([$user->id, $admin->id]);
        }

        return $this->apiResponse(true, 'Conversation support prête.', [
            'conversation_uuid' => $conversation->uuid,
        ]);
    }
}
