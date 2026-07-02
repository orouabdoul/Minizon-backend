<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

/**
 * Page "Historique des paiements" — revenus et retraits du conducteur.
 *
 * Fusionne deux sources :
 *  - Payment : revenus issus des réservations sur les trajets du conducteur
 *  - Withdrawal : retraits MoMo initiés par le conducteur
 *
 * Retourne les transactions groupées par mois (MonthGroup Flutter).
 */
class DriverPaymentHistoryController extends Controller
{
    // ── Couleurs ARGB Flutter ──────────────────────────────────────────────────
    private const GREEN_SOLID  = 0xFF10B981;
    private const GREEN_BG     = 0x1A10B981;
    private const RED_SOLID    = 0xFFEF4444;
    private const RED_BG       = 0x1AEF4444;
    private const ORANGE_SOLID = 0xFFF59E0B;
    private const ORANGE_BG    = 0x1AF59E0B;
    private const GRAY_SOLID   = 0xFF6B7280;
    private const GRAY_BG      = 0x1A6B7280;

    // =========================================================================
    //  GET /api/driver/payment-history
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/payment-history',
        operationId: 'driverPaymentHistory',
        summary: 'Historique des paiements — revenus et retraits groupés par mois',
        description: "Fusionne les revenus (paiements passagers sur les trajets du conducteur) et les retraits MoMo, puis les regroupe par mois en `MonthGroup` Flutter.\n\n**Filtres :**\n- `all` — tout (défaut)\n- `revenues` — revenus trajet uniquement\n- `withdrawals` — retraits MoMo uniquement\n- `pending` — transactions en attente (revenus locked/pending + retraits pending)\n\nChaque `TransactionModel` inclut `icon_name` (string → `Icons.X`) et `icon_background_color` / `amount_color` / `status_color` en ARGB 32-bit pour `Color(int)`.",
        tags: ['💰 Driver — Paiements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'filter', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['all', 'revenues', 'withdrawals', 'pending'], default: 'all')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Groupes mensuels',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Historique des paiements.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'summary', type: 'object', description: 'Totaux globaux (tous filtres)',
                                    properties: [
                                        new OA\Property(property: 'total_revenue',    type: 'integer', example: 45000),
                                        new OA\Property(property: 'total_withdrawn',  type: 'integer', example: 20000),
                                        new OA\Property(property: 'pending_amount',   type: 'integer', example: 8100),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'groups',
                                    type: 'array',
                                    description: 'Transactions groupées par mois (du plus récent au plus ancien)',
                                    items: new OA\Items(ref: '#/components/schemas/MonthGroup')
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $filter = $request->query('filter', 'all');

        // ── Revenus (payments sur les trajets du conducteur) ───────────────────
        $revenueQuery = Payment::with(['booking.trip', 'booking.passenger.profile'])
            ->whereHas('booking.trip', fn ($q) => $q->where('user_id', $user->id));

        if ($filter === 'revenues') {
            $payments = $revenueQuery->latest()->get();
            $withdrawals = collect();
        } elseif ($filter === 'withdrawals') {
            $payments = collect();
            $withdrawals = Withdrawal::where('user_id', $user->id)->latest()->get();
        } elseif ($filter === 'pending') {
            $payments    = $revenueQuery->whereIn('status', ['pending', 'locked'])->latest()->get();
            $withdrawals = Withdrawal::where('user_id', $user->id)->where('status', 'pending')->latest()->get();
        } else {
            $payments    = $revenueQuery->latest()->get();
            $withdrawals = Withdrawal::where('user_id', $user->id)->latest()->get();
        }

        // ── Fusion + mise en forme ─────────────────────────────────────────────
        $revenueItems = $payments->map(fn ($p) => $this->formatRevenue($p));
        $withdrawalItems = $withdrawals->map(fn ($w) => $this->formatWithdrawal($w));

        $all = $revenueItems->merge($withdrawalItems)
            ->sortByDesc('_sort_date')
            ->values();

        // ── Groupement par mois ────────────────────────────────────────────────
        $groups = $all
            ->groupBy(fn ($t) => $t['_month_key']) // "2026-07"
            ->map(function ($items, $key) {
                $totalCredit = $items->where('_is_credit', true)->sum('_raw_amount');
                $totalDebit  = $items->where('_is_credit', false)->sum('_raw_amount');
                $label       = $this->monthLabel($key);

                return [
                    'label'        => $label,
                    'total_credit' => $totalCredit,
                    'total_debit'  => $totalDebit,
                    'transactions' => $items->map(fn ($t) => collect($t)->except(['_sort_date', '_month_key', '_is_credit', '_raw_amount'])->all())->values(),
                ];
            })
            ->sortKeysDesc()
            ->values();

        // ── Totaux globaux (toujours calculés sur l'ensemble, pas le filtre) ───
        $allPaymentsTotal = Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'success')->sum('net_amount');
        $allWithdrawalsTotal = Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')->sum('amount');
        $pendingTotal = Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['pending', 'locked'])->sum('net_amount');

