<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Trip;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use OpenApi\Attributes as OA;

/**
 * Page "Avis reçus" — évaluations passagers à destination du conducteur.
 */
class DriverReviewsController extends Controller
{
    private const REPLY_TEMPLATES = [
        ['id' => 'apology', 'label' => "M'excuser",  'text' => "Je suis vraiment désolé pour cette expérience. Ce n'est pas le niveau de service que je m'efforce de fournir. Je ferai mieux la prochaine fois."],
        ['id' => 'thanks',  'label' => 'Remercier',   'text' => "Merci beaucoup pour votre avis ! C'est un plaisir de vous avoir eu comme passager."],
        ['id' => 'improve', 'label' => "M'améliorer", 'text' => "Merci pour votre retour. Je prends note et ferai de mon mieux pour améliorer mon service lors de nos prochains trajets."],
        ['id' => 'clarify', 'label' => 'Expliquer',   'text' => "Merci pour votre commentaire. Je tiens à vous expliquer la situation afin d'éviter tout malentendu pour l'avenir."],
    ];

    // =========================================================================
    //  GET /api/driver/reviews  — tous les avis reçus
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/reviews',
        operationId: 'driverMyReviews',
        summary: 'Tous les avis reçus par le conducteur',
        description: 'Avis visibles, résumé global et modèles de réponse rapide.',
        tags: ['⭐ Driver — Avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page',    in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'rating',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 5)),
            new OA\Parameter(name: 'replied', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Avis et résumé'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Review::where('reviewee_id', $user->id)
            ->where('status', 'visible')
            ->with(['reviewer.profile', 'trip']);

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->input('rating'));
        }
        if ($request->has('replied')) {
            $replied = filter_var($request->input('replied'), FILTER_VALIDATE_BOOLEAN);
            $replied ? $query->whereNotNull('driver_reply') : $query->whereNull('driver_reply');
        }

        $reviews      = $query->latest()->paginate(20);
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
        description: "Retourne les avis visibles du trajet avec résumé et modèles de réponse. Seuls les trajets `completed` sont acceptés. Pour répondre : `POST /api/driver/reviews/{uuid}/reply`. Pour réagir : `PATCH /api/driver/reviews/{uuid}/react`.",
        tags: ['⭐ Driver — Avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID du trajet'),
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
                                        new OA\Property(property: 'uuid',   type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'route',  type: 'string', example: 'Cotonou → Porto-Novo'),
                                        new OA\Property(property: 'date',   type: 'string', example: '14 juillet 2026'),
                                        new OA\Property(property: 'status', type: 'string', example: 'completed'),
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
            new OA\Response(response: 409, description: 'Le trajet n\'est pas terminé'),
        ]
    )]
    public function tripReviews(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip)                       return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        if ($trip->user_id !== $user->id)  return $this->apiResponse(false, 'Ce trajet ne vous appartient pas.', [], 403);
        if ($trip->status !== 'completed') return $this->apiResponse(false, 'Les avis ne sont disponibles que pour les trajets terminés.', [], 409);

        $reviews      = Review::where('trip_id', $trip->id)
            ->where('reviewee_id', $user->id)
            ->where('status', 'visible')
            ->with(['reviewer.profile'])
            ->latest()
            ->get();

        $total        = $reviews->count();
        $average      = $total > 0 ? round($reviews->avg('rating'), 2) : 0.0;
        $repliedCount = $reviews->whereNotNull('driver_reply')->count();
        $tripDate     = ($trip->ended_at ?? $trip->departure_time)?->setTimezone('Africa/Porto-Novo')->translatedFormat('j F Y');

        return $this->apiResponse(true, 'Avis du trajet.', [
            'trip' => [
                'uuid'   => $trip->uuid,
                'route'  => "{$trip->departure_city} → {$trip->arrival_city}",
                'date'   => $tripDate,
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
    //  POST /api/driver/reviews/{uuid}/reply  — répondre à un avis
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/reviews/{uuid}/reply',
        operationId: 'driverReviewReply',
        summary: 'Répondre à un avis reçu',
        description: 'Le passager reçoit une notification FCM. Fonctionne depuis la liste globale ou depuis un trajet terminé.',
        tags: ['⭐ Driver — Avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de l\'avis'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reply'],
                properties: [
                    new OA\Property(property: 'reply', type: 'string', maxLength: 500,
                        example: 'Je suis vraiment désolé pour cette expérience.'),
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

        $validated = $request->validate(['reply' => ['required', 'string', 'max:500']]);
        $review->update(['driver_reply' => $validated['reply']]);

        $notifSent  = false;
        $fcmToken   = $review->reviewer?->fcm_token;
        $driverName = $user->profile?->first_name ?? 'Votre conducteur';

        if ($fcmToken) {
            $notifSent = app(FcmService::class)->send(
                $fcmToken,
                '💬 ' . $driverName . ' a répondu à votre avis',
                $validated['reply'],
                ['type' => 'driver_review_reply', 'review_uuid' => $review->uuid]
            );
        }

        return $this->apiResponse(true, 'Réponse publiée.', ['notification_sent' => $notifSent]);
    }

    // =========================================================================
    //  PATCH /api/driver/reviews/{uuid}/react  — OK / Non / Signaler
    // =========================================================================

    #[OA\Patch(
        path: '/api/driver/reviews/{uuid}/react',
        operationId: 'driverReviewReact',
        summary: 'Réagir à un avis (OK / Contester / Signaler)',
        description: <<<'MD'
Permet au conducteur d'exprimer une réaction à un avis reçu :

- **`ok`** — Le conducteur accepte l'avis comme juste (icône ✅)
- **`disputed`** — Le conducteur conteste l'avis (icône ❌) — signalé à l'équipe pour modération
- **`reported`** — Le conducteur signale l'avis comme abusif ou faux (icône 🚨) — incrémente `report_count`, alerte admin
- `null` — Réinitialise la réaction (annuler)
MD,
        tags: ['⭐ Driver — Avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de l\'avis'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reaction'],
                properties: [
                    new OA\Property(
                        property: 'reaction',
                        type: 'string',
                        enum: ['ok', 'disputed', 'reported'],
                        nullable: true,
                        example: 'ok',
                        description: 'null pour réinitialiser'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Réaction enregistrée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success',          type: 'boolean', example: true),
                        new OA\Property(property: 'message',          type: 'string',  example: 'Réaction enregistrée.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'driver_reaction', type: 'string', nullable: true, example: 'ok',
                                    enum: ['ok', 'disputed', 'reported']),
                                new OA\Property(property: 'report_count',    type: 'integer', example: 1),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Avis introuvable'),
        ]
    )]
    public function react(Request $request, string $uuid): JsonResponse
    {
        $user   = $request->user();
        $review = Review::where('uuid', $uuid)
            ->where('reviewee_id', $user->id)
            ->where('status', 'visible')
            ->firstOrFail();

        $validated = $request->validate([
            'reaction' => ['nullable', 'string', 'in:ok,disputed,reported'],
        ]);

        $reaction    = $validated['reaction'] ?? null;
        $updates     = ['driver_reaction' => $reaction];
        $message     = 'Réaction enregistrée.';

        if ($reaction === 'reported') {
            // Incrémenter le compteur de signalement
            $updates['report_count'] = $review->report_count + 1;
            // Signaler à la modération si seuil atteint
            if ($updates['report_count'] >= 3) {
                $updates['status'] = 'signalé';
            }
            $message = 'Avis signalé à l\'équipe de modération.';
        } elseif ($reaction === 'disputed') {
            $message = 'Contestation enregistrée. Notre équipe examinera cet avis.';
        } elseif ($reaction === 'ok') {
            $message = 'Vous avez accepté cet avis.';
        }

        $review->update($updates);

        return $this->apiResponse(true, $message, [
            'driver_reaction' => $review->fresh()->driver_reaction,
            'report_count'    => $review->fresh()->report_count,
        ]);
    }

    // =========================================================================
    //  OA SCHEMAS
    // =========================================================================

    #[OA\Schema(
        schema: 'DriverReviewItem',
        description: 'Avis reçu par le conducteur — inclus dans la liste globale et dans le détail de trajet terminé.',
        properties: [
            new OA\Property(property: 'uuid',               type: 'string', format: 'uuid'),
            new OA\Property(property: 'passenger_name',     type: 'string', example: 'Fatou BELLO'),
            new OA\Property(property: 'passenger_initial',  type: 'string', example: 'F'),
            new OA\Property(property: 'rating',             type: 'integer', minimum: 1, maximum: 5, example: 3),
            new OA\Property(property: 'date',               type: 'string', example: 'Il y a 2 jours'),
            new OA\Property(property: 'trip_route',         type: 'string', nullable: true, example: 'Cotonou → Porto-Novo'),
            new OA\Property(property: 'comment',            type: 'string', nullable: true),
            new OA\Property(property: 'driver_reply',       type: 'string', nullable: true, example: 'Merci pour votre retour !'),
            new OA\Property(property: 'driver_reaction',    type: 'string', nullable: true, enum: ['ok', 'disputed', 'reported'],
                description: 'Réaction du conducteur : ok ✅ | disputed ❌ | reported 🚨 | null = pas encore réagi'),
            new OA\Property(property: 'needs_reply',        type: 'boolean', example: true,
                description: 'true si aucune réponse n\'a encore été donnée'),
            new OA\Property(
                property: 'actions',
                type: 'object',
                description: 'Actions disponibles sur cet avis',
                properties: [
                    new OA\Property(property: 'can_reply',  type: 'boolean', example: true,  description: 'Toujours true — la réponse peut être mise à jour'),
                    new OA\Property(property: 'can_react',  type: 'boolean', example: true,  description: 'Toujours true — la réaction peut être changée'),
                ]
            ),
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

        $tripRoute = null;
        if ($withTripRoute && $review->relationLoaded('trip') && $review->trip) {
            $tripRoute = "{$review->trip->departure_city} → {$review->trip->arrival_city}";
        }

        return [
            'uuid'              => $review->uuid,
            'passenger_name'    => $name,
            'passenger_initial' => $initial,
            'rating'            => (int) $review->rating,
            'date'              => $date,
            'trip_route'        => $tripRoute,
            'comment'           => $review->comment,
            'driver_reply'      => $review->driver_reply ?? null,
            'driver_reaction'   => $review->driver_reaction ?? null,
            'needs_reply'       => $review->driver_reply === null,
            'actions' => [
                'can_reply' => true,
                'can_react' => true,
            ],
        ];
    }
}
