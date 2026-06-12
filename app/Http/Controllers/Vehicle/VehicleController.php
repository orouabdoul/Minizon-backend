<?php

namespace App\Http\Controllers\Vehicle;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

/**
 * Gestion de la flotte de véhicules Minizon.
 *
 * Couvre :
 *  - Consultation et gestion du parc (conducteur / admin)
 *  - Soumission et modification des fiches véhicule
 *  - Certification administrative (admin)
 */
class VehicleController extends Controller
{
    // =========================================================================
    //  CONSULTATION (conducteur + admin)
    // =========================================================================

    #[OA\Get(
        path: '/api/vehicles',
        operationId: 'vehiclesIndex',
        summary: 'Lister les véhicules',
        description: 'Un **conducteur** ne voit que ses propres véhicules. Un **administrateur** accède à la flotte globale avec les informations du propriétaire.',
        tags: ['🚙 Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des véhicules récupérée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Liste des véhicules récupérée.'),
                        new OA\Property(property: 'body',    type: 'array',   items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $vehicles = Vehicle::with(['user.profile', 'vehicleType'])
                ->orderByDesc('created_at')
                ->get();
        } else {
            $vehicles = Vehicle::with('vehicleType')
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get();
        }

        return $this->apiResponse(true, 'Liste des véhicules récupérée.', $vehicles);
    }

    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/vehicles/{id}',
        operationId: 'vehiclesShow',
        summary: 'Détails d\'un véhicule',
        description: 'Retourne la fiche complète d\'un véhicule. **Cloisonnement strict** : un conducteur ne peut pas consulter le véhicule d\'un tiers. Un admin peut tout voir.',
        tags: ['🚙 Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant numérique du véhicule',
                schema: new OA\Schema(type: 'integer', example: 12)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fiche véhicule récupérée avec succès'),
            new OA\Response(response: 403, description: 'Ce véhicule ne vous appartient pas',  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Véhicule introuvable',                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::with('vehicleType')->find($id);

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        if (! $request->user()->isAdmin() && $vehicle->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Accès interdit. Ce véhicule ne vous appartient pas.', [], 403);
        }

        return $this->apiResponse(true, 'Fiche véhicule récupérée.', $vehicle);
    }

    // =========================================================================
    //  CRÉATION & MODIFICATION (conducteur)
    // =========================================================================

    #[OA\Post(
        path: '/api/vehicles',
        operationId: 'vehiclesStore',
        summary: 'Soumettre un nouveau véhicule',
        description: 'Permet à un conducteur connecté de soumettre un véhicule pour certification. Le véhicule est créé avec `is_approved = false` et doit être validé par un administrateur avant d\'être utilisable.',
        tags: ['🚙 Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'vehicle_type_id', 'brand', 'model', 'color',
                    'available_seats', 'license_plate',
                    'vehicle_photo', 'registration_doc', 'insurance_doc', 'tvm_doc',
                ],
                properties: [
                    new OA\Property(property: 'vehicle_type_id',       type: 'integer', example: 1,                              description: 'ID du type de véhicule (voir table vehicle_types)'),
                    new OA\Property(property: 'brand',                 type: 'string',  example: 'Toyota'),
                    new OA\Property(property: 'model',                 type: 'string',  example: 'Camry'),
                    new OA\Property(property: 'color',                 type: 'string',  example: 'Gris Étoile'),
                    new OA\Property(property: 'available_seats',       type: 'integer', example: 4,                              description: 'Nombre de places disponibles (hors conducteur)'),
                    new OA\Property(property: 'license_plate',         type: 'string',  example: 'RB 1234 X',                    description: 'Immatriculation unique'),
                    new OA\Property(property: 'vehicle_photo',         type: 'string',  example: 'uploads/vehicles/photo.png',   description: 'Chemin de la photo du véhicule'),
                    new OA\Property(property: 'registration_doc',      type: 'string',  example: 'uploads/docs/carte_grise.pdf', description: 'Carte grise'),
                    new OA\Property(property: 'insurance_doc',         type: 'string',  example: 'uploads/docs/assurance.pdf',   description: 'Attestation d\'assurance'),
                    new OA\Property(property: 'tvm_doc',               type: 'string',  example: 'uploads/docs/tvm.pdf',         description: 'TVM (Taxe sur les Véhicules à Moteur)'),
                    new OA\Property(property: 'technical_control_doc', type: 'string',  example: 'uploads/docs/ct.pdf',          description: 'Visite technique (optionnel)', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Véhicule soumis — en attente de certification admin'),
            new OA\Response(response: 422, description: 'Données invalides ou immatriculation déjà existante', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_type_id'       => ['required', 'integer', 'exists:vehicle_types,id'],
            'brand'                 => ['required', 'string', 'max:100'],
            'model'                 => ['required', 'string', 'max:100'],
            'color'                 => ['required', 'string', 'max:50'],
            'available_seats'       => ['required', 'integer', 'min:1'],
            'license_plate'         => ['required', 'string', 'max:50', 'unique:vehicles,license_plate'],
            'vehicle_photo'         => ['required', 'string'],
            'registration_doc'      => ['required', 'string'],
            'insurance_doc'         => ['required', 'string'],
            'tvm_doc'               => ['required', 'string'],
            'technical_control_doc' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Erreur de validation des données.', $validator->errors(), 422);
        }

        // Vérification du rôle — seul un conducteur peut soumettre un véhicule
        if (! $request->user()->isDriver() && ! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux conducteurs.', [], 403);
        }

        $vehicle = Vehicle::create([
            'user_id'               => $request->user()->id,
            'vehicle_type_id'       => $request->vehicle_type_id,
            'brand'                 => ucfirst(strtolower($request->brand)),
            'model'                 => ucfirst(strtolower($request->model)),
            'color'                 => ucfirst(strtolower($request->color)),
            'available_seats'       => $request->available_seats,
            'license_plate'         => strtoupper($request->license_plate),
            'vehicle_photo'         => $request->vehicle_photo,
            'registration_doc'      => $request->registration_doc,
            'insurance_doc'         => $request->insurance_doc,
            'tvm_doc'               => $request->tvm_doc,
            'technical_control_doc' => $request->technical_control_doc,
            'is_approved'           => false,
        ]);

        return $this->apiResponse(true, 'Véhicule enregistré. En attente de vérification par l\'équipe.', $vehicle->load('vehicleType'), 201);
    }

    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/vehicles/{id}',
        operationId: 'vehiclesUpdate',
        summary: 'Modifier les spécifications d\'un véhicule',
        description: 'Met à jour les informations modifiables d\'un véhicule (marque, modèle, couleur, places). Un conducteur ne peut modifier que ses propres véhicules.',
        tags: ['🚙 Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 12)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'brand',           type: 'string',  example: 'Honda',  nullable: true),
                    new OA\Property(property: 'model',           type: 'string',  example: 'Civic',  nullable: true),
                    new OA\Property(property: 'color',           type: 'string',  example: 'Rouge',  nullable: true),
                    new OA\Property(property: 'available_seats', type: 'integer', example: 3,        nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Informations du véhicule mises à jour'),
            new OA\Response(response: 403, description: 'Action non autorisée',  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Véhicule introuvable',  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides',     content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        if ($vehicle->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action non autorisée.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'brand'           => ['nullable', 'string', 'max:100'],
            'model'           => ['nullable', 'string', 'max:100'],
            'color'           => ['nullable', 'string', 'max:50'],
            'available_seats' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        $vehicle->update(array_filter([
            'brand'           => $request->brand           ? ucfirst(strtolower($request->brand))  : null,
            'model'           => $request->model           ? ucfirst(strtolower($request->model))  : null,
            'color'           => $request->color           ? ucfirst(strtolower($request->color))  : null,
            'available_seats' => $request->available_seats ?? null,
        ]));

        return $this->apiResponse(true, 'Informations du véhicule mises à jour.', $vehicle->fresh()->load('vehicleType'));
    }

    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/vehicles/{id}',
        operationId: 'vehiclesDestroy',
        summary: 'Retirer un véhicule du parc',
        description: 'Supprime définitivement un véhicule. **Bloqué** si le véhicule est rattaché à un trajet `pending` ou `active`.',
        tags: ['🚙 Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 12)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Véhicule supprimé de la flotte avec succès'),
            new OA\Response(response: 403, description: 'Action interdite',                                       content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Véhicule introuvable',                                   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Suppression impossible — véhicule sur un trajet actif', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        if ($vehicle->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action interdite.', [], 403);
        }

        // Blocage si un trajet en cours ou en attente utilise ce véhicule
        $hasActiveTrips = Trip::where('vehicle_id', $id)
            ->whereIn('status', ['pending', 'active'])
            ->exists();

        if ($hasActiveTrips) {
            return $this->apiResponse(
                false,
                'Action impossible : ce véhicule est rattaché à un trajet en attente ou en cours.',
                [],
                422
            );
        }

        $vehicle->delete();

        return $this->apiResponse(true, 'Véhicule supprimé de votre flotte avec succès.');
    }

    // =========================================================================
    //  PANEL ADMINISTRATIF
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/vehicles/{id}/approve',
        operationId: 'vehiclesToggleApproval',
        summary: '[ADMIN] Certifier / Suspendre un véhicule',
        description: 'Action réservée au Back-Office. Active ou gèle l\'autorisation de rouler d\'un véhicule (`is_approved`). Un véhicule non certifié ne peut pas être utilisé pour créer un trajet.',
        tags: ['🚙 Flotte & Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant numérique du véhicule',
                schema: new OA\Schema(type: 'integer', example: 5)
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
                        description: '`true` pour certifier, `false` pour suspendre'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut de certification du véhicule mis à jour'),
            new OA\Response(response: 403, description: 'Privilèges administratifs requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Véhicule introuvable',             content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Valeur d\'approbation invalide',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function toggleApproval(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé. Privilèges administratifs requis.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'is_approved' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Valeur d\'approbation erronée.', $validator->errors(), 422);
        }

        $vehicle = Vehicle::with('vehicleType')->find($id);

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        $vehicle->update(['is_approved' => $request->is_approved]);

        $statusLabel = $request->is_approved ? 'certifié et approuvé pour le service' : 'suspendu / bloqué';

        return $this->apiResponse(
            true,
            "Le véhicule a été {$statusLabel}.",
            $vehicle->fresh()->load(['user.profile', 'vehicleType'])
        );
    }

    // =========================================================================
    //  HELPER PRIVÉ
    // =========================================================================

    private function apiResponse(bool $success, string $message, mixed $body = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'body'    => $body,
        ], $status);
    }
}