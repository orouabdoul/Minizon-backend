<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '🗺️ Admin — Trajets', description: 'Supervision et gestion des trajets (Back-Office)')]
class AdminTripController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function tripsQuery()
    {
        return Trip::with([
            'user.profile',
            'bookings' => fn ($q) => $q->where('status', 'accepted')->with('passenger.profile'),
        ])->withCount(['bookings as dispute_count' => fn ($q) => $q->whereHas('dispute')]);
    }

    private function frontendStatus(Trip $trip): string
    {
        if ($trip->dispute_count > 0)       return 'Signalé';
        if ($trip->status === 'completed')  return 'Terminé';
        if ($trip->status === 'cancelled')  return 'Annulé';
        return 'Actif'; // pending ou active
    }

    private function format(Trip $trip): array
    {
        $driver     = $trip->user;
        $profile    = $driver?->profile;
        $driverName = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''))
            ?: ($driver?->phone ?? '—');

        $seatsBooked = ($trip->total_seats ?? 0) - ($trip->available_seats ?? 0);
        $revenue     = $seatsBooked * ($trip->price_per_seat ?? 0);

        $passengers = $trip->bookings->map(fn ($b) => [
            'id'     => $b->passenger?->uuid,
            'avatar' => $b->passenger?->profile?->selfie_front
                ? Storage::disk('public')->url($b->passenger->profile->selfie_front)
                : null,
        ])->values();

        Carbon::setLocale('fr');

        return [
            'id'            => $trip->uuid,
            'tripId'        => 'TRP-' . strtoupper(substr($trip->uuid, 0, 8)),
            'driverName'    => $driverName,
            'driverAvatar'  => $profile?->selfie_front ? Storage::disk('public')->url($profile->selfie_front) : null,
            'driverRating'  => $driver?->averageRating() ?? 0,
            'driverReviews' => $driver?->reviewsReceived()->count() ?? 0,
            'from'          => $trip->departure_city,
            'to'            => $trip->arrival_city,
            'date'          => $trip->departure_time?->format('d/m/Y'),
            'time'          => $trip->departure_time?->format('H:i'),
            'seats'         => $trip->total_seats ?? 0,
            'seatsBooked'   => $seatsBooked,
            'pricePerSeat'  => number_format($trip->price_per_seat ?? 0, 0, ',', ' ') . ' FCFA',
            'revenue'       => number_format($revenue, 0, ',', ' ') . ' FCFA',
            'status'        => $this->frontendStatus($trip),
            'passengers'    => $passengers,
        ];
    }

    private function applyFilters($query, Request $request): void
    {
        $departure   = trim($request->input('departure',   ''));
        $destination = trim($request->input('destination', ''));
        $status      = $request->input('status', '');
        $date        = $request->input('date',   '');

        if ($departure !== '') {
            $query->where('departure_city', 'like', "%{$departure}%");
        }

        if ($destination !== '') {
            $query->where('arrival_city', 'like', "%{$destination}%");
        }

        if ($date !== '') {
            $query->whereDate('departure_time', $date);
        }

        if ($status !== '') {
            match ($status) {
                'Actif'   => $query->whereIn('status', ['pending', 'active']),
                'Terminé' => $query->where('status', 'completed'),
                'Annulé'  => $query->where('status', 'cancelled'),
                'Signalé' => $query->whereHas('bookings', fn ($q) => $q->whereHas('dispute')),
                default   => null,
            };
        }
    }

    // =========================================================================
    //  METRICS  GET /api/admin/trips/metrics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/trips/metrics',
        operationId: 'adminTripMetrics',
        summary: '[ADMIN] Métriques des trajets',
        description: 'Retourne les 4 KPI affichés en haut de la page Gestion des Trajets.',
        tags: ['🗺️ Admin — Trajets'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Métriques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Métriques trajets récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',         type: 'integer', example: 1240),
                                new OA\Property(property: 'completed',     type: 'integer', example: 980),
                                new OA\Property(property: 'reported',      type: 'integer', example: 23),
                                new OA\Property(property: 'total_revenue', type: 'integer', example: 4500000, description: 'Montant total en FCFA'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function metrics(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $total     = Trip::count();
        $completed = Trip::where('status', 'completed')->count();
        $reported  = Trip::whereHas('bookings', fn ($q) => $q->whereHas('dispute'))->count();
        $revenue   = Payment::where('status', 'success')->sum('gross_amount');

        return $this->apiResponse(true, 'Métriques trajets récupérées.', [
            'total'         => $total,
            'completed'     => $completed,
            'reported'      => $reported,
            'total_revenue' => (int) $revenue,
        ]);
    }

    // =========================================================================
    //  INDEX  GET /api/admin/trips
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/trips',
        operationId: 'adminTripIndex',
        summary: '[ADMIN] Liste paginée des trajets',
        description: 'Retourne tous les trajets avec filtres par ville de départ, destination, statut et date.',
        tags: ['🗺️ Admin — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page',        in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page',    in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'departure',   in: 'query', schema: new OA\Schema(type: 'string'), description: 'Ville de départ (recherche partielle)'),
            new OA\Parameter(name: 'destination', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Ville d\'arrivée (recherche partielle)'),
            new OA\Parameter(name: 'status',      in: 'query', schema: new OA\Schema(type: 'string', enum: ['Actif', 'Terminé', 'Annulé', 'Signalé'])),
            new OA\Parameter(name: 'date',        in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de départ (YYYY-MM-DD)'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des trajets'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $query   = $this->tripsQuery();

        $this->applyFilters($query, $request);

        $paginated = $query->orderByDesc('departure_time')->paginate($perPage);

        return $this->apiResponse(true, 'Trajets récupérés.', [
            'data'         => $paginated->map(fn ($t) => $this->format($t))->values(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  SHOW  GET /api/admin/trips/{uuid}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/trips/{uuid}',
        operationId: 'adminTripShow',
        summary: '[ADMIN] Détail d\'un trajet',
        description: 'Retourne toutes les informations d\'un trajet, incluant conducteur, passagers et revenus.',
        tags: ['🗺️ Admin — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trajet trouvé'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $trip = $this->tripsQuery()->where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Trajet récupéré.', $this->format($trip));
    }

    // =========================================================================
    //  UPDATE  PUT /api/admin/trips/{uuid}
    // =========================================================================

    #[OA\Put(
        path: '/api/admin/trips/{uuid}',
        operationId: 'adminTripUpdate',
        summary: '[ADMIN] Modifier le statut d\'un trajet',
        tags: ['🗺️ Admin — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'active', 'completed', 'cancelled'], example: 'cancelled'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Trajet mis à jour'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function update(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'in:pending,active,completed,cancelled'],
        ]);

        $trip->update(['status' => $data['status']]);

        return $this->apiResponse(true, 'Trajet mis à jour.', $this->format(
            $this->tripsQuery()->where('uuid', $uuid)->first()
        ));
    }

    // =========================================================================
    //  DESTROY  DELETE /api/admin/trips/{uuid}
    // =========================================================================

    #[OA\Delete(
        path: '/api/admin/trips/{uuid}',
        operationId: 'adminTripDelete',
        summary: '[ADMIN] Supprimer un trajet',
        description: "Supprime définitivement un trajet et annule toutes ses réservations non terminées.\n\n**Règles :**\n- Un trajet `active` (en cours) ne peut pas être supprimé directement — l'annuler d'abord.\n- Les réservations `pending` et `accepted` sont automatiquement annulées.\n- Les réservations `completed` et les paiements sont conservés pour l'historique.",
        tags: ['🗺️ Admin — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Trajet supprimé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Trajet supprimé. 2 réservation(s) annulée(s).'),
                        new OA\Property(property: 'body',    type: 'object',  nullable: true, example: null),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé ou trajet en cours', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable',               content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $trip = Trip::with('bookings')->where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if ($trip->status === 'active') {
            return $this->apiResponse(false, 'Impossible de supprimer un trajet en cours. Annulez-le d\'abord.', [], 403);
        }

        // Annuler les réservations non terminées
        $cancelledCount = $trip->bookings()
            ->whereIn('status', ['pending', 'accepted'])
            ->update(['status' => 'cancelled']);

        $trip->delete();

        return $this->apiResponse(
            true,
            "Trajet supprimé. {$cancelledCount} réservation(s) annulée(s)."
        );
    }
}
