<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ReviewController extends Controller
{
    // =========================================================================
    //  GET /api/trips/{uuid}/reviews
    // =========================================================================

    #[OA\Get(
        path: '/api/trips/{uuid}/reviews',
        operationId: 'tripReviews',
        summary: 'Avis passagers pour un trajet',
        description: 'Retourne la liste paginée des évaluations soumises pour un trajet donné. Accessible sans authentification.',
        tags: ['⭐ Avis'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des avis',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Avis du trajet.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'avg_rating',    type: 'number', format: 'float', nullable: true, example: 4.3),
                                new OA\Property(property: 'total_reviews', type: 'integer', example: 12),
                                new OA\Property(
                                    property: 'reviews',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'uuid',          type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'rating',        type: 'integer', example: 4),
                                            new OA\Property(property: 'comment',       type: 'string', nullable: true),
                                            new OA\Property(property: 'tags',          type: 'array', nullable: true, items: new OA\Items(type: 'string')),
                                            new OA\Property(property: 'reviewer_name', type: 'string', example: 'Koffi A.'),
                                            new OA\Property(property: 'driver_reply',  type: 'string', nullable: true),
                                            new OA\Property(property: 'created_at',    type: 'string', format: 'date-time'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    public function tripReviews(string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        $reviews = Review::with('reviewer.profile')
            ->where('trip_id', $trip->id)
            ->where('status', 'visible')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Review $r) => $this->formatReview($r));

        $avg = Review::where('trip_id', $trip->id)->where('status', 'visible')->avg('rating');

        return $this->apiResponse(true, 'Avis du trajet.', [
            'avg_rating'    => $avg ? round((float) $avg, 1) : null,
            'total_reviews' => $reviews->count(),
            'reviews'       => $reviews,
        ]);
    }

    // =========================================================================
    //  GET /api/drivers/{uuid}/reviews
    // =========================================================================

    #[OA\Get(
        path: '/api/drivers/{uuid}/reviews',
        operationId: 'driverReviews',
        summary: 'Avis reçus par un conducteur',
        description: 'Retourne la liste paginée des évaluations reçues par un conducteur. Accessible sans authentification.',
        tags: ['⭐ Avis'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID du conducteur'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des avis',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Avis conducteur.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'avg_rating',    type: 'number', format: 'float', nullable: true, example: 4.5),
                                new OA\Property(property: 'total_reviews', type: 'integer', example: 28),
                                new OA\Property(
                                    property: 'reviews',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'uuid',          type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'rating',        type: 'integer', example: 5),
                                            new OA\Property(property: 'comment',       type: 'string', nullable: true),
                                            new OA\Property(property: 'tags',          type: 'array', nullable: true, items: new OA\Items(type: 'string')),
                                            new OA\Property(property: 'reviewer_name', type: 'string', example: 'Amina K.'),
                                            new OA\Property(property: 'driver_reply',  type: 'string', nullable: true),
                                            new OA\Property(property: 'created_at',    type: 'string', format: 'date-time'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Conducteur introuvable'),
        ]
    )]
    public function driverReviews(string $uuid): JsonResponse
    {
        $driver = User::where('uuid', $uuid)->first();

        if (! $driver) {
            return $this->apiResponse(false, 'Conducteur introuvable.', [], 404);
        }

        $reviews = Review::with('reviewer.profile')
            ->where('reviewee_id', $driver->id)
            ->where('status', 'visible')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Review $r) => $this->formatReview($r));

        $avg = Review::where('reviewee_id', $driver->id)->where('status', 'visible')->avg('rating');

        return $this->apiResponse(true, 'Avis conducteur.', [
            'avg_rating'    => $avg ? round((float) $avg, 1) : null,
            'total_reviews' => $reviews->count(),
            'reviews'       => $reviews,
        ]);
    }

    // =========================================================================
    //  POST /api/trips/{uuid}/reviews
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/reviews',
        operationId: 'tripReviewStore',
        summary: 'Soumettre un avis pour un trajet',
        description: "Crée une évaluation pour le conducteur d'un trajet. Le passager doit avoir une réservation acceptée pour ce trajet. Un seul avis par trajet par passager.",
        tags: ['⭐ Avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['rating'],
                properties: [
                    new OA\Property(property: 'rating',  type: 'integer', minimum: 1, maximum: 5, example: 4),
                    new OA\Property(
                        property: 'tags',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'string', maxLength: 100),
                        example: ['Ponctuel', 'Conduite sûre']
                    ),
                    new OA\Property(property: 'comment', type: 'string', nullable: true, maxLength: 1000),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avis soumis',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Avis envoyé. Merci !'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'review_uuid', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'rating',      type: 'integer', example: 4),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
            new OA\Response(response: 422, description: 'Avis déjà soumis ou passager non autorisé'),
        ]
    )]
    public function store(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'tags'    => ['nullable', 'array'],
            'tags.*'  => ['string', 'max:100'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        $passenger = $request->user();

        // Vérifier que le passager a une réservation acceptée pour ce trajet
        $booking = Booking::where('trip_id', $trip->id)
            ->where('passenger_id', $passenger->id)
            ->where('status', 'accepted')
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Vous devez avoir une réservation acceptée pour évaluer ce trajet.', [], 422);
        }

        // Un seul avis par trajet par passager
        $existing = Review::where('trip_id', $trip->id)
            ->where('reviewer_id', $passenger->id)
            ->first();

        if ($existing) {
            return $this->apiResponse(false, 'Vous avez déjà évalué ce trajet.', [], 422);
        }

        $review              = new Review();
        $review->trip_id     = $trip->id;
        $review->reviewer_id = $passenger->id;
        $review->reviewee_id = $trip->user_id;
        $review->rating      = $validated['rating'];
        $review->comment     = $validated['comment'] ?? null;
        $review->tags        = $validated['tags'] ?? [];
        $review->status      = 'visible';
        $review->save();

        return $this->apiResponse(true, 'Avis envoyé. Merci !', [
            'review_uuid' => $review->uuid,
            'rating'      => $review->rating,
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function formatReview(Review $review): array
    {
        $profile      = $review->reviewer?->profile;
        $firstName    = $profile?->first_name ?? '';
        $lastInitial  = $profile?->last_name  ? strtoupper($profile->last_name[0]) . '.' : '';
        $reviewerName = trim("$firstName $lastInitial") ?: 'Anonyme';

        return [
            'uuid'          => $review->uuid,
            'rating'        => $review->rating,
            'comment'       => $review->comment,
            'tags'          => $review->tags ?? [],
            'reviewer_name' => $reviewerName,
            'driver_reply'  => $review->driver_reply,
            'created_at'    => $review->created_at?->toIso8601String(),
        ];
    }
}
