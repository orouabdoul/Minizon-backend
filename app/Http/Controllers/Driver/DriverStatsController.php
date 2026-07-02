<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

/**
 * Page "Statistiques" — métriques du conducteur par période.
 *
 * Périodes : day | week | month | year
 */
class DriverStatsController extends Controller
{
    // =========================================================================
    //  GET /api/driver/stats
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/stats',
        operationId: 'driverStats',
        summary: 'Statistiques du conducteur connecté',
        description: "Métriques agrégées par période : trajets, passagers, revenus, note moyenne, taux d'acceptation.\n\n**Périodes disponibles :** `day` · `week` · `month` · `year`",
        tags: ['📊 Driver — Statistiques'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'period', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['day', 'week', 'month', 'year'], default: 'week')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques récupérées'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $period = $request->query('period', 'week');
        $tz     = 'Africa/Porto-Novo';
        $now    = Carbon::now($tz);

        [$start, $end] = $this->periodBounds($period, $now);

        // ── Trajets du conducteur dans la période ──────────────────────────
        $trips = Trip::where('user_id', $user->id)
            ->whereBetween('created_at', [$start->copy()->setTimezone('UTC'), $end->copy()->setTimezone('UTC')])
            ->with(['bookings.payment'])
            ->get();

        $tripsCount       = $trips->count();
        $passengersCount  = $trips->sum(fn ($t) => $t->bookings->where('status', 'accepted')->sum('seats_booked'));
        $avgDurationMin   = $tripsCount > 0
            ? (int) round($trips->avg('estimated_duration_minutes') ?? 0)
            : 0;

        // ── Revenus dans la période ────────────────────────────────────────
        $tripIds         = $trips->pluck('id');
        $payments        = Payment::whereHas('booking', fn ($q) => $q->whereIn('trip_id', $tripIds))
            ->where('status', 'success')
            ->get();
        $totalRevenue    = (int) $payments->sum('gross_amount');
        $netRevenue      = (int) $payments->sum('net_amount');

        // ── Taux acceptation / annulation — tous bookings sur les trajets ─
        $bookings         = Booking::whereIn('trip_id', $tripIds)->get();
        $totalB           = $bookings->count();
        $acceptedB        = $bookings->where('status', 'accepted')->count();
        $cancelledB       = $bookings->where('status', 'cancelled')->count();
        $acceptanceRate   = $totalB > 0 ? round($acceptedB / $totalB, 2) : 0.0;
        $cancellationRate = $totalB > 0 ? round($cancelledB / $totalB, 2) : 0.0;

        // ── Note moyenne (tous temps) ──────────────────────────────────────
        $avgRating = round(
            Review::where('reviewee_id', $user->id)->avg('rating') ?? 0,
            1
        );

        // ── Objectif (approximatif : +20% du revenu de la période précédente) ─
        $objectiveRevenue = $this->objectiveRevenue($user->id, $period, $start, $tz);

        // ── Données graphique ─────────────────────────────────────────────
        $chartData = $this->buildChartData($period, $now, $tz, $user->id);

