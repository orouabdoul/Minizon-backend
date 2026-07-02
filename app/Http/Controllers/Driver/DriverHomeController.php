<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DriverHomeController extends Controller
{
    // =========================================================================
    //  GET /api/driver/dashboard
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/dashboard',
        operationId: 'driverDashboard',
        summary: "Tableau de bord conducteur",
        description: "Retourne toutes les données de la page d'accueil conducteur en un seul appel : summary (gains aujourd'hui/semaine/mois, demandes en attente, commission), metrics (trajets, passagers, note, taux complétion), next_trip (prochain trajet planifié avec passagers), quick_requests (demandes urgentes en attente), recent_requests (5 dernières demandes), wallet (solde disponible et montant bloqué), level (niveau, progression, badges), disponibilité.",
        tags: ['🚗 Driver — Home'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Données du tableau de bord",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Tableau de bord conducteur.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'summary',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'today_earnings',   type: 'integer', example: 5000),
                                        new OA\Property(property: 'week_earnings',    type: 'integer', example: 28000),
                                        new OA\Property(property: 'month_earnings',   type: 'integer', example: 112000),
                                        new OA\Property(property: 'pending_count',    type: 'integer', example: 3),
                                        new OA\Property(property: 'total_commission', type: 'integer', example: 14000),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'metrics',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',      type: 'string'),
                                            new OA\Property(property: 'value',    example: 18),
                                            new OA\Property(property: 'label',    type: 'string'),
                                            new OA\Property(property: 'progress', type: 'number', nullable: true),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'next_trip',
                                    nullable: true,
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'uuid',                   type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'departure_city',          type: 'string', example: 'Cotonou'),
                                        new OA\Property(property: 'departure_neighborhood',  type: 'string', example: 'Cadjehoun'),
                                        new OA\Property(property: 'arrival_city',            type: 'string', example: 'Abomey-Calavi'),
                                        new OA\Property(property: 'arrival_neighborhood',    type: 'string', example: 'Godomey'),
                                        new OA\Property(property: 'departure_time',          type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'status',                  type: 'string', example: 'pending'),
                                        new OA\Property(property: 'price_per_seat',          type: 'integer', example: 1500),
                                        new OA\Property(property: 'available_seats',         type: 'integer', example: 2),
                                        new OA\Property(property: 'passengers_count',        type: 'integer', example: 2),
                                        new OA\Property(
                                            property: 'passengers',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: 'name',     type: 'string', example: 'Koffi Mensah'),
                                                    new OA\Property(property: 'initials', type: 'string', example: 'KM'),
                                                ]
                                            )
                                        ),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'quick_requests',
                                    description: 'Demandes en attente sur les trajets à venir (section urgente)',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/DriverBookingItem')
                                ),
                                new OA\Property(
                                    property: 'recent_requests',
                                    description: '5 dernières demandes toutes statuts confondus',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/DriverBookingItem')
                                ),
                                new OA\Property(
                                    property: 'wallet',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'available_balance', type: 'integer', example: 45000),
                                        new OA\Property(property: 'blocked_amount',    type: 'integer', example: 12000),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'level',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'current_level', type: 'string',  example: 'Argent'),
                                        new OA\Property(property: 'progress',      type: 'number',  example: 0.45),
                                        new OA\Property(property: 'next_level',    type: 'string',  nullable: true, example: 'Or'),
                                        new OA\Property(property: 'trips_to_next', type: 'integer', example: 22),
                                        new OA\Property(
                                            property: 'badges',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: 'key',   type: 'string', example: 'top_rated'),
                                                    new OA\Property(property: 'label', type: 'string', example: '5 étoiles'),
                                                ]
                                            )
                                        ),
                                    ]
                                ),
                                new OA\Property(property: 'is_online',         type: 'boolean', example: true),
                                new OA\Property(property: 'availability_mode', type: 'string',  enum: ['normal', 'pause', 'night'], example: 'normal'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Compte non approuvé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $completedTripIds = Trip::where('user_id', $user->id)
            ->where('status', 'completed')
            ->pluck('id');

        // ── Gains ─────────────────────────────────────────────────────────────
        $paymentsBase = Payment::whereHas(
            'booking',
            fn ($q) => $q->whereIn('trip_id', $completedTripIds)
        )->where('status', 'success');

        $todayEarnings   = (clone $paymentsBase)->whereDate('created_at', today())->sum('net_amount');
        $weekEarnings    = (clone $paymentsBase)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('net_amount');
        $monthEarnings   = (clone $paymentsBase)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('net_amount');
        $totalEarnings   = (clone $paymentsBase)->sum('net_amount');
        $totalCommission = (clone $paymentsBase)->sum('commission_amount');

        $pendingCount = Booking::whereHas('trip', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'pending')->count();

        // ── Wallet ────────────────────────────────────────────────────────────
        $totalWithdrawn   = Withdrawal::where('user_id', $user->id)->where('status', 'approved')->sum('amount');
        $availableBalance = max(0, $totalEarnings - $totalWithdrawn);
        $blockedAmount    = Payment::whereHas('booking', fn ($q) =>
            $q->whereHas('trip', fn ($q2) => $q2->where('user_id', $user->id))
        )->where('status', 'locked')->sum('net_amount');

        // ── Metrics ───────────────────────────────────────────────────────────
        $tripsCompleted        = $completedTripIds->count();
        $passengersTransported = (int) Booking::whereIn('trip_id', $completedTripIds)->where('status', 'accepted')->sum('seats_booked');
        $avgRating             = $user->averageRating();
        $totalDriverTrips      = Trip::where('user_id', $user->id)->count();
        $completionRate        = $totalDriverTrips > 0 ? round($tripsCompleted / $totalDriverTrips, 2) : 0.0;

        // ── Prochain trajet ───────────────────────────────────────────────────
        $nextTrip = Trip::with(['bookings' => fn ($q) => $q->where('status', 'accepted')->with('passenger.profile')])
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'active'])
            ->where('departure_time', '>', now())
            ->orderBy('departure_time')
            ->first();

        // ── Demandes urgentes (upcoming, status = pending) ────────────────────
        $quickRequests = Booking::with(['trip', 'passenger.profile'])
            ->whereHas('trip', fn ($q) => $q->where('user_id', $user->id)->where('departure_time', '>', now()))
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($b) => $this->serializeBooking($b));

        // ── 5 dernières demandes ──────────────────────────────────────────────
        $recentRequests = Booking::with(['trip', 'passenger.profile'])
            ->whereHas('trip', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($b) => $this->serializeBooking($b));

        return $this->apiResponse(true, 'Tableau de bord conducteur.', [
            'summary' => [
                'today_earnings'   => (int) $todayEarnings,
                'week_earnings'    => (int) $weekEarnings,
                'month_earnings'   => (int) $monthEarnings,
                'pending_count'    => $pendingCount,
                'total_commission' => (int) $totalCommission,
            ],
            'metrics' => [
                ['key' => 'trips_completed',        'value' => $tripsCompleted,        'label' => 'Trajets',          'progress' => null],
                ['key' => 'passengers_transported', 'value' => $passengersTransported, 'label' => 'Passagers',        'progress' => null],
                ['key' => 'average_rating',         'value' => $avgRating ?? 0,        'label' => 'Note moyenne',     'progress' => $avgRating ? round($avgRating / 5, 2) : 0],
                ['key' => 'completion_rate',        'value' => round($completionRate * 100) . '%', 'label' => 'Taux complétion', 'progress' => $completionRate],
            ],
            'next_trip'        => $nextTrip ? $this->serializeNextTrip($nextTrip) : null,
            'quick_requests'   => $quickRequests,
            'recent_requests'  => $recentRequests,
            'wallet'           => [
                'available_balance' => (int) $availableBalance,
                'blocked_amount'    => (int) $blockedAmount,
            ],
            'level'            => $this->computeLevel($tripsCompleted, $avgRating, $passengersTransported),
            'is_online'        => (bool) $user->is_online,
            'availability_mode'=> $user->availability_mode ?? 'normal',
        ]);
    }

    // =========================================================================
    //  PATCH /api/driver/availability
    // =========================================================================

    #[OA\Patch(
        path: '/api/driver/availability',
        operationId: 'driverUpdateAvailability',
        summary: "Mettre à jour la disponibilité",
        description: "Bascule le statut en ligne / hors ligne et définit le mode (normal, pause, night).",
        tags: ['🚗 Driver — Home'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['is_online'],
                properties: [
                    new OA\Property(property: 'is_online', type: 'boolean', example: true),
                    new OA\Property(property: 'mode',      type: 'string',  enum: ['normal', 'pause', 'night'], example: 'normal'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Disponibilité mise à jour',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Disponibilité mise à jour.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'is_online',         type: 'boolean', example: true),
                                new OA\Property(property: 'availability_mode', type: 'string',  example: 'normal'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Compte non approuvé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function updateAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_online' => 'required|boolean',
            'mode'      => 'sometimes|string|in:normal,pause,night',
        ]);

        $user = $request->user();

        $user->update([
            'is_online'         => $validated['is_online'],
            'availability_mode' => $validated['mode'] ?? $user->availability_mode,
        ]);

        return $this->apiResponse(true, 'Disponibilité mise à jour.', [
            'is_online'         => (bool) $user->is_online,
            'availability_mode' => $user->availability_mode,
        ]);
    }

    // =========================================================================
    //  GET /api/driver/stats
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/stats',
        operationId: 'driverStats',
        summary: "Statistiques financières conducteur",
        description: "Gains totaux, solde disponible, total retiré, trajets complétés, passagers transportés et note moyenne.",
        tags: ['🚗 Driver — Home'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total_earnings',         type: 'integer', example: 125000),
                                new OA\Property(property: 'available_balance',      type: 'integer', example: 45000),
                                new OA\Property(property: 'total_withdrawals',      type: 'integer', example: 80000),
                                new OA\Property(property: 'trips_completed',        type: 'integer', example: 18),
                                new OA\Property(property: 'passengers_transported', type: 'integer', example: 42),
                                new OA\Property(property: 'average_rating',         type: 'number',  nullable: true, example: 4.6),
                                new OA\Property(property: 'total_reviews',          type: 'integer', example: 14),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $completedTripIds = Trip::where('user_id', $user->id)->where('status', 'completed')->pluck('id');

        $totalEarnings   = Payment::whereHas('booking', fn ($q) => $q->whereIn('trip_id', $completedTripIds))->where('status', 'success')->sum('net_amount');
        $totalWithdrawn  = Withdrawal::where('user_id', $user->id)->where('status', 'approved')->sum('amount');
        $passengers      = Booking::whereIn('trip_id', $completedTripIds)->where('status', 'accepted')->sum('seats_booked');

        return $this->apiResponse(true, 'Statistiques conducteur.', [
            'total_earnings'         => (int) $totalEarnings,
            'available_balance'      => (int) max(0, $totalEarnings - $totalWithdrawn),
            'total_withdrawals'      => (int) $totalWithdrawn,
            'trips_completed'        => $completedTripIds->count(),
            'passengers_transported' => (int) $passengers,
            'average_rating'         => $user->averageRating(),
            'total_reviews'          => $user->reviewsReceived()->count(),
        ]);
    }

    // =========================================================================
    //  SCHEMAS PARTAGÉS
    // =========================================================================

    #[OA\Schema(
        schema: 'DriverBookingItem',
        properties: [
            new OA\Property(property: 'uuid',            type: 'string', format: 'uuid'),
            new OA\Property(property: 'status',          type: 'string', example: 'pending'),
            new OA\Property(property: 'seats_booked',    type: 'integer', example: 2),
            new OA\Property(property: 'created_at',      type: 'string', format: 'date-time'),
            new OA\Property(
                property: 'passenger',
                type: 'object',
                properties: [
                    new OA\Property(property: 'name',     type: 'string', example: 'Koffi Mensah'),
                    new OA\Property(property: 'initials', type: 'string', example: 'KM'),
                    new OA\Property(property: 'rating',   type: 'number', nullable: true, example: 4.2),
                ]
            ),
            new OA\Property(
                property: 'trip',
                type: 'object',
                properties: [
                    new OA\Property(property: 'uuid',           type: 'string', format: 'uuid'),
                    new OA\Property(property: 'departure_city', type: 'string', example: 'Cotonou'),
                    new OA\Property(property: 'arrival_city',   type: 'string', example: 'Abomey-Calavi'),
                    new OA\Property(property: 'departure_time', type: 'string', format: 'date-time'),
                ]
            ),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function serializeNextTrip(Trip $trip): array
    {
        $passengers = $trip->bookings->map(fn ($b) => [
            'name'     => $this->fullName($b->passenger->profile ?? null),
            'initials' => $this->initials($b->passenger->profile ?? null),
        ])->values();

        return [
            'uuid'                   => $trip->uuid,
            'departure_city'         => $trip->departure_city,
            'departure_neighborhood' => $trip->departure_neighborhood,
            'arrival_city'           => $trip->arrival_city,
            'arrival_neighborhood'   => $trip->arrival_neighborhood,
            'departure_time'         => $trip->departure_time?->toIso8601String(),
            'status'                 => $trip->status,
            'price_per_seat'         => $trip->price_per_seat,
            'available_seats'        => $trip->available_seats,
            'passengers'             => $passengers,
            'passengers_count'       => $passengers->count(),
        ];
    }

    private function serializeBooking(Booking $booking): array
    {
        $profile  = $booking->passenger->profile ?? null;
        $avgRating = $booking->passenger->averageRating();

        return [
            'uuid'         => $booking->uuid,
            'status'       => $booking->status,
            'seats_booked' => $booking->seats_booked,
            'created_at'   => $booking->created_at->toIso8601String(),
            'passenger'    => [
                'name'     => $this->fullName($profile),
                'initials' => $this->initials($profile),
                'rating'   => $avgRating,
            ],
            'trip' => [
                'uuid'           => $booking->trip->uuid,
                'departure_city' => $booking->trip->departure_city,
                'arrival_city'   => $booking->trip->arrival_city,
                'departure_time' => $booking->trip->departure_time?->toIso8601String(),
            ],
        ];
    }

    private function computeLevel(int $trips, ?float $rating, int $passengers): array
    {
        [$levelName, $progress, $nextLevel, $tripsToNext] = match (true) {
            $trips >= 100 => ['Platine', 1.0,                 null,      0],
            $trips >= 50  => ['Or',      ($trips - 50) / 50,  'Platine', 100 - $trips],
            $trips >= 10  => ['Argent',  ($trips - 10) / 40,  'Or',      50  - $trips],
            default       => ['Bronze',   $trips        / 10,  'Argent',  10  - $trips],
        };

        $badges = [];
        if ($trips >= 1)                        $badges[] = ['key' => 'first_trip', 'label' => '1er trajet'];
        if ($rating !== null && $rating >= 4.5) $badges[] = ['key' => 'top_rated',  'label' => '5 étoiles'];
        if ($trips >= 10)                       $badges[] = ['key' => 'veteran',    'label' => 'Vétéran'];
        if ($passengers >= 100)                 $badges[] = ['key' => 'popular',    'label' => 'Populaire'];

        return [
            'current_level' => $levelName,
            'progress'      => round(min(1.0, $progress), 2),
            'next_level'    => $nextLevel,
            'trips_to_next' => $tripsToNext,
            'badges'        => $badges,
        ];
    }

    private function fullName(?Profile $profile): string
    {
        if (! $profile) return 'Passager';
        return trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? '')) ?: 'Passager';
    }

    private function initials(?Profile $profile): string
    {
        if (! $profile) return '?';
        return strtoupper(substr($profile->first_name ?? '?', 0, 1) . substr($profile->last_name ?? '', 0, 1)) ?: '?';
    }
}
