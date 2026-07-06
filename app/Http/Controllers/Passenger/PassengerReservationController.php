<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;

/**
 * Page "Mes réservations" — liste paginée et facture.
 *
 * Statuts Flutter ≠ statuts DB :
 *   pending     ← booking.status == 'pending'
 *   confirmed   ← booking.status == 'accepted' + trip.status == 'pending'
 *   in_progress ← booking.status == 'accepted' + trip.status == 'active'
 *   completed   ← trip.status == 'completed'   (booking.status == 'accepted')
 *   cancelled   ← booking.status in ['cancelled','rejected']
 *
 * isPaid = booking.payment_status === 'escrow_locked'
 * Annulation : déléguer à l'endpoint existant POST /api/bookings/{uuid}/cancel
 */
class PassengerReservationController extends Controller
{
    private const STATUS_TABS = [
        ['status' => 'pending',     'label' => 'En attente'],
        ['status' => 'confirmed',   'label' => 'Confirmé'],
        ['status' => 'in_progress', 'label' => 'En cours'],
        ['status' => 'completed',   'label' => 'Terminé'],
        ['status' => 'cancelled',   'label' => 'Annulé'],
    ];

    // =========================================================================
    //  GET /api/passenger/reservations
    //  Page principale — liste complète avec bannière trajet actif
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/reservations',
        operationId: 'passengerReservationIndex',
        summary: 'Mes réservations (vue passager formatée)',
        description: "Retourne l'intégralité des réservations du passager, formatées pour la page Flutter `ReservationView`. Inclut la bannière trajet actif, les compteurs par onglet et les objets `ReservationItem`.\n\nLe paramètre `status` est optionnel : si absent, toutes les réservations sont retournées (le Flutter filtre côté client).",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'status', in: 'query', required: false,
                description: 'Filtre côté serveur (optionnel). Valeurs acceptées : pending, confirmed, in_progress, completed, cancelled',
                schema: new OA\Schema(type: 'string', enum: ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données de la page réservations',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Réservations.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'active_trip',
                                    nullable: true,
                                    description: 'Trajet en cours pour la bannière verte — null si aucun.',
                                    properties: [
                                        new OA\Property(property: 'uuid',           type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'departure_city', type: 'string', example: 'Cotonou'),
                                        new OA\Property(property: 'arrival_city',   type: 'string', example: 'Abomey-Calavi'),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(
                                    property: 'status_tabs',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                            new OA\Property(property: 'label',  type: 'string', example: 'En attente'),
                                            new OA\Property(property: 'count',  type: 'integer', example: 2),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'items',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/PassengerReservationItem')
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $bookings = Booking::with([
            'trip.user.profile',
            'trip.vehicle',
            'payment',
        ])
        ->where('passenger_id', $user->id)
        ->orderByDesc('created_at')
        ->get();

        // Préchargement des reviews et litiges pour éviter les N+1
        $tripIds    = $bookings->pluck('trip_id')->filter()->unique();
        $bookingIds = $bookings->pluck('id');

        $allReviews         = Review::whereIn('trip_id', $tripIds)->get();
        $myReviewedTripIds  = $allReviews->where('reviewer_id', $user->id)->pluck('trip_id')->flip();
        $avgRatingByDriver  = $allReviews->groupBy('reviewee_id')->map(fn ($r) => round($r->avg('rating'), 1));
        $cntByDriver        = $allReviews->groupBy('reviewee_id')->map->count();

        $disputesByBooking  = Dispute::whereIn('booking_id', $bookingIds)
            ->where('reporter_id', $user->id)
            ->get()
            ->keyBy('booking_id');

        // Formater tous les items
        $allFormatted = $bookings->map(fn ($b) => $this->formatBooking(
            $b, $user->id, $myReviewedTripIds, $avgRatingByDriver, $cntByDriver, $disputesByBooking
        ));

        // Filtre serveur optionnel (le Flutter peut aussi filtrer côté client)
        $statusFilter = $request->query('status');
        $items = ($statusFilter && $statusFilter !== 'all')
            ? $allFormatted->filter(fn ($i) => $i['status'] === $statusFilter)->values()
            : $allFormatted->values();

        // Comptes par onglet
        $counts = $allFormatted->groupBy('status')->map->count();
        $statusTabs = collect(self::STATUS_TABS)->map(fn ($tab) => [
            ...$tab,
            'count' => $counts[$tab['status']] ?? 0,
        ])->values()->toArray();

        // Trajet actif pour la bannière verte
        $activeFmt = $allFormatted->firstWhere('status', 'in_progress');
        $activeTrip = $activeFmt ? [
            'uuid'           => $activeFmt['uuid'],
            'departure_city' => $activeFmt['departure_city'],
            'arrival_city'   => $activeFmt['arrival_city'],
        ] : null;

        return $this->apiResponse(true, 'Réservations.', [
            'active_trip' => $activeTrip,
            'status_tabs' => $statusTabs,
            'items'       => $items,
        ]);
    }

    // =========================================================================
    //  GET /api/passenger/reservations/{uuid}/invoice
    //  Données structurées pour génération de facture PDF côté Flutter
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/reservations/{uuid}/invoice',
        operationId: 'passengerReservationInvoice',
        summary: 'Données de facture d\'une réservation',
        description: "Retourne les données structurées nécessaires à la génération d'un PDF de facture côté Flutter. N'est disponible que pour les réservations `completed`.",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données de facture',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Facture générée.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'invoice_ref',       type: 'string', example: 'INV-1A2B3C4D'),
                                new OA\Property(property: 'issued_at',         type: 'string', example: 'Sam. 05/07/2025'),
                                new OA\Property(property: 'passenger_name',    type: 'string', example: 'Issa Orou'),
                                new OA\Property(property: 'driver_name',       type: 'string', example: 'Koffi Adjovi'),
                                new OA\Property(property: 'route',             type: 'string', example: 'Cotonou → Abomey-Calavi'),
                                new OA\Property(property: 'departure_date',    type: 'string', example: 'Sam. 05/07 à 08h30'),
                                new OA\Property(property: 'seats',             type: 'integer', example: 1),
                                new OA\Property(property: 'price_per_seat',    type: 'string', example: '1 500 FCFA'),
                                new OA\Property(property: 'total_amount',      type: 'string', example: '1 500 FCFA'),
                                new OA\Property(property: 'payment_method',    type: 'string', example: 'MTN Mobile Money'),
                                new OA\Property(property: 'transaction_ref',   type: 'string', example: 'TXN-XXXXXXXX'),
                                new OA\Property(property: 'booking_ref',       type: 'string', format: 'uuid'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé',           content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Réservation introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Trajet non encore terminé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function invoice(Request $request, string $uuid): JsonResponse
    {
        $booking = Booking::with([
            'trip.user.profile',
            'trip.vehicle',
            'payment',
            'passenger.profile',
        ])->where('uuid', $uuid)->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable.', [], 404);
        }

        if ($booking->passenger_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        if ($booking->trip?->status !== 'completed') {
            return $this->apiResponse(false, 'La facture n\'est disponible qu\'après la fin du trajet.', [], 409);
        }

        $trip     = $booking->trip;
        $driver   = $trip->user;
        $tz       = 'Africa/Porto-Novo';

        $passengerProfile = $booking->passenger?->profile;
        $driverProfile    = $driver?->profile;

        $passengerName = trim(($passengerProfile?->first_name ?? '') . ' ' . ($passengerProfile?->last_name ?? '')) ?: 'Passager';
        $driverName    = trim(($driverProfile?->first_name ?? '') . ' ' . ($driverProfile?->last_name ?? '')) ?: 'Conducteur';

        $payment      = $booking->payment;
        $pricePerSeat = (int) ($trip->price_per_seat ?? 0);
        $seats        = (int) $booking->seats_booked;
        $totalAmount  = $payment ? (int) $payment->gross_amount : $pricePerSeat * $seats;

        $providerLabels = [
            'mtn'    => 'MTN Mobile Money',
            'moov'   => 'Moov Money',
            'celtiis'=> 'Celtiis Money',
        ];
        $paymentMethod = $providerLabels[$payment?->provider] ?? 'Mobile Money';

        $depTime = $trip->departure_time?->setTimezone($tz);

        return $this->apiResponse(true, 'Facture générée.', [
            'invoice_ref'    => 'INV-' . strtoupper(substr(str_replace('-', '', $booking->uuid), 0, 8)),
            'issued_at'      => now()->setTimezone($tz)->translatedFormat('D. d/m/Y'),
            'passenger_name' => $passengerName,
            'driver_name'    => $driverName,
            'route'          => ($trip->departure_city ?? '—') . ' → ' . ($trip->arrival_city ?? '—'),
            'departure_date' => $depTime?->translatedFormat('D. d/m \à H\hi') ?? '—',
            'seats'          => $seats,
            'price_per_seat' => number_format($pricePerSeat, 0, ',', ' ') . ' FCFA',
            'total_amount'   => number_format($totalAmount, 0, ',', ' ') . ' FCFA',
            'payment_method' => $paymentMethod,
            'transaction_ref'=> $payment?->transaction_reference ?? $payment?->provider_reference ?? '—',
            'booking_ref'    => $booking->uuid,
        ]);
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'PassengerReservationItem',
        description: 'Correspond au modèle Flutter `ReservationItem`.',
        properties: [
            new OA\Property(property: 'uuid',             type: 'string',  format: 'uuid'),
            new OA\Property(property: 'status',           type: 'string',  enum: ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], description: 'Statut dérivé pour le Flutter — combinaison booking.status + trip.status.'),
            new OA\Property(property: 'is_paid',          type: 'boolean', example: true),
            new OA\Property(property: 'cancel_reason',    type: 'string',  nullable: true),
            new OA\Property(property: 'time_ago',         type: 'string',  example: 'il y a 2h'),
            new OA\Property(property: 'driver_name',      type: 'string',  example: 'Koffi Adjovi'),
            new OA\Property(property: 'driver_initials',  type: 'string',  example: 'KA'),
            new OA\Property(property: 'rating',           type: 'number',  format: 'float', example: 4.8),
            new OA\Property(property: 'review_count',     type: 'string',  example: '247 avis'),
            new OA\Property(property: 'total_price',      type: 'string',  example: '1 500 FCFA'),
            new OA\Property(property: 'seats_count',      type: 'integer', example: 1),
            new OA\Property(property: 'departure_city',   type: 'string',  example: 'Cotonou'),
            new OA\Property(property: 'departure_note',   type: 'string',  example: 'Cadjehoun'),
            new OA\Property(property: 'arrival_city',     type: 'string',  example: 'Abomey-Calavi'),
            new OA\Property(property: 'arrival_note',     type: 'string',  example: 'Godomey'),
            new OA\Property(property: 'departure_time',   type: 'string',  example: '08h30'),
            new OA\Property(property: 'departure_date',   type: 'string',  example: 'Sam. 05/07'),
            new OA\Property(property: 'vehicle',          type: 'string',  example: 'Toyota Corolla'),
            new OA\Property(property: 'vehicle_plate',    type: 'string',  example: 'AB-123-CD'),
            new OA\Property(property: 'eta_minutes',      type: 'integer', nullable: true, example: 12),
            new OA\Property(property: 'has_rated',        type: 'boolean', example: false),
            new OA\Property(property: 'refund_status',    type: 'string',  enum: ['none', 'pending', 'refunded', 'rejected']),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function deriveStatus(Booking $booking): string
    {
        if (in_array($booking->status, ['cancelled', 'rejected'], true)) {
            return 'cancelled';
        }

        if ($booking->status === 'pending') {
            return 'pending';
        }

        if ($booking->status === 'accepted') {
            return match ($booking->trip?->status) {
                'active'    => 'in_progress',
                'completed' => 'completed',
                default     => 'confirmed',
            };
        }

        return 'pending';
    }

    private function formatBooking(
        Booking $booking,
        int $passengerId,
        Collection $myReviewedTripIds,
        Collection $avgRatingByDriver,
        Collection $cntByDriver,
        Collection $disputesByBooking
    ): array {
        $trip    = $booking->trip;
        $driver  = $trip?->user;
        $profile = $driver?->profile;
        $vehicle = $trip?->vehicle;
        $payment = $booking->payment;
        $tz      = 'Africa/Porto-Novo';

        // ── Driver ────────────────────────────────────────────────────────
        $firstName  = $profile?->first_name ?? '';
        $lastName   = $profile?->last_name  ?? '';
        $driverName = trim("$firstName $lastName") ?: 'Conducteur';
        $initials   = collect(explode(' ', $driverName))
            ->filter()
            ->take(2)
            ->map(fn ($w) => strtoupper($w[0]))
            ->join('');

        $driverId    = $driver?->id;
        $rating      = $driverId ? (float) ($avgRatingByDriver[$driverId] ?? 0.0) : 0.0;
        $reviewCount = $driverId ? (int) ($cntByDriver[$driverId] ?? 0) : 0;

        // ── Statut Flutter ────────────────────────────────────────────────
        $flutterStatus = $this->deriveStatus($booking);

        // ── Prix ──────────────────────────────────────────────────────────
        $seats  = (int) $booking->seats_booked;
        $amount = $payment
            ? (int) $payment->gross_amount
            : ((int) ($trip?->price_per_seat ?? 0)) * $seats;

        // ── Dates ─────────────────────────────────────────────────────────
        $depTime = $trip?->departure_time?->setTimezone($tz);

        // ── ETA (uniquement en cours de route) ────────────────────────────
        $etaMinutes = null;
        if ($flutterStatus === 'in_progress') {
            $estimatedArrival = $trip?->estimated_arrival_time
                ?? ($trip?->departure_time && $trip?->estimated_duration_minutes
                    ? $trip->departure_time->addMinutes($trip->estimated_duration_minutes)
                    : null);

            if ($estimatedArrival) {
                $remaining  = $estimatedArrival->setTimezone($tz)->diffInMinutes(now(), false);
                $etaMinutes = max(0, (int) -$remaining);
            }
        }

        // ── hasRated ──────────────────────────────────────────────────────
        $hasRated = $trip ? $myReviewedTripIds->has($trip->id) : false;

        // ── refundStatus ──────────────────────────────────────────────────
        $refundStatus = 'none';
        $dispute = $disputesByBooking->get($booking->id);
        if ($dispute) {
            $refundStatus = match ($dispute->status) {
                'pending', 'investigating' => 'pending',
                'resolved_passenger'       => 'refunded',
                'resolved_driver'          => 'rejected',
                default                    => 'none',
            };
        }

        return [
            'uuid'            => $booking->uuid,
            'status'          => $flutterStatus,
            'is_paid'         => $booking->payment_status === 'escrow_locked',
            'cancel_reason'   => null,
            'time_ago'        => $this->relativeTime($booking->created_at),
            'driver_name'     => $driverName,
            'driver_initials' => $initials ?: '??',
            'rating'          => $rating,
            'review_count'    => $reviewCount . ' avis',
            'total_price'     => number_format($amount, 0, ',', ' ') . ' FCFA',
            'seats_count'     => $seats,
            'departure_city'  => $trip?->departure_city ?? '—',
            'departure_note'  => $trip?->departure_neighborhood ?? '',
            'arrival_city'    => $trip?->arrival_city ?? '—',
            'arrival_note'    => $trip?->arrival_neighborhood ?? '',
            'departure_time'  => $depTime?->format('H\hi') ?? '—',
            'departure_date'  => $depTime?->translatedFormat('D. d/m') ?? '—',
            'vehicle'         => $vehicle ? trim("{$vehicle->brand} {$vehicle->model}") : '—',
            'vehicle_plate'   => $vehicle?->license_plate ?? '—',
            'eta_minutes'     => $etaMinutes,
            'has_rated'       => $hasRated,
            'refund_status'   => $refundStatus,
        ];
    }

    private function relativeTime(?\Carbon\CarbonInterface $date): string
    {
        if (! $date) return '—';
        $diff = $date->setTimezone('Africa/Porto-Novo')->diffInSeconds(now(), false);

        if ($diff < 60)     return 'à l\'instant';
        if ($diff < 3600)   return 'il y a ' . (int) ($diff / 60) . ' min';
        if ($diff < 86400)  return 'il y a ' . (int) ($diff / 3600) . 'h';
        if ($diff < 172800) return 'hier';
        return $date->setTimezone('Africa/Porto-Novo')->translatedFormat('d/m/Y');
    }
}
