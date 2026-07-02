<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

/**
 * Page "Prêt à partir ?" — pré-départ conducteur.
 *
 * Alimente toute la vue avant que le conducteur démarre la navigation.
 * Le démarrage effectif du trajet reste sur l'endpoint existant :
 *   POST /api/trips/{uuid}/start  (TripController::startTrip)
 */
class DriverActiveTripController extends Controller
{
    // =========================================================================
    //  GET /api/driver/trips/{uuid}/pre-departure
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/trips/{uuid}/pre-departure',
        operationId: 'driverTripPreDeparture',
        summary: 'Données pré-départ du conducteur',
        description: "Retourne en un seul appel tout ce dont la page \"Prêt à partir ?\" a besoin : résumé du trajet, checklist de vérification (véhicule approuvé, identité vérifiée, passagers confirmés, paiements sécurisés, fenêtre de départ), itinéraire ordonné avec prise en charge et dépose de chaque passager, ETAs calculées. Le bouton \"Démarrer la navigation\" doit ensuite appeler **POST /api/trips/{uuid}/start**.",
        tags: ['🚗 Driver — Active Trip'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données pré-départ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Prêt à partir.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'trip',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'uuid',                       type: 'string',  format: 'uuid'),
                                        new OA\Property(property: 'origin',                     type: 'string',  example: 'Cotonou, Akpakpa'),
                                        new OA\Property(property: 'origin_point',               type: 'string',  nullable: true),
                                        new OA\Property(property: 'destination',                type: 'string',  example: 'Parakou, Centre-ville'),
                                        new OA\Property(property: 'destination_point',          type: 'string',  nullable: true),
                                        new OA\Property(property: 'departure_time',             type: 'string',  format: 'date-time'),
                                        new OA\Property(property: 'departure_time_formatted',   type: 'string',  example: '07:00'),
                                        new OA\Property(property: 'estimated_arrival_time',     type: 'string',  format: 'date-time', nullable: true),
                                        new OA\Property(property: 'distance_km',                type: 'number',  nullable: true, example: 413.5),
                                        new OA\Property(property: 'duration_label',             type: 'string',  nullable: true, example: '5h30'),
                                        new OA\Property(property: 'passengers_count',           type: 'integer', example: 3),
                                        new OA\Property(property: 'booking_mode',               type: 'string',  enum: ['instant', 'approval']),
                                    ]
                                ),
                                new OA\Property(property: 'all_green', type: 'boolean', description: 'Vrai si tous les items de checklist sont satisfaits'),
                                new OA\Property(
                                    property: 'checklist',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',     type: 'string',  example: 'vehicle_approved'),
                                            new OA\Property(property: 'label',   type: 'string',  example: 'Véhicule vérifié et approuvé'),
                                            new OA\Property(property: 'is_done', type: 'boolean', example: true),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'stops',
                                    type: 'array',
                                    description: 'Itinéraire ordonné : prises en charge puis dépose finale',
                                    items: new OA\Items(ref: '#/components/schemas/ActiveTripStop')
                                ),
                                new OA\Property(
                                    property: 'pending_approvals',
                                    type: 'integer',
                                    description: 'Nombre de demandes de réservation en attente d\'approbation',
                                    example: 0
                                ),
                                new OA\Property(
                                    property: 'start_endpoint',
                                    type: 'string',
                                    description: 'Endpoint à appeler pour démarrer le trajet',
                                    example: 'POST /api/trips/{uuid}/start'
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Ce trajet ne vous appartient pas ou statut incompatible', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function readiness(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $trip = Trip::with([
            'vehicle',
            'bookings' => fn ($q) => $q->with(['passenger.profile', 'payment']),
        ])->where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if ($trip->user_id !== $user->id) {
            return $this->apiResponse(false, 'Ce trajet ne vous appartient pas.', [], 403);
        }

        if (! in_array($trip->status, ['pending', 'active'])) {
            return $this->apiResponse(false, 'Ce trajet ne peut pas être démarré (statut : ' . $trip->status . ').', [], 403);
        }

        $vehicle         = $trip->vehicle;
        $profile         = $user->load('profile')->profile;
        $acceptedBookings = $trip->bookings->where('status', 'accepted');
        $pendingBookings  = $trip->bookings->where('status', 'pending');
        $passengersCount  = (int) $acceptedBookings->sum('seats_booked');

        // ── Checklist ─────────────────────────────────────────────────────────
        $vehicleApproved  = $vehicle?->verification_status === 'approved';
        $driverVerified   = $profile?->kyc_status === 'approved' && ! empty($profile?->driving_license_number);
        $hasPassengers    = $acceptedBookings->count() > 0;
        $paymentsSecured  = $acceptedBookings->isNotEmpty() && $acceptedBookings->every(
            fn (Booking $b) => in_array($b->payment_status, ['escrow_locked', 'released'])
        );
        $departureWindow  = $this->isDepartureInWindow($trip->departure_time);

        $checklist = [
            ['key' => 'vehicle_approved',  'label' => 'Véhicule vérifié et approuvé par l\'administration', 'is_done' => $vehicleApproved],
            ['key' => 'driver_verified',   'label' => 'Identité et permis de conduire vérifiés',            'is_done' => $driverVerified],
            ['key' => 'passengers_ready',  'label' => $passengersCount > 0
                ? "{$passengersCount} passager" . ($passengersCount > 1 ? 's' : '') . ' confirmé' . ($passengersCount > 1 ? 's' : '')
                : 'Aucun passager confirmé pour ce trajet',                                                  'is_done' => $hasPassengers],
            ['key' => 'payments_secured',  'label' => 'Paiements des passagers sécurisés (escrow)',         'is_done' => $paymentsSecured],
            ['key' => 'departure_window',  'label' => 'Heure de départ dans la fenêtre autorisée',          'is_done' => $departureWindow],
        ];

        $allGreen = $vehicleApproved && $driverVerified && $hasPassengers && $paymentsSecured && $departureWindow;

        // ── Distance & durée ─────────────────────────────────────────────────
        $distanceKm    = $this->haversineKm(
            $trip->departure_latitude,  $trip->departure_longitude,
            $trip->arrival_latitude,    $trip->arrival_longitude,
        );
        $durationLabel = $this->formatDuration($trip->estimated_duration_minutes);

        // ── Itinéraire des arrêts ────────────────────────────────────────────
        $stops = $this->buildStops($trip, $acceptedBookings);

        return $this->apiResponse(true, 'Prêt à partir.', [
            'trip' => [
                'uuid'                     => $trip->uuid,
                'origin'                   => $trip->departure_city . ', ' . $trip->departure_neighborhood,
                'origin_point'             => $trip->departure_point,
                'destination'              => $trip->arrival_city . ', ' . $trip->arrival_neighborhood,
                'destination_point'        => $trip->arrival_point,
                'departure_time'           => $trip->departure_time,
                'departure_time_formatted' => $trip->departure_time->setTimezone('Africa/Porto-Novo')->format('H:i'),
                'estimated_arrival_time'   => $trip->estimated_arrival_time,
                'distance_km'              => $distanceKm,
                'duration_label'           => $durationLabel,
                'passengers_count'         => $passengersCount,
                'booking_mode'             => $trip->booking_mode ?? 'instant',
            ],
            'all_green'        => $allGreen,
            'checklist'        => $checklist,
            'stops'            => $stops,
            'pending_approvals'=> $pendingBookings->count(),
            'start_endpoint'   => 'POST /api/trips/' . $trip->uuid . '/start',
        ]);
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'ActiveTripStop',
        description: 'Arrêt de l\'itinéraire pré-départ (prise en charge ou dépose)',
        properties: [
            new OA\Property(property: 'index',          type: 'integer', example: 1, description: 'Position dans l\'itinéraire (1-based)'),
            new OA\Property(property: 'type',           type: 'string',  enum: ['pickup', 'dropoff']),
            new OA\Property(property: 'passenger_name', type: 'string',  example: 'Koffi Mensah', description: 'Nom du passager (null pour le point final)'),
            new OA\Property(property: 'address',        type: 'string',  example: 'Akpakpa, Cotonou'),
            new OA\Property(property: 'eta',            type: 'string',  example: '07:00', description: 'Heure estimée au format HH:mm'),
            new OA\Property(property: 'booking_uuid',   type: 'string',  format: 'uuid', nullable: true),
            new OA\Property(property: 'phone',          type: 'string',  nullable: true, description: 'Téléphone du passager (pour prise en charge)'),
            new OA\Property(property: 'seats',          type: 'integer', nullable: true),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    /**
     * Construit la liste ordonnée des arrêts :
     *  1. Une entrée "pickup" par passager accepté (trié par heure de réservation)
     *  2. Un unique arrêt "dropoff" vers la destination finale
     *
     * ETAs calculées à partir de l'heure de départ :
     *  - Pickups : départ + (index * 5 min) d'offset minimal (même zone de départ)
     *  - Dropoff  : estimated_arrival_time si disponible, sinon départ + estimated_duration_minutes
     */
    private function buildStops(Trip $trip, \Illuminate\Support\Collection $acceptedBookings): array
    {
        $stops       = [];
        $index       = 1;
        $departsAt   = $trip->departure_time->setTimezone('Africa/Porto-Novo');
        $tz          = 'Africa/Porto-Novo';

        $pickupOffset = 0; // minutes after departure for each successive pickup

        foreach ($acceptedBookings->sortBy('created_at') as $booking) {
            $passenger = $booking->passenger;
            $profile   = $passenger?->profile;

            $address = $this->passengerPickupAddress($profile, $trip);
            $eta     = $departsAt->copy()->addMinutes($pickupOffset)->format('H:i');

            $stops[] = [
                'index'          => $index++,
                'type'           => 'pickup',
                'passenger_name' => $profile?->fullName() ?: ($passenger?->phone ?? '—'),
                'address'        => $address,
                'eta'            => $eta,
                'booking_uuid'   => $booking->uuid,
                'phone'          => $passenger?->phone,
                'seats'          => $booking->seats_booked,
            ];

            $pickupOffset += 5; // +5 min par passager supplémentaire (même zone)
        }

        // Arrêt final — dépose à la destination
        $arrivalTime = $trip->estimated_arrival_time
            ?? ($trip->estimated_duration_minutes
                ? $departsAt->copy()->addMinutes($trip->estimated_duration_minutes)
                : null);

        $dropoffEta  = $arrivalTime?->setTimezone($tz)->format('H:i') ?? '—';
        $dropoffAddr = $trip->arrival_city . ', ' . $trip->arrival_neighborhood
            . ($trip->arrival_point ? ' — ' . $trip->arrival_point : '');

        $stops[] = [
            'index'          => $index,
            'type'           => 'dropoff',
            'passenger_name' => 'Tous les passagers',
            'address'        => $dropoffAddr,
            'eta'            => $dropoffEta,
            'booking_uuid'   => null,
            'phone'          => null,
            'seats'          => null,
        ];

        return $stops;
    }

    /**
     * Adresse de prise en charge d'un passager.
     * Priorité : quartier du profil → ville du profil → point de départ du trajet.
     */
    private function passengerPickupAddress(?\App\Models\Profile $profile, Trip $trip): string
    {
        if ($profile?->neighborhood && $profile?->city) {
            return "{$profile->neighborhood}, {$profile->city}";
        }

        if ($profile?->city) {
            return $profile->city;
        }

        // Fallback : même point que le départ du trajet
        return $trip->departure_city . ', ' . $trip->departure_neighborhood
            . ($trip->departure_point ? ' — ' . $trip->departure_point : '');
    }

    /**
     * Vérifie que l'heure de départ est dans une fenêtre acceptable :
     * de 30 min avant jusqu'à 4h après l'heure prévue.
     */
    private function isDepartureInWindow(Carbon $departureTime): bool
    {
        $now     = now();
        $from    = $departureTime->copy()->subMinutes(30);
        $until   = $departureTime->copy()->addHours(4);
        return $now->between($from, $until);
    }

    /** Distance Haversine (km) entre deux points WGS84. */
    private function haversineKm(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }
        $R  = 6371;
        $dL = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lon2 - $lon1);
        $a  = sin($dL / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dl / 2) ** 2;
        return round(2 * $R * asin(sqrt($a)), 1);
    }

    /** Formate une durée en minutes en "Xh00" ou "XhYY". */
    private function formatDuration(?int $minutes): ?string
    {
        if ($minutes === null) return null;
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $m > 0 ? "{$h}h{$m}" : "{$h}h00";
    }
}
