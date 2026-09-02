<?php

namespace App\Http\Controllers\Withdrawal;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Payment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalProcessed;
use App\Notifications\WithdrawalRequested;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gestion des retraits conducteurs.
 *
 * Driver :
 *   GET  /api/withdrawals           → historique du conducteur
 *   POST /api/withdrawals           → créer une demande de retrait
 *
 * Admin :
 *   GET  /api/admin/withdrawals         → liste toutes les demandes
 *   GET  /api/admin/withdrawals/balance → solde global plateforme
 *   POST /api/admin/withdrawals/{id}/process → approuver et marquer traité
 *   POST /api/admin/withdrawals/{id}/reject  → refuser avec motif
 */
class WithdrawalController extends Controller
{
    private const MIN_AMOUNT = 1000;

    // =========================================================================
    //  GET /api/withdrawals  — historique conducteur
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $withdrawals = Withdrawal::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Withdrawal $w) => $this->format($w));

        return $this->apiResponse(true, 'Historique des retraits.', $withdrawals);
    }

    // =========================================================================
    //  POST /api/withdrawals  — créer une demande (conducteur)
    // =========================================================================

    public function store(Request $request): JsonResponse
    {
        $user  = $request->user();
        $isMomo = in_array($request->input('provider'), ['mtn', 'moov', 'celtiis'], true);
        $isBank = $request->input('provider') === 'bank';

        $rules = [
            'amount'   => ['required', 'integer', 'min:' . self::MIN_AMOUNT],
            'provider' => ['required', 'string', 'in:mtn,moov,celtiis,bank'],
        ];
        if ($isMomo) {
            $rules['phone_number'] = ['required', 'string', 'max:30'];
        }
        if ($isBank) {
            $rules['bank_name']           = ['required', 'string', 'max:100'];
            $rules['account_number']      = ['required', 'string', 'max:100'];
            $rules['account_holder_name'] = ['required', 'string', 'max:150'];
        }

        $validated = $request->validate($rules);
        $amount    = $validated['amount'];

        // Vérification solde
        $totalRevenue   = (int) Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'success')->sum('net_amount');
        $totalWithdrawn = (int) Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')->sum('amount');
        $available = max(0, $totalRevenue - $totalWithdrawn);

        if ($amount > $available) {
            return $this->apiResponse(false, 'Solde insuffisant. Disponible : ' . number_format($available, 0, '.', ' ') . ' FCFA.', null, 422);
        }

        $withdrawalData = [
            'user_id'  => $user->id,
            'amount'   => $amount,
            'provider' => $validated['provider'],
            'status'   => 'pending',
        ];
        if ($isMomo) $withdrawalData['phone_number'] = $validated['phone_number'];
        if ($isBank) {
            $withdrawalData['bank_name']           = $validated['bank_name'];
            $withdrawalData['account_number']      = $validated['account_number'];
            $withdrawalData['account_holder_name'] = $validated['account_holder_name'];
        }

        $withdrawal = Withdrawal::create($withdrawalData);

        try {
            $user->notify(new WithdrawalRequested($withdrawal));
        } catch (\Throwable $e) {
            Log::warning('WithdrawalController: notif WithdrawalRequested échouée', ['error' => $e->getMessage()]);
        }

        $profile    = $user->profile;
        $driverName = $profile ? trim("{$profile->first_name} {$profile->last_name}") : $user->phone;
        AdminNotification::notifyAdmins(
            type:        'payment',
            priority:    'high',
            title:       'Nouvelle demande de retrait',
            description: "{$driverName} demande un retrait de " . number_format($amount, 0, '.', ' ') . " FCFA via {$validated['provider']}.",
            refType:     'withdrawal',
            refId:       $withdrawal->reference ?? (string) $withdrawal->id,
            userId:      $user->id,
        );

        return $this->apiResponse(true, 'Retrait initié. Traitement sous 24h.', [
            'reference' => $withdrawal->reference,
            'amount'    => $amount,
            'provider'  => $validated['provider'],
            'status'    => 'pending',
        ]);
    }

    // =========================================================================
    //  GET /api/admin/withdrawals  — liste admin
    // =========================================================================

    public function adminIndex(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $status  = $request->input('status', '');
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Withdrawal::with('user.profile')->orderByDesc('created_at');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $paginated = $query->paginate($perPage);

        return $this->apiResponse(true, 'Demandes de retrait.', [
            'data'         => $paginated->map(fn (Withdrawal $w) => $this->formatAdmin($w))->values(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  GET /api/admin/withdrawals/balance  — solde global plateforme
    // =========================================================================

    public function balance(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $totalCollected = (int) Payment::where('status', 'success')->sum('gross_amount');
        $totalPaidOut   = (int) Withdrawal::where('status', 'approved')->sum('amount');
        $pendingPayouts = (int) Withdrawal::where('status', 'pending')->sum('amount');

        return $this->apiResponse(true, 'Solde plateforme.', [
            'total_collected'    => $totalCollected,
            'total_paid_out'     => $totalPaidOut,
            'pending_payouts'    => $pendingPayouts,
            'platform_balance'   => max(0, $totalCollected - $totalPaidOut - $pendingPayouts),
        ]);
    }

    // =========================================================================
    //  POST /api/admin/withdrawals/{id}/process  — approuver
    // =========================================================================

    public function process(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $withdrawal = Withdrawal::with('user')->findOrFail($id);

        if (! $withdrawal->isPending()) {
            return $this->apiResponse(false, 'Cette demande a déjà été traitée.', [], 422);
        }

        $withdrawal->update([
            'status'       => 'approved',
            'processed_at' => now(),
        ]);

        try {
            $withdrawal->user?->notify(new WithdrawalProcessed($withdrawal));
        } catch (\Throwable $e) {
            Log::warning('WithdrawalController: notif WithdrawalProcessed (approved) échouée', ['error' => $e->getMessage()]);
        }

        return $this->apiResponse(true, 'Retrait approuvé. Le conducteur a été notifié.', $this->formatAdmin($withdrawal));
    }

    // =========================================================================
    //  POST /api/admin/withdrawals/{id}/reject  — refuser
    // =========================================================================

    public function reject(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $withdrawal = Withdrawal::with('user')->findOrFail($id);

        if (! $withdrawal->isPending()) {
            return $this->apiResponse(false, 'Cette demande a déjà été traitée.', [], 422);
        }

        $withdrawal->update([
            'status'        => 'rejected',
            'failed_reason' => $validated['reason'] ?? null,
            'processed_at'  => now(),
        ]);

        try {
            $withdrawal->user?->notify(new WithdrawalProcessed($withdrawal));
        } catch (\Throwable $e) {
            Log::warning('WithdrawalController: notif WithdrawalProcessed (rejected) échouée', ['error' => $e->getMessage()]);
        }

        return $this->apiResponse(true, 'Retrait refusé. Le conducteur a été notifié.', $this->formatAdmin($withdrawal));
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function format(Withdrawal $w): array
    {
        return [
            'id'           => $w->id,
            'reference'    => $w->reference,
            'amount'       => $w->amount,
            'provider'     => $w->provider,
            'status'       => $w->status,
            'created_at'   => $w->created_at?->toIso8601String(),
            'processed_at' => $w->processed_at?->toIso8601String(),
            'failed_reason'=> $w->failed_reason,
        ];
    }

    private function formatAdmin(Withdrawal $w): array
    {
        $user    = $w->user;
        $profile = $user?->profile;
        $name    = $profile ? trim("{$profile->first_name} {$profile->last_name}") : ($user?->phone ?? '—');

        return array_merge($this->format($w), [
            'driver_name'  => $name,
            'driver_phone' => $user?->phone,
            'driver_uuid'  => $user?->uuid,
            'phone_number' => $w->phone_number,
            'bank_name'    => $w->bank_name,
            'account_number'=> $w->account_number,
        ]);
    }
}
