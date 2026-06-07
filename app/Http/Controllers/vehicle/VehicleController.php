<?php

namespace App\Http\Controllers\Vehicle;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class VehicleController extends Controller
{
    // =========================================================================
    //  INDEX — Lister les véhicules
    // =========================================================================

    #[OA\Get(
        path: '/api/vehicles',
        summary: 'Lister les véhicules',
        description: <<<DESC
        Retourne la liste des véhicules selon le rôle de l'utilisateur connecté :
        - **Conducteur** : uniquement ses propres véhicules.
        - **Administrateur** : l'intégralité de la flotte avec les informations du propriétaire.
        DESC,
        tags: ['Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des véhicules récupérée avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Liste des véhicules récupérée avec succès.'),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Vehicle')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            $vehicles = Vehicle::with(['user.profile', 'vehicleType'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $vehicles = Vehicle::with('vehicleType')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des véhicules récupérée avec succès.',
            'body'    => $vehicles,
        ]);
    }

    // =========================================================================
    //  STORE — Enregistrer un nouveau véhicule
    // =========================================================================

    #[OA\Post(
        path: '/api/vehicles',
        summary: 'Soumettre un nouveau véhicule',
        description: <<<DESC
        Permet à un conducteur connecté d'enregistrer un véhicule.
        Le véhicule est créé avec `is_approved = false` et devra être validé par un administrateur avant d'être utilisable sur un trajet.
        DESC,
        tags: ['Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Informations du véhicule et chemins des documents justificatifs.',
            content: new OA\JsonContent(
                required: [
                    'vehicle_type_id', 'brand', 'model', 'color',
                    'available_seats', 'license_plate', 'vehicle_photo',
                    'registration_doc', 'insurance_doc', 'tvm_doc',
                ],
                properties: [
                    new OA\Property(property: 'vehicle_type_id',       type: 'integer', example: 1,                                    description: 'ID du type de véhicule (ref. table vehicle_types)'),
                    new OA\Property(property: 'brand',                 type: 'string',  example: 'Toyota',                             description: 'Marque du véhicule (max 100 caractères)'),
                    new OA\Property(property: 'model',                 type: 'string',  example: 'Camry',                              description: 'Modèle du véhicule (max 100 caractères)'),
                    new OA\Property(property: 'color',                 type: 'string',  example: 'Gris Étoile',                        description: 'Couleur principale (max 50 caractères)'),
                    new OA\Property(property: 'available_seats',       type: 'integer', example: 4,                                    description: 'Nombre de places passagers disponibles (min 1)'),
                    new OA\Property(property: 'license_plate',         type: 'string',  example: 'RB 1234 X',                          description: 'Numéro d\'immatriculation unique (max 50 caractères)'),
                    new OA\Property(property: 'vehicle_photo',         type: 'string',  example: 'uploads/vehicles/photo.png',         description: 'Chemin relatif de la photo du véhicule'),
                    new OA\Property(property: 'registration_doc',      type: 'string',  example: 'uploads/docs/carte_grise.pdf',       description: 'Chemin relatif de la carte grise'),
                    new OA\Property(property: 'insurance_doc',         type: 'string',  example: 'uploads/docs/assurance.pdf',         description: 'Chemin relatif de l\'attestation d\'assurance'),
                    new OA\Property(property: 'tvm_doc',               type: 'string',  example: 'uploads/docs/tvm.pdf',               description: 'Chemin relatif du document TVM'),
                    new OA\Property(property: 'technical_control_doc', type: 'string',  example: 'uploads/docs/ct.pdf',                description: 'Chemin relatif du contrôle technique (optionnel)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Véhicule enregistré, en attente de validation administrative.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Véhicule enregistré. En attente de vérification de vos documents par l\'équipe.'),
                        new OA\Property(property: 'body',    ref: '#/components/schemas/Vehicle'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Erreur de validation (champ manquant, immatriculation déjà existante, etc.).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string',  example: 'Erreur de validation des données.'),
                        new OA\Property(property: 'body',    type: 'object',  description: 'Map des erreurs par champ'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
        ]
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_type_id'       => 'required|integer|exists:vehicle_types,id',
            'brand'                 => 'required|string|max:100',
            'model'                 => 'required|string|max:100',
            'color'                 => 'required|string|max:50',
            'available_seats'       => 'required|integer|min:1',
            'license_plate'         => 'required|string|max:50|unique:vehicles,license_plate',
            'vehicle_photo'         => 'required|string',
            'registration_doc'      => 'required|string',
            'insurance_doc'         => 'required|string',
            'tvm_doc'               => 'required|string',
            'technical_control_doc' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'body'    => $validator->errors(),
            ], 422);
        }

        // ✅ CORRECTION : utiliser validated() plutôt que $request->all()
        //    pour n'insérer que les champs explicitement validés,
        //    et éviter toute injection de champ (ex: is_approved, user_id).
        $vehicle = Vehicle::create(array_merge($validator->validated(), [
            'user_id'     => $request->user()->id,
            'is_approved' => false,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Véhicule enregistré. En attente de vérification de vos documents par l\'équipe.',
            'body'    => $vehicle,
        ], 201);
    }

    // =========================================================================
    //  SHOW — Détails d'un véhicule
    // =========================================================================

    #[OA\Get(
        path: '/api/vehicles/{id}',
        summary: 'Consulter la fiche d\'un véhicule',
        description: <<<DESC
        Retourne les détails complets d'un véhicule.
        **Cloisonnement strict** : un conducteur ne peut accéder qu'à ses propres véhicules.
        Un administrateur peut accéder à n'importe quel véhicule.
        DESC,
        tags: ['Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant numérique du véhicule.',
                schema: new OA\Schema(type: 'integer', example: 12)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fiche du véhicule retournée avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Détails du véhicule récupérés.'),
                        new OA\Property(property: 'body',    ref: '#/components/schemas/Vehicle'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 403, description: 'Accès interdit — ce véhicule ne vous appartient pas.'),
            new OA\Response(response: 404, description: 'Véhicule introuvable.'),
        ]
    )]
    public function show(Request $request, $id)
    {
        $vehicle = Vehicle::with('vehicleType')->find($id);

        if (! $vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Véhicule introuvable.',
                'body'    => [],
            ], 404);
        }

        if ($request->user()->role !== 'admin' && $vehicle->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès interdit. Ce véhicule ne vous appartient pas.',
                'body'    => [],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails du véhicule récupérés.',
            'body'    => $vehicle,
        ]);
    }

    // =========================================================================
    //  UPDATE — Modifier un véhicule
    // =========================================================================

    #[OA\Put(
        path: '/api/vehicles/{id}',
        summary: 'Modifier les informations d\'un véhicule',
        description: <<<DESC
        Permet au propriétaire du véhicule (ou à un administrateur) de mettre à jour
        certaines caractéristiques. Seuls `brand`, `model`, `color` et `available_seats`
        sont modifiables ici ; les documents officiels nécessitent une procédure dédiée.
        DESC,
        tags: ['Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant numérique du véhicule.',
                schema: new OA\Schema(type: 'integer', example: 12)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Champs à mettre à jour (tous optionnels, au moins un attendu).',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'brand',           type: 'string',  example: 'Honda',  description: 'Nouvelle marque'),
                    new OA\Property(property: 'model',           type: 'string',  example: 'Civic',  description: 'Nouveau modèle'),
                    new OA\Property(property: 'color',           type: 'string',  example: 'Rouge',  description: 'Nouvelle couleur'),
                    new OA\Property(property: 'available_seats', type: 'integer', example: 3,        description: 'Nouveau nombre de places (min 1)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Véhicule mis à jour avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Informations du véhicule mises à jour.'),
                        new OA\Property(property: 'body',    ref: '#/components/schemas/Vehicle'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 403, description: 'Action non autorisée.'),
            new OA\Response(response: 404, description: 'Véhicule introuvable.'),
            new OA\Response(response: 422, description: 'Données de mise à jour invalides.'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Véhicule introuvable.',
                'body'    => [],
            ], 404);
        }

        if ($vehicle->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.',
                'body'    => [],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'brand'           => 'nullable|string|max:100',
            'model'           => 'nullable|string|max:100',
            'color'           => 'nullable|string|max:50',
            'available_seats' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'body'    => $validator->errors(),
            ], 422);
        }

        // ✅ CORRECTION : utiliser only() sur les données validées (pas sur $request directement)
        $vehicle->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Informations du véhicule mises à jour.',
            'body'    => $vehicle,
        ]);
    }

    // =========================================================================
    //  DESTROY — Supprimer un véhicule
    // =========================================================================

    #[OA\Delete(
        path: '/api/vehicles/{id}',
        summary: 'Supprimer un véhicule du parc',
        description: <<<DESC
        Supprime définitivement un véhicule de la flotte.
        **Bloqué** si le véhicule est rattaché à un trajet dont le statut est `pending` ou `active`.
        Cette protection évite de laisser des passagers sans véhicule assigné.
        DESC,
        tags: ['Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant numérique du véhicule.',
                schema: new OA\Schema(type: 'integer', example: 12)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Véhicule supprimé avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Véhicule supprimé de votre flotte avec succès.'),
                        new OA\Property(property: 'body',    type: 'array',   example: []),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 403, description: 'Action interdite.'),
            new OA\Response(response: 404, description: 'Véhicule introuvable.'),
            new OA\Response(
                response: 422,
                description: 'Suppression impossible : le véhicule est lié à un trajet actif ou en attente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string',  example: 'Action impossible : Ce véhicule est actuellement rattaché à un trajet en attente ou en cours de route.'),
                        new OA\Property(property: 'body',    type: 'array',   example: []),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Request $request, $id)
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Véhicule introuvable.',
                'body'    => [],
            ], 404);
        }

        if ($vehicle->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Action interdite.',
                'body'    => [],
            ], 403);
        }

        $hasActiveTrips = Trip::where('vehicle_id', $id)
            ->whereIn('status', ['pending', 'active'])
            ->exists();

        if ($hasActiveTrips) {
            return response()->json([
                'success' => false,
                'message' => 'Action impossible : Ce véhicule est actuellement rattaché à un trajet en attente ou en cours de route.',
                'body'    => [],
            ], 422);
        }

        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Véhicule supprimé de votre flotte avec succès.',
            'body'    => [],
        ]);
    }

    // =========================================================================
    //  TOGGLE APPROVAL — Approuver / Bloquer un véhicule [ADMIN]
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/vehicles/{id}/approve',
        summary: '[ADMIN] Approuver ou bloquer un véhicule',
        description: <<<DESC
        **Accès restreint aux administrateurs.**
        Permet de valider ou de geler l'autorisation de circulation d'un véhicule
        après vérification des documents soumis (carte grise, assurance, TVM, etc.).
        - `is_approved: true`  → le conducteur peut créer des trajets avec ce véhicule.
        - `is_approved: false` → le véhicule est suspendu, aucun nouveau trajet possible.
        DESC,
        tags: ['Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant numérique du véhicule à traiter.',
                schema: new OA\Schema(type: 'integer', example: 7)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['is_approved'],
                properties: [
                    new OA\Property(
                        property: 'is_approved',
                        type: 'boolean',
                        example: true,
                        description: '`true` pour approuver, `false` pour bloquer/suspendre.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statut du véhicule mis à jour.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Le statut du véhicule a été mis à jour : approuvé pour le service.'),
                        new OA\Property(property: 'body',    ref: '#/components/schemas/Vehicle'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié.'),
            new OA\Response(response: 403, description: 'Privilèges insuffisants — réservé aux administrateurs.'),
            new OA\Response(response: 404, description: 'Véhicule introuvable.'),
            new OA\Response(response: 422, description: 'Valeur d\'approbation invalide.'),
        ]
    )]
    public function toggleApproval(Request $request, $id)
    {
        // ✅ CORRECTION : le guard de rôle en premier, puis validation, puis find du modèle.
        //    L'ordre original faisait la validation avant le find, ce qui est illogique
        //    et pouvait exposer un message d'erreur différent selon l'input.

        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Privilèges insuffisants.',
                'body'    => [],
            ], 403);
        }

        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Véhicule introuvable.',
                'body'    => [],
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'is_approved' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Valeur d\'approbation erronée.',
                'body'    => $validator->errors(),
            ], 422);
        }

        $vehicle->update(['is_approved' => $request->boolean('is_approved')]);

        $status = $request->boolean('is_approved') ? 'approuvé pour le service' : 'bloqué/suspendu';

        return response()->json([
            'success' => true,
            'message' => "Le statut du véhicule a été mis à jour : {$status}.",
            'body'    => $vehicle->fresh(),
        ]);
    }
}