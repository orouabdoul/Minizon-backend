<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PassengerStatsController extends Controller
{
    // =========================================================================
    //  PASSAGER — Statistiques personnelles
    //  GET /api/passenger/stats
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/stats',
        operationId: 'passengerStats',
        summary: 'Statistiques du passager connecté',
        description: 'Retourne les métriques de déplacement du passager : trajets effectués, dépenses totales, conducteurs fréquents, etc.',
        tags: ['👤 Passager'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques récupérées'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $bookings = Booking::where('passenger_id', $user->id)
            ->with(['trip.user.profile', 'trip.vehicle.vehicleType', 'payment'])
            ->orderByDesc('created_at')
            ->get();

        $totalBookings    = $bookings->count();
        $acceptedBookings = $bookings->where('status', 'accepted');
        $cancelledCount   = $bookings->where('status', 'cancelled')->count();
        $rejectedCount    = $bookings->where('status', 'rejected')->count();

        // Trajets "complétés" = acceptés avec paiement success
        $completedTrips = $acceptedBookings->filter(
            fn ($b) => in_array($b->payment?->status, ['success', 'released'])
        )->count();

        $totalSpent = $bookings->sum(
            fn ($b) => in_array($b->payment?->status, ['success', 'released', 'locked'])
                ? ($b->payment->gross_amount ?? 0)
                : 0
        );

        // Dépenses ce mois
        $spentThisMonth = $bookings->filter(function ($b) {
            return $b->created_at->isCurrentMonth()
                && in_array($b->payment?->status, ['success', 'released', 'locked']);
        })->sum(fn ($b) => $b->payment->gross_amount ?? 0);

        // Conducteurs les plus souvent utilisés
        $topDrivers = $bookings
            ->whereNotIn('status', ['rejected'])
            ->groupBy(fn ($b) => $b->trip?->user_id)
            ->map(function ($group) {
                $trip    = $group->first()->trip;
                $driver  = $trip?->user;
                $profile = $driver?->profile;
                return [
                    'driver_uuid'  => $driver?->uuid,
                    'driver_name'  => trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: $driver?->name,
                    'trips_count'  => $group->count(),
                    'total_spent'  => $group->sum(fn ($b) => $b->payment?->gross_amount ?? 0),
                ];
            })
            ->sortByDesc('trips_count')
            ->values()
            ->take(5);

        // Répartition par statut
        $byStatus = $bookings->groupBy('status')->map->count();

        return $this->apiResponse(true, 'Statistiques passager récupérées.', [
            'bookings' => [
                'total'     => $totalBookings,
                'accepted'  => $acceptedBookings->count(),
                'completed' => $completedTrips,
                'cancelled' => $cancelledCount,
                'rejected'  => $rejectedCount,
                'by_status' => $byStatus,
            ],
            'spending' => [
                'total_fcfa'      => (int) $totalSpent,
                'this_month_fcfa' => (int) $spentThisMonth,
            ],
            'top_drivers' => $topDrivers,
            'member_since' => $user->created_at?->toDateString(),
        ]);
    }

}
