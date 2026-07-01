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

class DriverDashboardController extends Controller
{
    // =========================================================================
    //  GET /api/driver/dashboard
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/dashboard',
        operationId: 'driverDashboard',
        summary: 'Page d\'accueil conducteur (données agrégées)',
        description: <<<'DESC'
Retourne en un seul appel toutes les données nécessaires à la page d\'accueil du conducteur :
- `summary`   : gains aujourd\'hui / semaine / mois, nombre de demandes en attente, commission
- `metrics`   : trajets effectués, passagers transportés, note moyenne, taux de complétion
- `next_trip` : prochain trajet planifié avec les passagers confirmés (null si aucun)
- `wallet`    : solde disponible et montant bloqué en escrow
- `level`     : niveau actuel, progression et badges obtenus
- `is_online` / `availability_mode` : statut de disponibilité
DESC,
        tags: ['🏠 Dashboard Conducteur'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Données du tableau de bord'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        // ── IDs des trajets complétés par ce conducteur ───────────────────────
        $completedTripIds = Trip::where('user_id', $user->id)
            ->where('status', 'completed')
            ->pluck('id');

        // ── Gains (net reçu après commission) ─────────────────────────────────
        $paymentsBase = Payment::whereHas(
            'booking',
            fn ($q) => $q->whereIn('trip_id', $completedTripIds)
        )->where('status', 'success');

        $todayEarnings = (clone $paymentsBase)
            ->whereDate('created_at', today())
            ->sum('net_amount');

        $weekEarnings = (clone $paymentsBase)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('net_amount');

        $monthEarnings = (clone $paymentsBase)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('net_amount');

        $totalEarnings    = (clone $paymentsBase)->sum('net_amount');
        $totalCommission  = (clone $paymentsBase)->sum('commission_amount');

        // ── Demandes en attente ────────────────────────────────────────────────
        $pendingCount = Booking::whereHas(
            'trip',
            fn ($q) => $q->where('user_id', $user->id)
        )->where('status', 'pending')->count();

        // ── Wallet ────────────────────────────────────────────────────────────
        $totalWithdrawn = Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->sum('amount');

        $availableBalance = max(0, $totalEarnings - $totalWithdrawn);

        $blockedAmount = Payment::whereHas(
            'booking',
            fn ($q) => $q->whereHas('trip', fn ($q2) => $q2->where('user_id', $user->id))
        )->where('status', 'locked')->sum('net_amount');

        // ── Metrics ───────────────────────────────────────────────────────────
        $tripsCompleted = $completedTripIds->count();

        $passengersTransported = Booking::whereIn('trip_id', $completedTripIds)
            ->where('status', 'accepted')
            ->sum('seats_booked');

        $avgRating = $user->averageRating();

        $totalDriverTrips = Trip::where('user_id', $user->id)->count();
        $completionRate   = $totalDriverTrips > 0
            ? round($tripsCompleted / $totalDriverTrips, 2)
            : 0.0;

        // ── Prochain trajet ───────────────────────────────────────────────────
        $nextTrip = Trip::with([
                'bookings' => fn ($q) => $q->where('status', 'accepted')
                    ->with('passenger.profile'),
            ])
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'active'])
            ->where('departure_time', '>', now())
            ->orderBy('departure_time')
            ->first();

        // ── Niveau ────────────────────────────────────────────────────────────
        $level = $this->computeLevel($tripsCompleted, $avgRating, (int) $passengersTransported);

        return $this->apiResponse(true, 'Tableau de bord conducteur.', [
            'summary' => [
                'today_earnings'   => (int) $todayEarnings,
                'week_earnings'    => (int) $weekEarnings,
                'month_earnings'   => (int) $monthEarnings,
                'pending_count'    => $pendingCount,
                'total_commission' => (int) $totalCommission,
            ],
            'metrics' => [
                [
                    'key'      => 'trips_completed',
                    'value'    => $tripsCompleted,
                    'label'    => 'Trajets',
                    'progress' => null,
                ],
                [
                    'key'      => 'passengers_transported',
                    'value'    => (int) $passengersTransported,
                    'label'    => 'Passagers',
                    'progress' => null,
                ],
                [
                    'key'      => 'average_rating',
                    'value'    => $avgRating ?? 0,
                    'label'    => 'Note moyenne',
                    'progress' => $avgRating ? round($avgRating / 5, 2) : 0,
                ],
                [
                    'key'      => 'completion_rate',
                    'value'    => round($completionRate * 100) . '%',
                    'label'    => 'Taux complétion',
                    'progress' => $completionRate,
                ],
            ],
            'next_trip'         => $nextTrip ? $this->serializeNextTrip($nextTrip) : null,
            'wallet'            => [
                'available_balance' => (int) $availableBalance,
                'blocked_amount'    => (int) $blockedAmount,
            ],
            'level'             => $level,
            'is_online'         => (bool) $user->is_online,
            'availability_mode' => $user->availability_mode ?? 'normal',
        ]);
    }

    // =========================================================================
    //  PATCH /api/driver/availability
    // =========================================================================

    #[OA\Patch(
        path: '/api/driver/availability',
        operationId: 'driverUpdateAvailability',
        summary: 'Mettre à jour la disponibilité du conducteur',
        description: 'Bascule le statut en ligne / hors ligne et définit le mode (normal, pause, nuit).',
        tags: ['🏠 Dashboard Conducteur'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['is_online'],
                properties: [
                    new OA\Property(property: 'is_online', type: 'boolean', example: true),
                    new OA\Property(
                        property: 'mode',
                        type: 'string',
                        enum: ['normal', 'pause', 'night'],
                        example: 'normal'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Disponibilité mise à jour'),
            new OA\Response(response: 422, description: 'Données invalides'),
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
    //  HELPERS PRIVÉS
    // =========================================================================

    private function serializeNextTrip(Trip $trip): array
    {
        $passengers = $trip->bookings->map(fn ($b) => [
            'name'     => $this->fullName($b->passenger->profile ?? null),
            'initials' => $this->initials($b->passenger->profile ?? null),
        ])->values();

        return [
            'uuid'                    => $trip->uuid,
            'departure_city'          => $trip->departure_city,
            'departure_neighborhood'  => $trip->departure_neighborhood,
            'arrival_city'            => $trip->arrival_city,
            'arrival_neighborhood'    => $trip->arrival_neighborhood,
            'departure_time'          => $trip->departure_time?->toIso8601String(),
            'status'                  => $trip->status,
            'price_per_seat'          => $trip->price_per_seat,
            'available_seats'         => $trip->available_seats,
            'passengers'              => $passengers,
            'passengers_count'        => $passengers->count(),
        ];
    }

    private function computeLevel(int $trips, ?float $rating, int $passengers): array
    {
        [$levelName, $progress, $nextLevel, $tripsToNext] = match (true) {
            $trips >= 100 => ['Platine', 1.0,                      null,      0],
            $trips >= 50  => ['Or',      ($trips - 50)  / 50,      'Platine', 100 - $trips],
            $trips >= 10  => ['Argent',  ($trips - 10)  / 40,      'Or',      50  - $trips],
            default       => ['Bronze',   $trips         / 10,      'Argent',  10  - $trips],
        };

        $badges = [];

        if ($trips >= 1)                    $badges[] = ['key' => 'first_trip',  'label' => '1er trajet'];
        if ($rating !== null && $rating >= 4.5) $badges[] = ['key' => 'top_rated',  'label' => '5 étoiles'];
        if ($trips >= 10)                   $badges[] = ['key' => 'veteran',     'label' => 'Vétéran'];
        if ($passengers >= 100)             $badges[] = ['key' => 'popular',     'label' => 'Populaire'];

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
        if (! $profile) {
            return 'Passager';
        }

        return trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? '')) ?: 'Passager';
    }

    private function initials(?Profile $profile): string
    {
        if (! $profile) {
            return '?';
        }

        $first = strtoupper(substr($profile->first_name ?? '?', 0, 1));
        $last  = strtoupper(substr($profile->last_name  ?? '',  0, 1));

        return $first . $last ?: '?';
    }
}
