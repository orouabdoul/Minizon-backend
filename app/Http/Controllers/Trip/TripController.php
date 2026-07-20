<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Gestion des trajets — endpoints publics + actions conducteur.
 *
 * POST /api/trips/{uuid}/location  — push GPS toutes les ~5s depuis le device conducteur
 * POST /api/trips/{uuid}/start     — démarre le trajet (status pending → active)
 * POST /api/trips/{uuid}/end       — termine le trajet (status active → completed)
 * GET  /api/trips/{uuid}/tracking  — position publique pour les passagers
 */
class TripController extends Controller
{
    // =========================================================================
    //  GET /api/trips  — liste publique
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $query = Trip::with(['user.profile', 'vehicle'])
            ->where('is_published', true)
            ->where('is_flagged', false)
            ->where('status', 'pending')
            ->where('departure_time', '>', now())
            ->orderBy('departure_time');

        if ($request->filled('from')) {
            $query->where('departure_city', 'like', '%' . $request->from . '%');
        }
        if ($request->filled('to')) {
            $query->where('arrival_city', 'like', '%' . $request->to . '%');
        }
        if ($request->filled('date')) {
            $query->whereDate('departure_time', $request->date);
        }
        if ($request->filled('seats')) {
            $query->where('available_seats', '>=', (int) $request->seats);
        }

        $trips = $query->paginate($request->input('per_page', 20));

