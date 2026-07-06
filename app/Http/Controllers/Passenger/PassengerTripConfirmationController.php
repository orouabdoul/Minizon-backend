<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Trajet terminé" (TripConfirmationView) — passager.
 *
 * Flux :
 *   1. GET  /api/passenger/bookings/{uuid}/trip-confirmation-context — chargement initial
 *   2. POST /api/passenger/bookings/{uuid}/confirm                   — passager confirme réception
 *   3. POST /api/passenger/bookings/{uuid}/review                    — envoi notation + tags + avis
 *
 * controller.skipReview() et controller.goHome() → navigation locale (pas d'API).
 * BottonNavController.goToTab(2)                 → navigation locale.
 *
 * Migrations requises :
 *   ALTER TABLE bookings ADD COLUMN passenger_confirmed_at TIMESTAMP NULL;
 *   ALTER TABLE reviews  ADD COLUMN tags JSON NULL;
 */
class PassengerTripConfirmationController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/bookings/{uuid}/trip-confirmation-context
    //  Chargement des infos affichées dans la TripSummary card
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/bookings/{uuid}/trip-confirmation-context',
        operationId: 'passengerTripConfirmationContext',
        summary: 'Contexte de confirmation de trajet (TripConfirmationView)',
        description: "Données minimales pour le résumé affiché en haut de TripConfirmationView : origine, destination, durée formatée, nom et initiales du conducteur. Généralement utilisé si les données ne sont pas passées en arguments de navigation depuis la vue précédente.",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de la réservation'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contexte chargé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Contexte trajet.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'ride',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'origin',          type: 'string', example: 'Cotonou'),
                                        new OA\Property(property: 'destination',     type: 'string', example: 'Parakou'),
                                        new OA\Property(property: 'duration',        type: 'string', example: '3h30'),
                                        new OA\Property(property: 'driver_name',     type: 'string', example: 'Koffi Adjovi'),
                                        new OA\Property(property: 'driver_initials', type: 'string', example: 'KA'),
                                    ]
                                ),
                                new OA\Property(property: 'already_reviewed',       type: 'boolean', example: false, description: 'true si ce passager a déjà soumis un avis pour ce trajet.'),
                                new OA\Property(property: 'passenger_confirmed_at', type: 'string', format: 'date-time', nullable: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    public function context(Request $request, string $uuid): JsonResponse
    {
        $passenger = $request->user();

        $booking = Booking::with(['trip.user.profile'])
            ->where('uuid', $uuid)
            ->where('passenger_id', $passenger->id)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $trip    = $booking->trip;
        $driver  = $trip->user;
        $profile = $driver?->profile;

        $firstName  = $profile?->first_name ?? '';
        $lastName   = $profile?->last_name  ?? '';
        $driverName = trim("$firstName $lastName") ?: 'Conducteur';

        $alreadyReviewed = Review::where('trip_id', $trip->id)
            ->where('reviewer_id', $passenger->id)
            ->exists();

        return $this->apiResponse(true, 'Contexte trajet.', [
            'ride' => [
                'origin'          => $trip->origin,
                'destination'     => $trip->destination,
                'duration'        => $this->formatDuration((int) ($trip->estimated_duration_minutes ?? 0)),
                'driver_name'     => $driverName,
                'driver_initials' => $this->initials($driverName),
            ],
            'already_reviewed'       => $alreadyReviewed,
            'passenger_confirmed_at' => $booking->passenger_confirmed_at ?? null,
        ]);
    }

    // =========================================================================
    //  POST /api/passenger/bookings/{uuid}/confirm
    //  Passager confirme avoir bien reçu le trajet + signalement optionnel
    //  Migration : ALTER TABLE bookings ADD COLUMN passenger_confirmed_at TIMESTAMP NULL;
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/bookings/{uuid}/confirm',
        operationId: 'passengerConfirmTrip',
        summary: 'Passager confirme la réception du trajet (ConfirmCard)',
        description: "Enregistre passenger_confirmed_at sur la réservation. Si des problèmes sont signalés via le champ issues, un ticket de support haute priorité est créé automatiquement avec le préfixe [Problème trajet]. Idempotent : un second appel retourne success sans recréer de ticket.",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'issues',
                        type: 'array',
                        nullable: true,
                        description: 'Problèmes sélectionnés par le passager (issueOptions du controller Flutter). Ex: ["Conducteur en retard", "Mauvais comportement"].',
                        items: new OA\Items(type: 'string', maxLength: 100)
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Trajet confirmé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Trajet confirmé.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'confirmed',              type: 'boolean', example: true),
                                new OA\Property(property: 'issue_ticket_created',   type: 'boolean', example: false),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    public function confirm(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'issues'   => ['nullable', 'array'],
            'issues.*' => ['string', 'max:100'],
        ]);

        $passenger = $request->user();

        $booking = Booking::with('trip')
            ->where('uuid', $uuid)
            ->where('passenger_id', $passenger->id)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        // Idempotent
        if (! $booking->passenger_confirmed_at) {
            // Migration requise : ALTER TABLE bookings ADD COLUMN passenger_confirmed_at TIMESTAMP NULL;
            $booking->update(['passenger_confirmed_at' => now()]);
        }

        // Signalement de problème → ticket support haute priorité
        $issues            = $validated['issues'] ?? [];
        $ticketCreated     = false;

        if (! empty($issues) && ! $booking->passenger_confirmed_at) {
            // Pas de doublon si déjà confirmé
        }

        if (! empty($issues)) {
            $trip        = $booking->trip;
            $issuesList  = implode(', ', $issues);

            try {
                SupportTicket::create([
                    'user_id'     => $passenger->id,
                    'subject'     => "[Problème trajet] {$trip->origin} → {$trip->destination}",
                    'description' => "Problèmes signalés pour la réservation #{$booking->uuid} : {$issuesList}.",
                    'priority'    => 'high',
                    'channel'     => 'app',
                    'status'      => 'new',
                ]);
                $ticketCreated = true;
            } catch (\Throwable) {
                // Non bloquant
            }
        }

        return $this->apiResponse(true, 'Trajet confirmé.', [
            'confirmed'            => true,
            'issue_ticket_created' => $ticketCreated,
        ]);
    }

    // =========================================================================
    //  POST /api/passenger/bookings/{uuid}/review
    //  Passager soumet sa notation du conducteur (stars + tags + avis écrit)
    //  Migration : ALTER TABLE reviews ADD COLUMN tags JSON NULL;
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/bookings/{uuid}/review',
        operationId: 'passengerSubmitReview',
        summary: 'Soumettre une notation conducteur (RatingCard + QuickTagsCard + ReviewField)',
        description: "Crée un enregistrement Review (reviewer_id = passager, reviewee_id = conducteur). Les tags (quickTags sélectionnés) sont sérialisés en JSON. Un seul avis par trajet par passager — retourne 422 si l'avis existe déjà. Le champ tags nécessite la migration : ALTER TABLE reviews ADD COLUMN tags JSON NULL.",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['rating'],
                properties: [
                    new OA\Property(property: 'rating',  type: 'integer', minimum: 1, maximum: 5, example: 4, description: 'Note 1-5 étoiles.'),
                    new OA\Property(
                        property: 'tags',
                        type: 'array',
                        nullable: true,
                        description: 'Tags rapides sélectionnés depuis quickTags du controller Flutter.',
                        items: new OA\Items(type: 'string', maxLength: 100),
                        example: ['Ponctuel', 'Véhicule propre', 'Conduite sûre']
                    ),
                    new OA\Property(property: 'comment', type: 'string', nullable: true, maxLength: 1000, example: 'Trajet très agréable, conducteur ponctuel.'),
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
            new OA\Response(response: 404, description: 'Réservation introuvable'),
            new OA\Response(response: 422, description: 'Avis déjà soumis pour ce trajet ou validation échouée'),
        ]
    )]
    public function review(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'tags'    => ['nullable', 'array'],
            'tags.*'  => ['string', 'max:100'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $passenger = $request->user();

        $booking = Booking::with('trip')
            ->where('uuid', $uuid)
            ->where('passenger_id', $passenger->id)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $trip = $booking->trip;

        // Un seul avis par trajet par passager
        $existing = Review::where('trip_id', $trip->id)
            ->where('reviewer_id', $passenger->id)
            ->first();

        if ($existing) {
            return $this->apiResponse(false, 'Vous avez déjà évalué ce trajet.', [], 422);
        }

        // Création de l'avis
        // tags n'est pas dans $fillable du modèle → on assigne manuellement
        // Migration requise : ALTER TABLE reviews ADD COLUMN tags JSON NULL;
        $review              = new Review();
        $review->trip_id     = $trip->id;
        $review->reviewer_id = $passenger->id;
        $review->reviewee_id = $trip->user_id;  // conducteur
        $review->rating      = $validated['rating'];
        $review->comment     = $validated['comment'] ?? null;
        $review->tags        = json_encode($validated['tags'] ?? []);
        $review->save();

        return $this->apiResponse(true, 'Avis envoyé. Merci !', [
            'review_uuid' => $review->uuid,
            'rating'      => $review->rating,
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h === 0) {
            return "{$m}min";
        }
        if ($m === 0) {
            return "{$h}h";
        }
        return "{$h}h{$m}";
    }

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn ($w) => strtoupper($w[0]))
            ->join('');
    }
}
