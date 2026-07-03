<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

/**
 * Page "Avis reçus" — évaluations passagers à destination du conducteur.
 */
class DriverReviewsController extends Controller
{
    // =========================================================================
    //  GET /api/driver/reviews
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/reviews',
        operationId: 'driverMyReviews',
        summary: 'Avis reçus par le conducteur connecté',
        tags: ['⭐ Driver — Avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Avis et résumé'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $reviews = Review::where('reviewee_id', $user->id)
            ->with(['reviewer.profile', 'trip'])
            ->latest()
            ->paginate(20);

        $allReviews = Review::where('reviewee_id', $user->id)->get();
        $total      = $allReviews->count();
        $average    = $total > 0 ? round($allReviews->avg('rating'), 2) : 0.0;

        $distribution = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0];
        if ($total > 0) {
            foreach ($allReviews->groupBy('rating') as $rating => $group) {
                $distribution[(int) $rating] = round($group->count() / $total, 2);
            }
        }

        $items = $reviews->getCollection()->map(fn ($r) => $this->formatReview($r));

        return $this->apiResponse(true, 'Avis reçus.', [
            'summary' => [
                'average_rating'      => $average,
                'total_reviews'       => $total,
                'rating_distribution' => $distribution,
            ],
            'reviews'  => $items,
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'total'        => $reviews->total(),
            ],
        ]);
    }

    // =========================================================================
    //  POST /api/driver/reviews/{uuid}/reply
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/reviews/{uuid}/reply',
        operationId: 'driverReviewReply',
        summary: 'Répondre à un avis reçu',
        tags: ['⭐ Driver — Avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reply'],
                properties: [
                    new OA\Property(property: 'reply', type: 'string', maxLength: 500),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Réponse publiée'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Avis introuvable'),
        ]
    )]
    public function reply(Request $request, string $uuid): JsonResponse
    {
        $user   = $request->user();
        $review = Review::where('uuid', $uuid)
            ->where('reviewee_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:500'],
        ]);

        $review->update(['driver_reply' => $validated['reply']]);

        return $this->apiResponse(true, 'Réponse publiée.');
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatReview(Review $review): array
    {
        $profile  = $review->reviewer?->profile;
        $name     = $profile
            ? trim("{$profile->first_name} {$profile->last_name}")
            : ($review->reviewer?->phone ?? 'Passager');
        $initial  = mb_strtoupper(mb_substr($name, 0, 1));
        $trip     = $review->trip;
        $route    = $trip ? "{$trip->departure_city} → {$trip->arrival_city}" : '—';
        $date     = $review->created_at
            ->setTimezone('Africa/Porto-Novo')
            ->diffForHumans();

        return [
            'uuid'           => $review->uuid,
            'passenger_name'    => $name,
            'passenger_initial' => $initial,
            'rating'            => $review->rating,
            'date'              => $date,
            'trip_route'        => $route,
            'comment'           => $review->comment,
            'driver_reply'      => $review->driver_reply ?? null,
        ];
    }
}
