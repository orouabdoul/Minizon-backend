<?php

namespace App\Http\Controllers\Dispute;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\TripValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class DisputeController extends Controller
{
    // =========================================================================
    //  PASSAGER / CONDUCTEUR — Ouvrir un litige
    //  POST /api/bookings/{uuid}/disputes
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/disputes',
        operationId: 'disputeStore',
        summary: 'Ouvrir un litige',
        description: 'Permet à un passager ou conducteur de signaler un problème sur une réservation. Le paiement est immédiatement gelé en attente de décision admin. Une seule plainte par réservation par utilisateur.',
        tags: ['⚖️ Litiges'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de la réservation'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['reason_type', 'description'],
                    properties: [
                        new OA\Property(property: 'reason_type',  type: 'string', enum: ['driver_absent', 'passenger_absent', 'scam', 'bad_behavior'], example: 'driver_absent'),
                        new OA\Property(property: 'description',  type: 'string', example: 'Le conducteur n\'est jamais arrivé au point de rendez-vous.'),
                        new OA\Property(property: 'proof',        type: 'string', format: 'binary', nullable: true, description: 'Capture d\'écran ou preuve (jpg/png/pdf — max 5 Mo)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Litige ouvert, paiement gelé'),
            new OA\Response(response: 403, description: 'Accès refusé',         content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Litige déjà ouvert',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides',    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request, string $bookingUuid): JsonResponse
    {
        $booking = Booking::with(['trip', 'payment'])->where('uuid', $bookingUuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $user        = $request->user();
        $isDriver    = $booking->trip->user_id    === $user->id;
        $isPassenger = $booking->passenger_id     === $user->id;

        if (! $isDriver && ! $isPassenger) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $alreadyDisputed = Dispute::where('booking_id', $booking->id)
            ->where('reporter_id', $user->id)
            ->exists();

        if ($alreadyDisputed) {
            return $this->apiResponse(false, 'Vous avez déjà ouvert un litige pour cette réservation.', [], 409);
        }

        $validator = Validator::make($request->all(), [
            'reason_type' => ['required', 'in:driver_absent,passenger_absent,scam,bad_behavior'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'proof'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('disputes/proofs', 'public');
        }

        $dispute = Dispute::create([
            'booking_id'  => $booking->id,
            'reporter_id' => $user->id,
            'reason_type' => $request->reason_type,
            'description' => $request->description,
            'proof_path'  => $proofPath,
            'status'      => 'pending',
        ]);

        // Geler le paiement en escrow (empêche la libération auto à 24h)
        TripValidation::where('booking_id', $booking->id)
            ->update(['status' => 'disputed']);

        return $this->apiResponse(true, 'Litige ouvert. Notre équipe examine votre demande sous 48h.', $dispute->load(['booking', 'reporter.profile']), 201);
    }

    // =========================================================================
    //  UTILISATEUR — Mes litiges
    //  GET /api/disputes
    // =========================================================================

    #[OA\Get(
        path: '/api/disputes',
        operationId: 'disputeIndex',
        summary: 'Mes litiges',
        description: 'Liste tous les litiges ouverts par l\'utilisateur connecté.',
        tags: ['⚖️ Litiges'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des litiges'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $disputes = Dispute::with(['booking.trip', 'assignedAdmin.profile'])
            ->where('reporter_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->apiResponse(true, 'Litiges récupérés.', $disputes);
    }

    // =========================================================================
    //  UTILISATEUR — Détail d'un litige
    //  GET /api/disputes/{id}
    // =========================================================================

    #[OA\Get(
        path: '/api/disputes/{id}',
        operationId: 'disputeShow',
        summary: 'Détail d\'un litige',
        tags: ['⚖️ Litiges'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail du litige'),
            new OA\Response(response: 403, description: 'Accès refusé',       content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Litige introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $dispute = Dispute::with(['booking.trip', 'booking.passenger.profile', 'reporter.profile', 'assignedAdmin.profile'])
            ->find($id);

        if (! $dispute) {
            return $this->apiResponse(false, 'Litige introuvable.', [], 404);
        }

        $user = $request->user();
        if ($dispute->reporter_id !== $user->id && ! $user->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        return $this->apiResponse(true, 'Détail du litige.', $dispute);
    }

}