        return $this->apiResponse(true, 'Trajets disponibles.', [
            'trips'        => $trips->items(),
            'current_page' => $trips->currentPage(),
            'last_page'    => $trips->lastPage(),
            'total'        => $trips->total(),
        ]);
    }

    // =========================================================================
    //  GET /api/trips/{uuid}  — détail public
    // =========================================================================

    public function show(string $uuid): JsonResponse
    {
        $trip = Trip::with(['user.profile', 'vehicle.vehicleType'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->apiResponse(true, 'Détail du trajet.', ['trip' => $trip]);
    }

    // =========================================================================
    //  POST /api/trips  — création (conducteur approuvé)
    // =========================================================================

    public function store(Request $request): JsonResponse
    {
        // Délégué au DriverAddTripController — utiliser POST /api/driver/trips
        return $this->apiResponse(false, 'Utilisez POST /api/driver/trips pour créer un trajet.', [], 410);
    }

    // =========================================================================
    //  PUT /api/trips/{uuid}  — mise à jour
    // =========================================================================

    public function update(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if ($trip->isActive() || $trip->isCompleted()) {
            return $this->apiResponse(false, 'Impossible de modifier un trajet en cours ou terminé.', [], 422);
        }

        $validated = $request->validate([
            'departure_time'             => 'sometimes|date|after:now',
            'price_per_seat'             => 'sometimes|integer|min:100',
            'available_seats'            => 'sometimes|integer|min:1',
            'description'                => 'nullable|string|max:500',
            'preferences'                => 'nullable|array',
        ]);

        $trip->update($validated);

        return $this->apiResponse(true, 'Trajet mis à jour.', ['trip' => $trip->fresh()]);
    }

    // =========================================================================
    //  DELETE /api/trips/{uuid}
    // =========================================================================

    public function destroy(string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if ($trip->isActive()) {
            return $this->apiResponse(false, 'Impossible de supprimer un trajet en cours.', [], 422);
        }

        $trip->delete();

        return $this->apiResponse(true, 'Trajet supprimé.');
    }

    // =========================================================================
    //  POST /api/trips/{uuid}/start  — démarrage officiel du trajet
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/start',
        operationId: 'tripStart',
        summary: 'Démarrer un trajet',
        tags: ['🚗 Driver — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trajet démarré'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ]
    )]
    public function startTrip(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if (! $trip->isPending()) {
            return $this->apiResponse(false, 'Le trajet ne peut pas être démarré dans son état actuel.', [], 422);
        }

        $trip->update([
            'status'     => 'active',
            'started_at' => now(),
        ]);

        return $this->apiResponse(true, 'Trajet démarré.', [
            'uuid'       => $trip->uuid,
            'status'     => 'active',
            'started_at' => $trip->started_at?->toIso8601String(),
        ]);
    }

    // =========================================================================
    //  POST /api/trips/{uuid}/end  — fin de trajet
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/end',
        operationId: 'tripEnd',
        summary: 'Terminer un trajet',
        tags: ['🚗 Driver — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trajet terminé'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Trajet non actif'),
        ]
    )]
    public function endTrip(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if (! $trip->isActive()) {
            return $this->apiResponse(false, 'Le trajet n\'est pas en cours.', [], 422);
        }

        $trip->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        return $this->apiResponse(true, 'Trajet terminé.', [
            'uuid'         => $trip->uuid,
            'status'       => 'completed',
            'completed_at' => $trip->completed_at?->toIso8601String(),
        ]);
    }

    // =========================================================================
    //  POST /api/trips/{uuid}/location  — push GPS conducteur (polling ~5s)
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/location',
        operationId: 'tripUpdateLocation',
        summary: 'Mettre à jour la position GPS du conducteur',
        description: 'Appelé par le device conducteur toutes les 5–10 secondes pendant un trajet actif. Met à jour `current_latitude`, `current_longitude`, `current_speed` et `location_updated_at` sur le trajet.',
        tags: ['🚗 Driver — Trajets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['latitude', 'longitude'],
                properties: [
                    new OA\Property(property: 'latitude',  type: 'number', format: 'float', example: 7.1234),
                    new OA\Property(property: 'longitude', type: 'number', format: 'float', example: 2.3456),
                    new OA\Property(property: 'speed',     type: 'number', format: 'float', example: 72.5, nullable: true, description: 'Vitesse en km/h (optionnel)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Position enregistrée'),
            new OA\Response(response: 403, description: 'Non autorisé ou trajet non actif'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function updateLocation(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if ($trip->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        if (! $trip->isActive()) {
            return $this->apiResponse(false, 'La position ne peut être mise à jour que pendant un trajet actif.', [], 403);
        }

        $validated = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed'     => 'nullable|numeric|min:0|max:300',
        ]);

        $trip->update([
            'current_latitude'    => $validated['latitude'],
            'current_longitude'   => $validated['longitude'],
            'current_speed'       => $validated['speed'] ?? null,
            'location_updated_at' => now(),
        ]);

        return $this->apiResponse(true, 'Position mise à jour.', [
            'lat'   => (float) $validated['latitude'],
            'lng'   => (float) $validated['longitude'],
            'speed' => isset($validated['speed']) ? (float) $validated['speed'] : null,
        ]);
    }

    // =========================================================================
    //  GET /api/trips/{uuid}/tracking  — position publique (passager)
    // =========================================================================

    #[OA\Get(
        path: '/api/trips/{uuid}/tracking',
        operationId: 'tripGetTracking',
        summary: 'Position en temps réel du conducteur (accès public)',
        tags: ['👤 Passenger — Réservations'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Position du conducteur'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    public function getTracking(string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->firstOrFail();

        if (! $trip->isActive()) {
            return $this->apiResponse(false, 'Ce trajet n\'est pas en cours.', [], 422);
        }

        $staleThreshold = now()->subMinutes(2);
        $isStale = $trip->location_updated_at === null
            || $trip->location_updated_at->lt($staleThreshold);

        return $this->apiResponse(true, 'Position du conducteur.', [
            'lat'                => $trip->current_latitude,
            'lng'                => $trip->current_longitude,
            'speed_kmh'          => $trip->current_speed,
            'location_updated_at'=> $trip->location_updated_at?->toIso8601String(),
            'is_stale'           => $isStale,
            'status'             => $trip->status,
        ]);
    }
}
