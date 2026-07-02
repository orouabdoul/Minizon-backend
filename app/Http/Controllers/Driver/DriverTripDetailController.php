<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DriverTripDetailController extends Controller
{
    // =========================================================================
    //  GET /api/driver/trips/{uuid}
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/trips/{uuid}',
        operationId: 'driverTripDetail',
        summary: 'Détail complet d\'un trajet (conducteur)',
        description: "Retourne toutes les données nécessaires à la page Détail du trajet : statut, itinéraire complet (GPS + points texte), liste des passagers acceptés (avec note, vérification et statut de paiement), récapitulatif financier (revenus brut/commission/net), statistiques (distance estimée, durée, places dispo) et actions disponibles (démarrer, modifier, annuler).",
        tags: ['🚗 Driver — Détail trajet'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détail du trajet',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Détail du trajet.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid',          type: 'string', format: 'uuid'),
                                new OA\Property(property: 'status',        type: 'string', enum: ['pending', 'active', 'completed', 'cancelled']),
                                new OA\Property(property: 'status_label',  type: 'string', example: 'À venir'),
                                new OA\Property(property: 'published_ago', type: 'string', example: 'il y a 3h'),
                                new OA\Property(property: 'description',   type: 'string', nullable: true),
                                new OA\Property(property: 'preferences',   type: 'array',  items: new OA\Items(type: 'string')),
                                new OA\Property(property: 'booking_mode',       type: 'string', enum: ['instant', 'approval']),
                                new OA\Property(property: 'cancellation_policy',type: 'string', enum: ['flexible', 'moderate', 'strict']),
                                new OA\Property(
                                    property: 'route',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'origin',                      type: 'string',  example: 'Cotonou, Akpakpa'),
                                        new OA\Property(property: 'origin_point',                type: 'string',  nullable: true, example: 'Carrefour Étoile Rouge'),
                                        new OA\Property(property: 'destination',                 type: 'string',  example: 'Parakou, Centre-ville'),
                                        new OA\Property(property: 'destination_point',           type: 'string',  nullable: true, example: 'Gare routière'),
                                        new OA\Property(property: 'departure_time',              type: 'string',  format: 'date-time'),
                                        new OA\Property(property: 'departure_time_formatted',    type: 'string',  example: '07:00'),
                                        new OA\Property(property: 'estimated_arrival_time',      type: 'string',  format: 'date-time', nullable: true),
                                        new OA\Property(property: 'estimated_arrival_formatted', type: 'string',  nullable: true, example: '~12:00'),
                                        new OA\Property(property: 'estimated_duration_minutes',  type: 'integer', nullable: true),
                                        new OA\Property(property: 'duration_label',              type: 'string',  nullable: true, example: '5h00'),
                                        new OA\Property(property: 'vehicle_label',               type: 'string',  nullable: true, example: 'Toyota Camry · RB 1234 X'),
                                        new OA\Property(property: 'departure_latitude',          type: 'number',  nullable: true),
                                        new OA\Property(property: 'departure_longitude',         type: 'number',  nullable: true),
                                        new OA\Property(property: 'arrival_latitude',            type: 'number',  nullable: true),
                                        new OA\Property(property: 'arrival_longitude',           type: 'number',  nullable: true),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'waypoints',
                                    type: 'array',
                                    items: new OA\Items(type: 'object')
                                ),
                                new OA\Property(
                                    property: 'passengers',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'total_seats',    type: 'integer'),
                                        new OA\Property(property: 'booked_seats',   type: 'integer'),
                                        new OA\Property(property: 'available_seats',type: 'integer'),
                                        new OA\Property(
                                            property: 'list',
                                            type: 'array',
                                            items: new OA\Items(ref: '#/components/schemas/TripDetailPassenger')
                                        ),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'finances',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'price_per_seat',    type: 'integer', example: 5000),
                                        new OA\Property(property: 'booked_seats',      type: 'integer', example: 2),
                                        new OA\Property(property: 'total_revenue',     type: 'integer', example: 10000),
                                        new OA\Property(property: 'commission_rate',   type: 'integer', example: 10),
                                        new OA\Property(property: 'commission_amount', type: 'integer', example: 1000),
                                        new OA\Property(property: 'net_revenue',       type: 'integer', example: 9000),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'stats',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'distance_km',    type: 'number',  nullable: true, example: 413),
                                        new OA\Property(property: 'duration_label', type: 'string',  nullable: true, example: '5h00'),
                                        new OA\Property(property: 'available_seats',type: 'integer', example: 2),
                                    ]
                                ),
                                new OA\Property(property: 'can_start',  type: 'boolean'),
                                new OA\Property(property: 'can_edit',   type: 'boolean'),
                                new OA\Property(property: 'can_cancel', type: 'boolean'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Ce trajet ne vous appartient pas', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable',               content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::with([
            'vehicle.vehicleType',
            'bookings' => fn ($q) => $q->whereIn('status', ['accepted', 'pending'])->with(['passenger.profile', 'passenger.reviewsReceived', 'payment']),
        ])->where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if ($trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Ce trajet ne vous appartient pas.', [], 403);
        }

        $vehicle  = $trip->vehicle;
        $accepted = $trip->bookings->where('status', 'accepted');
        $bookedSeats = (int) $accepted->sum('seats_booked');

        // ── Finances ──────────────────────────────────────────────────────────
        $totalRevenue     = $trip->price_per_seat * $bookedSeats;
        $commissionAmount = $trip->platformCommission($bookedSeats);
        $netRevenue       = $trip->driverEarnings($bookedSeats);

        // ── Distance estimée (Haversine) ──────────────────────────────────────
        $distanceKm = $this->haversineKm(
            $trip->departure_latitude,  $trip->departure_longitude,
            $trip->arrival_latitude,    $trip->arrival_longitude,
        );

        // ── Durée ─────────────────────────────────────────────────────────────
        $durationLabel = $this->formatDuration($trip->estimated_duration_minutes);

        // ── Passagers ─────────────────────────────────────────────────────────
        $passengerList = $accepted->map(fn (Booking $b) => $this->serializePassenger($b))->values();

        // ── Statut paiement aggregé (pour le badge "En attente") ──────────────
        $hasPendingPayment = $accepted->contains(
            fn (Booking $b) => $b->payment_status !== 'escrow_locked' && $b->payment_status !== 'released'
        );

        // ── Note contextuelle (demandes en attente) ────────────────────────────
        $pendingCount = $trip->bookings->where('status', 'pending')->count();

        return $this->apiResponse(true, 'Détail du trajet.', [
            'uuid'          => $trip->uuid,
            'status'        => $trip->status,
            'status_label'  => $this->statusLabel($trip->status),
            'published_ago' => $trip->created_at->diffForHumans(),
            'description'   => $trip->description,
            'preferences'   => $trip->preferences ?? [],
            'booking_mode'        => $trip->booking_mode ?? 'instant',
            'cancellation_policy' => $trip->cancellation_policy ?? 'flexible',
            'is_recurring'        => (bool) $trip->is_recurring,
            'recurring_days'      => $trip->recurring_days ?? [],

            'route' => [
                'origin'                     => $trip->departure_city . ', ' . $trip->departure_neighborhood,
                'origin_point'               => $trip->departure_point,
                'destination'                => $trip->arrival_city . ', ' . $trip->arrival_neighborhood,
                'destination_point'          => $trip->arrival_point,
                'departure_time'             => $trip->departure_time,
                'departure_time_formatted'   => $trip->departure_time->format('H:i'),
                'estimated_arrival_time'     => $trip->estimated_arrival_time,
                'estimated_arrival_formatted'=> $trip->estimated_arrival_time
                    ? '~' . $trip->estimated_arrival_time->format('H:i')
                    : null,
                'estimated_duration_minutes' => $trip->estimated_duration_minutes,
                'duration_label'             => $durationLabel,
                'vehicle_label'              => $vehicle
                    ? "{$vehicle->brand} {$vehicle->model} · {$vehicle->license_plate}"
                    : null,
                'departure_latitude'         => $trip->departure_latitude,
                'departure_longitude'        => $trip->departure_longitude,
                'arrival_latitude'           => $trip->arrival_latitude,
                'arrival_longitude'          => $trip->arrival_longitude,
            ],

            'waypoints' => $trip->waypoints ?? [],

            'passengers' => [
                'total_seats'     => $trip->total_seats,
                'booked_seats'    => $bookedSeats,
                'available_seats' => $trip->available_seats,
                'pending_count'   => $pendingCount,
                'list'            => $passengerList,
            ],

            'finances' => [
                'price_per_seat'    => $trip->price_per_seat,
                'booked_seats'      => $bookedSeats,
                'total_revenue'     => $totalRevenue,
                'commission_rate'   => $trip->commission_rate ?? 10,
                'commission_amount' => $commissionAmount,
                'net_revenue'       => $netRevenue,
                'has_pending_payment' => $hasPendingPayment,
            ],

            'stats' => [
                'distance_km'    => $distanceKm,
                'duration_label' => $durationLabel,
                'available_seats'=> $trip->available_seats,
                'view_count'     => $trip->view_count ?? 0,
            ],

            'can_start'  => $trip->status === 'pending',
            'can_edit'   => $trip->status === 'pending',
            'can_cancel' => $trip->status === 'pending',
        ]);
    }

    // =========================================================================
    //  PATCH /api/driver/trips/{uuid}  — modifier un trajet
    // =========================================================================

    #[OA\Patch(
        path: '/api/driver/trips/{uuid}',
        operationId: 'driverTripUpdate',
        summary: 'Modifier un trajet (conducteur)',
        description: "Permet au conducteur de modifier les champs éditables d'un trajet `pending` : prix, heure de départ, durée estimée, mode de réservation, politique d'annulation, places max par réservation, description, préférences et waypoints.",
        tags: ['🚗 Driver — Détail trajet'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'price_per_seat',          type: 'integer', nullable: true, example: 5500),
                    new OA\Property(property: 'departure_date',          type: 'string',  nullable: true, example: '12/07/2026', description: 'Format jj/mm/aaaa'),
                    new OA\Property(property: 'departure_time',          type: 'string',  nullable: true, example: '08:00',      description: 'Format HH:mm'),
                    new OA\Property(property: 'estimated_duration_minutes', type: 'integer', nullable: true),
                    new OA\Property(property: 'booking_mode',            type: 'string',  nullable: true, enum: ['instant', 'approval']),
                    new OA\Property(property: 'max_per_booking',         type: 'integer', nullable: true),
                    new OA\Property(property: 'cancellation_policy',     type: 'string',  nullable: true, enum: ['flexible', 'moderate', 'strict']),
                    new OA\Property(property: 'description',             type: 'string',  nullable: true),
                    new OA\Property(property: 'preferences',             type: 'array',   nullable: true, items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'waypoints',               type: 'array',   nullable: true, items: new OA\Items(type: 'object')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Trajet mis à jour'),
            new OA\Response(response: 403, description: 'Modification impossible (statut incompatible ou accès refusé)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides',  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->where('user_id', $request->user()->id)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if ($trip->status !== 'pending') {
            return $this->apiResponse(false, 'Seuls les trajets en attente peuvent être modifiés.', [], 403);
        }

        $validated = $request->validate([
            'price_per_seat'             => 'sometimes|integer|min:0',
            'departure_date'             => 'sometimes|string',
            'departure_time'             => ['sometimes', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'estimated_duration_minutes' => 'sometimes|nullable|integer|min:1|max:1440',
            'booking_mode'               => 'sometimes|string|in:instant,approval',
            'max_per_booking'            => 'sometimes|integer|min:1|max:20',
            'cancellation_policy'        => 'sometimes|string|in:flexible,moderate,strict',
            'description'                => 'sometimes|nullable|string|max:500',
            'preferences'                => 'sometimes|nullable|array',
            'preferences.*'              => 'string',
            'waypoints'                  => 'sometimes|nullable|array|max:5',
            'waypoints.*.city'                   => 'required_with:waypoints|string|max:100',
            'waypoints.*.neighborhood'           => 'nullable|string|max:100',
            'waypoints.*.arrival_offset_minutes' => 'required_with:waypoints|integer|min:1',
            'waypoints.*.extra_price'            => 'nullable|integer|min:0',
        ]);

        $updates = [];

        if (isset($validated['price_per_seat'])) {
            $updates['price_per_seat'] = $validated['price_per_seat'];
        }

        // Re-parser la date/heure si fournie
        if (isset($validated['departure_date']) || isset($validated['departure_time'])) {
            $date = $validated['departure_date'] ?? $trip->departure_time->format('d/m/Y');
            $time = $validated['departure_time'] ?? $trip->departure_time->format('H:i');

            try {
                $newDeparture = \Illuminate\Support\Carbon::createFromFormat(
                    'd/m/Y H:i', "{$date} {$time}", 'Africa/Porto-Novo'
                );
            } catch (\Exception) {
                return $this->apiResponse(false, 'Format date/heure invalide.', [], 422);
            }

            if ($newDeparture->isPast()) {
                return $this->apiResponse(false, 'L\'heure de départ doit être dans le futur.', [], 422);
            }

            $updates['departure_time'] = $newDeparture;

            // Recalcul de l'heure d'arrivée estimée si la durée est connue
            $minutes = $validated['estimated_duration_minutes'] ?? $trip->estimated_duration_minutes;
            $updates['estimated_arrival_time'] = $minutes ? $newDeparture->copy()->addMinutes($minutes) : null;
        }

        if (isset($validated['estimated_duration_minutes'])) {
            $updates['estimated_duration_minutes'] = $validated['estimated_duration_minutes'];
            $base = $updates['departure_time'] ?? $trip->departure_time;
            $updates['estimated_arrival_time'] = $validated['estimated_duration_minutes']
                ? $base->copy()->addMinutes($validated['estimated_duration_minutes'])
                : null;
        }

        foreach (['booking_mode', 'max_per_booking', 'cancellation_policy', 'description', 'waypoints', 'preferences'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }

        $trip->update($updates);

        return $this->apiResponse(true, 'Trajet mis à jour.', [
            'uuid'                       => $trip->uuid,
            'price_per_seat'             => $trip->fresh()->price_per_seat,
            'departure_time'             => $trip->fresh()->departure_time,
            'estimated_arrival_time'     => $trip->fresh()->estimated_arrival_time,
            'estimated_duration_minutes' => $trip->fresh()->estimated_duration_minutes,
            'booking_mode'               => $trip->fresh()->booking_mode,
            'cancellation_policy'        => $trip->fresh()->cancellation_policy,
        ]);
    }

    // =========================================================================
    //  OA SCHEMAS
    // =========================================================================

    #[OA\Schema(
        schema: 'TripDetailPassenger',
        properties: [
            new OA\Property(property: 'booking_uuid',    type: 'string',  format: 'uuid'),
            new OA\Property(property: 'name',            type: 'string',  example: 'Koffi Mensah'),
            new OA\Property(property: 'avatar_initial',  type: 'string',  example: 'KM'),
            new OA\Property(property: 'is_verified',     type: 'boolean', example: true),
            new OA\Property(property: 'rating',          type: 'number',  nullable: true, example: 4.8),
            new OA\Property(property: 'trips_count',     type: 'integer', example: 12),
            new OA\Property(property: 'payment_status',  type: 'string',  enum: ['paid', 'pending', 'failed']),
            new OA\Property(property: 'phone',           type: 'string',  example: '+22997000000'),
            new OA\Property(property: 'seats_booked',    type: 'integer', example: 1),
            new OA\Property(property: 'booking_status',  type: 'string',  enum: ['accepted', 'pending']),
            new OA\Property(property: 'booked_at',       type: 'string',  format: 'date-time'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function serializePassenger(Booking $booking): array
    {
        $passenger = $booking->passenger;
        $profile   = $passenger?->profile;
        $fullName  = $profile?->fullName() ?: ($passenger?->phone ?? '—');
        $initials  = $this->initials($fullName);

        // Note moyenne et nombre de trajets du passager
        $avgRating  = $passenger?->reviewsReceived?->avg('rating');
        $tripsCount = $passenger
            ? \App\Models\Booking::where('passenger_id', $passenger->id)
                ->where('status', 'accepted')
                ->count()
            : 0;

        // Mapping statut paiement → label Flutter
        $paymentStatus = match ($booking->payment_status) {
            'escrow_locked', 'released' => 'paid',
            'failed', 'refunded'        => 'failed',
            default                     => 'pending',
        };

        return [
            'booking_uuid'   => $booking->uuid,
            'name'           => $fullName,
            'avatar_initial' => $initials,
            'is_verified'    => (bool) ($profile?->kyc_status === 'approved' || $passenger?->is_verified),
            'rating'         => $avgRating ? round($avgRating, 1) : null,
            'trips_count'    => $tripsCount,
            'payment_status' => $paymentStatus,
            'phone'          => $passenger?->phone,
            'seats_booked'   => $booking->seats_booked,
            'booking_status' => $booking->status,
            'booked_at'      => $booking->created_at,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending'   => 'À venir',
            'active'    => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            default     => $status,
        };
    }

    private function formatDuration(?int $minutes): ?string
    {
        if ($minutes === null) return null;
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $m > 0 ? "{$h}h{$m}" : "{$h}h00";
    }

    /** Distance Haversine entre deux coordonnées WGS84, en km. */
    private function haversineKm(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }

        $R  = 6371; // rayon Terre en km
        $dL = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lon2 - $lon1);
        $a  = sin($dL / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dl / 2) ** 2;

        return round(2 * $R * asin(sqrt($a)), 1);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), 2);
        $first = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
        $last  = mb_strtoupper(mb_substr($parts[1] ?? '', 0, 1));
        return $first . $last ?: '??';
    }
}
