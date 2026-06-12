<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

/**
 * Gestion des avis laissés par les passagers à la fin d'un trajet.
 */
class ReviewController extends Controller
{
    // =========================================================================
    //  CONSULTATION PUBLIQUE
    // =========================================================================

    #[OA\Get(
        path: '/api/trips/{uuid}/reviews',
        operationId: 'tripReviews',
        summary: 'Avis d\'un trajet',
        description: 'Retourne tous les avis laissés sur un trajet terminé, avec la note moyenne.',
        tags: ['⭐ Avis Passagers'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID du trajet',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des avis avec note moyenne',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success',      type: 'boolean', example: true),
                        new OA\Property(property: 'message',      type: 'string',  example: 'Avis récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'average_rating', type: 'number', format: 'float', example: 4.5),
                                new OA\Property(property: 'total',          type: 'integer', example: 3),
                                new OA\Property(property: 'reviews',        type: 'array', items: new OA\Items(type: 'object')),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function tripReviews(string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        $reviews = Review::with(['reviewer.profile'])
            ->where('trip_id', $trip->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->apiResponse(true, 'Avis récupérés.', [
            'average_rating' => $reviews->avg('rating') ? round($reviews->avg('rating'), 1) : null,
            'total'          => $reviews->count(),
            'reviews'        => $reviews,
        ]);
    }

    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/drivers/{uuid}/reviews',
        operationId: 'driverReviews',
        summary: 'Avis d\'un conducteur',
        description: 'Retourne tous les avis reçus par un conducteur sur l\'ensemble de ses trajets, avec la note moyenne globale.',
        tags: ['⭐ Avis Passagers'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID du conducteur',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avis du conducteur avec note moyenne globale',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Avis du conducteur récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'average_rating', type: 'number', format: 'float', example: 4.2),
                                new OA\Property(property: 'total',          type: 'integer', example: 12),
                                new OA\Property(property: 'reviews',        type: 'array',  items: new OA\Items(type: 'object')),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Conducteur introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function driverReviews(string $uuid): JsonResponse
    {
        $driver = User::where('uuid', $uuid)->first();

        if (! $driver) {
            return $this->apiResponse(false, 'Conducteur introuvable.', [], 404);
        }

        $reviews = Review::with(['reviewer.profile', 'trip'])
            ->where('reviewee_id', $driver->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->apiResponse(true, 'Avis du conducteur récupérés.', [
            'average_rating' => $reviews->avg('rating') ? round($reviews->avg('rating'), 1) : null,
            'total'          => $reviews->count(),
            'reviews'        => $reviews,
        ]);
    }

    // =========================================================================
    //  SOUMISSION D'UN AVIS (passager authentifié)
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/reviews',
        operationId: 'reviewsStore',
        summary: 'Laisser un avis sur un trajet',
        description: 'Permet à un **passager authentifié** de noter et commenter un trajet **terminé** (`completed`). Un seul avis par passager par trajet.',
        tags: ['⭐ Avis Passagers'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID du trajet',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['rating'],
                properties: [
                    new OA\Property(property: 'rating',  type: 'integer', minimum: 1, maximum: 5, example: 5, description: 'Note de 1 (mauvais) à 5 (excellent)'),
                    new OA\Property(property: 'comment', type: 'string',  maxLength: 500, nullable: true, example: 'Conducteur ponctuel, trajet très agréable !'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Avis soumis avec succès'),
            new OA\Response(response: 403, description: 'Réservé aux passagers / trajet non terminé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable',                         content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Avis déjà soumis pour ce trajet',            content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides',                          content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if (! $trip->isCompleted()) {
            return $this->apiResponse(false, 'Les avis ne peuvent être laissés que sur un trajet terminé.', [], 403);
        }

        if (! $request->user()->isPassenger()) {
            return $this->apiResponse(false, 'Seuls les passagers peuvent laisser un avis.', [], 403);
        }

        $alreadyReviewed = Review::where('trip_id', $trip->id)
            ->where('reviewer_id', $request->user()->id)
            ->exists();

        if ($alreadyReviewed) {
            return $this->apiResponse(false, 'Vous avez déjà soumis un avis pour ce trajet.', [], 409);
        }

        $validator = Validator::make($request->all(), [
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        $review = Review::create([
            'trip_id'     => $trip->id,
            'reviewer_id' => $request->user()->id,
            'reviewee_id' => $trip->user_id,
            'rating'      => $request->rating,
            'comment'     => $request->comment,
        ]);

        return $this->apiResponse(true, 'Avis soumis avec succès. Merci pour votre retour !', $review->load(['reviewer.profile', 'trip']), 201);
    }

    // =========================================================================
    //  HELPER PRIVÉ
    // =========================================================================

    private function apiResponse(bool $success, string $message, mixed $body = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'body'    => $body,
        ], $status);
    }
}
