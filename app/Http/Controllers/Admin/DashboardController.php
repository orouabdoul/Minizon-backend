<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    // =========================================================================
    //  ADMIN — Statistiques globales de la plateforme
    //  GET /api/admin/dashboard
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/dashboard',
        operationId: 'adminDashboard',
        summary: '[ADMIN] Tableau de bord — statistiques plateforme',
        description: 'Retourne un snapshot complet des métriques clés : utilisateurs, trajets, paiements, litiges et revenus. Réservé aux administrateurs.',
        tags: ['👑 Administration'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques récupérées'),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès réservé aux administrateurs.', [], 403);
        }

        // ---- Utilisateurs ----
        $totalUsers      = User::count();
        $totalDrivers    = User::whereHas('role', fn ($q) => $q->where('name', 'driver'))->count();
        $totalPassengers = User::whereHas('role', fn ($q) => $q->where('name', 'passenger'))->count();
        $pendingKyc      = User::where('is_verified', false)->whereHas('profile')->count();
        $blockedUsers    = User::where('is_blocked', true)->count();

        // Nouveaux inscrits ce mois
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // ---- Trajets ----
        $totalTrips     = Trip::count();
        $activeTrips    = Trip::whereIn('status', ['pending', 'active'])->count();
        $completedTrips = Trip::where('status', 'completed')->count();
        $cancelledTrips = Trip::where('status', 'cancelled')->count();

        $tripsThisMonth = Trip::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // ---- Réservations ----
        $totalBookings     = Booking::count();
        $pendingBookings   = Booking::where('status', 'pending')->count();
        $acceptedBookings  = Booking::where('status', 'accepted')->count();
        $cancelledBookings = Booking::where('status', 'cancelled')->count();

        // ---- Paiements & revenus ----
        $totalRevenue      = Payment::where('status', 'success')->sum('gross_amount');
        $platformRevenue   = Payment::where('status', 'success')->sum('commission_amount');
        $escrowLocked      = Payment::where('status', 'locked')->sum('gross_amount');
        $totalRefunded     = Payment::where('status', 'refunded')->sum('gross_amount');

        $revenueThisMonth  = Payment::where('status', 'success')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('gross_amount');

        // ---- Retraits ----
        $pendingWithdrawals = Withdrawal::where('status', 'pending')->count();
        $pendingWithdrawalAmount = Withdrawal::where('status', 'pending')->sum('amount');

        // ---- Litiges ----
        $totalDisputes      = Dispute::count();
        $pendingDisputes    = Dispute::where('status', 'pending')->count();
        $investigatingDisputes = Dispute::where('status', 'investigating')->count();
        $resolvedDisputes   = Dispute::whereIn('status', ['resolved_refunded', 'resolved_paid_to_driver'])->count();

        // ---- Revenus par jour (7 derniers jours) ----
        $dailyRevenue = Payment::where('status', 'success')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, SUM(gross_amount) as total, COUNT(*) as transactions')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // ---- Top conducteurs (par revenus nets) ----
        $topDrivers = Payment::where('payments.status', 'success')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->join('trips', 'bookings.trip_id', '=', 'trips.id')
            ->join('users', 'trips.user_id', '=', 'users.id')
            ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
            ->selectRaw('users.uuid, users.phone, profiles.first_name, profiles.last_name, SUM(payments.net_amount) as total_earned, COUNT(payments.id) as trips_count')
            ->groupBy('users.id', 'users.uuid', 'users.phone', 'profiles.first_name', 'profiles.last_name')
            ->orderByDesc('total_earned')
            ->limit(5)
            ->get();

        return $this->apiResponse(true, 'Tableau de bord récupéré.', [
            'users' => [
                'total'           => $totalUsers,
                'drivers'         => $totalDrivers,
                'passengers'      => $totalPassengers,
                'pending_kyc'     => $pendingKyc,
                'blocked'         => $blockedUsers,
                'new_this_month'  => $newUsersThisMonth,
            ],
            'trips' => [
                'total'           => $totalTrips,
                'active'          => $activeTrips,
                'completed'       => $completedTrips,
                'cancelled'       => $cancelledTrips,
                'this_month'      => $tripsThisMonth,
            ],
            'bookings' => [
                'total'           => $totalBookings,
                'pending'         => $pendingBookings,
                'accepted'        => $acceptedBookings,
                'cancelled'       => $cancelledBookings,
            ],
            'payments' => [
                'total_volume_fcfa'    => $totalRevenue,
                'platform_revenue_fcfa'=> $platformRevenue,
                'escrow_locked_fcfa'   => $escrowLocked,
                'refunded_fcfa'        => $totalRefunded,
                'this_month_fcfa'      => $revenueThisMonth,
            ],
            'withdrawals' => [
                'pending_count'   => $pendingWithdrawals,
                'pending_amount'  => $pendingWithdrawalAmount,
            ],
            'disputes' => [
                'total'           => $totalDisputes,
                'pending'         => $pendingDisputes,
                'investigating'   => $investigatingDisputes,
                'resolved'        => $resolvedDisputes,
            ],
            'charts' => [
                'daily_revenue_7d' => $dailyRevenue,
                'top_drivers'      => $topDrivers,
            ],
        ]);
    }
}
