<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '📊 Admin — Rapports', description: 'Statistiques et rapports de la plateforme (Back-Office)')]
class AdminReportController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function periodRange(string $period): array
    {
        $days = match ($period) {
            '30j'   => 30,
            '90j'   => 90,
            default => 7,
        };

        return [
            'from'     => now()->subDays($days)->startOfDay(),
            'to'       => now()->endOfDay(),
            'prevFrom' => now()->subDays($days * 2)->startOfDay(),
            'prevTo'   => now()->subDays($days)->startOfDay(),
            'days'     => $days,
        ];
    }

    private function formatAmount(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }

    private function trendLabel(float|int $current, float|int $previous): array
    {
        if ($previous == 0) {
            return ['trend' => $current > 0 ? '+100%' : '0%', 'up' => $current > 0];
        }
        $pct = round((($current - $previous) / $previous) * 100, 1);
        return [
            'trend' => ($pct >= 0 ? '+' : '') . $pct . '%',
            'up'    => $pct >= 0,
        ];
    }

    private function avatarUrl(?string $path, string $name): string
    {
        if ($path) {
            return Storage::disk('public')->url($path);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode(trim($name) ?: 'U') . '&background=2563EB&color=fff&size=64';
    }

    // =========================================================================
    //  GET /api/admin/reports?period=7j|30j|90j
    //  Données complètes pour la page Rapports & Statistiques
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/reports',
        operationId: 'adminReportsIndex',
        summary: '[ADMIN] Rapport global — KPIs, graphique revenus, top conducteurs, zones',
        description: 'Retourne toutes les données de la page Rapports & Statistiques : 4 KPIs avec tendance, graphique revenus/trajets, top 5 conducteurs, répartition par zone.',
        tags: ['📊 Admin — Rapports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'period', in: 'query', required: false,
                description: 'Période d\'analyse : 7 derniers jours, 30 jours, ou 90 jours',
                schema: new OA\Schema(type: 'string', enum: ['7j', '30j', '90j'], default: '7j')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Rapport généré avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Rapport généré.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'period', type: 'string', example: '7j'),
                                new OA\Property(
                                    property: 'kpis',
                                    type: 'array',
                                    description: '4 indicateurs clés avec tendance vs période précédente',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id',    type: 'string',  example: 'revenue'),
                                            new OA\Property(property: 'label', type: 'string',  example: 'Revenus'),
                                            new OA\Property(property: 'value', type: 'string',  example: '1 250 000 FCFA'),
                                            new OA\Property(property: 'color', type: 'string',  example: '#2563EB'),
                                            new OA\Property(property: 'trend', type: 'string',  example: '+12.5%'),
                                            new OA\Property(property: 'up',    type: 'boolean', example: true),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'revenueChart',
                                    type: 'array',
                                    description: 'Données du graphique en barres (revenus + trajets par jour/semaine/mois)',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'label',   type: 'string',  example: 'lu 28'),
                                            new OA\Property(property: 'revenue', type: 'integer', example: 85000),
                                            new OA\Property(property: 'trips',   type: 'integer', example: 12),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'topDrivers',
                                    type: 'array',
                                    description: 'Top 5 conducteurs par revenus sur la période',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'name',    type: 'string',  example: 'Kofi Mensah'),
                                            new OA\Property(property: 'avatar',  type: 'string',  example: 'https://...'),
                                            new OA\Property(property: 'trips',   type: 'integer', example: 24),
                                            new OA\Property(property: 'rating',  type: 'number',  example: 4.8),
                                            new OA\Property(property: 'revenue', type: 'string',  example: '320 000 FCFA'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'byZone',
                                    type: 'array',
                                    description: 'Répartition des trajets par ville de départ (max 8)',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'zone',    type: 'string',  example: 'Cotonou'),
                                            new OA\Property(property: 'trips',   type: 'integer', example: 89),
                                            new OA\Property(property: 'percent', type: 'integer', example: 63),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $period = $request->input('period', '7j');
        $range  = $this->periodRange($period);

        // ── KPIs ─────────────────────────────────────────────────────────────
        $revenue     = (int) Payment::where('status', 'success')->whereBetween('created_at', [$range['from'], $range['to']])->sum('gross_amount');
        $prevRevenue = (int) Payment::where('status', 'success')->whereBetween('created_at', [$range['prevFrom'], $range['prevTo']])->sum('gross_amount');

        $trips     = Trip::where('status', 'completed')->whereBetween('updated_at', [$range['from'], $range['to']])->count();
        $prevTrips = Trip::where('status', 'completed')->whereBetween('updated_at', [$range['prevFrom'], $range['prevTo']])->count();

        $newUsers     = User::whereBetween('created_at', [$range['from'], $range['to']])->count();
        $prevNewUsers = User::whereBetween('created_at', [$range['prevFrom'], $range['prevTo']])->count();

        $commission     = (int) Payment::where('status', 'success')->whereBetween('created_at', [$range['from'], $range['to']])->sum('commission_amount');
        $prevCommission = (int) Payment::where('status', 'success')->whereBetween('created_at', [$range['prevFrom'], $range['prevTo']])->sum('commission_amount');

        $kpis = [
            array_merge(['id' => 'revenue', 'label' => 'Revenus', 'value' => $this->formatAmount($revenue), 'color' => '#2563EB'], $this->trendLabel($revenue, $prevRevenue)),
            array_merge(['id' => 'trips', 'label' => 'Trajets complétés', 'value' => number_format($trips, 0, ',', ' '), 'color' => '#00A86B'], $this->trendLabel($trips, $prevTrips)),
            array_merge(['id' => 'users', 'label' => 'Nouveaux utilisateurs', 'value' => number_format($newUsers, 0, ',', ' '), 'color' => '#8B5CF6'], $this->trendLabel($newUsers, $prevNewUsers)),
            array_merge(['id' => 'commission', 'label' => 'Commissions perçues', 'value' => $this->formatAmount($commission), 'color' => '#F59E0B'], $this->trendLabel($commission, $prevCommission)),
        ];

        return $this->apiResponse(true, 'Rapport généré.', [
            'period'       => $period,
            'kpis'         => $kpis,
            'revenueChart' => $this->buildRevenueChart($period, $range),
            'topDrivers'   => $this->buildTopDrivers($range),
            'byZone'       => $this->buildByZone($range),
        ]);
    }

    // ── Revenue chart ─────────────────────────────────────────────────────────

    private function buildRevenueChart(string $period, array $range): array
    {
        // Choisir la granularité SQL selon la période
        [$paymentSql, $tripSql, $step] = match ($period) {
            '30j'   => ["YEARWEEK(created_at, 1)", "YEARWEEK(updated_at, 1)", 'week'],
            '90j'   => ["DATE_FORMAT(created_at, '%Y-%m')", "DATE_FORMAT(updated_at, '%Y-%m')", 'month'],
            default => ["DATE(created_at)", "DATE(updated_at)", 'day'],
        };

        $paymentsRaw = Payment::where('status', 'success')
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->select(DB::raw("{$paymentSql} as pk"), DB::raw('SUM(gross_amount) as revenue'))
            ->groupBy('pk')
            ->orderBy('pk')
            ->pluck('revenue', 'pk');

        $tripsRaw = Trip::where('status', 'completed')
            ->whereBetween('updated_at', [$range['from'], $range['to']])
            ->select(DB::raw("{$tripSql} as pk"), DB::raw('COUNT(*) as cnt'))
            ->groupBy('pk')
            ->orderBy('pk')
            ->pluck('cnt', 'pk');

        // Remplir toutes les cases du calendrier même si valeur = 0
        $chart   = [];
        $current = $range['from']->copy();

        while ($current->lte($range['to'])) {
            [$key, $label] = match ($step) {
                'week'  => [$current->format('oW'), 'S' . $current->isoWeek()],
                'month' => [$current->format('Y-m'), $current->locale('fr')->isoFormat('MMM YY')],
                default => [$current->format('Y-m-d'), $current->locale('fr')->isoFormat('dd D')],
            };

            if (! isset($chart[$key])) {
                $chart[$key] = [
                    'label'   => $label,
                    'revenue' => (int) ($paymentsRaw[$key] ?? 0),
                    'trips'   => (int) ($tripsRaw[$key] ?? 0),
                ];
            }

            $current->add(1, $step);
        }

        return array_values($chart);
    }

    // ── Top 5 conducteurs ─────────────────────────────────────────────────────

    private function buildTopDrivers(array $range): array
    {
        $rows = DB::table('users as u')
            ->join('profiles as p', 'p.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->join('trips as t', 't.user_id', '=', 'u.id')
            ->join('bookings as b', 'b.trip_id', '=', 't.id')
            ->join('payments as pay', 'pay.booking_id', '=', 'b.id')
            ->where('r.name', 'driver')
            ->where('pay.status', 'success')
            ->whereBetween('pay.created_at', [$range['from'], $range['to']])
            ->select(
                'u.id',
                DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as full_name"),
                'p.selfie_front as avatar_path',
                DB::raw('SUM(pay.net_amount) as revenue'),
                DB::raw('COUNT(DISTINCT t.id) as trips_count'),
            )
            ->groupBy('u.id', 'p.first_name', 'p.last_name', 'p.selfie_front')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $driverIds = $rows->pluck('id');
        $ratings   = Review::whereIn('reviewee_id', $driverIds)
            ->select('reviewee_id', DB::raw('ROUND(AVG(rating), 1) as avg_rating'))
            ->groupBy('reviewee_id')
            ->get()
            ->keyBy('reviewee_id');

        return $rows->map(function ($row) use ($ratings): array {
            $name = trim($row->full_name) ?: '—';
            return [
                'name'    => $name,
                'avatar'  => $this->avatarUrl($row->avatar_path, $name),
                'trips'   => (int) $row->trips_count,
                'rating'  => (float) ($ratings[$row->id]?->avg_rating ?? 0),
                'revenue' => $this->formatAmount((int) $row->revenue),
            ];
        })->values()->all();
    }

    // ── Répartition par zone ──────────────────────────────────────────────────

    private function buildByZone(array $range): array
    {
        $zones = Trip::where('status', 'completed')
            ->whereBetween('updated_at', [$range['from'], $range['to']])
            ->whereNotNull('departure_city')
            ->select('departure_city as zone', DB::raw('COUNT(*) as trips'))
            ->groupBy('departure_city')
            ->orderByDesc('trips')
            ->limit(8)
            ->get();

        $total = $zones->sum('trips');
        if ($total == 0) {
            return [];
        }

        return $zones->map(fn ($z) => [
            'zone'    => $z->zone,
            'trips'   => (int) $z->trips,
            'percent' => (int) round(($z->trips / $total) * 100),
        ])->all();
    }

    // =========================================================================
    //  GET /api/admin/reports/export?period=7j&format=excel|pdf
    //  Téléchargement du rapport (CSV pour Excel, message pour PDF)
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/reports/export',
        operationId: 'adminReportsExport',
        summary: '[ADMIN] Exporter le rapport en CSV (Excel) ou PDF',
        description: 'Télécharge toutes les transactions de la période au format CSV (Excel) ou retourne un message JSON pour le PDF. Le CSV inclut : date, référence, passager, conducteur, trajet, montants.',
        tags: ['📊 Admin — Rapports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'period', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['7j', '30j', '90j'], default: '7j')
            ),
            new OA\Parameter(
                name: 'format', in: 'query', required: false,
                description: 'excel → télécharge un fichier .csv. pdf → message JSON (à venir).',
                schema: new OA\Schema(type: 'string', enum: ['excel', 'pdf'], default: 'excel')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fichier CSV téléchargé (Content-Type: text/csv) ou message JSON'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function export(Request $request): JsonResponse|Response
    {
        $period = $request->input('period', '7j');
        $format = $request->input('format', 'excel');
        $range  = $this->periodRange($period);

        if ($format !== 'excel') {
            return $this->apiResponse(true, 'Export PDF disponible prochainement. Utilisez Excel pour l\'instant.', [
                'period' => $period,
            ]);
        }

        // ── CSV (s'ouvre dans Excel avec BOM UTF-8) ───────────────────────────
        $payments = Payment::with([
            'booking.trip.user.profile',
            'booking.passenger.profile',
        ])
            ->where('status', 'success')
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->orderByDesc('created_at')
            ->get();

        $rows   = [];
        $rows[] = implode(';', [
            'Date', 'Réf. transaction', 'Passager', 'Conducteur',
            'Trajet', 'Montant brut (FCFA)', 'Commission (FCFA)', 'Net conducteur (FCFA)',
        ]);

        foreach ($payments as $p) {
            $booking   = $p->booking;
            $trip      = $booking?->trip;
            $driver    = $trip?->user?->profile;
            $passenger = $booking?->passenger?->profile;

            $rows[] = implode(';', [
                $p->created_at->format('d/m/Y H:i'),
                $p->transaction_reference,
                trim(($passenger?->first_name ?? '') . ' ' . ($passenger?->last_name ?? '')) ?: '—',
                trim(($driver?->first_name    ?? '') . ' ' . ($driver?->last_name    ?? '')) ?: '—',
                $trip ? ($trip->departure_city . ' → ' . $trip->arrival_city) : '—',
                $p->gross_amount,
                $p->commission_amount,
                $p->net_amount,
            ]);
        }

        $csv      = "\xEF\xBB\xBF" . implode("\r\n", $rows);
        $filename = 'minizon-rapport-' . $period . '-' . now()->format('Ymd') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
