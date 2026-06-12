<?php

namespace App\Http\Controllers\Withdrawal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalProcessed;
use FedaPay\Balance as FedaBalance;
use FedaPay\FedaPay;
use FedaPay\Payout as FedaPayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class WithdrawalController extends Controller
{
    public function __construct()
    {
        FedaPay::setApiKey(config('fedapay.secret_key'));
        FedaPay::setEnvironment(config('fedapay.environment'));
    }

    // ── Conducteur : soumettre une demande ───────────────────────────────────

    #[OA\Post(
        path: '/api/withdrawals',
        operationId: 'withdrawalStore',
        summary: 'Demander un retrait Mobile Money',
        description: 'Conducteur uniquement. Soumet une demande qui sera traitée par un admin dans les 24 h.',
        tags: ['💸 Retraits & Virements'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'provider', 'phone_number'],
                properties: [
                    new OA\Property(property: 'amount',       type: 'integer', minimum: 500,   example: 15000, description: 'Montant XOF (min 500)'),
                    new OA\Property(property: 'provider',     type: 'string',  enum: ['mtn', 'moov', 'celtiis'], example: 'mtn'),
                    new OA\Property(property: 'phone_number', type: 'string',  example: '97000000'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Demande enregistrée'),
            new OA\Response(response: 403, description: 'Réservé aux conducteurs'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isDriver()) {
            return $this->ok(false, 'Seuls les conducteurs peuvent demander un retrait.', [], 403);
        }

        $v = Validator::make($request->all(), [
            'amount'       => ['required', 'integer', 'min:500'],
            'provider'     => ['required', 'in:mtn,moov,celtiis'],
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        if ($v->fails()) {
            return $this->ok(false, 'Données invalides.', $v->errors(), 422);
        }

        $withdrawal = Withdrawal::create([
            'user_id'      => $request->user()->id,
            'amount'       => $request->amount,
            'provider'     => $request->provider,
            'phone_number' => $request->phone_number,
            'status'       => 'pending',
        ]);

        return $this->ok(true, 'Demande enregistrée. Elle sera traitée sous 24 h.', $withdrawal, 201);
    }

    // ── Conducteur : historique ──────────────────────────────────────────────

    #[OA\Get(
        path: '/api/withdrawals',
        operationId: 'withdrawalIndex',
        summary: 'Mes retraits (conducteur)',
        tags: ['💸 Retraits & Virements'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Historique')]
    )]
    public function index(Request $request): JsonResponse
    {
        $withdrawals = Withdrawal::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->ok(true, 'Historique des retraits.', $withdrawals);
    }

    // ── Admin : liste toutes les demandes ────────────────────────────────────

    #[OA\Get(
        path: '/api/admin/withdrawals',
        operationId: 'adminWithdrawals',
        summary: '[ADMIN] Demandes de retrait',
        tags: ['💸 Retraits & Virements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected', 'failed'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste'),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    public function adminIndex(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->ok(false, 'Accès refusé.', [], 403);
        }

        $query = Withdrawal::with(['user'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->ok(true, 'Demandes de retrait.', $query->get());
    }

    // ── Admin : solde FedaPay ────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/admin/withdrawals/balance',
        operationId: 'fedapayBalance',
        summary: '[ADMIN] Solde FedaPay disponible par opérateur',
        tags: ['💸 Retraits & Virements'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Soldes'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 502, description: 'Erreur API FedaPay'),
        ]
    )]
    public function balance(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->ok(false, 'Accès refusé.', [], 403);
        }

        try {
            $result    = FedaBalance::all();
            $formatted = collect($result->balances ?? [])->map(fn ($b) => [
                'mode'     => $b->mode,
                'amount'   => $b->amount,
                'currency' => 'XOF',
            ]);

            return $this->ok(true, 'Soldes FedaPay.', $formatted);

        } catch (\Exception $e) {
            return $this->ok(false, 'Impossible de récupérer les soldes : ' . $e->getMessage(), [], 502);
        }
    }

    // ── Admin : approuver + virer ────────────────────────────────────────────

    #[OA\Post(
        path: '/api/admin/withdrawals/{id}/process',
        operationId: 'withdrawalProcess',
        summary: '[ADMIN] Approuver & virer via FedaPay Payout',
        description: 'Vérifie le solde FedaPay, crée un Payout et envoie les fonds au conducteur. Notification push envoyée au conducteur.',
        tags: ['💸 Retraits & Virements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Virement envoyé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Demande introuvable'),
            new OA\Response(response: 422, description: 'Déjà traitée / solde insuffisant'),
            new OA\Response(response: 502, description: 'Échec FedaPay Payout'),
        ]
    )]
    public function process(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->ok(false, 'Accès refusé.', [], 403);
        }

        $withdrawal = Withdrawal::with(['user'])->find($id);

        if (! $withdrawal) {
            return $this->ok(false, 'Demande introuvable.', [], 404);
        }

        if (! $withdrawal->isPending()) {
            return $this->ok(false, "Demande déjà traitée (statut : « {$withdrawal->status} »).", [], 422);
        }

        $fedaMode = config('fedapay.modes.' . $withdrawal->provider);

        // Vérification du solde avant envoi (non bloquante si API indisponible)
        try {
            $result      = FedaBalance::all();
            $modeBalance = collect($result->balances ?? [])
                ->first(fn ($b) => $b->mode === $fedaMode);

            if ($modeBalance && $modeBalance->amount < $withdrawal->amount) {
                return $this->ok(false, sprintf(
                    'Solde %s insuffisant. Disponible : %s XOF, demandé : %s XOF.',
                    strtoupper($withdrawal->provider),
                    number_format($modeBalance->amount),
                    number_format($withdrawal->amount)
                ), [], 422);
            }
        } catch (\Exception $e) {
            AuditLog::record('withdrawal.balance_check_failed', $request->user()->id, $request->ip(), [
                'error' => $e->getMessage(), 'withdrawal_id' => $id,
            ]);
        }

        // Payout FedaPay
        try {
            $user = $withdrawal->user;

            $payout = FedaPayout::create([
                'amount'   => $withdrawal->amount,
                'currency' => ['iso' => 'XOF'],
                'mode'     => $fedaMode,
                'customer' => [
                    'firstname'    => $user->first_name ?? 'Conducteur',
                    'lastname'     => $user->last_name  ?? '',
                    'email'        => $user->email      ?? null,
                    'phone_number' => [
                        'number'  => $withdrawal->phone_number,
                        'country' => 'bj',
                    ],
                ],
            ]);

            $payout->sendNow();

            $withdrawal->update([
                'status'       => 'approved',
                'reference'    => 'WD-FEDA-' . $payout->id,
                'processed_at' => now(),
            ]);

            // Notification push conducteur
            $user->notify(new WithdrawalProcessed($withdrawal->fresh()));

            AuditLog::record('withdrawal.processed', $request->user()->id, $request->ip(), [
                'withdrawal_id'     => $id,
                'fedapay_payout_id' => $payout->id,
                'amount'            => $withdrawal->amount,
                'provider'          => $withdrawal->provider,
            ]);

            return $this->ok(true, 'Virement envoyé via FedaPay.', [
                'withdrawal_id'     => $withdrawal->id,
                'fedapay_payout_id' => $payout->id,
                'amount'            => $withdrawal->amount,
                'provider'          => $withdrawal->provider,
                'phone'             => $withdrawal->phone_number,
            ]);

        } catch (\Exception $e) {
            $withdrawal->update([
                'status'        => 'failed',
                'failed_reason' => $e->getMessage(),
            ]);

            AuditLog::record('withdrawal.payout_failed', $request->user()->id, $request->ip(), [
                'error' => $e->getMessage(), 'withdrawal_id' => $id,
            ]);

            return $this->ok(false, 'Échec FedaPay Payout : ' . $e->getMessage(), [], 502);
        }
    }

    // ── Admin : rejeter ──────────────────────────────────────────────────────

    #[OA\Post(
        path: '/api/admin/withdrawals/{id}/reject',
        operationId: 'withdrawalReject',
        summary: '[ADMIN] Rejeter une demande de retrait',
        tags: ['💸 Retraits & Virements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason'],
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Solde insuffisant sur le compte MINIZON.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Demande rejetée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Demande introuvable'),
            new OA\Response(response: 422, description: 'Déjà traitée ou motif manquant'),
        ]
    )]
    public function reject(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->ok(false, 'Accès refusé.', [], 403);
        }

        $withdrawal = Withdrawal::find($id);

        if (! $withdrawal) {
            return $this->ok(false, 'Demande introuvable.', [], 404);
        }

        if (! $withdrawal->isPending()) {
            return $this->ok(false, "Demande déjà traitée (statut : « {$withdrawal->status} »).", [], 422);
        }

        $v = Validator::make($request->all(), ['reason' => ['required', 'string', 'max:500']]);

        if ($v->fails()) {
            return $this->ok(false, 'Motif de rejet requis.', $v->errors(), 422);
        }

        $withdrawal->update([
            'status'        => 'rejected',
            'failed_reason' => $request->reason,
            'processed_at'  => now(),
        ]);

        // Notification push conducteur
        $withdrawal->user->notify(new WithdrawalProcessed($withdrawal->fresh()));

        return $this->ok(true, 'Demande rejetée.', $withdrawal->fresh());
    }

    private function ok(bool $success, string $message, mixed $body = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => $success, 'message' => $message, 'body' => $body], $status);
    }
}
