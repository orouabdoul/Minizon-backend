<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Suivi en temps réel" (LiveTrackingView) — passager.
 *
 * Endpoint de polling (GET) appelé toutes les ~3 secondes par le Flutter
 * pour rafraîchir la position du conducteur, l'ETA et la vitesse.
 *
 * L'ETA est approximé depuis la durée estimée et le temps écoulé depuis
 * le départ — suffisant pour un MVP sans géocodage inverse.
 *
 * Endpoints existants réutilisés depuis cette page :
 *   triggerSOS()         → POST /api/passenger/safety/sos
 *   callDriver()         → url_launcher tel: (pas d'API)
 *   sendQuickMessage()   → POST /api/bookings/{uuid}/conversation
 *                          puis envoi via la messagerie existante
 */
class PassengerLiveTrackingController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/trips/{uuid}/live-tracking
    //  Polling temps réel — appelé toutes les ~3 s par le Flutter
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/trips/{uuid}/live-tracking',
        operationId: 'passengerLiveTracking',
        summary: 'Position en temps réel du conducteur (LiveTrackingView)',
        description: "Endpoint de polling léger (~3 s) retournant la position GPS du conducteur, l'ETA calculé côté serveur, la vitesse et le statut du trajet. Seul le passager ayant une réservation active pour ce trajet peut y accéder.",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données de tracking',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Position mise à jour.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'lat',                    type: 'number', format: 'float', nullable: true, example: 6.8734, description: 'Latitude actuelle du conducteur.'),
                                new OA\Property(property: 'lng',                    type: 'number', format: 'float', nullable: true, example: 2.1234),
                                new OA\Property(property: 'speed_kmh',              type: 'number', format: 'float', example: 72.0, description: 'Vitesse GPS en km/h (0 si inconnue).'),
                                new OA\Property(property: 'eta_minutes',            type: 'integer', example: 23, description: 'Minutes restantes estimées (max avec durée et temps écoulé).'),
                                new OA\Property(property: 'distance_remaining_km',  type: 'number', format: 'float', nullable: true, example: 27.6, description: 'Distance restante approx. en km.'),
                                new OA\Property(property: 'trip_status',            type: 'string', enum: ['pending', 'active', 'completed', 'cancelled'], example: 'active'),
                                new OA\Property(property: 'trip_ended',             type: 'boolean', example: false, description: 'true quand trip_status === "completed".'),
                                new OA\Property(
                                    property: 'ride',
                                    type: 'object',
                                    nullable: true,
                                    description: 'Infos conducteur/véhicule pour le panneau bas (inclus uniquement au premier appel si absent des args de navigation).',
                                    properties: [
                                        new OA\Property(property: 'driver_name',    type: 'string', example: 'Koffi Adjovi'),
                                        new OA\Property(property: 'driver_initials',type: 'string', example: 'KA'),
                                        new OA\Property(property: 'rating',         type: 'string', example: '4.8'),
                                        new OA\Property(property: 'vehicle',        type: 'string', example: 'Toyota Corolla'),
                                        new OA\Property(property: 'vehicle_plate',  type: 'string', example: 'AB-123-CD'),
                                        new OA\Property(property: 'driver_phone',   type: 'string', nullable: true, example: '0159000892'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Aucune réservation active pour ce trajet'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    public function poll(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::with(['user.profile', 'vehicle'])
            ->where('uuid', $uuid)
            ->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        // Vérifier que le passager a une réservation acceptée pour ce trajet
        $booking = Booking::where('trip_id', $trip->id)
            ->where('passenger_id', $request->user()->id)
            ->where('status', 'accepted')
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Aucune réservation active pour ce trajet.', [], 403);
        }

        $tz = 'Africa/Porto-Novo';

        // ── Position GPS ──────────────────────────────────────────────────────
        $lat = $trip->current_latitude  ? (float) $trip->current_latitude  : null;
        $lng = $trip->current_longitude ? (float) $trip->current_longitude : null;

        // Vitesse (km/h) — stockée par POST /api/trips/{uuid}/location
        $speedKmh = $trip->current_speed ? (float) $trip->current_speed : 0.0;

        // ── ETA (minutes restantes) ───────────────────────────────────────────
        // Approximation : durée estimée - temps écoulé depuis le départ.
        // Remplacer par une vraie computation Haversine quand les coordonnées
        // de la destination seront disponibles dans la table trips.
        $etaMinutes    = null;
        $distRemaining = null;

        if ($trip->estimated_duration_minutes && $trip->departure_time) {
            $elapsedMinutes = (int) max(0, now()->diffInMinutes($trip->departure_time, false) * -1);
            $etaMinutes     = (int) max(0, $trip->estimated_duration_minutes - $elapsedMinutes);

            // Distance restante approx. : vitesse × ETA (ou proportion durée)
            if ($speedKmh > 0 && $etaMinutes > 0) {
                $distRemaining = round($speedKmh * ($etaMinutes / 60), 1);
            } elseif ($trip->estimated_duration_minutes > 0) {
                // Fallback : proportion de la distance totale estimée (50 km/h moyen)
                $totalKm       = round(($trip->estimated_duration_minutes / 60) * 50, 1);
                $fraction      = $etaMinutes / $trip->estimated_duration_minutes;
                $distRemaining = round($totalKm * $fraction, 1);
            }
        }

        // ── Statut ────────────────────────────────────────────────────────────
        $tripStatus = $trip->status ?? 'active';
        $tripEnded  = $tripStatus === 'completed';

        // ── Infos conducteur (incluses à chaque poll — légères) ───────────────
        $driver  = $trip->user;
        $profile = $driver?->profile;
        $vehicle = $trip->vehicle;

        $firstName  = $profile?->first_name ?? '';
        $lastName   = $profile?->last_name  ?? '';
        $driverName = trim("$firstName $lastName") ?: 'Conducteur';

        $avgRating = $driver
            ? (string) round(Review::where('reviewee_id', $driver->id)->avg('rating') ?? 0, 1)
            : '—';

        $rawPhone   = $driver?->phone ?? '';
        $driverPhone = $rawPhone ? (preg_replace('/^\+?229/', '', $rawPhone) ?: null) : null;

        $ride = [
            'driver_name'     => $driverName,
            'driver_initials' => $this->initials($driverName),
            'rating'          => $avgRating,
            'vehicle'         => $vehicle ? trim("{$vehicle->brand} {$vehicle->model}") : '—',
            'vehicle_plate'   => $vehicle?->license_plate ?? '—',
            'driver_phone'    => $driverPhone,
        ];

        return $this->apiResponse(true, 'Position mise à jour.', [
            'lat'                   => $lat,
            'lng'                   => $lng,
            'speed_kmh'             => $speedKmh,
            'eta_minutes'           => $etaMinutes ?? 0,
            'distance_remaining_km' => $distRemaining,
            'trip_status'           => $tripStatus,
            'trip_ended'            => $tripEnded,
            'ride'                  => $ride,
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn ($w) => strtoupper($w[0]))
            ->join('');
    }
}
