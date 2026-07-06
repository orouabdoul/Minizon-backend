<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\TripValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Page "Demande de remboursement" — formulaire et historique passager.
 *
 * Les remboursements sont stockés comme des Dispute avec reason_type
 * mappé depuis les raisons en langage naturel du passager.
 * Les preuves multiples sont sérialisées en JSON dans proof_path.
 *
 * Workflow : context → formulaire → POST store → état "Soumis"
 */
class PassengerRefundController extends Controller
{
    public const MAX_PROOF_IMAGES = 3;

    // ── Raisons affichées dans le formulaire Flutter ──────────────────────────
    private const REASONS = [
        ['key' => 'driver_absent',   'label' => 'Conducteur absent ou en retard'],
        ['key' => 'trip_cancelled',  'label' => 'Trajet annulé sans prévenir'],
        ['key' => 'wrong_route',     'label' => 'Trajet différent de la description'],
        ['key' => 'payment_issue',   'label' => 'Problème de paiement'],
        ['key' => 'bad_behavior',    'label' => 'Mauvais comportement du conducteur'],
        ['key' => 'other',           'label' => 'Autre raison'],
    ];

    // ── Mapping → reason_type Dispute (colonne enum existante) ───────────────
    private const REASON_TO_DISPUTE_TYPE = [
        'driver_absent'  => 'driver_absent',
        'trip_cancelled' => 'driver_absent',
        'wrong_route'    => 'bad_behavior',
        'payment_issue'  => 'scam',
        'bad_behavior'   => 'bad_behavior',
        'other'          => 'bad_behavior',
    ];

