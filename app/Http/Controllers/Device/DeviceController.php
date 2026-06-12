<?php

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

/**
 * Enregistrement et suppression du token FCM de l'appareil mobile.
 * Appelé au démarrage de l'app et à la déconnexion.
 */
class DeviceController extends Controller
{
    #[OA\Post(
        path: '/api/device/token',
        operationId: 'deviceTokenRegister',
        summary: 'Enregistrer le token FCM de l\'appareil',
        description: <<<'DESC'
À appeler dès que l\'app mobile obtient ou renouvelle son token FCM.
Stocker le token côté serveur permet d\'envoyer des push notifications
même quand l\'app est en arrière-plan.

**Quand appeler :**
- Au démarrage de l\'app (onApplicationMount)
- Quand `onTokenRefresh` est déclenché par le SDK Firebase
DESC,
        tags: ['📱 Appareil & Token FCM'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['fcm_token'],
                properties: [
                    new OA\Property(
                        property: 'fcm_token',
                        type: 'string',
                        example: 'dIhSm8…qA3x',
                        description: 'Token FCM fourni par le SDK Firebase côté mobile'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token enregistré avec succès'),
            new OA\Response(response: 422, description: 'Token manquant ou invalide'),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'fcm_token' => ['required', 'string', 'min:20'],
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => 'Token FCM invalide.', 'body' => $v->errors()], 422);
        }

        $request->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['success' => true, 'message' => 'Token FCM enregistré.']);
    }

    #[OA\Delete(
        path: '/api/device/token',
        operationId: 'deviceTokenRevoke',
        summary: 'Supprimer le token FCM (déconnexion)',
        description: 'À appeler lors de la déconnexion de l\'utilisateur pour ne plus recevoir de notifications sur cet appareil.',
        tags: ['📱 Appareil & Token FCM'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Token supprimé'),
        ]
    )]
    public function revoke(Request $request): JsonResponse
    {
        $request->user()->update(['fcm_token' => null]);

        return response()->json(['success' => true, 'message' => 'Token FCM supprimé. Vous ne recevrez plus de notifications.']);
    }
}
