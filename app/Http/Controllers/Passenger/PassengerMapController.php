<?php

namespace App\Http\Controllers\Passenger;

use App\Helpers\GeoHelper;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Carte de suivi passager — coordonnées GeoHelper-résolues + polyline route réelle.
 *
 * Endpoint appelé une seule fois à l'ouverture de la carte pour obtenir :
 *   - Les coords précises de prise/dépôt de CE passager (commune→arrondissement→quartier)
 *   - Les coords précises du départ/arrivée du trajet complet
 *   - La polyline complète du trajet (ORS→OSRM→lignes droites)
 */
class PassengerMapController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/trips/{tripUuid}/map
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/trips/{tripUuid}/map',
        operationId: 'passengerTripMap',
        summary: 'Données carte suivi passager (coordonnées précises + polyline)',
        description: "Retourne les coordonnées GeoHelper-résolues (commune→arrondissement→quartier→GPS) pour les points de prise et dépôt de CE passager, ainsi que les extrémités du trajet complet et la polyline de route réelle (ORS→OSRM→lignes droites).",
        tags: ['👤 Passenger — Réservations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'tripUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données carte',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Carte passager.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'pickup_latlng',    type: 'object', nullable: true,
                                    properties: [
                                        new OA\Property(property: 'lat', type: 'number', example: 6.3703),
                                        new OA\Property(property: 'lng', type: 'number', example: 2.3912),
                                    ]
                                ),
                                new OA\Property(property: 'dropoff_latlng',   type: 'object', nullable: true,
                                    properties: [
                                        new OA\Property(property: 'lat', type: 'number'),
                                        new OA\Property(property: 'lng', type: 'number'),
                                    ]
                                ),
                                new OA\Property(property: 'departure_latlng', type: 'object', nullable: true,
                                    properties: [
                                        new OA\Property(property: 'lat', type: 'number'),
                                        new OA\Property(property: 'lng', type: 'number'),
                                    ]
                                ),
                                new OA\Property(property: 'arrival_latlng',   type: 'object', nullable: true,
                                    properties: [
                                        new OA\Property(property: 'lat', type: 'number'),
                                        new OA\Property(property: 'lng', type: 'number'),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'route_polyline',
                                    type: 'array',
                                    description: 'Polyline du trajet complet (ORS→OSRM→lignes droites)',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'lat', type: 'number'),
                                            new OA\Property(property: 'lng', type: 'number'),
                                        ]
                                    )
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
    public function mapData(Request $request, string $tripUuid): JsonResponse
    {
        $trip = Trip::where('uuid', $tripUuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        $booking = Booking::where('trip_id', $trip->id)
            ->where('passenger_id', $request->user()->id)
            ->where('status', 'accepted')
            ->first();

        if (! $booking) {
            return $this->apiResponse(false, 'Aucune réservation active pour ce trajet.', [], 403);
        }

        // ── Coordonnées départ/arrivée du trajet complet (GeoHelper) ─────────
        [$depLat, $depLng] = GeoHelper::bestCoords(
            $trip->departure_latitude,
            $trip->departure_longitude,
            $trip->departure_city ?? '',
            $trip->departure_arrondissement ?? null,
            $trip->departure_neighborhood   ?? null,
        );
        [$arrLat, $arrLng] = GeoHelper::bestCoords(
            $trip->arrival_latitude,
            $trip->arrival_longitude,
            $trip->arrival_city ?? '',
            $trip->arrival_arrondissement ?? null,
            $trip->arrival_neighborhood   ?? null,
        );

        // ── Coordonnées prise/dépôt de CE passager (GeoHelper) ───────────────
        [$pickLat, $pickLng] = GeoHelper::bestCoords(
            $booking->pickup_latitude,
            $booking->pickup_longitude,
            $booking->pickup_city          ?? $trip->departure_city          ?? '',
            $booking->pickup_arrondissement ?? $trip->departure_arrondissement ?? null,
            $booking->pickup_neighborhood   ?? $trip->departure_neighborhood   ?? null,
        );
        [$dropLat, $dropLng] = GeoHelper::bestCoords(
            $booking->dropoff_latitude,
            $booking->dropoff_longitude,
            $booking->dropoff_city           ?? $trip->arrival_city           ?? '',
            $booking->dropoff_arrondissement ?? $trip->arrival_arrondissement ?? null,
            $booking->dropoff_neighborhood   ?? $trip->arrival_neighborhood   ?? null,
        );

        // ── Polyline complète : départ → prise → dépôt → arrivée ─────────────
        $waypoints = [];
        if ($depLat  && $depLng)  $waypoints[] = ['lat' => $depLat,  'lng' => $depLng];
        if ($pickLat && $pickLng) $waypoints[] = ['lat' => $pickLat, 'lng' => $pickLng];
        if ($dropLat && $dropLng) $waypoints[] = ['lat' => $dropLat, 'lng' => $dropLng];
        if ($arrLat  && $arrLng)  $waypoints[] = ['lat' => $arrLat,  'lng' => $arrLng];

        $waypoints = $this->deduplicateAdjacent($waypoints);
        $polyline  = GeoHelper::buildRoutePolyline($waypoints);

        if (count($polyline) < count($waypoints)) {
            $polyline = $waypoints;
        }

        return $this->apiResponse(true, 'Carte passager.', [
            'pickup_latlng'    => ($pickLat && $pickLng) ? ['lat' => $pickLat, 'lng' => $pickLng] : null,
            'dropoff_latlng'   => ($dropLat && $dropLng) ? ['lat' => $dropLat, 'lng' => $dropLng] : null,
            'departure_latlng' => ($depLat  && $depLng)  ? ['lat' => $depLat,  'lng' => $depLng]  : null,
            'arrival_latlng'   => ($arrLat  && $arrLng)  ? ['lat' => $arrLat,  'lng' => $arrLng]  : null,
            'route_polyline'   => $polyline,
        ]);
    }

    private function deduplicateAdjacent(array $waypoints): array
    {
        $result = [];
        $prev   = null;
        foreach ($waypoints as $w) {
            if ($prev === null
                || abs($w['lat'] - $prev['lat']) > 0.0001
                || abs($w['lng'] - $prev['lng']) > 0.0001) {
                $result[] = $w;
                $prev     = $w;
            }
        }
        return $result;
    }
}
