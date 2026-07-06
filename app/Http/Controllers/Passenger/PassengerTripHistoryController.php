<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Mes trajets" (TripHistoryView) — passager.
 *
 * Distinct de PassengerReservationController (ReservationView) :
 *   – 3 buckets de statut : upcoming / completed / cancelled
 *     (ReservationView en a 5 : pending / confirmed / in_progress / completed / cancelled)
 *   – `trip.rating` = NOTE PERSONNELLE du passager pour ce trajet (Review.rating)
 *     et non la moyenne du conducteur (qui est dans PassengerReservationController)
 *   – Prix retourné en entier brut (le Flutter formate via formattedPrice())
 *
 * Actions Flutter sans endpoint API :
 *   rebookTrip()    → navigation locale vers SearchView (GET /api/passenger/search publique)
 *   requestRefund() → navigation locale vers RefundView (POST /api/passenger/refunds existant)
 */
class PassengerTripHistoryController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/trips/history
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/trips/history',
        operationId: 'passengerTripHistory',
        summary: 'Historique des trajets du passager (TripHistoryView)',
        description: "Retourne tous les voyages du passager regroupés en 3 statuts : `upcoming` (à venir ou en attente de confirmation), `completed` (terminés), `cancelled` (annulés ou refusés).\n\nLe champ `rating` d'un `TripRecord` est la NOTE PERSONNELLE du passager pour ce trajet — pas la moyenne du conducteur. Il est `null` si le passager n'a pas encore noté.\n\nActions Flutter sans endpoint :\n- `rebookTrip()` → navigation vers SearchView (endpoint public existant)\n- `requestRefund()` → navigation vers RefundView (PassengerRefundController existant)",
        tags: ['👤 Passenger — Trajets'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Historique chargé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Historique des trajets.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'counts',
                                    type: 'object',
                                    description: 'Compteurs pour les StatChips du header.',
                                    properties: [
                                        new OA\Property(property: 'upcoming',  type: 'integer', example: 2),
                                        new OA\Property(property: 'completed', type: 'integer', example: 5),
                                        new OA\Property(property: 'cancelled', type: 'integer', example: 1),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'trips',
                                    type: 'array',
                                    description: 'Liste de TripRecord triée : upcoming en premier (par date croissante), puis completed et cancelled (par date décroissante).',
                                    items: new OA\Items(ref: '#/components/schemas/PassengerTripRecord')
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
        $passenger = $request->user();

        // Chargement des réservations pertinentes (hors rejected seul sans trajet associé)
        $bookings = Booking::with(['trip.user.profile', 'trip.vehicle', 'payment'])
            ->where('passenger_id', $passenger->id)
            ->whereNotNull('trip_id')
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->select('bookings.*', 'trips.departure_time as trip_departure_time')
            ->orderByDesc('trips.departure_time')
            ->get();

        // Notes personnelles du passager — une par trajet (anti-N+1)
        $tripIds = $bookings->pluck('trip_id')->filter()->unique();
        $personalRatings = Review::where('reviewer_id', $passenger->id)
            ->whereIn('trip_id', $tripIds)
            ->pluck('rating', 'trip_id');   // tripId => rating

        $tz = 'Africa/Porto-Novo';

        $mapped = $bookings->map(function (Booking $b) use ($personalRatings, $tz) {
            $trip    = $b->trip;
            $driver  = $trip?->user;
            $profile = $driver?->profile;
            $vehicle = $trip?->vehicle;
            $payment = $b->payment;

            // ── Statut (3 buckets) ─────────────────────────────────────────
            $status = $this->resolveStatus($b);

            // ── Conducteur ─────────────────────────────────────────────────
            $firstName  = $profile?->first_name ?? '';
            $lastName   = $profile?->last_name  ?? '';
            $driverName = trim("$firstName $lastName") ?: 'Conducteur';

            // ── Prix brut ──────────────────────────────────────────────────
            $seats    = (int) ($b->seats_booked ?? 1);
            $price    = $payment
                ? (int) $payment->gross_amount
                : ((int) ($trip?->price_per_seat ?? 0)) * $seats;

            // ── Dates ──────────────────────────────────────────────────────
            $depTime = $trip?->departure_time?->setTimezone($tz);
            // "06 juil." — format affiché dans la TripCard (icône calendar)
            $date = $depTime ? $depTime->translatedFormat('d M.') : '—';
            // "08:30" — format affiché dans la TripCard (icône clock)
            $time = $depTime ? $depTime->format('H:i') : '—';

            // ── Note personnelle (nullable) ────────────────────────────────
            // Est null si le passager n'a pas encore noté ce trajet.
            $myRating = isset($personalRatings[$trip?->id])
                ? (float) $personalRatings[$trip->id]
                : null;

            return [
                'uuid'          => $b->uuid,
                'trip_uuid'     => $trip?->uuid,
                'status'        => $status,
                'date'          => $date,
                'time'          => $time,
                'origin'        => $trip?->origin ?? $trip?->departure_city ?? '—',
                'destination'   => $trip?->destination ?? $trip?->arrival_city ?? '—',
                'price'         => $price,
                'seats'         => $seats,
                'driver_name'   => $driverName,
                'vehicle'       => $vehicle ? trim("{$vehicle->brand} {$vehicle->model}") : '—',
                'vehicle_plate' => $vehicle?->license_plate ?? '—',
                'rating'        => $myRating,
            ];
        });

        // Tri : upcoming croissant (prochain trajet en premier), les autres décroissants
        $upcoming  = $mapped->where('status', 'upcoming')->sortBy('time')->values();
        $rest      = $mapped->whereNotIn('status', ['upcoming'])->values();
        $sorted    = $upcoming->merge($rest);

        $counts = [
            'upcoming'  => $mapped->where('status', 'upcoming')->count(),
            'completed' => $mapped->where('status', 'completed')->count(),
            'cancelled' => $mapped->where('status', 'cancelled')->count(),
        ];

        return $this->apiResponse(true, 'Historique des trajets.', [
            'counts' => $counts,
            'trips'  => $sorted->values(),
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    /**
     * Mappe (booking.status + trip.status + departure_time) → 3 buckets Flutter.
     */
    private function resolveStatus(Booking $booking): string
    {
        $trip = $booking->trip;

        // Annulé en priorité
        if (in_array($booking->status, ['cancelled', 'rejected'], true)) {
            return 'cancelled';
        }
        if ($trip?->status === 'cancelled') {
            return 'cancelled';
        }

        // Terminé
        if ($trip?->status === 'completed') {
            return 'completed';
        }
        // Trajet passé mais sans status 'completed' (edge case données incohérentes)
        if ($booking->status === 'accepted' && $trip?->departure_time?->isPast()) {
            return 'completed';
        }

        // Tout le reste (pending acceptance ou accepted avec départ futur)
        return 'upcoming';
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'PassengerTripRecord',
        description: 'Correspond au modèle Flutter TripRecord utilisé dans TripHistoryView.',
        properties: [
            new OA\Property(property: 'uuid',          type: 'string', format: 'uuid', description: 'UUID de la réservation.'),
            new OA\Property(property: 'trip_uuid',     type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'status',        type: 'string', enum: ['upcoming', 'completed', 'cancelled']),
            new OA\Property(property: 'date',          type: 'string', example: '06 juil.', description: 'Date de départ formatée en français court.'),
            new OA\Property(property: 'time',          type: 'string', example: '08:30', description: 'Heure de départ HH:mm.'),
            new OA\Property(property: 'origin',        type: 'string', example: 'Cotonou'),
            new OA\Property(property: 'destination',   type: 'string', example: 'Parakou'),
            new OA\Property(property: 'price',         type: 'integer', example: 3300, description: 'Prix total brut payé (FCFA). Le Flutter le formate via formattedPrice().'),
            new OA\Property(property: 'seats',         type: 'integer', example: 2),
            new OA\Property(property: 'driver_name',   type: 'string', example: 'Koffi Adjovi'),
            new OA\Property(property: 'vehicle',       type: 'string', example: 'Toyota Corolla'),
            new OA\Property(property: 'vehicle_plate', type: 'string', example: 'AB-123-CD'),
            new OA\Property(property: 'rating',        type: 'number', format: 'float', nullable: true, example: 4.0, description: 'Note PERSONNELLE du passager pour ce trajet (1-5). null = pas encore noté.'),
        ]
    )]
    private function schemaPlaceholder(): void {}
}
