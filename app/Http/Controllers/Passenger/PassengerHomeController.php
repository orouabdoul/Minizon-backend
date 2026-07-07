<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PassengerHomeController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/home
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/home',
        operationId: 'passengerHome',
        summary: "Tableau de bord passager",
        description: "Retourne toutes les données de la page d'accueil passager en un seul appel : greeting, upcoming_trip (trajet actif ou imminent avec infos conducteur, ETA, progression), hero_metrics (trajets effectués, dépenses, CO2), popular_routes, available_rides (trajets disponibles à réserver), recommended_drivers, special_offers, recent_activities.",
        tags: ['👤 Passenger — Home'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Données du tableau de bord passager",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Tableau de bord passager.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'greeting', type: 'string', example: 'Bonjour, Koffi 👋'),
                                new OA\Property(
                                    property: 'upcoming_trip',
                                    nullable: true,
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'booking_uuid',       type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'trip_uuid',          type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'status',             type: 'string', enum: ['upcoming', 'driver_arriving', 'in_progress'], example: 'upcoming'),
                                        new OA\Property(property: 'origin',             type: 'string', example: 'Cotonou — Cadjehoun'),
                                        new OA\Property(property: 'destination',        type: 'string', example: 'Abomey-Calavi — Godomey'),
                                        new OA\Property(property: 'eta_minutes',        type: 'integer', nullable: true, example: 15),
                                        new OA\Property(property: 'trip_progress',      type: 'number', example: 0.35),
                                        new OA\Property(property: 'departure_time',     type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'driver_name',        type: 'string', example: 'Moussa Alabi'),
                                        new OA\Property(property: 'driver_initials',    type: 'string', example: 'MA'),
                                        new OA\Property(property: 'driver_rating',      type: 'number', example: 4.8),
                                        new OA\Property(property: 'driver_vehicle',     type: 'string', example: 'Toyota Corolla Blanc'),
                                        new OA\Property(property: 'driver_level',       type: 'string', example: 'Or'),
                                        new OA\Property(property: 'driver_trips',       type: 'integer', example: 67),
                                        new OA\Property(property: 'driver_level_progress', type: 'number', example: 0.34),
                                        new OA\Property(
                                            property: 'driver_badges',
                                            type: 'array',
                                            items: new OA\Items(type: 'string', example: '5 étoiles')
                                        ),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'hero_metrics',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'label', type: 'string', example: 'Trajets'),
                                            new OA\Property(property: 'value', type: 'string', example: '12'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'popular_routes',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'label',          type: 'string', example: 'Cotonou → Abomey-Calavi'),
                                            new OA\Property(property: 'departure_city', type: 'string', example: 'Cotonou'),
                                            new OA\Property(property: 'arrival_city',   type: 'string', example: 'Abomey-Calavi'),
                                            new OA\Property(property: 'count',          type: 'integer', example: 42),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'available_rides',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/PassengerRideItem')
                                ),
                                new OA\Property(
                                    property: 'recommended_drivers',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/PassengerDriverItem')
                                ),
                                new OA\Property(
                                    property: 'special_offers',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',      type: 'string', example: 'first_trip'),
                                            new OA\Property(property: 'title',    type: 'string', example: '1er trajet offert'),
                                            new OA\Property(property: 'subtitle', type: 'string', example: 'Utilisez le code BIENVENUE'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'recent_activities',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'booking_uuid', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'route',        type: 'string', example: 'Cotonou → Abomey-Calavi'),
                                            new OA\Property(property: 'time',         type: 'string', example: 'Hier, 14h30'),
                                            new OA\Property(property: 'status',       type: 'string', example: 'accepted'),
                                            new OA\Property(property: 'price',        type: 'integer', example: 1500),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->apiResponse(true, 'Tableau de bord passager.', [
            'greeting'            => $this->buildGreeting($user),
            'upcoming_trip'       => $this->upcomingTrip($user),
            'hero_metrics'        => $this->heroMetrics($user),
            'popular_routes'      => $this->popularRoutes(),
            'available_rides'     => $this->availableRides($user, limit: 5),
            'recommended_drivers' => $this->recommendedDrivers(limit: 5),
            'special_offers'      => $this->specialOffers(),
            'recent_activities'   => $this->recentActivities($user, limit: 5),
        ]);
    }

    // =========================================================================
    //  SCHEMAS PARTAGÉS
    // =========================================================================

    #[OA\Schema(
        schema: 'PassengerRideItem',
        properties: [
            new OA\Property(property: 'uuid',          type: 'string', format: 'uuid'),
            new OA\Property(property: 'from',          type: 'string', example: 'Cotonou'),
            new OA\Property(property: 'to',            type: 'string', example: 'Abomey-Calavi'),
            new OA\Property(property: 'schedule',      type: 'string', example: 'Aujourd\'hui, 14h30'),
            new OA\Property(property: 'price',         type: 'string', example: '1 500 FCFA'),
            new OA\Property(property: 'price_raw',     type: 'integer', example: 1500),
            new OA\Property(property: 'seats_left',    type: 'string', example: '2 places'),
            new OA\Property(property: 'driver_name',   type: 'string', example: 'Moussa Alabi'),
            new OA\Property(property: 'driver_vehicle', type: 'string', example: 'Toyota Corolla Blanc'),
        ]
    )]
    #[OA\Schema(
        schema: 'PassengerDriverItem',
        properties: [
            new OA\Property(property: 'uuid',        type: 'string', format: 'uuid'),
            new OA\Property(property: 'name',        type: 'string', example: 'Moussa Alabi'),
            new OA\Property(property: 'initials',    type: 'string', example: 'MA'),
            new OA\Property(property: 'vehicle',     type: 'string', example: 'Toyota Corolla Blanc'),
            new OA\Property(property: 'rating',      type: 'string', example: '4.8'),
            new OA\Property(property: 'trips_count', type: 'string', example: '67 trajets'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function buildGreeting(User $user): string
    {
        $hour  = (int) now()->format('H');
        $salut = match (true) {
            $hour >= 5  && $hour < 12 => 'Bonjour',
            $hour >= 12 && $hour < 18 => 'Bon après-midi',
            $hour >= 18 && $hour < 22 => 'Bonsoir',
            default                   => 'Bonne nuit',
        };

        $firstName = $user->profile?->first_name ?? '';
        return $firstName ? "$salut, $firstName" : $salut;
    }

    private function upcomingTrip(User $user): ?array
    {
        $booking = Booking::with(['trip.user.profile', 'trip.vehicle'])
            ->where('passenger_id', $user->id)
            ->whereIn('status', ['accepted', 'pending'])
            ->whereHas('trip', fn ($q) => $q->whereIn('status', ['pending', 'active']))
            ->orderByRaw("CASE WHEN status = 'accepted' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $booking) {
            return null;
        }

        $trip   = $booking->trip;
        $driver = $trip->user;

        // ── Status du trajet ──────────────────────────────────────────────────
        $tripStatus = 'upcoming';
        if ($trip->status === 'active') {
            $tripStatus = $booking->picked_up_at ? 'in_progress' : 'driver_arriving';
        }

        // ── ETA ───────────────────────────────────────────────────────────────
        $etaMinutes = null;
        if ($tripStatus === 'in_progress' && $trip->started_at && $trip->estimated_duration_minutes) {
            $elapsed    = now()->diffInMinutes($trip->started_at);
            $remaining  = $trip->estimated_duration_minutes - $elapsed;
            $etaMinutes = max(0, (int) $remaining);
        } elseif ($tripStatus === 'upcoming' && $trip->departure_time) {
            $etaMinutes = max(0, (int) now()->diffInMinutes($trip->departure_time, false));
        }

        // ── Progression du trajet ─────────────────────────────────────────────
        $tripProgress = 0.0;
        if ($tripStatus === 'in_progress' && $trip->started_at && $trip->estimated_duration_minutes > 0) {
            $elapsed      = now()->diffInMinutes($trip->started_at);
            $tripProgress = round(min(1.0, $elapsed / $trip->estimated_duration_minutes), 2);
        }

        // ── Infos conducteur ──────────────────────────────────────────────────
        $driverProfile  = $driver->profile;
        $driverName     = $this->fullName($driverProfile);
        $driverInitials = $this->initials($driverProfile);
        $driverRating   = $driver->averageRating() ?? 0.0;
        $driverTrips    = Trip::where('user_id', $driver->id)->where('status', 'completed')->count();

        $vehicle = $trip->vehicle;
        $driverVehicle = $vehicle
            ? trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '') . ' ' . ($vehicle->color ?? ''))
            : '';

        $level = $this->computeLevel($driverTrips, $driverRating);

        return [
            'booking_uuid'          => $booking->uuid,
            'trip_uuid'             => $trip->uuid,
            'status'                => $tripStatus,
            'origin'                => $this->formatLocation($trip->departure_city, $trip->departure_neighborhood),
            'destination'           => $this->formatLocation($trip->arrival_city, $trip->arrival_neighborhood),
            'eta_minutes'           => $etaMinutes,
            'trip_progress'         => $tripProgress,
            'departure_time'        => $trip->departure_time?->toIso8601String(),
            'driver_name'           => $driverName,
            'driver_initials'       => $driverInitials,
            'driver_rating'         => round((float) $driverRating, 1),
            'driver_vehicle'        => $driverVehicle,
            'driver_level'          => $level['current_level'],
            'driver_trips'          => $driverTrips,
            'driver_level_progress' => $level['progress'],
            'driver_badges'         => array_column($level['badges'], 'label'),
        ];
    }

    private function heroMetrics(User $user): array
    {
        $completedBookings = Booking::where('passenger_id', $user->id)
            ->whereIn('status', ['accepted'])
            ->whereHas('payment', fn ($q) => $q->where('status', 'success'))
            ->with('payment')
            ->get();

        $tripsCompleted = $completedBookings->count();
        $totalSpent     = (int) $completedBookings->sum(fn ($b) => $b->payment?->gross_amount ?? 0);

        // CO2 estimé : ~120g/km économisé vs voiture seule, trajet moyen 25 km
        $co2Kg = round($tripsCompleted * 120 * 25 / 1000, 1);

        return [
            ['label' => 'Trajets',       'value' => (string) $tripsCompleted],
            ['label' => 'Dépenses',      'value' => number_format($totalSpent, 0, ',', ' ') . ' F'],
            ['label' => 'CO2 économisé', 'value' => $co2Kg . ' kg'],
        ];
    }

    private function popularRoutes(int $limit = 6): array
    {
        return Trip::selectRaw('departure_city, arrival_city, COUNT(*) as count')
            ->where('status', '!=', 'cancelled')
            ->groupBy('departure_city', 'arrival_city')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'label'          => $row->departure_city . ' → ' . $row->arrival_city,
                'departure_city' => $row->departure_city,
                'arrival_city'   => $row->arrival_city,
                'count'          => (int) $row->count,
            ])
            ->values()
            ->toArray();
    }

    private function availableRides(User $user, int $limit = 5): array
    {
        return Trip::with(['user.profile', 'vehicle'])
            ->where('status', 'pending')
            ->where('is_published', true)
            ->where('available_seats', '>', 0)
            ->where('departure_time', '>', now())
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('bookings', fn ($q) =>
                $q->where('passenger_id', $user->id)->whereNotIn('status', ['rejected', 'cancelled'])
            )
            ->orderBy('departure_time')
            ->limit($limit)
            ->get()
            ->map(fn (Trip $trip) => $this->serializeRide($trip))
            ->toArray();
    }

    private function recommendedDrivers(int $limit = 5): array
    {
        return User::with(['profile', 'vehicle'])
            ->whereHas('role', fn ($q) => $q->where('name', 'driver'))
            ->whereHas('reviewsReceived')
            ->where('is_verified', true)
            ->withCount(['reviewsReceived'])
            ->withAvg('reviewsReceived', 'rating')
            ->orderByDesc('reviews_received_avg_rating')
            ->limit($limit)
            ->get()
            ->map(function (User $driver) {
                $profile    = $driver->profile;
                $name       = $this->fullName($profile);
                $driverTrips = Trip::where('user_id', $driver->id)->where('status', 'completed')->count();
                $vehicle    = $driver->vehicle;
                $vehicleStr = $vehicle
                    ? trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '') . ' ' . ($vehicle->color ?? ''))
                    : '';

                return [
                    'uuid'        => $driver->uuid,
                    'name'        => $name,
                    'initials'    => $this->initials($profile),
                    'vehicle'     => $vehicleStr,
                    'rating'      => number_format(round($driver->reviews_received_avg_rating ?? 0, 1), 1),
                    'trips_count' => $driverTrips . ' trajets',
                ];
            })
            ->toArray();
    }

    private function specialOffers(): array
    {
        return [
            [
                'key'      => 'morning_promo',
                'title'    => 'Trajet matinal',
                'subtitle' => 'Économisez sur vos trajets avant 8h',
            ],
            [
                'key'      => 'group_booking',
                'title'    => 'Réservation groupée',
                'subtitle' => 'Réservez 2 places, économisez 10%',
            ],
        ];
    }

    private function recentActivities(User $user, int $limit = 5): array
    {
        return Booking::with('trip')
            ->where('passenger_id', $user->id)
            ->whereIn('status', ['accepted', 'cancelled', 'rejected'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Booking $booking) {
                $trip  = $booking->trip;
                $route = $trip
                    ? ($trip->departure_city . ' → ' . $trip->arrival_city)
                    : 'Trajet supprimé';

                return [
                    'booking_uuid' => $booking->uuid,
                    'route'        => $route,
                    'time'         => $booking->created_at->diffForHumans(),
                    'status'       => $booking->status,
                    'price'        => $trip ? (int) $trip->price_per_seat : 0,
                ];
            })
            ->toArray();
    }

    // ── Sérialiseurs ──────────────────────────────────────────────────────────

    private function serializeRide(Trip $trip): array
    {
        $profile   = $trip->user->profile;
        $vehicle   = $trip->vehicle;
        $driverVehicle = $vehicle
            ? trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '') . ' ' . ($vehicle->color ?? ''))
            : '';

        return [
            'uuid'          => $trip->uuid,
            'from'          => $trip->departure_city,
            'to'            => $trip->arrival_city,
            'schedule'      => $trip->departure_time?->format('d/m, H\hi'),
            'price'         => number_format($trip->price_per_seat, 0, ',', ' ') . ' FCFA',
            'price_raw'     => (int) $trip->price_per_seat,
            'seats_left'    => $trip->available_seats . ' place' . ($trip->available_seats > 1 ? 's' : ''),
            'driver_name'   => $this->fullName($profile),
            'driver_vehicle'=> $driverVehicle,
        ];
    }

    // ── Niveau conducteur ─────────────────────────────────────────────────────

    private function computeLevel(int $trips, ?float $rating): array
    {
        [$levelName, $progress, $nextLevel] = match (true) {
            $trips >= 100 => ['Platine', 1.0,                null],
            $trips >= 50  => ['Or',      ($trips - 50) / 50, 'Platine'],
            $trips >= 10  => ['Argent',  ($trips - 10) / 40, 'Or'],
            default       => ['Bronze',   $trips        / 10, 'Argent'],
        };

        $badges = [];
        if ($trips >= 1)                        $badges[] = ['key' => 'first_trip', 'label' => '1er trajet'];
        if ($rating !== null && $rating >= 4.5) $badges[] = ['key' => 'top_rated',  'label' => '5 étoiles'];
        if ($trips >= 10)                       $badges[] = ['key' => 'veteran',    'label' => 'Expert'];
        if ($trips >= 50)                       $badges[] = ['key' => 'popular',    'label' => 'Populaire'];

        return [
            'current_level' => $levelName,
            'progress'      => round(min(1.0, $progress), 2),
            'next_level'    => $nextLevel,
            'badges'        => $badges,
        ];
    }

    // ── Utilitaires ───────────────────────────────────────────────────────────

    private function fullName(?Profile $profile): string
    {
        if (! $profile) return 'Conducteur';
        return trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? '')) ?: 'Conducteur';
    }

    private function initials(?Profile $profile): string
    {
        if (! $profile) return '?';
        return strtoupper(substr($profile->first_name ?? '?', 0, 1) . substr($profile->last_name ?? '', 0, 1)) ?: '?';
    }

    private function formatLocation(string $city, ?string $neighborhood): string
    {
        return $neighborhood ? "$city — $neighborhood" : $city;
    }
}
