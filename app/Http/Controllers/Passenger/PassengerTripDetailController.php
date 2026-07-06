<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Détail du trajet" (DetailJourneyView) — vue passager pré-réservation.
 *
 * Retourne les données formatées pour le Flutter : SearchRide complet,
 * métriques conducteur, avis récents, statut de réservation existante.
 *
 * Endpoints existants réutilisés par le Flutter depuis cette page :
 *   bookNow           → POST /api/trips/{uuid}/bookings
 *   contactDriver     → POST /api/bookings/{uuid}/conversation
 *   cancelReservation → POST /api/bookings/{uuid}/cancel
 *   onViewAllReviews  → GET  /api/trips/{uuid}/reviews
 *
 * Favorites : aucun modèle DB — retourne is_favorite=false, l'état est géré
 * localement dans le Flutter controller jusqu'à l'ajout d'une table.
 */
class PassengerTripDetailController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/trips/{uuid}/detail
    //  Données complètes de la page détail trajet
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/trips/{uuid}/detail',
        operationId: 'passengerTripDetail',
        summary: 'Détail complet d\'un trajet (vue passager)',
        description: "Retourne toutes les données nécessaires à `DetailJourneyView` : objet `SearchRide`, métriques du conducteur, 2 derniers avis, statut de réservation existante du passager connecté et préférences du trajet.",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détail du trajet',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Détail du trajet.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'ride',                  ref: '#/components/schemas/SearchRide'),
                                new OA\Property(
                                    property: 'driver_metrics',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'acceptance_rate', type: 'string', example: '97%'),
                                        new OA\Property(property: 'response_time',   type: 'string', example: '~5 min'),
                                        new OA\Property(property: 'member_since',    type: 'string', example: '3 ans'),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'recent_reviews',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'reviewer_name', type: 'string', example: 'Marie Kouadio'),
                                            new OA\Property(property: 'rating',        type: 'number', example: 4.8),
                                            new OA\Property(property: 'date',          type: 'string', example: 'Il y a 2 jours'),
                                            new OA\Property(property: 'comment',       type: 'string'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'preferences',
                                    type: 'object',
                                    nullable: true,
                                    description: 'Préférences du trajet issues du champ JSON Trip.preferences.'
                                ),
                                new OA\Property(property: 'is_favorite',            type: 'boolean', example: false, description: 'Toujours false — modèle Favorites non encore implémenté.'),
                                new OA\Property(property: 'is_existing_reservation', type: 'boolean', example: false),
                                new OA\Property(property: 'reservation_uuid',        type: 'string', format: 'uuid', nullable: true),
                                new OA\Property(
                                    property: 'reservation_status',
                                    type: 'string',
                                    nullable: true,
                                    enum: ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'],
                                    description: 'Statut Flutter dérivé — null si pas de réservation.'
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::with(['user.profile', 'vehicle'])
            ->where('uuid', $uuid)
            ->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        $user   = $request->user();
        $driver = $trip->user;
        $tz     = 'Africa/Porto-Novo';

        // ── SearchRide ────────────────────────────────────────────────────────
        $profile  = $driver?->profile;
        $vehicle  = $trip->vehicle;

        $driverFirstName = $profile?->first_name ?? '';
        $driverLastName  = $profile?->last_name  ?? '';
        $driverName      = trim("$driverFirstName $driverLastName") ?: 'Conducteur';

        $allDriverReviews = $driver
            ? Review::where('reviewee_id', $driver->id)->get()
            : collect();
        $avgRating    = $allDriverReviews->count() > 0
            ? (string) round($allDriverReviews->avg('rating'), 1)
            : '—';
        $reviewCount  = $allDriverReviews->count();

        $depTime = $trip->departure_time?->setTimezone($tz);
        $arrTime = $trip->estimated_arrival_time?->setTimezone($tz);

        // Durée en heures et minutes
        $durationLabel = '—';
        if ($trip->estimated_duration_minutes) {
            $h = intdiv($trip->estimated_duration_minutes, 60);
            $m = $trip->estimated_duration_minutes % 60;
            $durationLabel = $h > 0 ? "{$h}h" . ($m > 0 ? " {$m}min" : '') : "{$m} min";
        }

        // Waypoints (premier arrêt intermédiaire pour la vue)
        $waypoints = $trip->waypoints ?? [];
        $firstWaypoint = $waypoints[0] ?? null;

        $ride = [
            'uuid'            => $trip->uuid,
            'driver_name'     => $driverName,
            'driver_initials' => $this->initials($driverName),
            'rating'          => $avgRating,
            'review_count'    => $reviewCount,
            'vehicle'         => $vehicle ? trim("{$vehicle->brand} {$vehicle->model}") : '—',
            'vehicle_plate'   => $vehicle?->license_plate ?? '—',
            'origin'          => $trip->departure_city   ?? '—',
            'destination'     => $trip->arrival_city     ?? '—',
            'departure_time'  => $depTime?->format('H\hi') ?? '—',
            'arrival_time'    => $arrTime?->format('H\hi') ?? '—',
            'departure_note'  => $trip->departure_neighborhood ?? $trip->departure_point ?? '',
            'arrival_note'    => $trip->arrival_neighborhood   ?? $trip->arrival_point   ?? '',
            'duration'        => $durationLabel,
            'price'           => number_format((int) $trip->price_per_seat, 0, ',', ' ') . ' FCFA',
            'available_seats' => (int) $trip->available_seats,
            // Arrêt intermédiaire (pour _ItineraryCard)
            'waypoint_city'    => $firstWaypoint['city']   ?? null,
            'waypoint_note'    => $firstWaypoint['note']   ?? null,
        ];

        // ── Métriques conducteur ──────────────────────────────────────────────
        $driverMetrics = $this->buildDriverMetrics($driver, $tz);

        // ── 2 avis récents ────────────────────────────────────────────────────
        $recentReviews = $this->buildRecentReviews($trip->id, $tz);

        // ── Réservation existante pour ce passager ────────────────────────────
        $existingBooking = $driver
            ? Booking::with(['trip'])
                ->where('trip_id', $trip->id)
                ->where('passenger_id', $user->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->first()
            : null;

        $isExisting        = (bool) $existingBooking;
        $reservationUuid   = $existingBooking?->uuid;
        $reservationStatus = $existingBooking ? $this->deriveStatus($existingBooking) : null;

        return $this->apiResponse(true, 'Détail du trajet.', [
            'ride'                    => $ride,
            'driver_metrics'          => $driverMetrics,
            'recent_reviews'          => $recentReviews,
            'preferences'             => $trip->preferences,
            'is_favorite'             => false,
            'is_existing_reservation' => $isExisting,
            'reservation_uuid'        => $reservationUuid,
            'reservation_status'      => $reservationStatus,
        ]);
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'SearchRide',
        description: 'Objet trajet utilisé dans les pages recherche et détail Flutter.',
        properties: [
            new OA\Property(property: 'uuid',            type: 'string', format: 'uuid'),
            new OA\Property(property: 'driver_name',     type: 'string', example: 'Koffi Adjovi'),
            new OA\Property(property: 'driver_initials', type: 'string', example: 'KA'),
            new OA\Property(property: 'rating',          type: 'string', example: '4.8'),
            new OA\Property(property: 'review_count',    type: 'integer', example: 247),
            new OA\Property(property: 'vehicle',         type: 'string', example: 'Toyota Corolla'),
            new OA\Property(property: 'vehicle_plate',   type: 'string', example: 'AB-123-CD'),
            new OA\Property(property: 'origin',          type: 'string', example: 'Cotonou'),
            new OA\Property(property: 'destination',     type: 'string', example: 'Abomey-Calavi'),
            new OA\Property(property: 'departure_time',  type: 'string', example: '08h30'),
            new OA\Property(property: 'arrival_time',    type: 'string', example: '09h30'),
            new OA\Property(property: 'departure_note',  type: 'string', example: 'Carrefour Vêdoko'),
            new OA\Property(property: 'arrival_note',    type: 'string', example: 'Université d\'Abomey-Calavi'),
            new OA\Property(property: 'duration',        type: 'string', example: '1h 30min'),
            new OA\Property(property: 'price',           type: 'string', example: '1 500 FCFA'),
            new OA\Property(property: 'available_seats', type: 'integer', example: 3),
            new OA\Property(property: 'waypoint_city',   type: 'string', nullable: true, example: 'Calavi'),
            new OA\Property(property: 'waypoint_note',   type: 'string', nullable: true, example: 'Carrefour PK14'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function buildDriverMetrics(?\App\Models\User $driver, string $tz): array
    {
        if (! $driver) {
            return ['acceptance_rate' => '—', 'response_time' => '—', 'member_since' => '—'];
        }

        // Taux d'acceptation : (accepted + rejected) bookings du conducteur
        $driverTripIds = Trip::where('user_id', $driver->id)->pluck('id');
        $totalResponded = Booking::whereIn('trip_id', $driverTripIds)
            ->whereIn('status', ['accepted', 'rejected'])
            ->count();
        $accepted = Booking::whereIn('trip_id', $driverTripIds)
            ->where('status', 'accepted')
            ->count();
        $acceptanceRate = $totalResponded > 0
            ? round(($accepted / $totalResponded) * 100) . '%'
            : '—';

        // Temps de réponse moyen (approximé : updated_at - created_at pour les acceptées)
        $avgResponseSec = Booking::whereIn('trip_id', $driverTripIds)
            ->where('status', 'accepted')
            ->whereNotNull('updated_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_sec')
            ->value('avg_sec');
        $responseTime = $avgResponseSec !== null
            ? ($avgResponseSec < 60
                ? '~1 min'
                : '~' . round($avgResponseSec / 60) . ' min')
            : '—';

        // Ancienneté sur la plateforme
        $memberSince = $driver->created_at
            ? $this->formatMemberSince($driver->created_at)
            : '—';

        return [
            'acceptance_rate' => $acceptanceRate,
            'response_time'   => $responseTime,
            'member_since'    => $memberSince,
        ];
    }

    private function buildRecentReviews(int $tripId, string $tz): array
    {
        return Review::with(['reviewer.profile'])
            ->where('trip_id', $tripId)
            ->orderByDesc('created_at')
            ->take(2)
            ->get()
            ->map(function (Review $r) use ($tz) {
                $reviewerProfile = $r->reviewer?->profile;
                $firstName = $reviewerProfile?->first_name ?? '';
                $lastName  = $reviewerProfile?->last_name  ?? '';
                $name = trim("$firstName $lastName") ?: 'Passager';

                $diff = $r->created_at?->setTimezone($tz)->diffInDays(now());
                $date = match (true) {
                    $diff === 0 => "Aujourd'hui",
                    $diff === 1 => 'Hier',
                    $diff <= 6  => "Il y a {$diff} jours",
                    $diff <= 13 => 'Il y a 1 semaine',
                    $diff <= 20 => 'Il y a 2 semaines',
                    $diff <= 27 => 'Il y a 3 semaines',
                    default     => 'Il y a ' . (int) ($diff / 30) . ' mois',
                };

                return [
                    'reviewer_name' => $name,
                    'rating'        => (float) $r->rating,
                    'date'          => $date,
                    'comment'       => $r->comment ?? '',
                ];
            })
            ->toArray();
    }

    private function deriveStatus(Booking $booking): string
    {
        if (in_array($booking->status, ['cancelled', 'rejected'], true)) {
            return 'cancelled';
        }
        if ($booking->status === 'pending') return 'pending';
        if ($booking->status === 'accepted') {
            return match ($booking->trip?->status) {
                'active'    => 'in_progress',
                'completed' => 'completed',
                default     => 'confirmed',
            };
        }
        return 'pending';
    }

    private function formatMemberSince(\Carbon\CarbonInterface $date): string
    {
        $months = (int) $date->diffInMonths(now());
        if ($months < 1)  return 'Nouveau';
        if ($months < 12) return "{$months} mois";
        $years = (int) ($months / 12);
        return $years === 1 ? '1 an' : "{$years} ans";
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
