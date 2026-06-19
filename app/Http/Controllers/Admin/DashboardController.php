<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\User;
use App\Models\Withdrawal;
use Carbon\Carbon;
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

        Carbon::setLocale('fr');

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

    // =========================================================================
    //  ACTIVITÉ TEMPS RÉEL  GET /api/admin/dashboard/activity
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/dashboard/activity',
        operationId: 'adminDashboardActivity',
        summary: '[ADMIN] Activité en temps réel',
        description: 'Retourne les dernières inscriptions, trajets actifs, paiements et litiges pour le widget "Activité en Temps Réel" du dashboard.',
        tags: ['👑 Administration'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Activité récupérée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Activité récupérée.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'inscriptions',
                                    type: 'array',
                                    items: new OA\Items(properties: [
                                        new OA\Property(property: 'id',   type: 'string'),
                                        new OA\Property(property: 'name', type: 'string', example: 'Jean Dossou'),
                                        new OA\Property(property: 'time', type: 'string', example: 'il y a 2 minutes'),
                                    ])
                                ),
                                new OA\Property(
                                    property: 'trips',
                                    type: 'array',
                                    items: new OA\Items(properties: [
                                        new OA\Property(property: 'id',     type: 'string'),
                                        new OA\Property(property: 'route',  type: 'string', example: 'Cotonou → Porto-Novo'),
                                        new OA\Property(property: 'driver', type: 'string', example: 'Kofi Adjovi'),
                                    ])
                                ),
                                new OA\Property(
                                    property: 'payments',
                                    type: 'array',
                                    items: new OA\Items(properties: [
                                        new OA\Property(property: 'id',     type: 'string'),
                                        new OA\Property(property: 'amount', type: 'string', example: '2 500 FCFA'),
                                        new OA\Property(property: 'trip',   type: 'string', example: 'Cotonou → Parakou'),
                                    ])
                                ),
                                new OA\Property(
                                    property: 'disputes',
                                    type: 'array',
                                    items: new OA\Items(properties: [
                                        new OA\Property(property: 'id',      type: 'string'),
                                        new OA\Property(property: 'title',   type: 'string', example: 'Conducteur absent'),
                                        new OA\Property(property: 'trip',    type: 'string', example: 'Cotonou → Abomey'),
                                        new OA\Property(property: 'variant', type: 'string', enum: ['error', 'warning']),
                                    ])
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    public function activity(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès réservé aux administrateurs.', [], 403);
        }

        Carbon::setLocale('fr');

        // — Nouvelles inscriptions (5 derniers users passagers/conducteurs)
        $inscriptions = User::with('profile')
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['passenger', 'driver']))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (User $u) {
                $p    = $u->profile;
                $name = trim(($p?->first_name ?? '') . ' ' . ($p?->last_name ?? '')) ?: $u->phone;
                return [
                    'id'   => $u->uuid,
                    'name' => $name,
                    'time' => $u->created_at->diffForHumans(),
                ];
            });

        // — Trajets actifs (5 derniers)
        $trips = Trip::with(['user.profile'])
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Trip $t) {
                $driver = $t->user;
                $p      = $driver?->profile;
                $name   = trim(($p?->first_name ?? '') . ' ' . ($p?->last_name ?? '')) ?: ($driver?->phone ?? '—');
                return [
                    'id'     => $t->uuid,
                    'route'  => $t->route(),
                    'driver' => $name,
                ];
            });

        // — Paiements récents (5 derniers success ou locked)
        $payments = Payment::with(['booking.trip'])
            ->whereIn('status', ['success', 'locked'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Payment $pay) {
                $trip = $pay->booking?->trip;
                return [
                    'id'     => $pay->uuid,
                    'amount' => number_format($pay->gross_amount, 0, ',', ' ') . ' FCFA',
                    'trip'   => $trip ? $trip->route() : '—',
                ];
            });

        // — Litiges récents (5 derniers pending/investigating)
        $disputes = Dispute::with(['booking.trip'])
            ->whereIn('status', ['pending', 'investigating'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Dispute $d) {
                $trip    = $d->booking?->trip;
                $title   = $d->reason_type
                    ? ucfirst(str_replace('_', ' ', $d->reason_type))
                    : ($d->description ? mb_strimwidth($d->description, 0, 40, '…') : 'Litige signalé');
                return [
                    'id'      => (string) $d->id,
                    'title'   => $title,
                    'trip'    => $trip ? $trip->route() : '—',
                    'variant' => $d->status === 'pending' ? 'error' : 'warning',
                ];
            });

        return $this->apiResponse(true, 'Activité récupérée.', [
            'inscriptions' => $inscriptions,
            'trips'        => $trips,
            'payments'     => $payments,
            'disputes'     => $disputes,
        ]);
    }
}
