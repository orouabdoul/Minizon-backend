<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Retrait" — solde disponible et initiation d'un retrait MoMo/bancaire.
 */
class DriverWithdrawController extends Controller
{
    private const MIN_AMOUNT = 1000;

    // =========================================================================
    //  GET /api/driver/wallet
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/wallet',
        operationId: 'driverWallet',
        summary: 'Solde et revenus en attente du conducteur',
        tags: ['💳 Driver — Retrait'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Solde récupéré'),
        ]
    )]
    public function wallet(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalRevenue = (int) Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'success')
            ->sum('net_amount');

        $totalWithdrawn = (int) Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->sum('amount');

        $pendingAmount = (int) Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['pending', 'locked'])
            ->sum('net_amount');

        $availableBalance = max(0, $totalRevenue - $totalWithdrawn);

        return $this->apiResponse(true, 'Solde du portefeuille.', [
            'available_balance' => $availableBalance,
            'pending_amount'    => $pendingAmount,
            'total_revenue'     => $totalRevenue,
            'total_withdrawn'   => $totalWithdrawn,
        ]);
    }

    // =========================================================================
    //  POST /api/driver/withdraw
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/withdraw',
        operationId: 'driverWithdraw',
        summary: 'Initier un retrait',
        description: 'Crée une demande de retrait vers un compte MoMo ou bancaire. Montant minimum : 1 000 FCFA.',
        tags: ['💳 Driver — Retrait'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'provider', 'phone_number'],
                properties: [
                    new OA\Property(property: 'amount',       type: 'integer', example: 25000, description: 'Montant en FCFA'),
                    new OA\Property(property: 'provider',     type: 'string',  enum: ['mtn', 'moov', 'celtiis', 'bank'], example: 'mtn'),
                    new OA\Property(property: 'phone_number', type: 'string',  example: '+229 97 00 00 00'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Retrait initié'),
            new OA\Response(response: 422, description: 'Solde insuffisant ou validation'),
        ]
    )]
    public function withdraw(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'amount'       => ['required', 'integer', 'min:' . self::MIN_AMOUNT],
            'provider'     => ['required', 'string', 'in:mtn,moov,celtiis,bank'],
            'phone_number' => ['required', 'string', 'max:30'],
        ]);

        $amount = $validated['amount'];

        // ── Vérification du solde ──────────────────────────────────────────
        $totalRevenue   = (int) Payment::whereHas('booking.trip', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'success')
            ->sum('net_amount');
        $totalWithdrawn = (int) Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->sum('amount');
        $available      = max(0, $totalRevenue - $totalWithdrawn);

        if ($amount > $available) {
            return $this->apiResponse(false, 'Solde insuffisant. Solde disponible : ' . number_format($available, 0, '.', ' ') . ' FCFA.', null, 422);
        }

        // ── Création du retrait ────────────────────────────────────────────
        $withdrawal = Withdrawal::create([
            'user_id'      => $user->id,
            'amount'       => $amount,
            'provider'     => $validated['provider'],
            'phone_number' => $validated['phone_number'],
            'status'       => 'pending',
        ]);

        return $this->apiResponse(true, 'Retrait initié. Traitement sous 24h.', [
            'reference' => $withdrawal->reference,
            'amount'    => $amount,
            'provider'  => $validated['provider'],
            'status'    => 'pending',
        ]);
    }
}
