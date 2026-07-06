<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Approche du point de prise en charge" (DriverArrivalView) — conducteur.
 *
 * Phase : booking accepté → conducteur en route vers le passager → passager embarqué.
 *
 * Flux standard :
 *   1. GET  /api/driver/bookings/{uuid}/arrival-context  — chargement initial
 *   2. POST /api/trips/{uuid}/location                  — push GPS continu (existant)
 *   3. POST /api/driver/bookings/{uuid}/arrived          — conducteur marque son arrivée
 *   4. POST /api/trips/{uuid}/start                     — démarrage trajet (existant, TripController)
 *
 * Endpoints réutilisés depuis cette page (aucun nouveau code) :
 *   sendMessage()   → messagerie existante (POST /api/conversations/{uuid}/messages)
 *   callPassenger() → url_launcher tel: (pas d'API)
 *   goToPreDeparture() → GET /api/driver/trips/{uuid}/pre-departure (DriverActiveTripController)
 *
 * Migration requise : ALTER TABLE bookings ADD COLUMN driver_arrived_at TIMESTAMP NULL;
 */
class DriverArrivalController extends Controller
{
    // =========================================================================
    //  GET /api/driver/bookings/{uuid}/arrival-context
    //  Chargement initial — appelé une seule fois à l'ouverture de la page
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/bookings/{uuid}/arrival-context',
        operationId: 'driverArrivalContext',
        summary: 'Contexte d\'approche du point de prise en charge (DriverArrivalView)',
        description: "Données de départ pour DriverArrivalView : coordonnées GPS de la prise en charge et de la destination, infos passager, informations du trajet et identifiant de conversation. La position du conducteur est mise à jour via POST /api/trips/{uuid}/location (endpoint existant). L'ETA vers la prise en charge est calculé côté Flutter (Haversine + vitesse GPS).",
        tags: ['🚗 Driver — Arrivée passager'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de la réservation'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contexte d\'approche chargé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Contexte chargé.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                // Coordonnées carte
                                new OA\Property(property: 'pickup_lat',       type: 'number', format: 'float', nullable: true, example: 6.3654, description: 'Latitude du point de prise en charge (centre-ville d\'origine si non précisé).'),
                                new OA\Property(property: 'pickup_lng',       type: 'number', format: 'float', nullable: true, example: 2.4183),
                                new OA\Property(property: 'destination_lat',  type: 'number', format: 'float', nullable: true, example: 9.337),
                                new OA\Property(property: 'destination_lng',  type: 'number', format: 'float', nullable: true, example: 2.623),
                                // Infos trajet (ride card)
                                new OA\Property(
                                    property: 'ride',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'trip_uuid',      type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'origin',         type: 'string', example: 'Cotonou'),
                                        new OA\Property(property: 'destination',    type: 'string', example: 'Parakou'),
                                        new OA\Property(property: 'departure_time', type: 'string', example: 'Aujourd\'hui à 08:30'),
                                        new OA\Property(property: 'departure_note', type: 'string', nullable: true, example: 'Devant le marché Dantokpa'),
                                        new OA\Property(property: 'arrival_note',   type: 'string', nullable: true, example: 'Gare routière de Parakou'),
                                        new OA\Property(property: 'driver_name',    type: 'string', example: 'Koffi Adjovi', description: 'Nom du conducteur (soi-même) — affiché dans la carte conducteur de la vue.'),
                                        new OA\Property(property: 'vehicle',        type: 'string', example: 'Toyota Corolla'),
                                        new OA\Property(property: 'vehicle_plate',  type: 'string', example: 'AB-123-CD'),
                                        new OA\Property(property: 'rating',         type: 'string', example: '4.8'),
                                    ]
                                ),
                                // Infos passager (pour callPassenger / avatar)
                                new OA\Property(
                                    property: 'passenger',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'name',             type: 'string', example: 'Aminata Diallo'),
                                        new OA\Property(property: 'initials',         type: 'string', example: 'AD'),
                                        new OA\Property(property: 'phone',            type: 'string', nullable: true, example: '0196112233'),
                                        new OA\Property(property: 'seats_booked',     type: 'integer', example: 2),
                                        new OA\Property(property: 'pickup_note',      type: 'string', nullable: true, example: 'Appelle-moi à l\'arrivée'),
                                    ]
                                ),
                                // Conversation (pour sendMessage)
                                new OA\Property(property: 'conversation_uuid', type: 'string', format: 'uuid', nullable: true, description: 'UUID de la conversation avec ce passager — passer à POST /api/conversations/{uuid}/messages pour les messages rapides.'),
                                // État arrivée
                                new OA\Property(property: 'already_arrived', type: 'boolean', example: false, description: 'true si driver_arrived_at est déjà renseigné (reprise après crash).'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Réservation appartenant à un autre conducteur'),
            new OA\Response(response: 404, description: 'Réservation introuvable ou non acceptée'),
        ]
    )]
    public function context(Request $request, string $uuid): JsonResponse
    {
        $driver = $request->user();

        $booking = Booking::with(['trip.vehicle', 'passenger.profile'])
            ->where('uuid', $uuid)
            ->where('status', 'accepted')
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable ou non acceptée.', [], 404);
        }

        // Seul le conducteur du trajet peut accéder à cet endpoint
        if ($booking->trip->user_id !== $driver->id) {
            return $this->apiResponse(false, 'Accès non autorisé.', [], 403);
        }

        $trip    = $booking->trip;
        $vehicle = $trip->vehicle;

        // ── Profil conducteur (soi-même) ──────────────────────────────────────
        $driverProfile = $driver->profile;
        $driverFirst   = $driverProfile?->first_name ?? '';
        $driverLast    = $driverProfile?->last_name  ?? '';
        $driverName    = trim("$driverFirst $driverLast") ?: 'Conducteur';
        $driverRating  = (string) round(Review::where('reviewee_id', $driver->id)->avg('rating') ?? 0, 1);

        // ── Profil passager ───────────────────────────────────────────────────
        $passenger        = $booking->passenger;
        $passengerProfile = $passenger?->profile;
        $passengerFirst   = $passengerProfile?->first_name ?? '';
        $passengerLast    = $passengerProfile?->last_name  ?? '';
        $passengerName    = trim("$passengerFirst $passengerLast") ?: 'Passager';
        $rawPhone         = $passenger?->phone ?? '';
        $passengerPhone   = $rawPhone ? (preg_replace('/^\+?229/', '', $rawPhone) ?: null) : null;

        // ── Coordonnées GPS (lookup Bénin) ─────────────────────────────────────
        $pickup      = $this->cityCoords($trip->origin);
        $destination = $this->cityCoords($trip->destination);

        // ── Heure départ formatée ─────────────────────────────────────────────
        $tz            = 'Africa/Porto-Novo';
        $depTime       = $trip->departure_time?->setTimezone($tz);
        $depFormatted  = $depTime ? $depTime->translatedFormat('l à H\hi') : '—';

        // ── Conversation existante avec ce passager ────────────────────────────
        $conversationUuid = null;
        if (method_exists($booking, 'conversation') && $booking->conversation) {
            $conversationUuid = $booking->conversation->uuid;
        } elseif (method_exists($booking, 'conversations')) {
            $conversationUuid = $booking->conversations()->latest()->value('uuid');
        }

        $ride = [
            'trip_uuid'      => $trip->uuid,
            'origin'         => $trip->origin,
            'destination'    => $trip->destination,
            'departure_time' => $depFormatted,
            'departure_note' => $booking->pickup_note   ?? null,
            'arrival_note'   => $booking->dropoff_note  ?? null,
            'driver_name'    => $driverName,
            'vehicle'        => $vehicle ? trim("{$vehicle->brand} {$vehicle->model}") : '—',
            'vehicle_plate'  => $vehicle?->license_plate ?? '—',
            'rating'         => $driverRating,
        ];

        $passengerData = [
            'name'         => $passengerName,
            'initials'     => $this->initials($passengerName),
            'phone'        => $passengerPhone,
            'seats_booked' => $booking->seats_booked ?? 1,
            'pickup_note'  => $booking->pickup_note ?? null,
        ];

        return $this->apiResponse(true, 'Contexte chargé.', [
            'pickup_lat'        => $pickup ? $pickup['lat']      : null,
            'pickup_lng'        => $pickup ? $pickup['lng']      : null,
            'destination_lat'   => $destination ? $destination['lat'] : null,
            'destination_lng'   => $destination ? $destination['lng'] : null,
            'ride'              => $ride,
            'passenger'         => $passengerData,
            'conversation_uuid' => $conversationUuid,
            'already_arrived'   => isset($booking->driver_arrived_at),
        ]);
    }

    // =========================================================================
    //  POST /api/driver/bookings/{uuid}/arrived
    //  Conducteur marque son arrivée au point de prise en charge
    //  → notifie le passager + enregistre l'heure d'arrivée
    //  Migration : ALTER TABLE bookings ADD COLUMN driver_arrived_at TIMESTAMP NULL;
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/bookings/{uuid}/arrived',
        operationId: 'driverMarkArrived',
        summary: 'Conducteur marque son arrivée au point de prise en charge',
        description: "Enregistre l'heure d'arrivée du conducteur (`driver_arrived_at`) et envoie une notification push au passager. Idempotent : un second appel retourne success sans erreur. Après cet appel, le conducteur navigue vers DriverActiveTripView puis appelle POST /api/trips/{uuid}/start pour démarrer officiellement le trajet.",
        tags: ['🚗 Driver — Arrivée passager'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID de la réservation'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Arrivée confirmée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Arrivée confirmée. Le passager est notifié.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'arrived_at', type: 'string', format: 'date-time', example: '2026-07-06T08:15:00+01:00'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Accès non autorisé'),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    public function arrived(Request $request, string $uuid): JsonResponse
    {
        $driver = $request->user();

        $booking = Booking::with('trip', 'passenger')
            ->where('uuid', $uuid)
            ->where('status', 'accepted')
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Réservation introuvable ou non acceptée.', [], 404);
        }

        if ($booking->trip->user_id !== $driver->id) {
            return $this->apiResponse(false, 'Accès non autorisé.', [], 403);
        }

        // Idempotent : ne pas réécrire si déjà arrivé
        $arrivedAt = $booking->driver_arrived_at ?? now();
        if (! $booking->driver_arrived_at) {
            // Migration requise : ALTER TABLE bookings ADD COLUMN driver_arrived_at TIMESTAMP NULL;
            $booking->update(['driver_arrived_at' => now()]);
            $arrivedAt = $booking->driver_arrived_at;
        }

        // Notification push au passager
        $this->notifyPassenger($booking);

        return $this->apiResponse(true, 'Arrivée confirmée. Le passager est notifié.', [
            'arrived_at' => $arrivedAt,
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function notifyPassenger(Booking $booking): void
    {
        $passengerId = $booking->passenger_id;
        if (! $passengerId) {
            return;
        }

        $trip     = $booking->trip;
        $origin   = $trip->origin ?? 'votre point de départ';

        try {
            Notification::create([
                'user_id' => $passengerId,
                'type'    => 'driver_arrived',
                'title'   => 'Votre conducteur est arrivé !',
                'body'    => "Votre conducteur vous attend à {$origin}. Préparez-vous à embarquer.",
                'data'    => json_encode(['booking_uuid' => $booking->uuid, 'trip_uuid' => $trip->uuid]),
                'read'    => false,
            ]);
        } catch (\Throwable) {
            // Notification non bloquante — ne pas faire échouer la réponse
        }
    }

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn ($w) => strtoupper($w[0]))
            ->join('');
    }

    /**
     * Coordonnées GPS des principales villes du Bénin.
     * Utilisées quand les colonnes lat/lng ne sont pas encore sur les tables
     * trips / bookings (roadmap : ajouter pickup_lat/lng à trips).
     *
     * @return array{lat: float, lng: float}|null
     */
    private function cityCoords(string $cityName): ?array
    {
        static $map = [
            'cotonou'        => ['lat' => 6.3654,   'lng' => 2.4183],
            'porto-novo'     => ['lat' => 6.4969,   'lng' => 2.6289],
            'porto novo'     => ['lat' => 6.4969,   'lng' => 2.6289],
            'abomey-calavi'  => ['lat' => 6.4492,   'lng' => 2.3554],
            'abomey calavi'  => ['lat' => 6.4492,   'lng' => 2.3554],
            'parakou'        => ['lat' => 9.3370,   'lng' => 2.6230],
            'bohicon'        => ['lat' => 7.1778,   'lng' => 2.0667],
            'lokossa'        => ['lat' => 6.6378,   'lng' => 1.7167],
            'natitingou'     => ['lat' => 10.3039,  'lng' => 1.3780],
            'abomey'         => ['lat' => 7.1833,   'lng' => 1.9833],
            'kandi'          => ['lat' => 11.1333,  'lng' => 2.9333],
            'ouidah'         => ['lat' => 6.3606,   'lng' => 2.0833],
            'djougou'        => ['lat' => 9.7083,   'lng' => 1.6667],
            'malanville'     => ['lat' => 11.8667,  'lng' => 3.3833],
            'save'           => ['lat' => 8.0333,   'lng' => 2.4833],
            'savè'           => ['lat' => 8.0333,   'lng' => 2.4833],
            'savalou'        => ['lat' => 7.9333,   'lng' => 1.9667],
            'nikki'          => ['lat' => 9.9333,   'lng' => 3.2167],
            'tchaourou'      => ['lat' => 8.8833,   'lng' => 2.5967],
            'pobe'           => ['lat' => 6.9667,   'lng' => 2.6667],
            'pobè'           => ['lat' => 6.9667,   'lng' => 2.6667],
            'ketou'          => ['lat' => 7.3667,   'lng' => 2.6000],
            'kétou'          => ['lat' => 7.3667,   'lng' => 2.6000],
            'come'           => ['lat' => 6.4000,   'lng' => 1.8833],
            'comè'           => ['lat' => 6.4000,   'lng' => 1.8833],
            'bembereke'      => ['lat' => 10.2333,  'lng' => 2.6667],
            'bembèrèkè'      => ['lat' => 10.2333,  'lng' => 2.6667],
            'dangbo'         => ['lat' => 6.5333,   'lng' => 2.6833],
            'grand-popo'     => ['lat' => 6.2833,   'lng' => 1.8167],
            'grand popo'     => ['lat' => 6.2833,   'lng' => 1.8167],
            'dassa-zoume'    => ['lat' => 7.7333,   'lng' => 2.1833],
            'dassa'          => ['lat' => 7.7333,   'lng' => 2.1833],
            'glazoue'        => ['lat' => 7.9167,   'lng' => 2.2167],
            'glazoué'        => ['lat' => 7.9167,   'lng' => 2.2167],
        ];

        $key = mb_strtolower(trim($cityName));
        // Normalisation basique des accents pour la recherche
        $key = strtr($key, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'û' => 'u', 'ü' => 'u', 'ù' => 'u',
        ]);

        // Chercher d'abord la clé exacte, puis la clé normalisée
        return $map[$key] ?? $map[mb_strtolower(trim($cityName))] ?? null;
    }
}
