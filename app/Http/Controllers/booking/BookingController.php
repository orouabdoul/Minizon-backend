<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    // =========================================================================
    //  PASSAGER — Créer une réservation
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/bookings',
        operationId: 'bookingStore',
        summary: 'Réserver une place sur un trajet',
        description: 'Un passager envoie une demande de réservation pour un trajet `pending`. Le conducteur devra accepter ou rejeter.',
        tags: ['📦 Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'seats_booked', type: 'integer', minimum: 1, example: 1, description: 'Nombre de places (défaut : 1)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Demande envoyée au conducteur'),
            new OA\Response(response: 403, description: 'Places insuffisantes / trajet indisponible', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable',                          content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Réservation active déjà existante',           content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if (! $trip->isPending()) {
            return $this->apiResponse(false, "Ce trajet n'accepte plus de réservations (statut : « {$trip->status} »).", [], 403);
        }

        $seats = max(1, (int) $request->input('seats_booked', 1));

        if (! $trip->hasSeatsAvailable($seats)) {
            return $this->apiResponse(false, "Places insuffisantes. Disponible : {$trip->available_seats}.", [], 403);
        }

        $alreadyBooked = Booking::where('trip_id', $trip->id)
            ->where('passenger_id', $request->user()->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();

        if ($alreadyBooked) {
            return $this->apiResponse(false, 'Vous avez déjà une réservation active sur ce trajet.', [], 409);
        }

        $booking = Booking::create([
            'trip_id'      => $trip->id,
            'passenger_id' => $request->user()->id,
            'seats_booked' => $seats,
            'status'       => 'pending',
        ]);

        return $this->apiResponse(true, 'Demande de réservation envoyée au conducteur.', $booking->load(['trip', 'passenger.profile']), 201);
    }

    // =========================================================================
    //  PASSAGER — Mes réservations
    // =========================================================================

    #[OA\Get(
        path: '/api/bookings',
        operationId: 'bookingIndex',
        summary: 'Mes réservations (passager)',
        description: 'Toutes les réservations du passager connecté, triées de la plus récente.',
        tags: ['📦 Réservations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des réservations'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::with(['trip.user.profile', 'trip.vehicle.vehicleType'])
            ->where('passenger_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->apiResponse(true, 'Réservations récupérées.', $bookings);
    }

    // =========================================================================
    //  Détail d'une réservation
    // =========================================================================

    #[OA\Get(
        path: '/api/bookings/{uuid}',
        operationId: 'bookingShow',
        summary: 'Détail d\'une réservation',
        tags: ['📦 Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail complet'),
            new OA\Response(response: 403, description: 'Accès refusé',     content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Introuvable',      content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with(['trip.user.profile', 'trip.vehicle.vehicleType', 'passenger.profile', 'payment'])
            ->where('uuid', $uuid)
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $u = $request->user();
        $isPassenger = $booking->passenger_id === $u->id;
        $isDriver    = $booking->trip->user_id  === $u->id;

        if (! $isPassenger && ! $isDriver && ! $u->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        return $this->apiResponse(true, 'Détail de la réservation.', $booking);
    }

    // =========================================================================
    //  CONDUCTEUR — Accepter une réservation
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/accept',
        operationId: 'bookingAccept',
        summary: 'Accepter une réservation',
        description: 'Le conducteur accepte la demande. Les places disponibles sont décrémentées automatiquement.',
        tags: ['📦 Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réservation acceptée'),
            new OA\Response(response: 403, description: 'Accès refusé',            content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Statut incompatible',     content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function accept(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with('trip')->where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        if (! $booking->isPending()) {
            return $this->apiResponse(false, "Impossible d'accepter — statut actuel : « {$booking->status} ».", [], 422);
        }

        if (! $booking->trip->hasSeatsAvailable($booking->seats_booked)) {
            return $this->apiResponse(false, 'Plus assez de places disponibles pour accepter cette réservation.', [], 422);
        }

        $booking->update(['status' => 'accepted']);
        $booking->trip->decrement('available_seats', $booking->seats_booked);

        return $this->apiResponse(true, 'Réservation acceptée.', $booking->fresh(['trip', 'passenger.profile']));
    }

    // =========================================================================
    //  CONDUCTEUR — Rejeter une réservation
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/reject',
        operationId: 'bookingReject',
        summary: 'Rejeter une réservation',
        tags: ['📦 Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réservation rejetée'),
            new OA\Response(response: 403, description: 'Accès refusé',        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Introuvable',         content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Statut incompatible', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function reject(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with('trip')->where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        if (! $booking->isPending()) {
            return $this->apiResponse(false, "Impossible de rejeter — statut actuel : « {$booking->status} ».", [], 422);
        }

        $booking->update(['status' => 'rejected']);

        return $this->apiResponse(true, 'Réservation rejetée.', $booking->fresh());
    }

    // =========================================================================
    //  PASSAGER ou CONDUCTEUR — Annuler
    // =========================================================================

    #[OA\Post(
        path: '/api/bookings/{uuid}/cancel',
        operationId: 'bookingCancel',
        summary: 'Annuler une réservation',
        description: 'Le passager **ou** le conducteur peut annuler. Si la réservation était `accepted`, les places sont restituées au trajet.',
        tags: ['📦 Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réservation annulée'),
            new OA\Response(response: 403, description: 'Accès refusé',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Introuvable',    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Déjà annulée',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with('trip')->where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        $u           = $request->user();
        $isPassenger = $booking->passenger_id    === $u->id;
        $isDriver    = $booking->trip->user_id   === $u->id;

        if (! $isPassenger && ! $isDriver) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        if ($booking->status === 'cancelled') {
            return $this->apiResponse(false, 'Cette réservation est déjà annulée.', [], 422);
        }

        if ($booking->isAccepted()) {
            $booking->trip->increment('available_seats', $booking->seats_booked);
        }

        $booking->update(['status' => 'cancelled']);

        return $this->apiResponse(true, 'Réservation annulée.', $booking->fresh());
    }

    // =========================================================================
    //  CONDUCTEUR — Réservations reçues
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/bookings',
        operationId: 'driverBookings',
        summary: 'Réservations reçues (conducteur)',
        description: 'Toutes les demandes de réservation reçues sur les trajets du conducteur connecté.',
        tags: ['📦 Réservations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des réservations reçues'),
        ]
    )]
    public function driverBookings(Request $request): JsonResponse
    {
        $bookings = Booking::with(['trip', 'passenger.profile'])
            ->whereHas('trip', fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderByDesc('created_at')
            ->get();

        return $this->apiResponse(true, 'Réservations reçues récupérées.', $bookings);
    }

    // =========================================================================
    //  ADMIN — Supervision
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/bookings',
        operationId: 'adminBookings',
        summary: '[ADMIN] Supervision globale des réservations',
        tags: ['📦 Réservations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Toutes les réservations'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function adminIndex(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé. Privilèges administratifs requis.', [], 403);
        }

        $bookings = Booking::with(['trip.user.profile', 'passenger.profile', 'payment'])
            ->orderByDesc('created_at')
            ->get();

        return $this->apiResponse(true, 'Supervision globale des réservations.', $bookings);
    }

    // =========================================================================
    //  HELPER
    // =========================================================================

    private function apiResponse(bool $success, string $message, mixed $body = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => $success, 'message' => $message, 'body' => $body], $status);
    }
}
