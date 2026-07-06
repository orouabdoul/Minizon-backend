<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Recherche de trajets (SearchView).
 *
 * Retourne des objets `SearchRide` avec tous les champs calculés dont
 * le Flutter a besoin : minutesUntilDeparture, isVerified, rating formatté,
 * etc. Évite le N+1 en chargeant les stats de reviews en une seule requête.
 *
 * L'endpoint est public (aucun token requis) — la réservation qui suit
 * requiert auth:sanctum.
 *
 * Paramètres de recherche acceptés :
 *   origin       – ville de départ (LIKE %x%)
 *   destination  – ville d'arrivée (LIKE %x%)
 *   date         – YYYY-MM-DD
 *   passengers   – nombre de places min. (available_seats >=)
 *   max_price    – prix max par siège (XOF)
 *   sort         – 'price'|'time'|'rating' (défaut: 'time')
 */
class PassengerSearchController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/search
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/search',
        operationId: 'passengerSearch',
        summary: 'Rechercher des trajets (SearchView)',
        description: "Retourne une liste de `SearchRide` formatés pour l'affichage Flutter. Les champs calculés (`minutesUntilDeparture`, `rating`, `duration`) sont inclus. Limité à 50 résultats. Public — aucun token requis.",
        tags: ['👤 Passenger — Recherche'],
        parameters: [
            new OA\Parameter(name: 'origin',      in: 'query', required: false, schema: new OA\Schema(type: 'string',  example: 'Cotonou'), description: 'Ville de départ (LIKE)'),
            new OA\Parameter(name: 'destination', in: 'query', required: false, schema: new OA\Schema(type: 'string',  example: 'Porto-Novo'), description: 'Ville d\'arrivée (LIKE)'),
            new OA\Parameter(name: 'date',        in: 'query', required: false, schema: new OA\Schema(type: 'string',  format: 'date', example: '2026-07-06')),
            new OA\Parameter(name: 'passengers',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2), description: 'Places disponibles minimum'),
            new OA\Parameter(name: 'max_price',   in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 3000), description: 'Prix max par siège (XOF)'),
            new OA\Parameter(name: 'sort',        in: 'query', required: false, schema: new OA\Schema(type: 'string',  enum: ['price', 'time', 'rating'], example: 'time')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste de trajets SearchRide',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: '5 trajet(s) trouvé(s).'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 5),
                                new OA\Property(
                                    property: 'rides',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/SearchRide')
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function search(Request $request): JsonResponse
    {
        $tz = 'Africa/Porto-Novo';

        // ── Construction de la requête ────────────────────────────────────────
        $query = Trip::with(['user.profile', 'vehicle'])
            ->where('status', 'pending')
            ->where('departure_time', '>', now())
            ->where('available_seats', '>', 0);

        if ($request->filled('origin')) {
            $query->where('departure_city', 'like', '%' . $request->origin . '%');
        }

        if ($request->filled('destination')) {
            $query->where('arrival_city', 'like', '%' . $request->destination . '%');
        }

        if ($request->filled('date')) {
            $query->whereDate('departure_time', $request->date);
        }

        if ($request->filled('passengers') && (int) $request->passengers > 1) {
            $query->where('available_seats', '>=', (int) $request->passengers);
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_seat', '<=', (int) $request->max_price);
        }

        // Tri côté base (le Flutter peut retrier localement via filteredSortedRides)
        $sort = $request->input('sort', 'time');
        match ($sort) {
            'price'  => $query->orderBy('price_per_seat'),
            default  => $query->orderBy('departure_time'),
        };

        $trips = $query->limit(50)->get();

        if ($trips->isEmpty()) {
            return $this->apiResponse(true, 'Aucun trajet trouvé.', [
                'total' => 0,
                'rides' => [],
            ]);
        }

        // ── Stats de reviews : une seule requête pour tous les conducteurs ────
        $driverIds = $trips->pluck('user_id')->unique()->filter()->values();

        $reviewStats = Review::whereIn('reviewee_id', $driverIds)
            ->selectRaw('reviewee_id, COUNT(*) as review_count, AVG(rating) as avg_rating')
            ->groupBy('reviewee_id')
            ->get()
            ->keyBy('reviewee_id');

        // ── Formatage ─────────────────────────────────────────────────────────
        $rides = $trips->map(function (Trip $trip) use ($tz, $reviewStats, $sort) {
            $driver  = $trip->user;
            $profile = $driver?->profile;
            $vehicle = $trip->vehicle;

            // Nom et initiales du conducteur
            $firstName  = $profile?->first_name ?? '';
            $lastName   = $profile?->last_name  ?? '';
            $driverName = trim("$firstName $lastName") ?: 'Conducteur';

            // Notes
            $stats       = $reviewStats->get($driver?->id);
            $avgRating   = $stats ? round((float) $stats->avg_rating, 1) : 0.0;
            $reviewCount = $stats ? (int) $stats->review_count : 0;

            // Vérification du conducteur (champ is_verified ou verified_at sur Profile)
            $isVerified = (bool) ($profile?->is_verified ?? ($profile?->verified_at !== null));

            // Minutes avant le départ (positif = futur, ne peut être négatif ici car filtré)
            $minutesUntilDeparture = (int) max(0, now()->diffInMinutes($trip->departure_time, false));

            // Durée formatée
            $durationLabel = '—';
            if ($trip->estimated_duration_minutes) {
                $h = intdiv($trip->estimated_duration_minutes, 60);
                $m = $trip->estimated_duration_minutes % 60;
                $durationLabel = $h > 0 ? "{$h}h" . ($m > 0 ? " {$m}min" : '') : "{$m} min";
            }

            // Horaires
            $depTime = $trip->departure_time?->setTimezone($tz);
            $arrTime = $trip->estimated_arrival_time?->setTimezone($tz);

            return [
                'uuid'                  => $trip->uuid,
                'driver_name'           => $driverName,
                'driver_initials'       => $this->initials($driverName),
                'is_verified'           => $isVerified,
                'rating'                => $avgRating > 0 ? (string) $avgRating : '—',
                'review_count'          => $reviewCount,
                'price'                 => number_format((int) $trip->price_per_seat, 0, ',', ' ') . ' FCFA',
                'price_raw'             => (int) $trip->price_per_seat,
                'seats_available'       => (int) $trip->available_seats,
                'minutes_until_departure' => $minutesUntilDeparture,
                'duration'              => $durationLabel,
                'vehicle'               => $vehicle ? trim("{$vehicle->brand} {$vehicle->model}") : '—',
                'vehicle_plate'         => $vehicle?->license_plate ?? '—',
                'origin'                => $trip->departure_city   ?? '—',
                'destination'           => $trip->arrival_city     ?? '—',
                'departure_time'        => $depTime?->format('H\hi') ?? '—',
                'arrival_time'          => $arrTime?->format('H\hi') ?? '—',
                'departure_note'        => $trip->departure_neighborhood ?? $trip->departure_point ?? '',
                'arrival_note'          => $trip->arrival_neighborhood   ?? $trip->arrival_point   ?? '',
            ];
        });

        // Tri par rating côté PHP (nécessite les stats calculées)
        if ($sort === 'rating') {
            $rides = $rides->sortByDesc('rating')->values();
        }

        $count = $rides->count();

        return $this->apiResponse(true, "{$count} trajet" . ($count > 1 ? 's' : '') . " trouvé" . ($count > 1 ? 's' : '') . '.', [
            'total' => $count,
            'rides' => $rides->values()->toArray(),
        ]);
    }

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
