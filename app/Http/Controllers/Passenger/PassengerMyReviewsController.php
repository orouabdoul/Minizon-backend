<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Mes avis" (MyReviewsView) — avis DONNÉS par le passager connecté.
 *
 * Retourne :
 *   – La liste des reviews que le passager a soumises (reviewer_id = user)
 *   – La moyenne et la distribution par note (pour le RatingSummaryCard)
 *
 * La page est accessible depuis le profil passager.
 */
class PassengerMyReviewsController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/reviews
    //  Avis donnés par le passager connecté
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/reviews',
        operationId: 'passengerMyReviews',
        summary: 'Avis donnés par le passager (MyReviewsView)',
        description: "Retourne tous les avis que le passager connecté a soumis à des conducteurs, avec la moyenne globale et la distribution par étoiles pour le `_RatingSummaryCard`.",
        tags: ['👤 Passenger — Profil'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des avis donnés',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Mes avis.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'average_rating',    type: 'number',  format: 'float', example: 4.7),
                                new OA\Property(property: 'formatted_average', type: 'string',  example: '4.7', description: '"—" si aucun avis.'),
                                new OA\Property(
                                    property: 'rating_distribution',
                                    type: 'object',
                                    description: 'Nombre d\'avis par note (clés "1" à "5").',
                                    example: ['5' => 8, '4' => 3, '3' => 1, '2' => 0, '1' => 0]
                                ),
                                new OA\Property(
                                    property: 'reviews',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/PassengerReviewItem')
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
        $passenger = $request->user();
        $tz        = 'Africa/Porto-Novo';

        $reviews = Review::with(['reviewee.profile', 'trip'])
            ->where('reviewer_id', $passenger->id)
            ->orderByDesc('created_at')
            ->get();

        $total     = $reviews->count();
        $avgRating = $total > 0 ? round($reviews->avg('rating'), 1) : 0.0;

        // Distribution par étoile (1 → 5)
        $distribution = [];
        for ($s = 1; $s <= 5; $s++) {
            $distribution[(string) $s] = $reviews->where('rating', $s)->count();
        }

        $formatted = $reviews->map(function (Review $r) use ($tz) {
            // Conducteur évalué
            $profile    = $r->reviewee?->profile;
            $firstName  = $profile?->first_name ?? '';
            $lastName   = $profile?->last_name  ?? '';
            $driverName = trim("$firstName $lastName") ?: 'Conducteur';

            // Trajet concerné
            $trip  = $r->trip;
            $route = $trip
                ? (($trip->departure_city ?? '—') . ' → ' . ($trip->arrival_city ?? '—'))
                : '—';

            // Date relative (Africa/Porto-Novo)
            $diff = (int) ($r->created_at?->setTimezone($tz)->diffInDays(now()) ?? 0);
            $date = match (true) {
                $diff === 0  => "Aujourd'hui",
                $diff === 1  => 'Hier',
                $diff <= 6   => "Il y a {$diff} jours",
                $diff <= 13  => 'Il y a 1 semaine',
                $diff <= 20  => 'Il y a 2 semaines',
                $diff <= 27  => 'Il y a 3 semaines',
                default      => 'Il y a ' . (int) ($diff / 30) . ' mois',
            };

            // Tags (JSON ou tableau déjà décodé selon le cast du modèle)
            $rawTags = $r->tags ?? [];
            if (is_string($rawTags)) {
                $rawTags = json_decode($rawTags, true) ?: [];
            }

            return [
                'uuid'            => $r->uuid ?? (string) $r->id,
                'driver_name'     => $driverName,
                'driver_initials' => $this->initials($driverName),
                'route'           => $route,
                'date'            => $date,
                'rating'          => (int) $r->rating,
                'tags'            => array_values((array) $rawTags),
                'comment'         => $r->comment ?: null,
            ];
        })->values()->toArray();

        return $this->apiResponse(true, 'Mes avis.', [
            'average_rating'      => (float) $avgRating,
            'formatted_average'   => $total > 0 ? number_format($avgRating, 1) : '—',
            'rating_distribution' => $distribution,
            'reviews'             => $formatted,
        ]);
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'PassengerReviewItem',
        description: 'Avis donné par le passager à un conducteur.',
        properties: [
            new OA\Property(property: 'uuid',             type: 'string', example: 'abc12345'),
            new OA\Property(property: 'driver_name',      type: 'string', example: 'Koffi Adjovi'),
            new OA\Property(property: 'driver_initials',  type: 'string', example: 'KA'),
            new OA\Property(property: 'route',            type: 'string', example: 'Cotonou → Porto-Novo'),
            new OA\Property(property: 'date',             type: 'string', example: 'Il y a 3 jours'),
            new OA\Property(property: 'rating',           type: 'integer', minimum: 1, maximum: 5, example: 5),
            new OA\Property(property: 'tags',             type: 'array', items: new OA\Items(type: 'string'), example: ['Ponctuel', 'Conduite sûre']),
            new OA\Property(property: 'comment',          type: 'string', nullable: true, example: 'Excellent conducteur !'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn ($w) => strtoupper($w[0]))
            ->join('');
    }
}