        return $this->apiResponse(true, 'Historique des paiements.', [
            'summary' => [
                'total_revenue'   => $allPaymentsTotal,
                'total_withdrawn' => $allWithdrawalsTotal,
                'pending_amount'  => $pendingTotal,
            ],
            'groups' => $groups,
        ]);
    }

    // =========================================================================
    //  GET /api/driver/payment-history/receipt
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/payment-history/receipt',
        operationId: 'driverPaymentReceipt',
        summary: 'Télécharger un reçu récapitulatif (mois en cours)',
        description: 'Retourne un résumé JSON du mois en cours, utilisable par Flutter pour générer un reçu PDF côté client. La génération PDF backend (LaTeX/DomPDF) peut être ajoutée ultérieurement.',
        tags: ['💰 Driver — Paiements'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Résumé reçu du mois en cours'),
        ]
    )]
    public function receipt(Request $request): JsonResponse
    {
        $user  = $request->user();
        $now   = now()->setTimezone('Africa/Porto-Novo');
        $start = $now->copy()->startOfMonth();
        $end   = $now->copy()->endOfMonth();

        $monthRevenues = Payment::with(['booking.trip'])
            ->whereHas('booking.trip', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'success')
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $monthWithdrawals = Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $profile = $user->load('profile')->profile;

        return $this->apiResponse(true, 'Reçu du mois.', [
            'period'         => $now->translatedFormat('F Y'),
            'driver_name'    => $profile ? trim("{$profile->first_name} {$profile->last_name}") : $user->phone,
            'total_revenue'  => $monthRevenues->sum('net_amount'),
            'total_trips'    => $monthRevenues->count(),
            'total_withdrawn'=> $monthWithdrawals->sum('amount'),
            'generated_at'   => $now->toIso8601String(),
        ]);
    }

    // =========================================================================
    //  OA SCHEMAS
    // =========================================================================

    #[OA\Schema(
        schema: 'MonthGroup',
        description: 'Groupe mensuel de transactions — MonthGroup Flutter',
        properties: [
            new OA\Property(property: 'label',         type: 'string',  example: 'Juillet 2026'),
            new OA\Property(property: 'total_credit',  type: 'integer', example: 32400, description: 'Somme des revenus du mois (FCFA)'),
            new OA\Property(property: 'total_debit',   type: 'integer', example: 15000, description: 'Somme des retraits du mois (FCFA)'),
            new OA\Property(
                property: 'transactions',
                type: 'array',
                items: new OA\Items(ref: '#/components/schemas/TransactionItem')
            ),
        ]
    )]
    #[OA\Schema(
        schema: 'TransactionItem',
        description: 'Transaction individuelle — TransactionModel Flutter',
        properties: [
            new OA\Property(property: 'uuid',                   type: 'string',  format: 'uuid'),
            new OA\Property(property: 'type',                   type: 'string',  enum: ['revenue', 'withdrawal']),
            new OA\Property(property: 'title',                  type: 'string',  example: 'Trajet Cotonou → Parakou'),
            new OA\Property(property: 'subtitle',               type: 'string',  example: 'Koffi Mensah • 1 place'),
            new OA\Property(property: 'date',                   type: 'string',  example: '12 Juillet 2026'),
            new OA\Property(property: 'amount_label',           type: 'string',  example: '+8 100 FCFA'),
            new OA\Property(property: 'amount_color',           type: 'integer', example: 4279520129, description: 'ARGB int — Color(amountColor)'),
            new OA\Property(property: 'status_label',           type: 'string',  example: 'Payé'),
            new OA\Property(property: 'status_color',           type: 'integer', example: 4279520129, description: 'ARGB int — Color(statusColor)'),
            new OA\Property(property: 'icon_name',              type: 'string',  example: 'payments_rounded'),
            new OA\Property(property: 'icon_background_color',  type: 'integer', example: 436204673,  description: 'ARGB int 10% opacité — Color(iconBackground)'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function formatRevenue(Payment $payment): array
    {
        $booking    = $payment->booking;
        $trip       = $booking?->trip;
        $profile    = $booking?->passenger?->profile;
        $passengerName = $profile
            ? trim("{$profile->first_name} {$profile->last_name}")
            : ($booking?->passenger?->phone ?? '—');

        $route   = $trip ? "{$trip->departure_city} → {$trip->arrival_city}" : 'Trajet';
        $seats   = $booking?->seats_booked ?? 1;
        $amount  = $payment->net_amount;

        [$statusLabel, $statusColor] = $this->revenueStatus($payment->status);

        $createdAt = $payment->created_at->setTimezone('Africa/Porto-Novo');

        return [
            'uuid'                  => $payment->uuid,
            'type'                  => 'revenue',
            'title'                 => "Trajet {$route}",
            'subtitle'              => "{$passengerName} • {$seats} place" . ($seats > 1 ? 's' : ''),
            'date'                  => $this->longDate($createdAt),
            'created_at_iso'        => $createdAt->toIso8601String(),
            'raw_amount'            => $amount,
            'amount_label'          => '+' . number_format($amount, 0, '.', ' ') . ' FCFA',
            'amount_color'          => self::GREEN_SOLID,
            'status_label'          => $statusLabel,
            'status_color'          => $statusColor,
            'icon_name'             => 'payments_rounded',
            'icon_background_color' => self::GREEN_BG,
            // Colonnes internes pour groupement — exclues de la réponse finale
            '_sort_date'  => $createdAt->timestamp,
            '_month_key'  => $createdAt->format('Y-m'),
            '_is_credit'  => true,
            '_raw_amount' => $amount,
        ];
    }

    private function formatWithdrawal(Withdrawal $withdrawal): array
    {
        $provider = match (strtolower($withdrawal->provider ?? '')) {
            'mtn'    => 'MTN MoMo',
            'moov'   => 'Moov Money',
            'celtiis'=> 'Celtiis',
            default  => ucfirst($withdrawal->provider ?? 'MoMo'),
        };

        $amount = $withdrawal->amount;

        [$statusLabel, $statusColor] = $this->withdrawalStatus($withdrawal->status);

        $createdAt = $withdrawal->created_at->setTimezone('Africa/Porto-Novo');

        return [
            'uuid'                  => $withdrawal->reference,
            'type'                  => 'withdrawal',
            'title'                 => "Retrait {$provider}",
            'subtitle'              => "{$withdrawal->phone_number} • Réf : {$withdrawal->reference}",
            'date'                  => $this->longDate($createdAt),
            'created_at_iso'        => $createdAt->toIso8601String(),
            'raw_amount'            => $amount,
            'amount_label'          => '-' . number_format($amount, 0, '.', ' ') . ' FCFA',
            'amount_color'          => self::RED_SOLID,
            'status_label'          => $statusLabel,
            'status_color'          => $statusColor,
            'icon_name'             => 'account_balance_wallet_rounded',
            'icon_background_color' => self::RED_BG,
            '_sort_date'  => $createdAt->timestamp,
            '_month_key'  => $createdAt->format('Y-m'),
            '_is_credit'  => false,
            '_raw_amount' => $amount,
        ];
    }

    /** [statusLabel, statusColor ARGB] pour un Payment. */
    private function revenueStatus(string $status): array
    {
        return match ($status) {
            'success'  => ['Payé',       self::GREEN_SOLID],
            'locked'   => ['Sécurisé',   self::ORANGE_SOLID],
            'pending'  => ['En attente', self::ORANGE_SOLID],
            'refunded' => ['Remboursé',  self::GRAY_SOLID],
            'failed'   => ['Échoué',     self::RED_SOLID],
            default    => [$status,      self::GRAY_SOLID],
        };
    }

    /** [statusLabel, statusColor ARGB] pour un Withdrawal. */
    private function withdrawalStatus(string $status): array
    {
        return match ($status) {
            'approved' => ['Traité',     self::GREEN_SOLID],
            'pending'  => ['En attente', self::ORANGE_SOLID],
            'rejected' => ['Refusé',     self::RED_SOLID],
            'failed'   => ['Échoué',     self::RED_SOLID],
            default    => [$status,      self::GRAY_SOLID],
        };
    }

    /** Convertit "Y-m" → "Juillet 2026". */
    private function monthLabel(string $key): string
    {
        return Carbon::createFromFormat('Y-m', $key)
            ->setTimezone('Africa/Porto-Novo')
            ->translatedFormat('F Y');
    }

    /** Formate une date en "12 Juillet 2026 à 09:15". */
    private function longDate(Carbon $date): string
    {
        return $date->translatedFormat('d F Y') . ' à ' . $date->format('H:i');
    }
}
