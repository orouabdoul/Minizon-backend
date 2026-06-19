<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

/**
 * Gestion du cycle de vie complet des trajets de covoiturage.
 *
 * Couvre :
 *  - Recherche et consultation des trajets (public)
 *  - Publication, modification, annulation (conducteur)
 *  - Démarrage / clôture du voyage (conducteur)
 *  - Télémétrie GPS en temps réel (conducteur)
 *  - Supervision administrative (admin)
 */
class TripController extends Controller
{
    // =========================================================================
    //  ROUTES PUBLIQUES (sans token)
    // =========================================================================

    #[OA\Get(
        path: '/api/trips',
        operationId: 'tripsIndex',
        summary: 'Rechercher des trajets disponibles',
        description: 'Retourne les offres de covoiturage avec le statut `pending`. Filtrage optionnel par ville de départ, ville d\'arrivée et date.',
        tags: ['🚗 Trajets & Télémétrie'],
        parameters: [
            new OA\Parameter(name: 'departure_city', in: 'query', required: false,
                description: 'Ville de départ (recherche partielle)', schema: new OA\Schema(type: 'string', example: 'Cotonou')),
            new OA\Parameter(name: 'arrival_city', in: 'query', required: false,
                description: 'Ville d\'arrivée (recherche partielle)', schema: new OA\Schema(type: 'string', example: 'Parakou')),
            new OA\Parameter(name: 'date', in: 'query', required: false,
                description: 'Date de départ (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-07-01')),
            new OA\Parameter(name: 'min_seats', in: 'query', required: false,
                description: 'Nombre de places minimum disponibles', schema: new OA\Schema(type: 'integer', example: 2)),
            new OA\Parameter(name: 'max_price', in: 'query', required: false,
                description: 'Prix max par siège (XOF)', schema: new OA\Schema(type: 'integer', example: 5000)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                description: 'Résultats par page (défaut 15, max 50)', schema: new OA\Schema(type: 'integer', example: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false,
                description: 'Numéro de page', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des trajets correspondants',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Trajets disponibles récupérés.'),
                        new OA\Property(property: 'body',    type: 'array',   items: new OA\Items(type: 'object')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Trip::with(['user.profile', 'vehicle.vehicleType'])
            ->where('status', 'pending')
            ->where('departure_time', '>', now());

        if ($request->filled('departure_city')) {
            $query->where('departure_city', 'like', '%' . $request->departure_city . '%');
        }

        if ($request->filled('arrival_city')) {
            $query->where('arrival_city', 'like', '%' . $request->arrival_city . '%');
        }

        if ($request->filled('date')) {
            $query->whereDate('departure_time', $request->date);
        }

        if ($request->filled('min_seats')) {
            $query->where('available_seats', '>=', (int) $request->min_seats);
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_seat', '<=', (int) $request->max_price);
        }

        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));
        $trips   = $query->orderBy('departure_time')->paginate($perPage);

