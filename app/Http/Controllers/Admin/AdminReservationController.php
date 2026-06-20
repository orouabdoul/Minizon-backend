<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '🎫 Admin — Réservations', description: 'Supervision et gestion des réservations passagers (Back-Office)')]
class AdminReservationController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function reservationStatus(Booking $booking): string
    {
        if (in_array($booking->status, ['cancelled', 'rejected'])) return 'Annulée';
        if ($booking->status === 'pending')  return 'En attente';
        if ($booking->status === 'accepted') {
            return $booking->trip?->status === 'completed' ? 'Terminée' : 'Confirmée';
        }
        return 'En attente';
    }

    private function paymentLabel(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'escrow_locked', 'released_to_driver' => 'Payé',
            'refunded'                             => 'Remboursé',
            default                                => 'En attente',
        };
    }

    private function riskLevel(Booking $booking): string
    {
        if ($booking->relationLoaded('dispute') && $booking->dispute !== null) return 'Élevé';
        if (($booking->passenger?->penalty_points ?? 0) >= 3) return 'Moyen';
        return 'Faible';
    }

    private function format(Booking $booking): array
    {
        $trip             = $booking->trip;
        $passenger        = $booking->passenger;
        $passengerProfile = $passenger?->profile;
        $driver           = $trip?->user;
        $driverProfile    = $driver?->profile;
        $payment          = $booking->payment;
        $departureTime    = $trip?->departure_time;

        $driverRating = $driver
            ? round((float) ($driver->reviewsReceived?->avg('rating') ?? 0), 1)
            : 0;

        $passengerName = trim(($passengerProfile?->first_name ?? '') . ' ' . ($passengerProfile?->last_name ?? ''))
            ?: ($passenger?->phone ?? '—');

        $driverName = trim(($driverProfile?->first_name ?? '') . ' ' . ($driverProfile?->last_name ?? ''))
            ?: ($driver?->phone ?? '—');

        return [
            'id'                => $booking->uuid,
            'reservationId'     => 'RES-' . strtoupper(substr($booking->uuid, 0, 8)),
            'createdAt'         => $booking->created_at?->format('d/m/Y H:i') ?? '—',
            'createdAgo'        => $booking->created_at?->diffForHumans() ?? '—',

            'passengerName'     => $passengerName,
            'passengerAvatar'   => $this->fileUrl($passengerProfile?->selfie_front),
            'passengerVerified' => $passengerProfile?->kyc_status === 'approved',

            'driverName'        => $driverName,
            'driverAvatar'      => $this->fileUrl($driverProfile?->selfie_front),
            'driverRating'      => $driverRating,

            'from'              => $trip?->departure_city ?? '—',
            'to'                => $trip?->arrival_city   ?? '—',
            'seats'             => $booking->seats_booked,

            'date'              => $departureTime?->format('d/m/Y') ?? '—',
            'time'              => $departureTime?->format('H:i')   ?? '—',

            'amount'            => $payment
                ? number_format($payment->gross_amount, 0, ',', ' ') . ' FCFA'
                : '—',
            'paymentStatus'     => $this->paymentLabel($booking->payment_status),

            'status'    => $this->reservationStatus($booking),
            'riskLevel' => $this->riskLevel($booking),
        ];
    }

    private function buildTimeline(Booking $booking): array
    {
        $events = [];

        $events[] = [
            'label'  => 'Réservation créée',
            'time'   => $booking->created_at?->format('d/m/Y H:i'),
            'status' => 'done',
        ];

        if ($booking->status === 'accepted') {
            $events[] = [
                'label'  => 'Réservation confirmée par le conducteur',
                'time'   => $booking->updated_at?->format('d/m/Y H:i'),
                'status' => 'done',
            ];
        }

        if (in_array($booking->payment_status, ['escrow_locked', 'released_to_driver'])) {
            $events[] = [
                'label'  => 'Paiement sécurisé (escrow)',
                'time'   => $booking->payment?->updated_at?->format('d/m/Y H:i'),
                'status' => 'done',
            ];
        }

        if ($booking->trip?->status === 'completed') {
            $events[] = [
                'label'  => 'Trajet terminé',
                'time'   => $booking->trip->updated_at?->format('d/m/Y H:i'),
                'status' => 'done',
            ];
        }

        if ($booking->tripValidation?->passenger_confirmed) {
            $events[] = [
                'label'  => 'Arrivée confirmée par le passager',
                'time'   => $booking->tripValidation->passenger_confirmed_at?->format('d/m/Y H:i'),
                'status' => 'done',
            ];
        }

        if ($booking->payment_status === 'released_to_driver') {
            $events[] = [
                'label'  => 'Fonds libérés au conducteur',
                'time'   => $booking->payment?->updated_at?->format('d/m/Y H:i'),
                'status' => 'done',
            ];
        }

        if ($booking->payment_status === 'refunded') {
            $events[] = [
                'label'  => 'Remboursement effectué',
                'time'   => $booking->payment?->updated_at?->format('d/m/Y H:i'),
                'status' => 'cancelled',
            ];
        }

        if (in_array($booking->status, ['cancelled', 'rejected'])) {
            $events[] = [
                'label'  => $booking->status === 'cancelled'
                    ? 'Annulée par le passager'
                    : 'Rejetée par le conducteur',
                'time'   => $booking->updated_at?->format('d/m/Y H:i'),
                'status' => 'cancelled',
            ];
        }

        return $events;
    }

    private function baseQuery()
    {
        return Booking::query()->with([
            'trip.user.profile',
            'trip.user.reviewsReceived',
            'passenger.profile',
            'payment',
            'dispute',
            'tripValidation',
        ]);
    }

    // =========================================================================
    //  METRICS  GET /api/admin/reservations/metrics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/reservations/metrics',
        operationId: 'adminReservationMetrics',
        summary: '[ADMIN] Métriques des réservations',
        description: 'Retourne les 5 KPI affichés en haut de la page Gestion des Réservations : total, confirmées, annulées, chiffre d\'affaires et note moyenne.',
        tags: ['🎫 Admin — Réservations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Métriques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Métriques réservations récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',          type: 'integer', example: 3200,            description: 'Total de réservations toutes confondues'),
                                new OA\Property(property: 'confirmed',      type: 'integer', example: 2540,            description: 'Réservations avec statut accepted'),
                                new OA\Property(property: 'cancelled',      type: 'integer', example: 310,             description: 'Réservations cancelled ou rejected'),
                                new OA\Property(property: 'total_revenue',  type: 'string',  example: '4 520 000 FCFA', description: 'Somme des paiements en statut locked ou success'),
                                new OA\Property(property: 'average_rating', type: 'number',  example: 4.3,             description: 'Note moyenne de tous les avis'),
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

        $total     = Booking::count();
        $confirmed = Booking::where('status', 'accepted')->count();
        $cancelled = Booking::whereIn('status', ['cancelled', 'rejected'])->count();
        $revenue   = Payment::whereIn('status', ['locked', 'success'])->sum('gross_amount');
        $avgRating = Review::avg('rating') ?? 0;

        return $this->apiResponse(true, 'Métriques réservations récupérées.', [
            'total'          => $total,
            'confirmed'      => $confirmed,
            'cancelled'      => $cancelled,
            'total_revenue'  => number_format((int) $revenue, 0, ',', ' ') . ' FCFA',
            'average_rating' => round((float) $avgRating, 1),
        ]);
    }

    // =========================================================================
    //  INDEX  GET /api/admin/reservations
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/reservations',
        operationId: 'adminReservationIndex',
        summary: '[ADMIN] Liste paginée des réservations',
        description: 'Retourne toutes les réservations avec filtres combinables : ville, statut, paiement, date, recherche textuelle.',
        tags: ['🎫 Admin — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 10, maximum: 100)),
            new OA\Parameter(
                name: 'search', in: 'query',
                description: 'UUID partiel de la réservation, nom ou téléphone du passager',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'city', in: 'query',
                description: 'Ville de départ ou d\'arrivée (recherche partielle)',
                schema: new OA\Schema(type: 'string', example: 'Cotonou')
            ),
            new OA\Parameter(
                name: 'status', in: 'query',
                description: 'Filtre par statut de réservation',
                schema: new OA\Schema(type: 'string', enum: ['Confirmée', 'En attente', 'Annulée', 'Terminée'])
            ),
            new OA\Parameter(
                name: 'payment', in: 'query',
                description: 'Filtre par statut de paiement',
                schema: new OA\Schema(type: 'string', enum: ['Payé', 'En attente', 'Échoué', 'Remboursé'])
            ),
            new OA\Parameter(
                name: 'date', in: 'query',
                description: 'Date de départ du trajet (YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2025-03-15')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des réservations',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Réservations récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',        type: 'integer', example: 3200),
                                new OA\Property(property: 'per_page',     type: 'integer', example: 10),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page',    type: 'integer', example: 320),
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id',                type: 'string',  example: 'a3b4c5d6-...', description: 'UUID de la réservation'),
                                            new OA\Property(property: 'reservationId',     type: 'string',  example: 'RES-A3B4C5D6', description: 'Identifiant lisible affiché en tableau'),
                                            new OA\Property(property: 'createdAt',         type: 'string',  example: '14/06/2025 09:30'),
                                            new OA\Property(property: 'createdAgo',        type: 'string',  example: 'il y a 2 heures'),
                                            new OA\Property(property: 'passengerName',     type: 'string',  example: 'Aminata Sow'),
                                            new OA\Property(property: 'passengerAvatar',   type: 'string',  nullable: true, example: 'https://cdn.../selfie.jpg'),
                                            new OA\Property(property: 'passengerVerified', type: 'boolean', example: true),
                                            new OA\Property(property: 'driverName',        type: 'string',  example: 'Kofi Mensah'),
                                            new OA\Property(property: 'driverAvatar',      type: 'string',  nullable: true, example: 'https://cdn.../avatar.jpg'),
                                            new OA\Property(property: 'driverRating',      type: 'number',  example: 4.7),
                                            new OA\Property(property: 'from',              type: 'string',  example: 'Cotonou'),
                                            new OA\Property(property: 'to',                type: 'string',  example: 'Porto-Novo'),
                                            new OA\Property(property: 'seats',             type: 'integer', example: 2),
                                            new OA\Property(property: 'date',              type: 'string',  example: '15/06/2025'),
                                            new OA\Property(property: 'time',              type: 'string',  example: '08:30'),
                                            new OA\Property(property: 'amount',            type: 'string',  example: '3 000 FCFA'),
                                            new OA\Property(property: 'paymentStatus',     type: 'string',  enum: ['Payé', 'En attente', 'Échoué', 'Remboursé']),
                                            new OA\Property(property: 'status',            type: 'string',  enum: ['Confirmée', 'En attente', 'Annulée', 'Terminée']),
                                            new OA\Property(property: 'riskLevel',         type: 'string',  enum: ['Faible', 'Moyen', 'Élevé'], description: 'Élevé si dispute ouverte, Moyen si ≥3 points de pénalité'),
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
        $payment = $request->input('payment', '');
        $city    = trim($request->input('city', ''));
        $date    = $request->input('date', '');
        $search  = trim($request->input('search', ''));

        $query = $this->baseQuery();

        if ($status !== '') {
            match ($status) {
                'Confirmée'  => $query->where('status', 'accepted')
                                      ->whereHas('trip', fn ($q) => $q->where('status', '!=', 'completed')),
                'Terminée'   => $query->where('status', 'accepted')
                                      ->whereHas('trip', fn ($q) => $q->where('status', 'completed')),
                'Annulée'    => $query->whereIn('status', ['cancelled', 'rejected']),
                'En attente' => $query->where('status', 'pending'),
                default      => null,
            };
        }

        if ($payment !== '') {
            match ($payment) {
                'Payé'       => $query->whereIn('payment_status', ['escrow_locked', 'released_to_driver']),
                'Remboursé'  => $query->where('payment_status', 'refunded'),
                'Échoué'     => $query->whereHas('payment', fn ($q) => $q->where('status', 'failed')),
                'En attente' => $query->where('payment_status', 'unpaid'),
                default      => null,
            };
        }

        if ($city !== '') {
            $query->whereHas('trip', fn ($q) =>
                $q->where('departure_city', 'like', "%{$city}%")
                  ->orWhere('arrival_city',  'like', "%{$city}%")
            );
        }

        if ($date !== '') {
            $query->whereHas('trip', fn ($q) =>
                $q->whereDate('departure_time', $date)
            );
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', "%{$search}%")
                  ->orWhereHas('passenger', fn ($q) =>
                      $q->where('phone', 'like', "%{$search}%")
                        ->orWhereHas('profile', fn ($q) =>
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name',  'like', "%{$search}%")
                        )
                  );
            });
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->apiResponse(true, 'Réservations récupérées.', [
            'data'         => $paginated->map(fn ($b) => $this->format($b))->values(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  SHOW  GET /api/admin/reservations/{uuid}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/reservations/{uuid}',
        operationId: 'adminReservationShow',
        summary: '[ADMIN] Détail d\'une réservation',
        description: 'Retourne toutes les informations d\'une réservation (passager, conducteur, trajet, paiement) ainsi que la timeline des événements pour l\'affichage dans le modal de détail.',
        tags: ['🎫 Admin — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de la réservation (booking)'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Réservation trouvée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Réservation récupérée.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id',                type: 'string',  example: 'a3b4c5d6-...'),
                                new OA\Property(property: 'reservationId',     type: 'string',  example: 'RES-A3B4C5D6'),
                                new OA\Property(property: 'createdAt',         type: 'string',  example: '14/06/2025 09:30'),
                                new OA\Property(property: 'createdAgo',        type: 'string',  example: 'il y a 2 heures'),
                                new OA\Property(property: 'passengerName',     type: 'string',  example: 'Aminata Sow'),
                                new OA\Property(property: 'passengerAvatar',   type: 'string',  nullable: true),
                                new OA\Property(property: 'passengerVerified', type: 'boolean', example: true),
                                new OA\Property(property: 'driverName',        type: 'string',  example: 'Kofi Mensah'),
                                new OA\Property(property: 'driverAvatar',      type: 'string',  nullable: true),
                                new OA\Property(property: 'driverRating',      type: 'number',  example: 4.7),
                                new OA\Property(property: 'from',              type: 'string',  example: 'Cotonou'),
                                new OA\Property(property: 'to',                type: 'string',  example: 'Porto-Novo'),
                                new OA\Property(property: 'seats',             type: 'integer', example: 2),
                                new OA\Property(property: 'date',              type: 'string',  example: '15/06/2025'),
                                new OA\Property(property: 'time',              type: 'string',  example: '08:30'),
                                new OA\Property(property: 'amount',            type: 'string',  example: '3 000 FCFA'),
                                new OA\Property(property: 'paymentStatus',     type: 'string',  enum: ['Payé', 'En attente', 'Échoué', 'Remboursé']),
                                new OA\Property(property: 'status',            type: 'string',  enum: ['Confirmée', 'En attente', 'Annulée', 'Terminée']),
                                new OA\Property(property: 'riskLevel',         type: 'string',  enum: ['Faible', 'Moyen', 'Élevé']),
                                new OA\Property(
                                    property: 'timelineEvents',
                                    type: 'array',
                                    description: 'Historique chronologique des événements de la réservation',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'label',  type: 'string', example: 'Réservation créée'),
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
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $booking = $this->baseQuery()->where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Réservation récupérée.', array_merge(
            $this->format($booking),
            ['timelineEvents' => $this->buildTimeline($booking)]
        ));
    }
}