    // =========================================================================
    //  GET /api/passenger/bookings/{uuid}/refund-context
    //  Contexte de la réservation pour pré-remplir le formulaire
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/bookings/{uuid}/refund-context',
        operationId: 'passengerRefundContext',
        summary: 'Contexte de remboursement pour une réservation',
        description: "Retourne les données de pré-remplissage du formulaire de demande de remboursement : infos du trajet, montant, référence de transaction, liste des raisons disponibles et nombre maximum de preuves.",
        tags: ['👤 Passenger — Remboursement'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contexte de remboursement',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Contexte de remboursement.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'booking_uuid',       type: 'string', format: 'uuid'),
                                new OA\Property(property: 'trip_origin',        type: 'string', example: 'Cotonou — Cadjehoun'),
                                new OA\Property(property: 'trip_destination',   type: 'string', example: 'Abomey-Calavi — Godomey'),
                                new OA\Property(property: 'trip_date',          type: 'string', example: 'Sam. 05/07 à 08h30'),
                                new OA\Property(property: 'transaction_ref',    type: 'string', example: 'TXN-20250705-XXXXX'),
                                new OA\Property(property: 'amount',             type: 'integer', example: 1500),
                                new OA\Property(property: 'formatted_amount',   type: 'string',  example: '1 500 FCFA'),
                                new OA\Property(property: 'max_proof_images',   type: 'integer', example: 3),
                                new OA\Property(property: 'already_refunded',   type: 'boolean', example: false),
                                new OA\Property(
                                    property: 'reasons',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',   type: 'string', example: 'driver_absent'),
                                            new OA\Property(property: 'label', type: 'string', example: 'Conducteur absent ou en retard'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé',         content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function context(Request $request, string $bookingUuid): JsonResponse
    {
        $booking = Booking::with(['trip', 'payment'])
            ->where('uuid', $bookingUuid)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->passenger_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $trip    = $booking->trip;
        $payment = $booking->payment;

        $tz       = 'Africa/Porto-Novo';
        $tripDate = $trip?->departure_time
            ? $trip->departure_time->setTimezone($tz)->translatedFormat('D. d/m \à H\hi')
            : '—';

        $amount          = $payment ? (int) $payment->gross_amount : ($trip ? (int) $trip->price_per_seat : 0);
        $formattedAmount = number_format($amount, 0, ',', ' ') . ' FCFA';

        $transactionRef = $payment?->transaction_reference
            ?? $payment?->provider_reference
            ?? 'TXN-' . strtoupper(substr($booking->uuid, 0, 12));

        $alreadyRefunded = Dispute::where('booking_id', $booking->id)
            ->where('reporter_id', $request->user()->id)
            ->exists();

        return $this->apiResponse(true, 'Contexte de remboursement.', [
            'booking_uuid'     => $booking->uuid,
            'trip_origin'      => $this->formatLocation($trip?->departure_city, $trip?->departure_neighborhood),
            'trip_destination' => $this->formatLocation($trip?->arrival_city, $trip?->arrival_neighborhood),
            'trip_date'        => $tripDate,
            'transaction_ref'  => $transactionRef,
            'amount'           => $amount,
            'formatted_amount' => $formattedAmount,
            'max_proof_images' => self::MAX_PROOF_IMAGES,
            'already_refunded' => $alreadyRefunded,
            'reasons'          => self::REASONS,
        ]);
    }

    // =========================================================================
    //  POST /api/passenger/bookings/{uuid}/refund
    //  Soumettre la demande de remboursement
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/bookings/{uuid}/refund',
        operationId: 'passengerRefundStore',
        summary: 'Soumettre une demande de remboursement',
        description: "Crée une demande de remboursement (stockée comme Dispute de type `refund`). Accepte jusqu'à 3 images de preuve en `multipart/form-data`. Un seul remboursement par réservation par passager.",
        tags: ['👤 Passenger — Remboursement'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['reason'],
                    properties: [
                        new OA\Property(property: 'reason',       type: 'string', enum: ['driver_absent', 'trip_cancelled', 'wrong_route', 'payment_issue', 'bad_behavior', 'other'], example: 'driver_absent'),
                        new OA\Property(property: 'description',  type: 'string', nullable: true, example: 'Le conducteur n\'est pas arrivé après 30 minutes d\'attente.'),
                        new OA\Property(property: 'proof_images', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), nullable: true, description: 'Jusqu\'à 3 images (jpg/png/webp — max 5 Mo chacune)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Demande soumise',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Demande de remboursement soumise.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'refund_uuid',      type: 'string', format: 'uuid'),
                                new OA\Property(property: 'status',           type: 'string', example: 'pending'),
                                new OA\Property(property: 'formatted_amount', type: 'string', example: '1 500 FCFA'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé',           content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Demande déjà soumise',    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides',       content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request, string $bookingUuid): JsonResponse
    {
        $booking = Booking::with(['trip', 'payment'])
            ->where('uuid', $bookingUuid)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->passenger_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        if (Dispute::where('booking_id', $booking->id)->where('reporter_id', $request->user()->id)->exists()) {
            return $this->apiResponse(false, 'Une demande de remboursement existe déjà pour cette réservation.', [], 409);
        }

        $validated = $request->validate([
            'reason'          => ['required', 'string', 'in:driver_absent,trip_cancelled,wrong_route,payment_issue,bad_behavior,other'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'proof_images'    => ['nullable', 'array', 'max:' . self::MAX_PROOF_IMAGES],
            'proof_images.*'  => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // ── Sauvegarde des images ─────────────────────────────────────────────
        $proofPaths = [];
        if ($request->hasFile('proof_images')) {
            foreach ($request->file('proof_images') as $file) {
                $proofPaths[] = $file->store('refunds/proofs', 'public');
            }
        }

        $reasonLabel  = collect(self::REASONS)->firstWhere('key', $validated['reason'])['label'] ?? $validated['reason'];
        $disputeType  = self::REASON_TO_DISPUTE_TYPE[$validated['reason']] ?? 'bad_behavior';
        $description  = trim(($validated['description'] ?? '') . "\n\n[Raison : {$reasonLabel}]");

        $dispute = Dispute::create([
            'booking_id'  => $booking->id,
            'reporter_id' => $request->user()->id,
            'reason_type' => $disputeType,
            'description' => $description,
            'proof_path'  => ! empty($proofPaths) ? json_encode($proofPaths) : null,
            'status'      => 'pending',
        ]);

        // Geler le paiement si une TripValidation existe
        TripValidation::where('booking_id', $booking->id)
            ->whereNotIn('status', ['disputed', 'resolved_driver', 'resolved_passenger'])
            ->update(['status' => 'disputed']);

        $amount = $booking->payment ? (int) $booking->payment->gross_amount : 0;

        return $this->apiResponse(true, 'Demande de remboursement soumise. Traitement sous 3–7 jours ouvrables.', [
            'refund_uuid'     => $dispute->uuid,
            'status'          => $dispute->status,
            'formatted_amount'=> number_format($amount, 0, ',', ' ') . ' FCFA',
        ], 201);
    }

    // =========================================================================
    //  GET /api/passenger/refunds
    //  Historique des demandes de remboursement
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/refunds',
        operationId: 'passengerRefundHistory',
        summary: 'Historique des demandes de remboursement',
        description: 'Liste les demandes de remboursement soumises par le passager connecté, avec statut et détails.',
        tags: ['👤 Passenger — Remboursement'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Historique des remboursements',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Historique des remboursements.'),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/PassengerRefundItem')
                        ),
                    ]
                )
            ),
        ]
    )]
    public function history(Request $request): JsonResponse
    {
        $disputes = Dispute::with(['booking.trip', 'booking.payment'])
            ->where('reporter_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($d) => $this->formatRefund($d));

        return $this->apiResponse(true, 'Historique des remboursements.', $disputes);
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'PassengerRefundItem',
        description: 'Élément de l\'historique des remboursements — correspond au modèle RefundHistoryItem du Flutter.',
        properties: [
            new OA\Property(property: 'id',             type: 'string', example: 'REF-1A2B3C4D', description: 'Référence courte affichée en police monospace.'),
            new OA\Property(property: 'status',         type: 'string', enum: ['pending', 'under_review', 'approved', 'refunded', 'rejected'], description: 'Correspond aux cases de l\'enum Dart RefundHistoryStatus.'),
            new OA\Property(property: 'route',          type: 'string', example: 'Cotonou → Abomey-Calavi'),
            new OA\Property(property: 'date',           type: 'string', example: 'Sam. 05/07/2025'),
            new OA\Property(property: 'amount',         type: 'string', example: '1 500 FCFA'),
            new OA\Property(property: 'reason',         type: 'string', example: 'Conducteur absent ou en retard'),
            new OA\Property(property: 'processed_date', type: 'string', nullable: true, example: '08/07/2025', description: 'Date de traitement par l\'équipe — null si encore en attente.'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    // Mapping statuts DB Dispute → clés enum Dart RefundHistoryStatus
    private const STATUS_TO_FLUTTER = [
        'pending'            => 'pending',
        'investigating'      => 'under_review',
        'approved'           => 'approved',
        'resolved_passenger' => 'refunded',
        'resolved_driver'    => 'rejected',
        'closed'             => 'rejected',
    ];

    // Statuts considérés comme "traités" pour renseigner processed_date
    private const RESOLVED_STATUSES = ['approved', 'resolved_passenger', 'resolved_driver', 'closed'];

    private function formatRefund(Dispute $dispute): array
    {
        $booking = $dispute->booking;
        $trip    = $booking?->trip;
        $payment = $booking?->payment;
        $amount  = $payment ? (int) $payment->gross_amount : 0;

        $tz = 'Africa/Porto-Novo';

        // Référence courte monospace affichée dans la bannière de statut
        $shortId = 'REF-' . strtoupper(substr(str_replace('-', '', $dispute->uuid), 0, 8));

        // Raison extraite depuis la description (format injecté à la création)
        $reason = '—';
        if ($dispute->description && preg_match('/\[Raison : (.+?)\]/', $dispute->description, $m)) {
            $reason = $m[1];
        }

        // Date de traitement : updated_at si le statut est terminal
        $processedDate = null;
        if (in_array($dispute->status, self::RESOLVED_STATUSES, true) && $dispute->updated_at) {
            $processedDate = $dispute->updated_at->setTimezone($tz)->format('d/m/Y');
        }

        return [
            'id'             => $shortId,
            'status'         => self::STATUS_TO_FLUTTER[$dispute->status] ?? 'pending',
            'route'          => $trip
                ? $trip->departure_city . ' → ' . $trip->arrival_city
                : 'Trajet supprimé',
            'date'           => $dispute->created_at
                ? $dispute->created_at->setTimezone($tz)->translatedFormat('D. d/m/Y')
                : '—',
            'amount'         => number_format($amount, 0, ',', ' ') . ' FCFA',
            'reason'         => $reason,
            'processed_date' => $processedDate,
        ];
    }

    private function formatLocation(?string $city, ?string $neighborhood): string
    {
        if (! $city) return '—';
        return $neighborhood ? "$city — $neighborhood" : $city;
    }
}