        return $this->apiResponse(true, 'Trajets disponibles récupérés.', $trips);
    }

    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/trips/{uuid}',
        operationId: 'tripsShow',
        summary: 'Fiche complète d\'un trajet',
        description: 'Retourne tous les détails d\'un trajet : conducteur, véhicule, type de véhicule et statut actuel.',
        tags: ['🚗 Trajets & Télémétrie'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID unique du trajet',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails du trajet récupérés avec succès'),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $trip = Trip::with(['user.profile', 'vehicle.vehicleType'])
            ->where('uuid', $uuid)
            ->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Détails du trajet récupérés.', $trip);
    }

    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/trips/{uuid}/tracking',
        operationId: 'tripsTracking',
        summary: 'Position GPS en temps réel',
        description: 'Permet à l\'application passager de récupérer les dernières coordonnées GPS du conducteur pour l\'affichage sur la carte.',
        tags: ['🚗 Trajets & Télémétrie'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coordonnées GPS récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Position récupérée.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid',              type: 'string', example: 'abc-123'),
                                new OA\Property(property: 'status',            type: 'string', example: 'active'),
                                new OA\Property(property: 'current_latitude',  type: 'number', format: 'float', example: 6.3703),
                                new OA\Property(property: 'current_longitude', type: 'number', format: 'float', example: 2.3912),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Trajet introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function getTracking(string $uuid): JsonResponse
    {
        $trip = Trip::select('uuid', 'status', 'current_latitude', 'current_longitude')
            ->where('uuid', $uuid)
            ->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Position récupérée.', $trip);
    }

    // =========================================================================
    //  ROUTES CONDUCTEUR (auth:sanctum requis)
    // =========================================================================

    #[OA\Post(
        path: '/api/trips',
        operationId: 'tripsStore',
        summary: 'Publier un trajet',
        description: 'Permet à un conducteur dont le véhicule est **certifié** (`is_approved = true`) d\'ouvrir une offre de covoiturage sur la plateforme.',
        tags: ['🚗 Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'vehicle_id', 'departure_city', 'departure_neighborhood',
                    'arrival_city', 'arrival_neighborhood',
                    'price_per_seat', 'departure_time',
                ],
                properties: [
                    new OA\Property(property: 'vehicle_id',              type: 'integer', example: 5,                          description: 'ID du véhicule certifié appartenant au conducteur'),
                    new OA\Property(property: 'departure_city',          type: 'string',  example: 'Cotonou'),
                    new OA\Property(property: 'departure_neighborhood',  type: 'string',  example: 'Fidjrossè'),
                    new OA\Property(property: 'arrival_city',            type: 'string',  example: 'Bohicon'),
                    new OA\Property(property: 'arrival_neighborhood',    type: 'string',  example: 'Carrefour Mouillage'),
                    new OA\Property(property: 'price_per_seat',          type: 'integer', example: 3500,                       description: 'Prix par siège en FCFA'),
                    new OA\Property(property: 'departure_time',          type: 'string',  format: 'date-time',                 example: '2026-07-15T07:00:00Z', description: 'Doit être une date future'),
                    new OA\Property(property: 'total_seats',             type: 'integer', example: 4,                          nullable: true, description: 'Nombre de places offertes (défaut = capacité du véhicule, max 20)'),
                    new OA\Property(property: 'description',             type: 'string',  example: 'Pas de gros bagages SVP.', nullable: true, description: 'Instructions du conducteur'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Trajet publié avec succès'),
            new OA\Response(response: 403, description: 'Véhicule non certifié ou ne vous appartient pas', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données de formulaire invalides',                 content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id'             => ['required', 'integer', 'exists:vehicles,id'],
            'departure_city'         => ['required', 'string', 'max:100'],
            'departure_neighborhood' => ['required', 'string', 'max:100'],
            'arrival_city'           => ['required', 'string', 'max:100'],
            'arrival_neighborhood'   => ['required', 'string', 'max:100'],
            'price_per_seat'         => ['required', 'integer', 'min:0'],
            'departure_time'         => ['required', 'date', 'after:now'],
            'total_seats'            => ['nullable', 'integer', 'min:1', 'max:20'],
            'description'            => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        $vehicle = Vehicle::where('id', $request->vehicle_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $vehicle || ! $vehicle->is_approved) {
            return $this->apiResponse(false, 'Véhicule invalide ou non certifié par l\'administration.', [], 403);
        }

        $totalSeats = $request->total_seats ?? $vehicle->available_seats;

        $trip = Trip::create([
            'user_id'                => $request->user()->id,
            'vehicle_id'             => $request->vehicle_id,
            'departure_city'         => $request->departure_city,
            'departure_neighborhood' => $request->departure_neighborhood,
            'arrival_city'           => $request->arrival_city,
            'arrival_neighborhood'   => $request->arrival_neighborhood,
            'price_per_seat'         => $request->price_per_seat,
            'departure_time'         => $request->departure_time,
            'total_seats'            => $totalSeats,
            'available_seats'        => $totalSeats,
            'description'            => $request->description,
            'status'                 => 'pending',
        ]);

        return $this->apiResponse(true, 'Trajet publié avec succès.', $trip->load(['vehicle.vehicleType']), 201);
    }

    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/trips/{uuid}',
        operationId: 'tripsUpdate',
        summary: 'Modifier un trajet',
        description: 'Permet au conducteur d\'ajuster le prix, l\'heure de départ ou la description. **Uniquement possible si le trajet est encore `pending`.**',
        tags: ['🚗 Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'price_per_seat', type: 'integer', example: 4000,                description: 'Nouveau prix en FCFA'),
                    new OA\Property(property: 'departure_time', type: 'string',  format: 'date-time',           example: '2026-07-15T08:00:00Z'),
                    new OA\Property(property: 'description',    type: 'string',  example: 'Bagages légers ok.', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Trajet mis à jour avec succès'),
            new OA\Response(response: 403, description: 'Ce trajet ne vous appartient pas',                          content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable',                                        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Modification impossible — trajet déjà démarré ou terminé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if ($trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Ce trajet ne vous appartient pas.', [], 403);
        }

        if ($trip->status !== 'pending') {
            return $this->apiResponse(false, 'Modification impossible : statut actuel « ' . $trip->status . ' ».', [], 422);
        }

        $validator = Validator::make($request->all(), [
            'price_per_seat' => ['nullable', 'integer', 'min:0'],
            'departure_time' => ['nullable', 'date', 'after:now'],
            'description'    => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        $trip->update($request->only(['price_per_seat', 'departure_time', 'description']));

        return $this->apiResponse(true, 'Trajet mis à jour avec succès.', $trip->fresh());
    }

    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/trips/{uuid}',
        operationId: 'tripsDestroy',
        summary: 'Annuler / Supprimer un trajet',
        description: 'Supprime définitivement un trajet. Action réservée au conducteur propriétaire.',
        tags: ['🚗 Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trajet annulé et supprimé avec succès'),
            new OA\Response(response: 403, description: 'Ce trajet ne vous appartient pas', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable',               content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if ($trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Ce trajet ne vous appartient pas.', [], 403);
        }

        $trip->delete();

        return $this->apiResponse(true, 'Trajet annulé et supprimé avec succès.');
    }

    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/driver/trips',
        operationId: 'driverTrips',
        summary: 'Mes trajets publiés (conducteur)',
        description: 'Retourne l\'historique complet des trajets publiés par le conducteur connecté, triés du plus récent au plus ancien.',
        tags: ['🚗 Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Historique des trajets récupéré'),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function driverTrips(Request $request): JsonResponse
    {
        $trips = Trip::with(['vehicle.vehicleType'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('departure_time')
            ->get();

        return $this->apiResponse(true, 'Historique des trajets récupéré.', $trips);
    }

    // =========================================================================
    //  CYCLE DE VIE DU VOYAGE (conducteur)
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/start',
        operationId: 'tripsStart',
        summary: 'Démarrer le voyage',
        description: 'Passe le statut du trajet de `pending` à `active`. Bloque les nouvelles réservations et active le suivi GPS.',
        tags: ['🚗 Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Voyage démarré — statut passé à active'),
            new OA\Response(response: 403, description: 'Ce trajet ne vous appartient pas',            content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable',                          content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Trajet non démarrable (statut incompatible)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function startTrip(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if ($trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Ce trajet ne vous appartient pas.', [], 403);
        }

        if ($trip->status !== 'pending') {
            return $this->apiResponse(false, 'Ce trajet ne peut pas être démarré (statut actuel : « ' . $trip->status . ' »).', [], 422);
        }

        $trip->update(['status' => 'active']);

        return $this->apiResponse(true, 'Bon voyage ! Le trajet est maintenant en cours.', $trip->fresh());
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/trips/{uuid}/end',
        operationId: 'tripsEnd',
        summary: 'Clôturer le trajet',
        description: 'Passe le statut du trajet de `active` à `completed`. Déclenche le calcul des évaluations.',
        tags: ['🚗 Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trajet clôturé — statut passé à completed'),
            new OA\Response(response: 403, description: 'Ce trajet ne vous appartient pas',           content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable',                         content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Trajet non clôturable (statut incompatible)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function endTrip(Request $request, string $uuid): JsonResponse
    {
        $trip = Trip::where('uuid', $uuid)->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable.', [], 404);
        }

        if ($trip->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Ce trajet ne vous appartient pas.', [], 403);
        }

        if ($trip->status !== 'active') {
            return $this->apiResponse(false, 'Ce trajet ne peut pas être clôturé (statut actuel : « ' . $trip->status . ' »).', [], 422);
        }

        $trip->update(['status' => 'completed']);

        return $this->apiResponse(true, 'Trajet clôturé avec succès. Merci pour votre service !', $trip->fresh());
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/trips/{uuid}/location',
        operationId: 'tripsLocation',
        summary: 'Télémétrie GPS — Envoyer la position',
        description: 'L\'application mobile du conducteur pousse ses coordonnées GPS en tâche de fond. Disponible uniquement sur un trajet `active`. Coordonnées au format WGS84.',
        tags: ['🚗 Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_latitude', 'current_longitude'],
                properties: [
                    new OA\Property(property: 'current_latitude',  type: 'number', format: 'float', example: 6.3703, description: 'Latitude WGS84 — entre -90 et 90'),
                    new OA\Property(property: 'current_longitude', type: 'number', format: 'float', example: 2.3912, description: 'Longitude WGS84 — entre -180 et 180'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Coordonnées GPS synchronisées avec succès'),
            new OA\Response(response: 403, description: 'Ce trajet ne vous appartient pas', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Trajet introuvable',               content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Coordonnées GPS invalides',        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function updateLocation(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'current_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Coordonnées GPS invalides.', $validator->errors(), 422);
        }

        $trip = Trip::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $trip) {
            return $this->apiResponse(false, 'Trajet introuvable ou non autorisé.', [], 404);
        }

        $trip->update([
            'current_latitude'  => $request->current_latitude,
            'current_longitude' => $request->current_longitude,
        ]);

        return $this->apiResponse(true, 'Position GPS synchronisée.');
    }

    // =========================================================================
    //  PANEL ADMINISTRATIF
    // =========================================================================

    public function adminIndex(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé. Privilèges administratifs requis.', [], 403);
        }

        $trips = Trip::with(['user.profile', 'vehicle.vehicleType'])
            ->orderByDesc('created_at')
            ->get();

        return $this->apiResponse(true, 'Supervision globale des trajets.', $trips);
    }

    // =========================================================================
    //  HELPER PRIVÉ
    // =========================================================================

    protected function apiResponse(bool $success, string $message, mixed $body = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'body'    => $body,
        ], $status);
    }
}