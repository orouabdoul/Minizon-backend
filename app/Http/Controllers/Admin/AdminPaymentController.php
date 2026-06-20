<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TripValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '💳 Admin — Paiements', description: 'Supervision et gestion des transactions financières (Back-Office)')]
class AdminPaymentController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'locked'   => 'Sécurisé',
            'success'  => 'Libéré',
            'failed'   => 'Échoué',
            'refunded' => 'Remboursé',
            default    => 'En attente',
        };
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'mtn'     => 'MTN Money',
            'moov'    => 'Moov Money',
            'celtiis' => 'Celtiis Cash',
            default   => ucfirst($provider),
        };
    }

    private function format(Payment $payment): array
    {
        $booking          = $payment->booking;
        $trip             = $booking?->trip;
        $passenger        = $booking?->passenger;
        $passengerProfile = $passenger?->profile;
        $driver           = $trip?->user;
        $driverProfile    = $driver?->profile;

        $passengerName = trim(($passengerProfile?->first_name ?? '') . ' ' . ($passengerProfile?->last_name ?? ''))
            ?: ($passenger?->phone ?? '—');

        $driverName = trim(($driverProfile?->first_name ?? '') . ' ' . ($driverProfile?->last_name ?? ''))
            ?: ($driver?->phone ?? '—');

        return [
            'id'                => $payment->uuid,
            'paymentId'         => 'PAY-' . strtoupper(substr($payment->uuid, 0, 8)),
            'createdAt'         => $payment->created_at?->format('d/m/Y H:i') ?? '—',
            'createdAgo'        => $payment->created_at?->diffForHumans() ?? '—',

            'passengerName'     => $passengerName,
            'passengerAvatar'   => $this->fileUrl($passengerProfile?->selfie_front),
            'passengerPhone'    => $passenger?->phone ?? '—',
            'passengerVerified' => $passengerProfile?->kyc_status === 'approved',

            'driverName'        => $driverName,
            'driverAvatar'      => $this->fileUrl($driverProfile?->selfie_front),

            'from'              => $trip?->departure_city ?? '—',
            'to'                => $trip?->arrival_city   ?? '—',
            'reservationId'     => $booking ? 'RES-' . strtoupper(substr($booking->uuid, 0, 8)) : '—',

            'amount'            => number_format($payment->gross_amount,     0, ',', ' ') . ' FCFA',
            'commission'        => number_format($payment->commission_amount, 0, ',', ' ') . ' FCFA',
            'netAmount'         => number_format($payment->net_amount,        0, ',', ' ') . ' FCFA',

            'method'            => $this->providerLabel($payment->provider),
            'provider'          => $payment->provider,
            'reference'         => $payment->transaction_reference,
            'providerReference' => $payment->provider_reference,

            'status'            => $this->statusLabel($payment->status),
            'canRefund'         => $payment->status === 'locked',
        ];
    }

    private function buildTimeline(Payment $payment): array
    {
        $events = [];

        $events[] = [
            'label'  => 'Paiement initié',
            'time'   => $payment->created_at?->format('d/m/Y H:i'),
            'status' => 'done',
        ];

        if (in_array($payment->status, ['locked', 'success', 'refunded'])) {
            $events[] = [
                'label'  => 'Paiement approuvé et sécurisé (escrow)',
                'time'   => $payment->updated_at?->format('d/m/Y H:i'),
                'status' => 'done',
            ];
        }

        if ($payment->booking?->tripValidation?->passenger_confirmed) {
            $events[] = [
                'label'  => 'Arrivée confirmée par le passager',
                'time'   => $payment->booking->tripValidation->passenger_confirmed_at?->format('d/m/Y H:i'),
                'status' => 'done',
            ];
        }

        if ($payment->booking?->trip?->status === 'completed') {
            $events[] = [
                'label'  => 'Trajet terminé',
                'time'   => $payment->booking->trip->updated_at?->format('d/m/Y H:i'),
                'status' => 'done',
            ];
        }

        if ($payment->status === 'success') {
            $events[] = [
                'label'  => 'Fonds libérés au conducteur',
                'time'   => $payment->updated_at?->format('d/m/Y H:i'),
                'status' => 'done',
            ];
        }

        if ($payment->status === 'failed') {
            $events[] = [
                'label'  => 'Paiement échoué ou refusé',
                'time'   => $payment->updated_at?->format('d/m/Y H:i'),
                'status' => 'cancelled',
            ];
        }

        if ($payment->status === 'refunded') {
            $events[] = [
                'label'  => 'Remboursement effectué',
                'time'   => $payment->updated_at?->format('d/m/Y H:i'),
                'status' => 'cancelled',
            ];
        }

        return $events;
    }

    private function baseQuery()
    {
        return Payment::query()->with([
            'booking.trip.user.profile',
            'booking.passenger.profile',
            'booking.tripValidation',
        ]);
    }

    // =========================================================================
    //  METRICS  GET /api/admin/payments/metrics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/payments/metrics',
        operationId: 'adminPaymentMetrics',
        summary: '[ADMIN] Métriques des paiements',
        description: 'Retourne les 6 KPI affichés en haut de la page Gestion des Paiements : total, sécurisés, libérés, échoués/remboursés, volume brut et commissions collectées.',
        tags: ['💳 Admin — Paiements'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Métriques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Métriques paiements récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',      type: 'integer', example: 4820,              description: 'Nombre total de transactions'),
                                new OA\Property(property: 'secured',    type: 'integer', example: 312,               description: 'Paiements en escrow (status locked)'),
                                new OA\Property(property: 'released',   type: 'integer', example: 3940,              description: 'Fonds libérés au conducteur (status success)'),
                                new OA\Property(property: 'failed',     type: 'integer', example: 210,               description: 'Paiements échoués ou remboursés'),
                                new OA\Property(property: 'volume',     type: 'string',  example: '12 450 000 FCFA', description: 'Volume brut total (escrow + success)'),
                                new OA\Property(property: 'commission', type: 'string',  example: '1 245 000 FCFA',  description: 'Commissions collectées sur les paiements libérés'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function metrics(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $total      = Payment::count();
        $secured    = Payment::where('status', 'locked')->count();
        $released   = Payment::where('status', 'success')->count();
        $failed     = Payment::whereIn('status', ['failed', 'refunded'])->count();
        $volume     = Payment::whereIn('status', ['locked', 'success'])->sum('gross_amount');
        $commission = Payment::where('status', 'success')->sum('commission_amount');

        return $this->apiResponse(true, 'Métriques paiements récupérées.', [
            'total'      => $total,
            'secured'    => $secured,
            'released'   => $released,
            'failed'     => $failed,
            'volume'     => number_format((int) $volume,     0, ',', ' ') . ' FCFA',
            'commission' => number_format((int) $commission, 0, ',', ' ') . ' FCFA',
        ]);
    }

    // =========================================================================
    //  INDEX  GET /api/admin/payments
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/payments',
        operationId: 'adminPaymentIndex',
        summary: '[ADMIN] Liste paginée des paiements',
        description: 'Retourne toutes les transactions avec filtres combinables : statut, opérateur Mobile Money, date et recherche textuelle (référence, nom, téléphone).',
        tags: ['💳 Admin — Paiements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 10, maximum: 100)),
            new OA\Parameter(
                name: 'search', in: 'query',
                description: 'Référence de transaction, nom ou téléphone du passager',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'status', in: 'query',
                description: 'Filtre par statut de paiement',
                schema: new OA\Schema(type: 'string', enum: ['En attente', 'Sécurisé', 'Libéré', 'Échoué', 'Remboursé'])
            ),
            new OA\Parameter(
                name: 'method', in: 'query',
                description: 'Filtre par opérateur Mobile Money',
                schema: new OA\Schema(type: 'string', enum: ['MTN', 'Moov', 'Celtiis'])
            ),
            new OA\Parameter(
                name: 'date', in: 'query',
                description: 'Date de création du paiement (YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2025-06-15')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des paiements',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Paiements récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',        type: 'integer', example: 4820),
                                new OA\Property(property: 'per_page',     type: 'integer', example: 10),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page',    type: 'integer', example: 482),
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id',                type: 'string',  example: 'c7d8e9f0-...',   description: 'UUID du paiement'),
                                            new OA\Property(property: 'paymentId',         type: 'string',  example: 'PAY-C7D8E9F0',   description: 'Identifiant lisible affiché en tableau'),
                                            new OA\Property(property: 'createdAt',         type: 'string',  example: '14/06/2025 09:30'),
                                            new OA\Property(property: 'createdAgo',        type: 'string',  example: 'il y a 3 heures'),
                                            new OA\Property(property: 'passengerName',     type: 'string',  example: 'Aminata Sow'),
                                            new OA\Property(property: 'passengerAvatar',   type: 'string',  nullable: true),
                                            new OA\Property(property: 'passengerPhone',    type: 'string',  example: '+22997123456'),
                                            new OA\Property(property: 'passengerVerified', type: 'boolean', example: true),
                                            new OA\Property(property: 'driverName',        type: 'string',  example: 'Kofi Mensah'),
                                            new OA\Property(property: 'driverAvatar',      type: 'string',  nullable: true),
                                            new OA\Property(property: 'from',              type: 'string',  example: 'Cotonou'),
                                            new OA\Property(property: 'to',                type: 'string',  example: 'Porto-Novo'),
                                            new OA\Property(property: 'reservationId',     type: 'string',  example: 'RES-A3B4C5D6'),
                                            new OA\Property(property: 'amount',            type: 'string',  example: '3 000 FCFA',      description: 'Montant brut payé par le passager'),
                                            new OA\Property(property: 'commission',        type: 'string',  example: '300 FCFA',        description: 'Commission plateforme (10%)'),
                                            new OA\Property(property: 'netAmount',         type: 'string',  example: '2 700 FCFA',      description: 'Montant net reversé au conducteur'),
                                            new OA\Property(property: 'method',            type: 'string',  example: 'MTN Money',       description: 'Libellé opérateur affiché'),
                                            new OA\Property(property: 'provider',          type: 'string',  enum: ['mtn', 'moov', 'celtiis'], description: 'Valeur brute du provider'),
                                            new OA\Property(property: 'reference',         type: 'string',  example: 'TXN-00001234',   description: 'Référence interne Minizon'),
                                            new OA\Property(property: 'providerReference', type: 'string',  nullable: true,             description: 'ID transaction FedaPay'),
                                            new OA\Property(property: 'status',            type: 'string',  enum: ['En attente', 'Sécurisé', 'Libéré', 'Échoué', 'Remboursé']),
                                            new OA\Property(property: 'canRefund',         type: 'boolean', example: false,             description: 'true uniquement si status = locked (escrow actif)'),
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
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $perPage = min((int) $request->input('per_page', 10), 100);
        $status  = $request->input('status', '');
        $method  = $request->input('method', '');
        $date    = $request->input('date', '');
        $search  = trim($request->input('search', ''));

        $query = $this->baseQuery();

        if ($status !== '') {
            $dbStatus = match ($status) {
                'Sécurisé'  => 'locked',
                'Libéré'    => 'success',
                'Échoué'    => 'failed',
                'Remboursé' => 'refunded',
                default     => 'pending',
            };
            $query->where('status', $dbStatus);
        }

        if ($method !== '') {
            $provider = match ($method) {
                'MTN'     => 'mtn',
                'Moov'    => 'moov',
                'Celtiis' => 'celtiis',
                default   => strtolower($method),
            };
            $query->where('provider', $provider);
        }

        if ($date !== '') {
            $query->whereDate('created_at', $date);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_reference', 'like', "%{$search}%")
                  ->orWhere('provider_reference',  'like', "%{$search}%")
                  ->orWhere('uuid',                'like', "%{$search}%")
                  ->orWhereHas('booking.passenger', fn ($q) =>
                      $q->where('phone', 'like', "%{$search}%")
                        ->orWhereHas('profile', fn ($q) =>
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name',  'like', "%{$search}%")
                        )
                  );
            });
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->apiResponse(true, 'Paiements récupérés.', [
            'data'         => $paginated->map(fn ($p) => $this->format($p))->values(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  SHOW  GET /api/admin/payments/{uuid}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/payments/{uuid}',
        operationId: 'adminPaymentShow',
        summary: '[ADMIN] Détail d\'un paiement',
        description: 'Retourne toutes les informations d\'un paiement (passager, conducteur, trajet, montants, références FedaPay) ainsi que la timeline des événements financiers pour l\'affichage dans le modal de détail.',
        tags: ['💳 Admin — Paiements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID du paiement'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paiement trouvé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Paiement récupéré.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id',                type: 'string',  example: 'c7d8e9f0-...'),
                                new OA\Property(property: 'paymentId',         type: 'string',  example: 'PAY-C7D8E9F0'),
                                new OA\Property(property: 'createdAt',         type: 'string',  example: '14/06/2025 09:30'),
                                new OA\Property(property: 'createdAgo',        type: 'string',  example: 'il y a 3 heures'),
                                new OA\Property(property: 'passengerName',     type: 'string',  example: 'Aminata Sow'),
                                new OA\Property(property: 'passengerAvatar',   type: 'string',  nullable: true),
                                new OA\Property(property: 'passengerPhone',    type: 'string',  example: '+22997123456'),
                                new OA\Property(property: 'passengerVerified', type: 'boolean', example: true),
                                new OA\Property(property: 'driverName',        type: 'string',  example: 'Kofi Mensah'),
                                new OA\Property(property: 'driverAvatar',      type: 'string',  nullable: true),
                                new OA\Property(property: 'from',              type: 'string',  example: 'Cotonou'),
                                new OA\Property(property: 'to',                type: 'string',  example: 'Porto-Novo'),
                                new OA\Property(property: 'reservationId',     type: 'string',  example: 'RES-A3B4C5D6'),
                                new OA\Property(property: 'amount',            type: 'string',  example: '3 000 FCFA'),
                                new OA\Property(property: 'commission',        type: 'string',  example: '300 FCFA'),
                                new OA\Property(property: 'netAmount',         type: 'string',  example: '2 700 FCFA'),
                                new OA\Property(property: 'method',            type: 'string',  example: 'MTN Money'),
                                new OA\Property(property: 'provider',          type: 'string',  enum: ['mtn', 'moov', 'celtiis']),
                                new OA\Property(property: 'reference',         type: 'string',  example: 'TXN-00001234'),
                                new OA\Property(property: 'providerReference', type: 'string',  nullable: true, example: '1234567'),
                                new OA\Property(property: 'status',            type: 'string',  enum: ['En attente', 'Sécurisé', 'Libéré', 'Échoué', 'Remboursé']),
                                new OA\Property(property: 'canRefund',         type: 'boolean', example: false),
                                new OA\Property(
                                    property: 'timelineEvents',
                                    type: 'array',
                                    description: 'Historique chronologique des événements financiers',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'label',  type: 'string', example: 'Paiement initié'),
                                            new OA\Property(property: 'time',   type: 'string', example: '14/06/2025 09:30'),
                                            new OA\Property(property: 'status', type: 'string', enum: ['done', 'cancelled'], description: 'done = vert, cancelled = rouge'),
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
            new OA\Response(response: 404, description: 'Paiement introuvable'),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $payment = $this->baseQuery()->where('uuid', $uuid)->first();

        if (! $payment) {
            return $this->apiResponse(false, 'Paiement introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Paiement récupéré.', array_merge(
            $this->format($payment),
            ['timelineEvents' => $this->buildTimeline($payment)]
        ));
    }

    // =========================================================================
    //  REFUND  POST /api/admin/payments/{uuid}/refund
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/payments/{uuid}/refund',
        operationId: 'adminPaymentRefund',
        summary: '[ADMIN] Rembourser un paiement',
        description: 'Remboursement manuel d\'un paiement en escrow (status locked uniquement). Met à jour le paiement, la réservation associée et annule le minuteur TripValidation. Impossible si le paiement est déjà libéré, échoué ou remboursé.',
        tags: ['💳 Admin — Paiements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID du paiement à rembourser'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Remboursement effectué',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Remboursement effectué avec succès.'),
                        new OA\Property(property: 'body',    type: 'object',  description: 'Objet Payment mis à jour (canRefund = false, status = Remboursé)'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Paiement introuvable'),
            new OA\Response(
                response: 422,
                description: 'Remboursement impossible — le paiement n\'est pas en escrow',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string',  example: 'Ce paiement ne peut pas être remboursé (statut : Libéré).'),
                    ]
                )
            ),
        ]
    )]
    public function refund(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $payment = $this->baseQuery()->where('uuid', $uuid)->first();

        if (! $payment) {
            return $this->apiResponse(false, 'Paiement introuvable.', [], 404);
        }

        if ($payment->status !== 'locked') {
            return $this->apiResponse(
                false,
                'Ce paiement ne peut pas être remboursé (statut : ' . $this->statusLabel($payment->status) . ').',
                [],
                422
            );
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'refunded']);

            if ($payment->booking) {
                $payment->booking->update(['payment_status' => 'refunded']);

                TripValidation::where('booking_id', $payment->booking_id)
                    ->whereIn('status', ['waiting', 'disputed'])
                    ->update(['status' => 'cancelled']);
            }
        });

        $payment->refresh()->load([
            'booking.trip.user.profile',
            'booking.passenger.profile',
            'booking.tripValidation',
        ]);

        return $this->apiResponse(true, 'Remboursement effectué avec succès.', $this->format($payment));
    }
}
