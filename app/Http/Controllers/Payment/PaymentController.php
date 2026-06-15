<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TripValidation;
use App\Notifications\PaymentConfirmed;
use FedaPay\FedaPay;
use FedaPay\Transaction as FedaTransaction;
use FedaPay\Webhook as FedaWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * Gestion complète des paiements Mobile Money via FedaPay.
 *
 * Flux :
 *  1. POST /bookings/{uuid}/pay         → Initie la transaction FedaPay + push USSD
 *  2. GET  /payments/{uuid}             → Polling statut (toutes les 3s côté mobile)
 *  3. POST /payments/webhook/fedapay    → FedaPay notifie le résultat (async)
 *  4. POST /bookings/{uuid}/confirm-arrival → Passager confirme → libère l'escrow
 */
class PaymentController extends Controller
{
    // =========================================================================
    //  BOOT — Initialisation FedaPay
    // =========================================================================

    public function __construct()
    {
        FedaPay::setApiKey(config('fedapay.secret_key'));
        FedaPay::setEnvironment(config('fedapay.environment'));
    }

    // =========================================================================
    //  1. INITIER UN PAIEMENT — Push Mobile Money
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/pay',
        operationId: 'paymentInitiate',
        summary: 'Initier un paiement Mobile Money (FedaPay)',
        description: <<<'DESC'
Crée une transaction FedaPay et déclenche immédiatement un push USSD sur le téléphone du passager.

**Flux asynchrone :**
1. L'API crée la transaction et répond `202 Accepted` immédiatement.
2. Le passager reçoit une notification USSD et entre son code PIN.
3. FedaPay appelle le webhook `/payments/webhook/fedapay` avec le résultat.
4. L'application mobile poll `/payments/{uuid}` toutes les 3 secondes pour connaître le statut.
DESC,
        tags: ['💰 Paiements & Escrow'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'UUID de la réservation', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['provider', 'phone_number'],
                properties: [
                    new OA\Property(property: 'provider',     type: 'string', enum: ['mtn', 'moov', 'celtiis'], example: 'mtn',          description: 'Opérateur Mobile Money'),
                    new OA\Property(property: 'phone_number', type: 'string',                                   example: '97000000',      description: 'Numéro MoMo sans indicatif (ex: 97000000)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Transaction initiée — push USSD envoyé au passager',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Paiement initié. Validez sur votre téléphone.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'payment_uuid',          type: 'string',  example: 'uuid-xxx'),
                                new OA\Property(property: 'transaction_reference', type: 'string',  example: 'TXN-XXXXXXXX'),
                                new OA\Property(property: 'fedapay_id',            type: 'integer', example: 12345),
                                new OA\Property(property: 'amount',                type: 'integer', example: 5000),
                                new OA\Property(property: 'provider',              type: 'string',  example: 'mtn'),
                                new OA\Property(property: 'status',                type: 'string',  example: 'pending'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Réservation non acceptée / accès refusé',       content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable',                        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Un paiement existe déjà pour cette réservation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides',                              content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 502, description: 'Erreur communication FedaPay',                   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function initiate(Request $request, string $uuid): JsonResponse
    {
        // — Récupération et validations —

        $booking = Booking::with(['trip.user.profile', 'passenger.profile'])->where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->passenger_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        if (! $booking->isAccepted()) {
            return $this->apiResponse(false, 'La réservation doit être acceptée par le conducteur avant tout paiement.', [], 403);
        }

        if ($booking->payment()->exists()) {
            return $this->apiResponse(false, 'Un paiement a déjà été initié pour cette réservation.', [], 409);
        }

        $validator = Validator::make($request->all(), [
            'provider'     => ['required', 'in:mtn,moov,celtiis'],
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        // — Calcul des montants —

        $commissionRate = config('fedapay.commission_rate', 10) / 100;
        $gross          = $booking->trip->price_per_seat * $booking->seats_booked;
        $commission     = (int) round($gross * $commissionRate);
        $net            = $gross - $commission;
        $internalRef    = 'TXN-' . strtoupper(Str::random(12));
        $fedaMode       = config('fedapay.modes.' . $request->provider, 'mtn_open');

        // — Normalisation du numéro de téléphone —
        // FedaPay attend le numéro LOCAL sans indicatif pays.
        // Exemples d'entrées acceptées → résultat attendu :
        //   02290159000892  →  0159000892
        //   +2290159000892  →  0159000892
        //   2290159000892   →  0159000892
        //   0159000892      →  0159000892  (déjà local)
        //   97123456        →  97123456    (ancien format 8 chiffres)
        $localPhone = preg_replace('/\s+/', '', $request->phone_number); // retire les espaces
        $localPhone = preg_replace('/^\+?(00?)?229/', '', $localPhone);  // retire l'indicatif +229 / 00229 / 0229 / 229
        // Garde le 0 de tête du numéro local béninois (ex: 0159…)
        // Si encore > 10 chiffres après nettoyage, prendre les 8 derniers (sécurité)
        if (strlen($localPhone) > 10) {
            $localPhone = substr($localPhone, -8);
        }

        // — Création de la transaction FedaPay —

        $fedaTxn     = null;
        $checkoutUrl = null;

        try {
            $profile = $booking->passenger->profile;

            $fedaTxn = FedaTransaction::create([
                'description'  => "Réservation trajet MINIZON — {$booking->trip->departure_city} → {$booking->trip->arrival_city}",
                'amount'       => $gross,
                'currency'     => ['iso' => 'XOF'],
                'callback_url' => config('fedapay.callback_url'),
                'customer'     => [
                    'firstname' => $profile->first_name ?? 'Passager',
                    'lastname'  => $profile->last_name  ?? 'MINIZON',
                    'email'     => $profile->email       ?? "passenger+{$booking->passenger->id}@minizon.bj",
                    'phone_number' => [
                        'number'  => $localPhone,   // numéro local normalisé sans indicatif
                        'country' => 'bj',
                    ],
                ],
            ]);

            // — Génération du token (checkout URL) — utile comme fallback en sandbox
            $tokenObject = $fedaTxn->generateToken();
            $checkoutUrl = $tokenObject->url ?? null;

            // — Envoi du push USSD Mobile Money —
            // sendNow génère un token en interne + POST /v1/{mode} avec {"token":"..."}
            // Ne pas passer de paramètres téléphone : le numéro est déjà dans le customer
            $fedaTxn->sendNowWithToken($fedaMode, $tokenObject->token);

        } catch (\Exception $e) {
            // Extraire les détails de validation FedaPay si disponibles
            $fedaErrors  = method_exists($e, 'getErrors')  ? $e->getErrors()  : null;
            $fedaStatus  = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : null;
            $fedaBody    = method_exists($e, 'getHttpBody')   ? $e->getHttpBody()   : null;

            AuditLog::record(
                'payment.fedapay_error',
                $request->user()->id,
                $request->ip(),
                [
                    'error'        => $e->getMessage(),
                    'feda_errors'  => $fedaErrors,
                    'feda_status'  => $fedaStatus,
                    'booking_uuid' => $uuid,
                    'local_phone'  => $localPhone,
                ],
                $request->userAgent()
            );

            // Si la transaction a été créée mais le push USSD a échoué,
            // sauvegarder le paiement et retourner le checkout URL (utile en sandbox)
            if ($fedaTxn && $checkoutUrl) {
                $payment = Payment::create([
                    'booking_id'            => $booking->id,
                    'user_id'               => $request->user()->id,
                    'gross_amount'          => $gross,
                    'commission_amount'     => $commission,
                    'net_amount'            => $net,
                    'provider'              => $request->provider,
                    'idempotency_key'       => (string) Str::uuid(),
                    'transaction_reference' => $internalRef,
                    'provider_reference'    => (string) $fedaTxn->id,
                    'status'                => 'pending',
                ]);

                return $this->apiResponse(true, 'USSD push indisponible — utilisez le lien de paiement (checkout_url).', [
                    'payment_uuid'          => $payment->uuid,
                    'transaction_reference' => $internalRef,
                    'fedapay_id'            => $fedaTxn->id,
                    'amount'                => $gross,
                    'provider'              => $request->provider,
                    'status'                => 'pending',
                    'checkout_url'          => $checkoutUrl,
                    'push_error'            => $e->getMessage(),
                ], 202);
            }

            // La création même de la transaction a échoué — retourner les détails FedaPay
            return $this->apiResponse(false, 'Erreur FedaPay : ' . $e->getMessage(), [
                'feda_errors' => $fedaErrors,
                'feda_status' => $fedaStatus,
                'phone_used'  => $localPhone,
            ], 502);
        }

        // — Enregistrement en base —

        $payment = Payment::create([
            'booking_id'            => $booking->id,
            'user_id'               => $request->user()->id,
            'gross_amount'          => $gross,
            'commission_amount'     => $commission,
            'net_amount'            => $net,
            'provider'              => $request->provider,
            'idempotency_key'       => (string) Str::uuid(),
            'transaction_reference' => $internalRef,
            'provider_reference'    => (string) $fedaTxn->id,  // ID FedaPay
            'status'                => 'pending',
        ]);

        return $this->apiResponse(true, 'Paiement initié. Validez sur votre téléphone.', [
            'payment_uuid'          => $payment->uuid,
            'transaction_reference' => $internalRef,
            'fedapay_id'            => $fedaTxn->id,
            'amount'                => $gross,
            'provider'              => $request->provider,
            'status'                => 'pending',
            'checkout_url'          => $checkoutUrl, // fallback URL si USSD non disponible
        ], 202);
    }

    // =========================================================================
    //  2. POLLING — Statut d'un paiement
    // =========================================================================

    #[OA\Get(
        path: '/api/payments/{uuid}',
        operationId: 'paymentStatus',
        summary: 'Statut d\'un paiement (polling)',
        description: 'Appelée par l\'application mobile toutes les **3 secondes** après initiation. Retourne le statut actuel du paiement en interrogeant également FedaPay en temps réel.',
        tags: ['💰 Paiements & Escrow'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'UUID interne du paiement', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statut du paiement',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Statut récupéré.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'status',     type: 'string', enum: ['pending', 'locked', 'success', 'failed', 'refunded'], example: 'locked'),
                                new OA\Property(property: 'feda_status', type: 'string', enum: ['pending', 'approved', 'declined', 'canceled'],       example: 'approved'),
                                new OA\Property(property: 'amount',     type: 'integer', example: 5000),
                                new OA\Property(property: 'provider',   type: 'string',  example: 'mtn'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé',     content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Paiement introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function status(Request $request, string $uuid): JsonResponse
    {
        $payment = Payment::with(['booking.trip'])->where('uuid', $uuid)->first();

        if (! $payment) {
            return $this->apiResponse(false, 'Paiement introuvable.', [], 404);
        }

        $u = $request->user();
        if ($payment->user_id !== $u->id && ! $u->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        // Interrogation FedaPay en temps réel si statut encore pending
        $fedaStatus = null;
        if ($payment->isPending() && $payment->provider_reference) {
            try {
                $fedaTxn    = FedaTransaction::retrieve((int) $payment->provider_reference);
                $fedaStatus = $fedaTxn->status;

                // Synchronisation si FedaPay a changé le statut
                if ($fedaStatus === 'approved' && $payment->isPending()) {
                    $this->handleApproved($payment);
                } elseif (in_array($fedaStatus, ['declined', 'canceled']) && $payment->isPending()) {
                    $payment->update(['status' => 'failed']);
                    $payment->booking->update(['payment_status' => 'unpaid']);
                }

            } catch (\Exception) {
                // Silencieux — on retourne le statut local
            }
        }

        return $this->apiResponse(true, 'Statut récupéré.', [
            'status'       => $payment->fresh()->status,
            'feda_status'  => $fedaStatus,
            'amount'       => $payment->gross_amount,
            'provider'     => $payment->provider,
            'reference'    => $payment->transaction_reference,
            'escrow_until' => optional($payment->booking->tripValidation)->auto_release_at,
        ]);
    }

    // =========================================================================
    //  3. WEBHOOK FedaPay — Notification asynchrone
    // =========================================================================

    #[OA\Post(
        path: '/api/payments/webhook/fedapay',
        operationId: 'paymentWebhook',
        summary: 'Webhook FedaPay (notification asynchrone)',
        description: <<<'DESC'
**Route publique** appelée automatiquement par FedaPay lorsqu\'un paiement est finalisé.

Sécurisée par signature HMAC via l\'en-tête `X-FEDAPAY-SIGNATURE`.
Ne jamais exposer le `FEDAPAY_WEBHOOK_SECRET` côté client.
DESC,
        tags: ['💰 Paiements & Escrow'],
        parameters: [
            new OA\Parameter(name: 'X-FEDAPAY-SIGNATURE', in: 'header', required: true, description: 'Signature HMAC fournie par FedaPay', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Webhook traité avec succès'),
            new OA\Response(response: 400, description: 'Signature invalide ou payload corrompu'),
        ]
    )]
    public function webhook(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('X-FEDAPAY-SIGNATURE', '');
        $secret    = config('fedapay.webhook_secret');

        // — Vérification de la signature HMAC —
        try {
            $event = FedaWebhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException $e) {
            AuditLog::record('payment.webhook_invalid_payload', null, $request->ip(), ['error' => $e->getMessage()]);
            return $this->apiResponse(false, 'Payload invalide.', [], 400);
        } catch (\FedaPay\Error\SignatureVerification $e) {
            AuditLog::record('payment.webhook_invalid_signature', null, $request->ip(), ['error' => $e->getMessage()]);
            return $this->apiResponse(false, 'Signature invalide.', [], 400);
        }

        // — Traitement selon le type d'événement —
        $fedaTxn = $event->object ?? $event->data ?? null;
        $fedaId  = (string) ($fedaTxn->id ?? '');

        $payment = Payment::with(['booking.trip', 'booking.tripValidation'])
            ->where('provider_reference', $fedaId)
            ->first();

        if (! $payment) {
            // Transaction inconnue — on acquitte quand même (évite les retry infinis)
            return $this->apiResponse(true, 'Transaction non trouvée — ignorée.', []);
        }

        switch ($event->name) {

            case 'transaction.approved':
                if ($payment->isPending()) {
                    $this->handleApproved($payment);
                }
                break;

            case 'transaction.declined':
            case 'transaction.canceled':
                if ($payment->isPending()) {
                    $payment->update(['status' => 'failed']);
                    $payment->booking->update(['payment_status' => 'unpaid']);
                }
                break;
        }

        AuditLog::record(
            'payment.webhook_received',
            null,
            $request->ip(),
            ['event' => $event->name, 'fedapay_id' => $fedaId]
        );

        return $this->apiResponse(true, 'Webhook traité.', []);
    }

    // =========================================================================
    //  4. CONFIRMATION D'ARRIVÉE — Libération immédiate de l'escrow
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/confirm-arrival',
        operationId: 'confirmArrival',
        summary: 'Confirmer l\'arrivée (passager)',
        description: <<<'DESC'
Le passager confirme qu\'il est bien arrivé à destination.
Les fonds passent de l\'**escrow** au conducteur **immédiatement**, sans attendre le délai de 24h.

Si le passager ne confirme pas, les fonds sont libérés automatiquement par le planificateur `minizon:release-funds`.
DESC,
        tags: ['💰 Paiements & Escrow'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Arrivée confirmée — fonds libérés au conducteur'),
            new OA\Response(response: 403, description: 'Accès refusé',                 content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable',       content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Paiement non en escrow',        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function confirmArrival(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with(['tripValidation', 'payment'])->where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->passenger_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        if (! $booking->tripValidation) {
            return $this->apiResponse(false, 'Aucun paiement en escrow trouvé pour cette réservation.', [], 422);
        }

        if (! $booking->tripValidation->isWaiting()) {
            return $this->apiResponse(false, "Validation déjà traitée (statut : « {$booking->tripValidation->status} »).", [], 422);
        }

        // Libération immédiate
        $booking->tripValidation->update([
            'passenger_confirmed'    => true,
            'passenger_confirmed_at' => now(),
            'status'                 => 'released',
        ]);

        $booking->payment?->update(['status' => 'success']);
        $booking->update(['payment_status' => 'released_to_driver']);

        return $this->apiResponse(true, 'Arrivée confirmée. Les fonds ont été libérés au conducteur. Merci !');
    }

    // =========================================================================
    //  5. ADMIN — Supervision globale des paiements
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/payments',
        operationId: 'adminPayments',
        summary: '[ADMIN] Supervision globale des paiements',
        tags: ['💰 Paiements & Escrow'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Tous les paiements'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function adminIndex(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé. Privilèges administratifs requis.', [], 403);
        }

        $payments = Payment::with(['booking.trip.user.profile', 'booking.passenger.profile'])
            ->orderByDesc('created_at')
            ->get();

        return $this->apiResponse(true, 'Supervision globale des paiements.', $payments);
    }

    // =========================================================================
    //  MÉTHODE PRIVÉE — Logique de validation d'un paiement approuvé
    // =========================================================================

    private function handleApproved(Payment $payment): void
    {
        // Paiement verrouillé en escrow
        $payment->update(['status' => 'locked']);
        $payment->booking->update(['payment_status' => 'escrow_locked']);

        // Création du minuteur 24h pour la libération automatique
        TripValidation::updateOrCreate(
            ['booking_id' => $payment->booking_id],
            [
                'trip_id'         => $payment->booking->trip_id,
                'auto_release_at' => now()->addHours(24),
                'status'          => 'waiting',
            ]
        );

        // Notifier le passager que son paiement a été reçu
        $payment->user->notify(new PaymentConfirmed($payment->load('booking.trip')));
    }

    // =========================================================================
    //  HELPER
    // =========================================================================

    protected function apiResponse(bool $success, string $message, mixed $body = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => $success, 'message' => $message, 'body' => $body], $status);
    }
}
