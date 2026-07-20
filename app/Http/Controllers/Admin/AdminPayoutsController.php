<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DriverPayout;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

/**
 * Virements Conducteurs — Back-Office Admin (PayoutsScreen).
 *
 * Endpoints :
 *   GET  /api/admin/payouts/summary         — KPIs financiers
 *   GET  /api/admin/payouts                 — liste (filtrable par status)
 *   POST /api/admin/payouts/generate        — générer des fiches depuis les gains non payés
 *   POST /api/admin/payouts/{uuid}/process  — déclencher le virement (→ en_traitement)
 *   POST /api/admin/payouts/{uuid}/mark-paid— confirmer payé (→ payé)
 *   POST /api/admin/payouts/{uuid}/retry    — réessayer un virement échoué (→ en_attente)
 *   POST /api/admin/payouts/batch-process   — traiter plusieurs en une fois
 *   GET  /api/admin/payouts/export          — export CSV
 */
class AdminPayoutsController extends Controller
{
    private const METHODS = ['MTN Mobile Money', 'Moov Money', 'Virement bancaire'];

    // =========================================================================
    //  GET /api/admin/payouts/summary
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/payouts/summary',
        operationId: 'adminPayoutsSummary',
        summary: 'KPIs des virements conducteurs',
        tags: ['👑 Admin — Virements'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'KPIs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'totalPending',  type: 'integer', example: 850000, description: 'Montant total en attente (FCFA)'),
                                new OA\Property(property: 'pendingAmount', type: 'integer', example: 12,     description: 'Nombre de virements en attente'),
                                new OA\Property(property: 'totalPaid',     type: 'integer', example: 3200000,description: 'Montant total payé ce mois'),
                                new OA\Property(property: 'totalDrivers',  type: 'integer', example: 24,     description: 'Conducteurs actifs'),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function summary(): JsonResponse
    {
        $totalPending  = DriverPayout::where('status', 'en_attente')->sum('net_amount');
        $pendingAmount = DriverPayout::where('status', 'en_attente')->count();
        $totalPaid     = DriverPayout::where('status', 'payé')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at',  now()->year)
            ->sum('net_amount');
        $totalDrivers  = User::whereHas('role', fn ($q) => $q->where('name', 'driver'))
            ->where('is_blocked', false)
            ->count();

        return $this->apiResponse(true, 'Résumé virements.', [
            'totalPending'  => (int) $totalPending,
            'pendingAmount' => $pendingAmount,
            'totalPaid'     => (int) $totalPaid,
            'totalDrivers'  => $totalDrivers,
        ]);
    }

    // =========================================================================
    //  GET /api/admin/payouts
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/payouts',
        operationId: 'adminPayoutsList',
        summary: 'Liste des virements conducteurs',
        tags: ['👑 Admin — Virements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'en_attente', 'en_traitement', 'payé', 'échoué'], default: 'all')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des virements'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $q = DriverPayout::with('driver.profile')
            ->orderByRaw("CASE status WHEN 'en_attente' THEN 0 WHEN 'en_traitement' THEN 1 WHEN 'échoué' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $q->where('status', $request->input('status'));
        }

        $payouts = $q->get()->map(fn (DriverPayout $p) => $this->formatPayout($p));

        return $this->apiResponse(true, 'Virements.', ['payouts' => $payouts]);
    }

    // =========================================================================
    //  POST /api/admin/payouts/generate
    //  Génère des fiches de paiement depuis les trajets terminés non encore payés
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/payouts/generate',
        operationId: 'adminPayoutsGenerate',
        summary: 'Générer les fiches de paiement depuis les gains non payés',
        description: 'Calcule pour chaque conducteur les paiements de la période en cours et crée une fiche driver_payout si elle n\'existe pas encore.',
        tags: ['👑 Admin — Virements'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'period', type: 'string', enum: ['month', 'week'], example: 'month', description: 'Période de calcul'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Fiches générées'),
        ]
    )]
    public function generate(Request $request): JsonResponse
    {
        $period = $request->input('period', 'month');
        $from   = $period === 'week' ? now()->startOfWeek() : now()->startOfMonth();

        // Récupérer les conducteurs ayant des trajets terminés dans la période
        $drivers = User::whereHas('role', fn ($q) => $q->where('name', 'driver'))
            ->whereHas('trips', fn ($q) => $q->where('status', 'completed')->where('ended_at', '>=', $from))
            ->with(['profile', 'trips' => fn ($q) => $q->where('status', 'completed')->where('ended_at', '>=', $from)->with('bookings.payment')])
            ->get();

        $created = 0;

        foreach ($drivers as $driver) {
            // Éviter les doublons pour la même période
            $alreadyExists = DriverPayout::where('driver_id', $driver->id)
                ->where('created_at', '>=', $from)
                ->where('status', '!=', 'payé')
                ->exists();

            if ($alreadyExists) continue;

            $grossAmount      = 0;
            $commissionAmount = 0;
            $tripsCount       = $driver->trips->count();

            foreach ($driver->trips as $trip) {
                foreach ($trip->bookings as $booking) {
                    $payment = $booking->payment;
                    if ($payment && $payment->status === 'success') {
                        $grossAmount      += $payment->gross_amount;
                        $commissionAmount += $payment->commission_amount;
                    }
                }
            }

            $netAmount = $grossAmount - $commissionAmount;
            if ($netAmount <= 0) continue;

            $profile     = $driver->profile;
            $phoneNumber = $profile?->phone ?? $driver->phone;

            DriverPayout::create([
                'driver_id'        => $driver->id,
                'gross_amount'     => $grossAmount,
                'commission_amount'=> $commissionAmount,
                'net_amount'       => $netAmount,
                'trips_count'      => $tripsCount,
                'method'           => 'MTN Mobile Money',
                'phone_number'     => $phoneNumber,
            ]);

            $created++;
        }

        return $this->apiResponse(true, "{$created} fiche(s) de paiement créée(s).", [
            'generated' => $created,
            'period'    => $period,
        ]);
    }

    // =========================================================================
    //  POST /api/admin/payouts/{uuid}/process  — en_attente → en_traitement
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/payouts/{uuid}/process',
        operationId: 'adminPayoutsProcess',
        summary: 'Déclencher un virement (→ en traitement)',
        tags: ['👑 Admin — Virements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['method'],
                properties: [
                    new OA\Property(property: 'method', type: 'string', enum: ['MTN Mobile Money', 'Moov Money', 'Virement bancaire'], example: 'MTN Mobile Money'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Virement déclenché'),
            new OA\Response(response: 409, description: 'Statut incompatible'),
        ]
    )]
    public function process(Request $request, string $uuid): JsonResponse
    {
        $payout = DriverPayout::where('uuid', $uuid)->firstOrFail();

        if ($payout->status !== 'en_attente') {
            return $this->apiResponse(false, 'Ce virement n\'est pas en attente.', [], 409);
        }

        $validated = $request->validate([
            'method' => 'required|in:MTN Mobile Money,Moov Money,Virement bancaire',
        ]);

        $payout->update([
            'status'       => 'en_traitement',
            'method'       => $validated['method'],
            'admin_id'     => auth()->id(),
            'processed_at' => now(),
        ]);

        AuditLog::record(
            action:      'payout.process',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'remboursement',
            severity:    'info',
            description: "Virement {$payout->reference} déclenché via {$validated['method']} ({$this->fmt($payout->net_amount)})",
            targetType:  'payout',
            targetName:  $payout->reference,
            userAgent:   $request->userAgent(),
        );

        return $this->apiResponse(true, 'Virement déclenché.', $this->formatPayout($payout->fresh('driver.profile')));
    }

    // =========================================================================
    //  POST /api/admin/payouts/{uuid}/mark-paid  — en_traitement → payé
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/payouts/{uuid}/mark-paid',
        operationId: 'adminPayoutsMarkPaid',
        summary: 'Confirmer qu\'un virement a été effectué (→ payé)',
        tags: ['👑 Admin — Virements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Virement marqué comme payé'),
            new OA\Response(response: 409, description: 'Statut incompatible'),
        ]
    )]
    public function markPaid(Request $request, string $uuid): JsonResponse
    {
        $payout = DriverPayout::where('uuid', $uuid)->firstOrFail();

        if ($payout->status !== 'en_traitement') {
            return $this->apiResponse(false, 'Ce virement n\'est pas en cours de traitement.', [], 409);
        }

        $payout->update([
            'status'  => 'payé',
            'paid_at' => now(),
        ]);

        AuditLog::record(
            action:      'payout.paid',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'remboursement',
            severity:    'info',
            description: "Virement {$payout->reference} confirmé payé ({$this->fmt($payout->net_amount)})",
            targetType:  'payout',
            targetName:  $payout->reference,
            userAgent:   $request->userAgent(),
        );

        return $this->apiResponse(true, 'Virement confirmé payé.', $this->formatPayout($payout->fresh('driver.profile')));
    }

    // =========================================================================
    //  POST /api/admin/payouts/{uuid}/retry  — échoué → en_attente
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/payouts/{uuid}/retry',
        operationId: 'adminPayoutsRetry',
        summary: 'Réessayer un virement échoué (→ en_attente)',
        tags: ['👑 Admin — Virements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Virement remis en attente'),
            new OA\Response(response: 409, description: 'Statut incompatible'),
        ]
    )]
    public function retry(Request $request, string $uuid): JsonResponse
    {
        $payout = DriverPayout::where('uuid', $uuid)->firstOrFail();

        if ($payout->status !== 'échoué') {
            return $this->apiResponse(false, 'Ce virement n\'a pas échoué.', [], 409);
        }

        $payout->update([
            'status'        => 'en_attente',
            'failed_reason' => null,
            'processed_at'  => null,
        ]);

        return $this->apiResponse(true, 'Virement remis en attente.', $this->formatPayout($payout->fresh('driver.profile')));
    }

    // =========================================================================
    //  POST /api/admin/payouts/batch-process
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/payouts/batch-process',
        operationId: 'adminPayoutsBatchProcess',
        summary: 'Traiter plusieurs virements en attente en une fois',
        tags: ['👑 Admin — Virements'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['uuids', 'method'],
                properties: [
                    new OA\Property(property: 'uuids',  type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                    new OA\Property(property: 'method', type: 'string', enum: ['MTN Mobile Money', 'Moov Money', 'Virement bancaire'], example: 'MTN Mobile Money'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Virements déclenchés'),
        ]
    )]
    public function batchProcess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuids'    => 'required|array|min:1|max:50',
            'uuids.*'  => 'string',
            'method'   => 'required|in:MTN Mobile Money,Moov Money,Virement bancaire',
        ]);

        $count = DriverPayout::whereIn('uuid', $validated['uuids'])
            ->where('status', 'en_attente')
            ->update([
                'status'       => 'en_traitement',
                'method'       => $validated['method'],
                'admin_id'     => auth()->id(),
                'processed_at' => now(),
            ]);

        AuditLog::record(
            action:      'payout.batch',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'remboursement',
            severity:    'info',
            description: "{$count} virement(s) déclenchés via {$validated['method']}",
            userAgent:   $request->userAgent(),
        );

        return $this->apiResponse(true, "{$count} virement(s) déclenchés.", [
            'processed' => $count,
        ]);
    }

    // =========================================================================
    //  GET /api/admin/payouts/export
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/payouts/export',
        operationId: 'adminPayoutsExport',
        summary: 'Exporter les virements en CSV',
        tags: ['👑 Admin — Virements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fichier CSV'),
        ]
    )]
    public function export(Request $request): Response
    {
        $q = DriverPayout::with('driver.profile')->orderByDesc('created_at');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $q->where('status', $request->input('status'));
        }

        $payouts  = $q->limit(5000)->get();
        $filename = 'virements_minizon_' . now()->format('Ymd_His') . '.csv';
        $bom      = "\xEF\xBB\xBF";

        $headers = ['Référence', 'Conducteur', 'Téléphone', 'Trajets', 'Brut (FCFA)', 'Commission (FCFA)', 'Net (FCFA)', 'Méthode', 'Statut', 'Date virement'];
        $csv     = $bom . implode(';', $headers) . "\n";

        foreach ($payouts as $p) {
            $profile = $p->driver?->profile;
            $name    = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
            if (empty($name)) $name = $p->driver?->phone ?? '—';

            $row = [
                $p->reference,
                $name,
                $p->phone_number ?? $p->driver?->phone ?? '—',
                $p->trips_count,
                $p->gross_amount,
                $p->commission_amount,
                $p->net_amount,
                $p->method,
                $p->status,
                $p->paid_at?->format('d/m/Y H:i') ?? '—',
            ];

            $csv .= implode(';', array_map(
                fn ($cell) => '"' . str_replace('"', '""', (string) $cell) . '"',
                $row
            )) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatPayout(DriverPayout $p): array
    {
        $profile    = $p->driver?->profile;
        $driverName = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
        if (empty($driverName)) $driverName = $p->driver?->phone ?? 'Conducteur';

        $avatar = $profile?->selfie_front
            ? asset('storage/' . $profile->selfie_front)
            : 'https://ui-avatars.com/api/?name=' . urlencode($driverName) . '&background=00A86B&color=fff';

        return [
            'id'           => $p->uuid,
            'driverName'   => $driverName,
            'driverAvatar' => $avatar,
            'driverPhone'  => $p->driver?->phone ?? '',
            'tripsCount'   => $p->trips_count,
            'grossAmount'  => $p->gross_amount,
            'commission'   => $p->commission_amount,
            'netAmount'    => $p->net_amount,
            'method'       => $p->method,
            'status'       => $p->status,
            'reference'    => $p->reference,
            'processedAt'  => $p->processed_at?->toIso8601String(),
            'paidAt'       => $p->paid_at?->toIso8601String(),
        ];
    }

    private function fmt(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }
}
