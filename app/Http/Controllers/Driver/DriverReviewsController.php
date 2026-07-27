<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Trip;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Avis reçus" — évaluations passagers à destination du conducteur.
 */
class DriverReviewsController extends Controller
{
    // Suggestions de réponse rapide selon la note
    private const REPLY_TEMPLATES = [
        [
            'id'    => 'apology',
            'label' => "M'excuser",
            'text'  => "Je suis vraiment désolé pour cette expérience. Ce n'est pas le niveau de service que je m'efforce de fournir. Je ferai mieux la prochaine fois.",
        ],
        [
            'id'    => 'thanks',
            'label' => 'Remercier',
            'text'  => "Merci beaucoup pour votre avis ! C'est un plaisir de vous avoir eu comme passager.",
        ],
        [
            'id'    => 'improve',
            'label' => "M'améliorer",
            'text'  => "Merci pour votre retour. Je prends note et ferai de mon mieux pour améliorer mon service lors de nos prochains trajets.",
        ],
        [
            'id'    => 'clarify',
            'label' => 'Expliquer',
            'text'  => "Merci pour votre commentaire. Je tiens à vous expliquer la situation afin d'éviter tout malentendu pour l'avenir.",
        ],
    ];

    // =========================================================================
    //  GET /api/driver/reviews  — tous les avis reçus (global)
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/reviews',
        operationId: 'driverMyReviews',
        summary: 'Tous les avis reçus par le conducteur',
        description: 'Retourne les avis visibles reçus, le résumé (note moyenne, distribution) et les modèles de réponse rapide.',
        tags: ['⭐ Driver — Avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'rating', in: 'query', required: false,
                description: 'Filtrer par note (1 à 5)',
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 5)),
            new OA\Parameter(name: 'replied', in: 'query', required: false,
                description: 'true = avec réponse, false = sans réponse',
                schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avis et résumé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Avis reçus.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'summary',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'average_rating',      type: 'number',  example: 4.7),
                                        new OA\Property(property: 'total_reviews',       type: 'integer', example: 24),
                                        new OA\Property(property: 'replied_count',       type: 'integer', example: 12),
                                        new OA\Property(property: 'pending_reply_count', type: 'integer', example: 12),
                                        new OA\Property(property: 'rating_distribution', type: 'object',
                                            example: ['1' => 0.04, '2' => 0.0, '3' => 0.08, '4' => 0.21, '5' => 0.67]),
                                    ]
                                ),
                                new OA\Property(property: 'reply_templates', type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/ReplyTemplate')),
                                new OA\Property(property: 'reviews', type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/DriverReviewItem')),
                                new OA\Property(
                                    property: 'meta', type: 'object',
                                    properties: [
                                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                        new OA\Property(property: 'last_page',    type: 'integer', example: 3),
                                        new OA\Property(property: 'total',        type: 'integer', example: 24),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Review::where('reviewee_id', $user->id)
            ->where('status', 'visible')
            ->with(['reviewer.profile', 'trip']);

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->input('rating'));
        }
        if ($request->has('replied')) {
            $replied = filter_var($request->input('replied'), FILTER_VALIDATE_BOOLEAN);
            $replied
                ? $query->whereNotNull('driver_reply')
                : $query->whereNull('driver_reply');
        }

        $reviews = $query->latest()->paginate(20);

        $allVisible   = Review::where('reviewee_id', $user->id)->where('status', 'visible')->get();
        $total        = $allVisible->count();
        $average      = $total > 0 ? round($allVisible->avg('rating'), 2) : 0.0;
        $repliedCount = $allVisible->whereNotNull('driver_reply')->count();

        $distribution = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0];
        if ($total > 0) {
            foreach ($allVisible->groupBy('rating') as $rating => $group) {
                $distribution[(int) $rating] = round($group->count() / $total, 2);
            }
        }

        return $this->apiResponse(true, 'Avis reçus.', [
            'summary' => [
                'average_rating'      => $average,
                'total_reviews'       => $total,
                'replied_count'       => $repliedCount,
                'pending_reply_count' => $total - $repliedCount,
                'rating_distribution' => $distribution,
            ],
            'reply_templates' => self::REPLY_TEMPLATES,
            'reviews'         => $reviews->getCollection()->map(fn ($r) => $this->formatReview($r))->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'total'        => $reviews->total(),
            ],
        ]);
    }

    // =========================================================================
    //  GET /api/driver/trips/{uuid}/reviews  — avis d'un trajet terminé
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/trips/{uuid}/reviews',
        operationId: 'driverTripReviews',
        summary: 'Avis reçus pour un trajet terminé',
        description: "Retourne tous les avis visibles laissés par les passagers pour un trajet spécifique (doit être `completed`). Inclut le résumé de note pour ce trajet et les modèles de réponse rapide. Pour répondre à un avis, utiliser `POST /api/driver/reviews/{uuid}/reply`.",
        tags: ['⭐ Driver — Avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'UUID du trajet'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avis du trajet',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Avis du trajet.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'trip',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'uuid',        type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'route',       type: 'string', example: 'Cotonou → Porto-Novo'),
                                        new OA\Property(property: 'date',        type: 'string', example: '14 juillet 2026'),
                                        new OA\Property(property: 'status',      type: 'string', example: 'completed'),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'summary',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'average_rating',      type: 'number',  example: 4.3),
                                        new OA\Property(property: 'total_reviews',       type: 'integer', example: 3),
                                        new OA\Property(property: 'replied_count',       type: 'integer', example: 1),
                                        new OA\Property(property: 'pending_reply_count', type: 'integer', example: 2),
                                    ]
                                ),
                                new OA\Property(property: 'reply_templates', type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/ReplyTemplate')),
                                new OA\Property(property: 'reviews', type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/DriverReviewItem')),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Ce trajet ne vous appartient pas'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
            new OA\Response(response: 409, description: 'Le trajet n\'est pas encore terminé'),
        ]
    )]
    public function tripReviews(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }
        if ($trip->user_id !== $user->id) {
            return $this->apiResponse(false, 'Ce trajet ne vous appartient pas.', [], 403);
        }
        if ($trip->status !== 'completed') {
            return $this->apiResponse(false, 'Les avis ne sont disponibles que pour les trajets terminés.', [], 409);
        }

        $reviews = Review::where('trip_id', $trip->id)
            ->where('reviewee_id', $user->id)
            ->where('status', 'visible')
            ->with(['reviewer.profile'])
            ->latest()
            ->get();

        $total        = $reviews->count();
        $average      = $total > 0 ? round($reviews->avg('rating'), 2) : 0.0;
        $repliedCount = $reviews->whereNotNull('driver_reply')->count();

        $tripDate = $trip->ended_at ?? $trip->departure_time;

        return $this->apiResponse(true, 'Avis du trajet.', [
            'trip' => [
                'uuid'   => $trip->uuid,
                'route'  => $trip->departure_city . ' → ' . $trip->arrival_city,
                'date'   => $tripDate
                    ? $tripDate->setTimezone('Africa/Porto-Novo')->translatedFormat('j F Y')
                    : null,
                'status' => $trip->status,
            ],
            'summary' => [
                'average_rating'      => $average,
                'total_reviews'       => $total,
                'replied_count'       => $repliedCount,
                'pending_reply_count' => $total - $repliedCount,
            ],
            'reply_templates' => self::REPLY_TEMPLATES,
            'reviews'         => $reviews->map(fn ($r) => $this->formatReview($r, withTripRoute: false))->values(),
        ]);
    }

    // =========================================================================
    //  POST /api/driver/reviews/{uuid}/reply
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/reviews/{uuid}/reply',
        operationId: 'driverReviewReply',
        summary: 'Répondre à un avis reçu',
        description: 'Le conducteur répond à un avis passager. Une notification push est envoyée au passager. Fonctionne depuis la liste globale ou depuis un trajet terminé.',
        tags: ['⭐ Driver — Avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'UUID de l\'avis (pas du trajet)'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reply'],
                properties: [
                    new OA\Property(
                        property: 'reply',
                        type: 'string',
                        maxLength: 500,
                        example: 'Je suis vraiment désolé pour cette expérience. Je ferai mieux la prochaine fois.',
                        description: 'Texte libre ou basé sur un reply_template (max 500 caractères)'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Réponse publiée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success',           type: 'boolean', example: true),
                        new OA\Property(property: 'message',           type: 'string',  example: 'Réponse publiée.'),
                        new OA\Property(property: 'notification_sent', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Avis introuvable'),
        ]
    )]
    public function reply(Request $request, string $uuid): JsonResponse
    {
        $user   = $request->user();
        $review = Review::where('uuid', $uuid)
            ->where('reviewee_id', $user->id)
            ->where('status', 'visible')
            ->with(['reviewer:id,fcm_token', 'reviewer.profile'])
            ->firstOrFail();

        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:500'],
        ]);

        $review->update(['driver_reply' => $validated['reply']]);

        $notifSent  = false;
        $fcmToken   = $review->reviewer?->fcm_token;
        $driverName = $user->profile?->first_name ?? 'Votre conducteur';

        if ($fcmToken) {
            $notifSent = app(FcmService::class)->send(
                $fcmToken,
                '💬 ' . $driverName . ' a répondu à votre avis',
                $validated['reply'],
                [
                    'type'        => 'driver_review_reply',
                    'review_uuid' => $review->uuid,
                ]
            );
        }

        return $this->apiResponse(true, 'Réponse publiée.', [
            'notification_sent' => $notifSent,
        ]);
    }

    // =========================================================================
    //  OA SCHEMAS
    // =========================================================================

    #[OA\Schema(
        schema: 'DriverReviewItem',
        properties: [
            new OA\Property(property: 'uuid',               type: 'string', format: 'uuid'),
            new OA\Property(property: 'passenger_name',     type: 'string', example: 'Fatou BELLO'),
            new OA\Property(property: 'passenger_initial',  type: 'string', example: 'F'),
            new OA\Property(property: 'rating',             type: 'integer', minimum: 1, maximum: 5, example: 3),
            new OA\Property(property: 'date',               type: 'string', example: 'Il y a 2 jours'),
            new OA\Property(property: 'trip_route',         type: 'string', nullable: true, example: 'Cotonou → Porto-Novo',
                description: 'null quand appelé depuis GET /driver/trips/{uuid}/reviews (route déjà connue)'),
            new OA\Property(property: 'comment',            type: 'string', nullable: true),
            new OA\Property(property: 'driver_reply',       type: 'string', nullable: true, example: 'Merci pour votre retour !'),
            new OA\Property(property: 'needs_reply',        type: 'boolean', example: true,
                description: 'true si le conducteur n\'a pas encore répondu'),
        ]
    )]
    #[OA\Schema(
        schema: 'ReplyTemplate',
        properties: [
            new OA\Property(property: 'id',    type: 'string', example: 'apology'),
            new OA\Property(property: 'label', type: 'string', example: "M'excuser"),
            new OA\Property(property: 'text',  type: 'string', example: 'Je suis vraiment désolé…'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatReview(Review $review, bool $withTripRoute = true): array
    {
        $profile  = $review->reviewer?->profile;
        $name     = $profile
            ? trim("{$profile->first_name} {$profile->last_name}")
            : ($review->reviewer?->phone ?? 'Passager');
        $initial  = mb_strtoupper(mb_substr($name, 0, 1));

        $diff = (int) ($review->created_at?->setTimezone('Africa/Porto-Novo')->diffInDays(now()) ?? 0);
        $date = match (true) {
            $diff === 0 => "Aujourd'hui",
            $diff === 1 => 'Hier',
            $diff <= 6  => "Il y a {$diff} jours",
            $diff <= 13 => 'Il y a 1 semaine',
            $diff <= 27 => 'Il y a ' . (int) ceil($diff / 7) . ' semaines',
            default     => 'Il y a ' . (int) round($diff / 30) . ' mois',
        };

        $item = [
            'uuid'              => $review->uuid,
            'passenger_name'    => $name,
            'passenger_initial' => $initial,
            'rating'            => (int) $review->rating,
            'date'              => $date,
            'comment'           => $review->comment,
            'driver_reply'      => $review->driver_reply ?? null,
            'needs_reply'       => $review->driver_reply === null,
        ];

        if ($withTripRoute) {
            $trip = $review->relationLoaded('trip') ? $review->trip : null;
            $item['trip_route'] = $trip
                ? "{$trip->departure_city} → {$trip->arrival_city}"
                : null;
        } else {
            $item['trip_route'] = null;
        }

        return $item;
    }
}
