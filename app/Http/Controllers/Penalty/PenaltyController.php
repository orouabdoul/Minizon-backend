<?php

namespace App\Http\Controllers\Penalty;

use App\Http\Controllers\Controller;
use App\Models\Penalty;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class PenaltyController extends Controller
{
    // =========================================================================
    //  ADMIN — Infliger une pénalité
    //  POST /api/admin/users/{uuid}/penalties
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/users/{uuid}/penalties',
        operationId: 'penaltyStore',
        summary: '[ADMIN] Infliger une pénalité',
        description: "Ajoute des points de pénalité et/ou une amende financière à un utilisateur.\n- À **10 points** cumulés, le compte est automatiquement suspendu temporairement.\n- L'amende financière est déduite du prochain retrait du conducteur.",
        tags: ['🚨 Pénalités'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de l\'utilisateur'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason', 'points_added'],
                properties: [
                    new OA\Property(property: 'reason',         type: 'string',  example: 'late_cancellation', description: 'Code raison (ex: late_cancellation, minor_dispute, no_show)'),
                    new OA\Property(property: 'points_added',   type: 'integer', minimum: 1, maximum: 10, example: 2, description: 'Points de pénalité à ajouter (1-10)'),
                    new OA\Property(property: 'financial_fine', type: 'integer', minimum: 0, example: 500, nullable: true, description: 'Amende en FCFA (optionnelle)'),
                    new OA\Property(property: 'note',           type: 'string',  nullable: true, example: 'Annulation tardive pour la 3e fois ce mois.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pénalité infligée'),
            new OA\Response(response: 403, description: 'Accès refusé',         content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Utilisateur introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès réservé aux administrateurs.', [], 403);
        }

        $user = User::where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'reason'         => ['required', 'string', 'max:100'],
            'points_added'   => ['required', 'integer', 'min:1', 'max:10'],
            'financial_fine' => ['nullable', 'integer', 'min:0'],
            'note'           => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        $penalty = Penalty::create([
            'user_id'        => $user->id,
            'reason'         => $request->reason,
            'points_added'   => $request->points_added,
            'financial_fine' => $request->input('financial_fine', 0),
        ]);

        // Ajouter les points au score cumulatif de l'utilisateur
        $newTotal = $user->increment('penalty_points', $request->points_added) && $user->fresh()->penalty_points;
        $user->refresh();

        // Suspension automatique si ≥ 10 points
        $suspended = false;
        if ($user->penalty_points >= 10 && ! $user->is_blocked) {
            $user->update([
                'is_blocked'    => true,
                'blocked_until' => now()->addDays(7),
            ]);
            $suspended = true;
        }

        // Notification FCM à l'utilisateur
        if ($user->fcm_token) {
            $msg = $suspended
                ? 'Votre compte a été suspendu 7 jours suite à l\'accumulation de pénalités.'
                : "Une pénalité de {$request->points_added} point(s) a été ajoutée à votre compte.";

            app(FcmService::class)->send(
                $user->fcm_token,
                'Pénalité reçue',
                $msg,
                ['type' => 'penalty', 'points' => (string) $request->points_added]
            );
        }

        return $this->apiResponse(true, 'Pénalité infligée.' . ($suspended ? ' Compte suspendu (≥ 10 pts).' : ''), [
            'penalty'         => $penalty,
            'penalty_points'  => $user->penalty_points,
            'account_suspended' => $suspended,
        ], 201);
    }

    // =========================================================================
    //  UTILISATEUR — Mes pénalités
    //  GET /api/penalties
    // =========================================================================

    #[OA\Get(
        path: '/api/penalties',
        operationId: 'penaltyIndex',
        summary: 'Mes pénalités',
        description: 'Retourne toutes les pénalités reçues par l\'utilisateur connecté et son score cumulatif actuel.',
        tags: ['🚨 Pénalités'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Pénalités récupérées'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $penalties = Penalty::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->apiResponse(true, 'Pénalités récupérées.', [
            'penalty_points_total' => $user->penalty_points,
            'suspension_threshold' => 10,
            'penalties'            => $penalties,
        ]);
    }

    // =========================================================================
    //  ADMIN — Toutes les pénalités
    //  GET /api/admin/penalties
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/penalties',
        operationId: 'adminPenaltyIndex',
        summary: '[ADMIN] Toutes les pénalités',
        description: 'Liste toutes les pénalités infligées sur la plateforme, paginées.',
        tags: ['🚨 Pénalités'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'user_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filtrer par UUID utilisateur'),
            new OA\Parameter(name: 'per_page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée des pénalités'),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function adminIndex(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès réservé aux administrateurs.', [], 403);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Penalty::with(['user.profile'])
            ->orderByDesc('created_at');

        if ($request->filled('user_uuid')) {
            $query->whereHas('user', fn ($q) => $q->where('uuid', $request->user_uuid));
        }

        return $this->apiResponse(true, 'Pénalités récupérées.', $query->paginate($perPage));
    }

    // =========================================================================
    //  ADMIN — Réinitialiser les points d'un utilisateur
    //  POST /api/admin/users/{uuid}/penalties/reset
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/users/{uuid}/penalties/reset',
        operationId: 'penaltyReset',
        summary: '[ADMIN] Réinitialiser les points de pénalité',
        description: 'Remet à zéro le compteur de points de pénalité et débloque le compte si suspendu.',
        tags: ['🚨 Pénalités'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Points réinitialisés'),
            new OA\Response(response: 403, description: 'Accès refusé',         content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Utilisateur introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function reset(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès réservé aux administrateurs.', [], 403);
        }

        $user = User::where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        $user->update([
            'penalty_points' => 0,
            'is_blocked'     => false,
            'blocked_until'  => null,
        ]);

        if ($user->fcm_token) {
            app(FcmService::class)->send(
                $user->fcm_token,
                'Compte réactivé',
                'Vos pénalités ont été effacées et votre compte est de nouveau actif.',
                ['type' => 'penalty_reset']
            );
        }

        return $this->apiResponse(true, 'Points réinitialisés. Compte débloqué.', ['penalty_points' => 0]);
    }
}
