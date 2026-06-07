<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class TripController extends Controller
{
    // =========================================================================
    //  INDEX — Rechercher des trajets disponibles
    // =========================================================================

    #[OA\Get(
        path: '/api/trips',
        summary: 'Rechercher des trajets disponibles',
        description: <<<DESC
        Retourne la liste des offres de covoiturage dont le statut est `pending` (ouvertes à la réservation).
        Les résultats peuvent être filtrés par ville de départ, ville d\'arrivée et/ou date de départ.
        Endpoint **public** — aucune authentification requise.
        DESC,
        tags: ['Trajets & Télémétrie'],
        parameters: [
            new OA\Parameter(
                name: 'departure_city',
                in: 'query',
                required: false,
                description: 'Filtre partiel sur la ville de départ (insensible à la casse).',
                schema: new OA\Schema(type: 'string', example: 'Cotonou')
            ),
            new OA\Parameter(
                name: 'arrival_city',
                in: 'query',
                required: false,
                description: 'Filtre partiel sur la ville d\'arrivée.',
                schema: new OA\Schema(type: 'string', example: 'Parakou')
            ),
            new OA\Parameter(
                name: 'date',
                in: 'query',
                required: false,
                description: 'Filtre sur la date de départ au format `YYYY-MM-DD`.',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2026-06-15')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des trajets correspondant aux critères.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Trip')
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request)
    {
        $query = Trip::with(['user.profile', 'vehicle'])->where('status', 'pending');

        if ($request->filled('departure_city')) {
            $query->where('departure_city', 'like', '%' . $request->departure_city . '%');
        }

        if ($request->filled('arrival_city')) {
            $query->where('arrival_city', 'like', '%' . $request->arrival_city . '%');
        }

        // ✅ CORRECTION : le filtre "date" était déclaré dans Swagger mais jamais appliqué en base.
        if ($request->filled('date')) {
            $query->whereDate('departure_time', $request->date);
        }

        return response()->json([
            'success' => true,
            'body'    => $query->orderBy('departure_time')->get(),
        ]);
    }

    // =========================================================================
    //  STORE — Publier un trajet
    // =========================================================================

    #[OA\Post(
        path: '/api/trips',
        summary: 'Publier une offre de covoiturage',
        description: <<<DESC
        Permet à un conducteur authentifié et vérifié de créer une offre de trajet.
        **Prérequis** :
        - Le véhicule doit appartenir au conducteur connecté.
        - Le véhicule doit avoir été approuvé (`is_approved = true`) par un administrateur.
        - L\'heure de départ doit être dans le futur.
        Le trajet est créé avec le statut `pending`.
        DESC,
        tags: ['Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Données du trajet à publier.',
            content: new OA\JsonContent(
                required: [
                    'vehicle_id', 'departure_city', 'departure_neighborhood',
                    'arrival_city', 'arrival_neighborhood', 'price_per_seat', 'departure_time',
                ],
                properties: [
                    new OA\Property(property: 'vehicle_id',              type: 'integer', example: 5,                        description: 'ID du véhicule approuvé appartenant au conducteur'),
                    new OA\Property(property: 'departure_city',          type: 'string',  example: 'Cotonou',                description: 'Ville de départ'),
                    new OA\Property(property: 'departure_neighborhood',  type: 'string',  example: 'Fidjrossè',              description: 'Quartier / point de départ précis'),
                    new OA\Property(property: 'arrival_city',            type: 'string',  example: 'Bohicon',                description: 'Ville d\'arrivée'),
                    new OA\Property(property: 'arrival_neighborhood',    type: 'string',  example: 'Carrefour Mouillage',    description: 'Quartier / point d\'arrivée précis'),
                    new OA\Property(property: 'price_per_seat',          type: 'integer', example: 3500,                    description: 'Prix par place en FCFA (min 0)'),
                    new OA\Property(property: 'departure_time',          type: 'string',  format: 'date-time', example: '2026-06-15T07:00:00Z', description: 'Date et heure de départ (doit être dans le futur)'),
                    new OA\Property(property: 'description',             type: 'string',  example: 'Pas de gros bagages.',   description: 'Instructions ou commentaires pour les passagers (optionnel)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Trajet publié avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'body',    ref: '#/components/schemas/Trip'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 403, description: 'Véhicule invalide, non certifié ou n\'appartenant pas au conducteur.'),
            new OA\Response(response: 422, description: 'Données de saisie invalides.'),
        ]
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id'             => 'required|integer|exists:vehicles,id',
            'departure_city'         => 'required|string',
            'departure_neighborhood' => 'required|string',
            'arrival_city'           => 'required|string',
            'arrival_neighborhood'   => 'required|string',
            'price_per_seat'         => 'required|integer|min:0',
            'departure_time'         => 'required|date|after:now',
            'description'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données de saisie invalides.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $vehicle = Vehicle::where('id', $request->vehicle_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $vehicle || ! $vehicle->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Véhicule invalide ou non certifié par l\'administration.',
            ], 403);
        }

        $trip = Trip::create(array_merge($validator->validated(), [
            'user_id' => $request->user()->id,
            'status'  => 'pending',
        ]));

        return response()->json([
            'success' => true,
            'body'    => $trip->load(['vehicle', 'user.profile']),
        ], 201);
    }

    // =========================================================================
    //  SHOW — Consulter un trajet
    // =========================================================================

    #[OA\Get(
        path: '/api/trips/{uuid}',
        summary: 'Consulter la fiche complète d\'un trajet',
        description: <<<DESC
        Retourne toutes les informations d\'un trajet identifié par son UUID,
        incluant le profil du conducteur et le détail du véhicule.
        Endpoint **public** — aucune authentification requise.
        DESC,
        tags: ['Trajets & Télémétrie'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID unique du trajet.',
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détails du trajet retournés.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'body',    ref: '#/components/schemas/Trip'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Trajet introuvable.'),
        ]
    )]
    public function show(Request $request, $uuid)
    {
        $trip = Trip::with(['user.profile', 'vehicle.vehicleType'])->where('uuid', $uuid)->first();

        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trajet introuvable.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'body'    => $trip,
        ]);
    }

    // =========================================================================
    //  UPDATE — Modifier un trajet
    // =========================================================================

    #[OA\Put(
        path: '/api/trips/{uuid}',
        summary: 'Modifier les conditions d\'un trajet',
        description: <<<DESC
        Permet au conducteur propriétaire d\'ajuster l\'heure de départ, le prix ou la description.
        **Conditions** :
        - Le trajet doit être dans le statut `pending` (non encore démarré).
        - Seul le conducteur auteur du trajet peut effectuer cette modification.
        DESC,
        tags: ['Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID unique du trajet.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Champs modifiables (au moins un requis).',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'price_per_seat',  type: 'integer', example: 4000,                    description: 'Nouveau prix par place en FCFA (min 0)'),
                    new OA\Property(property: 'departure_time',  type: 'string',  format: 'date-time', example: '2026-06-16T08:00:00Z', description: 'Nouvelle heure de départ (doit être dans le futur)'),
                    new OA\Property(property: 'description',     type: 'string',  example: 'Bagages légers uniquement.', description: 'Message mis à jour pour les passagers'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Trajet mis à jour avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'body',    ref: '#/components/schemas/Trip'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 422, description: 'Modification impossible (statut incorrect, trajet introuvable ou données invalides).'),
        ]
    )]
    public function update(Request $request, $uuid)
    {
        $trip = Trip::where('uuid', $uuid)->where('user_id', $request->user()->id)->first();

        if (! $trip || $trip->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Modification impossible : le trajet est introuvable ou n\'est plus modifiable.',
            ], 422);
        }

        // ✅ CORRECTION : validation ajoutée avant la mise à jour.
        //    L'original appelait $trip->update() sans valider les entrées,
        //    ce qui permettait d'injecter n'importe quel champ (ex: status, user_id).
        $validator = Validator::make($request->all(), [
            'price_per_seat' => 'nullable|integer|min:0',
            'departure_time' => 'nullable|date|after:now',
            'description'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $trip->update($validator->validated());

        return response()->json([
            'success' => true,
            'body'    => $trip->fresh(),
        ]);
    }

    // =========================================================================
    //  DESTROY — Annuler / Supprimer un trajet
    // =========================================================================

    #[OA\Delete(
        path: '/api/trips/{uuid}',
        summary: 'Annuler une offre de trajet',
        description: <<<DESC
        Supprime (annule) définitivement un trajet.
        Seul le conducteur auteur du trajet ou un administrateur peuvent effectuer cette action.
        DESC,
        tags: ['Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID unique du trajet.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Trajet annulé et supprimé avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Trajet supprimé avec succès.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 403, description: 'Action interdite — vous n\'êtes pas l\'auteur de ce trajet.'),
            new OA\Response(response: 404, description: 'Trajet introuvable.'),
        ]
    )]
    public function destroy(Request $request, $uuid)
    {
        // ✅ CORRECTION : ajout du cloisonnement admin.
        //    L'original ne permettait qu'au conducteur propriétaire de supprimer.
        //    Un admin doit aussi pouvoir supprimer n'importe quel trajet.
        $user = $request->user();

        $query = Trip::where('uuid', $uuid);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $trip = $query->first();

        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trajet introuvable ou accès non autorisé.',
            ], 404);
        }

        $trip->delete();

        return response()->json([
            'success' => true,
            'message' => 'Trajet supprimé avec succès.',
        ]);
    }

    // =========================================================================
    //  DRIVER TRIPS — Historique conducteur
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/trips',
        summary: 'Historique des trajets du conducteur connecté',
        description: 'Retourne tous les trajets créés par le conducteur authentifié, triés du plus récent au plus ancien.',
        tags: ['Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Historique récupéré avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Trip')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
        ]
    )]
    public function driverTrips(Request $request)
    {
        $trips = Trip::where('user_id', $request->user()->id)
            ->orderBy('departure_time', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'body'    => $trips,
        ]);
    }

    // =========================================================================
    //  ADMIN INDEX — Supervision globale [ADMIN]
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/trips',
        summary: '[ADMIN] Supervision de tous les covoiturages',
        description: <<<DESC
        **Accès restreint aux administrateurs.**
        Retourne l\'intégralité des trajets enregistrés sur la plateforme,
        tous statuts confondus (`pending`, `active`, `completed`), avec le profil du conducteur.
        DESC,
        tags: ['Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste complète des trajets retournée.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Trip')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 403, description: 'Accès refusé — privilèges administrateur requis.'),
        ]
    )]
    public function adminIndex(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'body'    => Trip::with('user.profile')->orderBy('created_at', 'desc')->get(),
        ]);
    }

    // =========================================================================
    //  START TRIP — Démarrer le voyage
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/start',
        summary: 'Démarrer le voyage',
        description: <<<DESC
        Passe le statut du trajet de `pending` à `active`.
        À partir de ce moment, **aucune nouvelle réservation n\'est acceptée** sur ce trajet.
        Seul le conducteur auteur du trajet peut effectuer cette action.
        DESC,
        tags: ['Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID unique du trajet.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Voyage démarré avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Bon voyage ! Le trajet est maintenant en cours.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 404, description: 'Trajet introuvable.'),
            new OA\Response(response: 422, description: 'Le trajet n\'est pas dans un état démarrable (doit être `pending`).'),
        ]
    )]
    public function startTrip(Request $request, $uuid)
    {
        $trip = Trip::where('uuid', $uuid)->where('user_id', $request->user()->id)->first();

        if (! $trip) {
            return response()->json(['success' => false, 'message' => 'Trajet introuvable.'], 404);
        }

        // ✅ CORRECTION : vérification du statut avant transition.
        //    L'original permettait de "démarrer" un trajet déjà actif ou terminé.
        if ($trip->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Ce trajet ne peut pas être démarré (statut actuel : ' . $trip->status . ').',
            ], 422);
        }

        $trip->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Bon voyage ! Le trajet est maintenant en cours.',
        ]);
    }

    // =========================================================================
    //  END TRIP — Clôturer le trajet
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/end',
        summary: 'Clôturer le trajet (arrivée à destination)',
        description: <<<DESC
        Passe le statut du trajet de `active` à `completed`.
        Cette action est irréversible. Elle déclenche la période d\'évaluation mutuelle
        entre le conducteur et les passagers.
        DESC,
        tags: ['Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID unique du trajet.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Trajet clôturé avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Trajet clôturé avec succès.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 404, description: 'Trajet introuvable.'),
            new OA\Response(response: 422, description: 'Le trajet n\'est pas dans un état clôturable (doit être `active`).'),
        ]
    )]
    public function endTrip(Request $request, $uuid)
    {
        $trip = Trip::where('uuid', $uuid)->where('user_id', $request->user()->id)->first();

        if (! $trip) {
            return response()->json(['success' => false, 'message' => 'Trajet introuvable.'], 404);
        }

        // ✅ CORRECTION : vérification du statut avant transition.
        //    L'original permettait de clôturer un trajet en `pending` ou déjà `completed`.
        if ($trip->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Ce trajet ne peut pas être clôturé (statut actuel : ' . $trip->status . ').',
            ], 422);
        }

        $trip->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'Trajet clôturé avec succès.',
        ]);
    }

    // =========================================================================
    //  UPDATE LOCATION — Télémétrie GPS
    // =========================================================================

    #[OA\Post(
        path: '/api/trips/{uuid}/location',
        summary: 'Envoyer les coordonnées GPS en temps réel',
        description: <<<DESC
        Permet à l\'application mobile du conducteur de pousser sa position géographique
        en tâche de fond pendant un trajet `active`.
        Ces données sont utilisées par les passagers pour le suivi en temps réel sur la carte.
        DESC,
        tags: ['Trajets & Télémétrie'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID unique du trajet.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Coordonnées géographiques actuelles du conducteur.',
            content: new OA\JsonContent(
                required: ['current_latitude', 'current_longitude'],
                properties: [
                    new OA\Property(property: 'current_latitude',  type: 'number', format: 'float', example: 6.3703,  description: 'Latitude en degrés décimaux (WGS 84)'),
                    new OA\Property(property: 'current_longitude', type: 'number', format: 'float', example: 2.3912,  description: 'Longitude en degrés décimaux (WGS 84)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coordonnées GPS synchronisées avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Position mise à jour.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 404, description: 'Trajet introuvable.'),
            new OA\Response(response: 422, description: 'Coordonnées invalides ou manquantes.'),
        ]
    )]
    public function updateLocation(Request $request, $uuid)
    {
        $validator = Validator::make($request->all(), [
            'current_latitude'  => 'required|numeric|between:-90,90',
            'current_longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Coordonnées GPS invalides.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $trip = Trip::where('uuid', $uuid)->where('user_id', $request->user()->id)->first();

        if (! $trip) {
            return response()->json(['success' => false, 'message' => 'Trajet introuvable.'], 404);
        }

        $trip->update([
            'current_latitude'  => $request->current_latitude,
            'current_longitude' => $request->current_longitude,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Position mise à jour.',
        ]);
    }

    // =========================================================================
    //  GET TRACKING — Récupérer la position live
    // =========================================================================

    #[OA\Get(
        path: '/api/trips/{uuid}/tracking',
        summary: 'Récupérer la position GPS live du conducteur',
        description: <<<DESC
        Retourne les dernières coordonnées GPS du conducteur pour un trajet donné.
        À interroger en polling côté client (app passager) pour actualiser la carte en temps réel.
        Endpoint **public** — aucune authentification requise pour permettre l\'accès aux passagers non inscrits.
        DESC,
        tags: ['Trajets & Télémétrie'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID unique du trajet.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données de suivi retournées.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid',               type: 'string', format: 'uuid',  example: '550e8400-e29b-41d4-a716-446655440000'),
                                new OA\Property(property: 'status',             type: 'string', example: 'active',  description: 'Statut actuel du trajet'),
                                new OA\Property(property: 'current_latitude',   type: 'number', format: 'float', example: 6.4281),
                                new OA\Property(property: 'current_longitude',  type: 'number', format: 'float', example: 2.3580),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Trajet introuvable.'),
        ]
    )]
    public function getTracking(Request $request, $uuid)
    {
        $trip = Trip::select('uuid', 'status', 'current_latitude', 'current_longitude')
            ->where('uuid', $uuid)
            ->first();

        if (! $trip) {
            return response()->json(['success' => false, 'message' => 'Trajet introuvable.'], 404);
        }

        return response()->json([
            'success' => true,
            'body'    => $trip,
        ]);
    }
}