        return $this->apiResponse(true, 'Statistiques récupérées.', [
            'period'             => $period,
            'trips_count'        => $tripsCount,
            'passengers_count'   => $passengersCount,
            'average_rating'     => $avgRating,
            'acceptance_rate'    => $acceptanceRate,
            'cancellation_rate'  => $cancellationRate,
            'total_revenue'      => $totalRevenue,
            'net_revenue'        => $netRevenue,
            'distance_km'        => 0, // requiert un champ distance sur Trip — à ajouter ultérieurement
            'avg_trip_minutes'   => $avgDurationMin,
            'objective_revenue'  => $objectiveRevenue,
            'chart_data'         => $chartData,
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function periodBounds(string $period, Carbon $now): array
    {
        return match ($period) {
            'day'   => [$now->copy()->startOfDay(),   $now->copy()->endOfDay()],
            'week'  => [$now->copy()->startOfWeek(),  $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'year'  => [$now->copy()->startOfYear(),  $now->copy()->endOfYear()],
            default => [$now->copy()->startOfWeek(),  $now->copy()->endOfWeek()],
        };
    }

    private function objectiveRevenue(int $userId, string $period, Carbon $start, string $tz): int
    {
        // Objectif = revenu période précédente + 20%
        $prev = $this->previousPeriodRevenue($userId, $period, $start, $tz);
        return (int) round($prev * 1.2);
    }

    private function previousPeriodRevenue(int $userId, string $period, Carbon $start, string $tz): int
    {
        $prevEnd   = $start->copy()->subSecond();
        $prevStart = match ($period) {
            'day'   => $prevEnd->copy()->startOfDay(),
            'week'  => $prevEnd->copy()->startOfWeek(),
            'month' => $prevEnd->copy()->startOfMonth(),
            'year'  => $prevEnd->copy()->startOfYear(),
            default => $prevEnd->copy()->startOfWeek(),
        };

        return (int) Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $userId))
            ->where('status', 'success')
            ->whereBetween('created_at', [$prevStart->setTimezone('UTC'), $prevEnd->setTimezone('UTC')])
            ->sum('gross_amount');
    }

    private function buildChartData(string $period, Carbon $now, string $tz, int $userId): array
    {
        return match ($period) {
            'day'   => $this->hourlyChart($now, $tz, $userId),
            'week'  => $this->weeklyChart($now, $tz, $userId),
            'month' => $this->weeklyGroupChart($now, $tz, $userId),
            'year'  => $this->monthlyChart($now, $tz, $userId),
            default => $this->weeklyChart($now, $tz, $userId),
        };
    }

    /** Graphique heure par heure (aujourd'hui, toutes les 2h). */
    private function hourlyChart(Carbon $now, string $tz, int $userId): array
    {
        $slots  = ['8h', '10h', '12h', '14h', '16h', '18h'];
        $hours  = [8, 10, 12, 14, 16, 18];
        $result = [];

        $payments = Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $userId))
            ->where('status', 'success')
            ->whereBetween('created_at', [$now->copy()->startOfDay()->setTimezone('UTC'), $now->copy()->endOfDay()->setTimezone('UTC')])
            ->get();

        foreach ($slots as $i => $label) {
            $h = $hours[$i];
            $amount = $payments->filter(function ($p) use ($h, $tz) {
                $h2 = $p->created_at->setTimezone($tz)->hour;
                return $h2 >= $h && $h2 < $h + 2;
            })->sum('gross_amount');
            $result[] = ['label' => $label, 'amount' => (int) $amount];
        }

        return $result;
    }

    /** Graphique jour par jour (semaine en cours). */
    private function weeklyChart(Carbon $now, string $tz, int $userId): array
    {
        $labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        $result = [];

        $payments = Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $userId))
            ->where('status', 'success')
            ->whereBetween('created_at', [
                $now->copy()->startOfWeek()->setTimezone('UTC'),
                $now->copy()->endOfWeek()->setTimezone('UTC'),
            ])
            ->get();

        foreach ($labels as $i => $label) {
            $dayOfWeek = $i + 1; // 1=Lun … 7=Dim (ISO)
            $amount = $payments->filter(fn ($p) => $p->created_at->setTimezone($tz)->isoWeekday() === $dayOfWeek)
                ->sum('gross_amount');
            $result[] = ['label' => $label, 'amount' => (int) $amount];
        }

        return $result;
    }

    /** Graphique par semaine (mois en cours : S1, S2, S3, S4). */
    private function weeklyGroupChart(Carbon $now, string $tz, int $userId): array
    {
        $result   = [];
        $payments = Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $userId))
            ->where('status', 'success')
            ->whereBetween('created_at', [
                $now->copy()->startOfMonth()->setTimezone('UTC'),
                $now->copy()->endOfMonth()->setTimezone('UTC'),
            ])
            ->get();

        for ($w = 1; $w <= 4; $w++) {
            $weekStart = $now->copy()->startOfMonth()->addWeeks($w - 1)->startOfDay();
            $weekEnd   = $weekStart->copy()->addDays(6)->endOfDay();
            $amount    = $payments->filter(function ($p) use ($weekStart, $weekEnd, $tz) {
                $d = $p->created_at->setTimezone($tz);
                return $d->between($weekStart, $weekEnd);
            })->sum('gross_amount');
            $result[] = ['label' => "S{$w}", 'amount' => (int) $amount];
        }

        return $result;
    }

    /** Graphique par mois (année en cours). */
    private function monthlyChart(Carbon $now, string $tz, int $userId): array
    {
        $monthLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        $result      = [];

        $payments = Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $userId))
            ->where('status', 'success')
            ->whereBetween('created_at', [
                $now->copy()->startOfYear()->setTimezone('UTC'),
                $now->copy()->endOfYear()->setTimezone('UTC'),
            ])
            ->get();

        foreach ($monthLabels as $i => $label) {
            $month  = $i + 1;
            $amount = $payments->filter(fn ($p) => $p->created_at->setTimezone($tz)->month === $month)
                ->sum('gross_amount');
            $result[] = ['label' => $label, 'amount' => (int) $amount];
        }

        return $result;
    }
}
