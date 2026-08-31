<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Payment;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalRequested;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            new OA\Response(
                response: 200,
                description: 'Solde récupéré',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Solde du portefeuille.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'available_balance', type: 'integer', example: 47500),
                                new OA\Property(property: 'pending_amount',    type: 'integer', example: 12000),
                                new OA\Property(property: 'total_revenue',     type: 'integer', example: 85000),
                                new OA\Property(property: 'total_withdrawn',   type: 'integer', example: 37500),
                            ]
                        ),
                    ]
                )
            ),
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
        description: <<<'MD'
Crée une demande de retrait vers un compte **MoMo** (MTN, Moov, Celtiis) ou un compte **bancaire**.
Montant minimum : **1 000 FCFA**.

**Retrait MoMo** — champs requis : `amount`, `provider` (mtn/moov/celtiis), `phone_number`

**Retrait bancaire** — champs requis : `amount`, `provider` = `bank`, `bank_name`, `account_number`, `account_holder_name`
MD,
        tags: ['💳 Driver — Retrait'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'provider'],
                properties: [
                    new OA\Property(
                        property: 'amount',
                        type: 'integer',
                        example: 25000,
                        description: 'Montant en FCFA (min 1 000)'
                    ),
                    new OA\Property(
                        property: 'provider',
                        type: 'string',
                        enum: ['mtn', 'moov', 'celtiis', 'bank'],
                        example: 'mtn',
                        description: 'Type de compte de réception'
                    ),
                    new OA\Property(
                        property: 'phone_number',
                        type: 'string',
                        example: '+229 97 00 00 00',
                        description: 'Numéro MoMo — requis si provider ∈ {mtn, moov, celtiis}'
                    ),
                    new OA\Property(
                        property: 'bank_name',
                        type: 'string',
                        example: 'Ecobank',
                        description: 'Nom de la banque — requis si provider = bank'
                    ),
                    new OA\Property(
                        property: 'account_number',
                        type: 'string',
                        example: 'BJ0610100400060000001278391',
                        description: 'IBAN ou numéro de compte — requis si provider = bank'
                    ),
                    new OA\Property(
                        property: 'account_holder_name',
                        type: 'string',
                        example: 'Jean Dupont',
                        description: 'Nom du titulaire du compte — requis si provider = bank'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Retrait initié avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Retrait initié. Traitement sous 24h.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'reference', type: 'string',  example: 'WD-A1B2C3D4E5F6'),
                                new OA\Property(property: 'amount',    type: 'integer', example: 25000),
                                new OA\Property(property: 'provider',  type: 'string',  example: 'mtn'),
                                new OA\Property(property: 'status',    type: 'string',  example: 'pending'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Solde insuffisant ou champs manquants',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string',  example: 'Solde insuffisant. Solde disponible : 5 000 FCFA.'),
                    ]
                )
            ),
        ]
    )]
    public function withdraw(Request $request): JsonResponse
    {
        $user = $request->user();

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
            return $this->apiResponse(
                false,
                'Solde insuffisant. Solde disponible : ' . number_format($available, 0, '.', ' ') . ' FCFA.',
                null,
                422
            );
        }

        // ── Création du retrait ────────────────────────────────────────────
        $withdrawalData = [
            'user_id'  => $user->id,
            'amount'   => $amount,
            'provider' => $validated['provider'],
            'status'   => 'pending',
        ];

        if ($isMomo) {
            $withdrawalData['phone_number'] = $validated['phone_number'];
        }

        if ($isBank) {
            $withdrawalData['bank_name']           = $validated['bank_name'];
            $withdrawalData['account_number']      = $validated['account_number'];
            $withdrawalData['account_holder_name'] = $validated['account_holder_name'];
        }

        $withdrawal = Withdrawal::create($withdrawalData);

        try {
            $user->notify(new WithdrawalRequested($withdrawal));
        } catch (\Throwable $e) {
            Log::warning('DriverWithdrawController: notif retrait échouée', ['error' => $e->getMessage()]);
        }

        // Notifier les admins de la nouvelle demande de retrait
        $profile    = $user->profile;
        $driverName = $profile ? trim("{$profile->first_name} {$profile->last_name}") : $user->phone;
        $formatted  = number_format($amount, 0, '.', ' ');

        AdminNotification::notifyAdmins(
            type:        'payment',
            priority:    'high',
            title:       'Nouvelle demande de retrait',
            description: "{$driverName} demande un retrait de {$formatted} FCFA via {$validated['provider']}.",
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
}
