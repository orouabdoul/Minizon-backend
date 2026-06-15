<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    // Rôles système — non supprimables
    private const PROTECTED_NAMES = ['admin', 'passenger', 'driver'];

    // =========================================================================
    //  INDEX
    // =========================================================================

    #[OA\Get(
        path: '/api/roles',
        operationId: 'rolesIndex',
        summary: 'Lister tous les rôles',
        description: 'Retourne la liste complète des rôles disponibles sur la plateforme. Accessible à tout utilisateur authentifié.',
        tags: ['🎭 Rôles'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des rôles récupérée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Liste des rôles récupérée.'),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id',         type: 'integer', example: 1),
                                    new OA\Property(property: 'name',       type: 'string',  example: 'admin'),
                                    new OA\Property(property: 'label',      type: 'string',  example: 'Administrateur'),
                                    new OA\Property(property: 'users_count',type: 'integer', example: 3),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(): JsonResponse
    {
        $roles = Role::withCount('users')->orderBy('id')->get();

        return $this->apiResponse(true, 'Liste des rôles récupérée.', $roles);
    }

    // =========================================================================
    //  SHOW
    // =========================================================================

    #[OA\Get(
        path: '/api/roles/{id}',
        operationId: 'rolesShow',
        summary: 'Détails d\'un rôle',
        description: 'Retourne les informations d\'un rôle et le nombre d\'utilisateurs qui le possèdent.',
        tags: ['🎭 Rôles'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rôle trouvé'),
            new OA\Response(response: 404, description: 'Rôle introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $role = Role::withCount('users')->find($id);

        if (! $role) {
            return $this->apiResponse(false, 'Rôle introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Rôle récupéré.', $role);
    }

    // =========================================================================
    //  STORE  (admin)
    // =========================================================================

    #[OA\Post(
        path: '/api/roles',
        operationId: 'rolesStore',
        summary: '[ADMIN] Créer un rôle',
        description: 'Crée un nouveau rôle. Le champ `name` doit être unique et en minuscules (ex : `moderateur`).',
        tags: ['🎭 Rôles'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'label'],
                properties: [
                    new OA\Property(property: 'name',  type: 'string', example: 'moderateur', description: 'Identifiant unique (minuscules, sans espaces)'),
                    new OA\Property(property: 'label', type: 'string', example: 'Modérateur',  description: 'Libellé affiché'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Rôle créé avec succès'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides',                 content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'  => ['required', 'string', 'max:50', 'unique:roles,name', 'regex:/^[a-z_]+$/'],
            'label' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        $role = Role::create([
            'name'  => strtolower($request->name),
            'label' => $request->label,
        ]);

        return $this->apiResponse(true, 'Rôle créé avec succès.', $role, 201);
    }

    // =========================================================================
    //  UPDATE  (admin)
    // =========================================================================

    #[OA\Put(
        path: '/api/roles/{id}',
        operationId: 'rolesUpdate',
        summary: '[ADMIN] Modifier un rôle',
        description: 'Met à jour le libellé d\'un rôle. Le `name` des rôles système (`admin`, `passenger`, `driver`) ne peut pas être modifié.',
        tags: ['🎭 Rôles'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['label'],
                properties: [
                    new OA\Property(property: 'label', type: 'string', example: 'Modérateur Senior'),
                    new OA\Property(property: 'name',  type: 'string', example: 'moderateur_senior', description: 'Ignoré pour les rôles système'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Rôle mis à jour'),
            new OA\Response(response: 403, description: 'Accès interdit',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Rôle introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $role = Role::find($id);
        if (! $role) {
            return $this->apiResponse(false, 'Rôle introuvable.', [], 404);
        }

        $isProtected = in_array($role->name, self::PROTECTED_NAMES);

        $rules = ['label' => ['required', 'string', 'max:100']];

        if (! $isProtected) {
            $rules['name'] = ['sometimes', 'string', 'max:50', 'unique:roles,name,' . $id, 'regex:/^[a-z_]+$/'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        $role->label = $request->label;

        if (! $isProtected && $request->filled('name')) {
            $role->name = strtolower($request->name);
        }

        $role->save();

        return $this->apiResponse(true, 'Rôle mis à jour avec succès.', $role);
    }

    // =========================================================================
    //  DESTROY  (admin)
    // =========================================================================

    #[OA\Delete(
        path: '/api/roles/{id}',
        operationId: 'rolesDestroy',
        summary: '[ADMIN] Supprimer un rôle',
        description: 'Supprime un rôle personnalisé. Les rôles système (`admin`, `passenger`, `driver`) sont **protégés** et ne peuvent pas être supprimés. Un rôle attribué à des utilisateurs ne peut pas non plus être supprimé.',
        tags: ['🎭 Rôles'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rôle supprimé'),
            new OA\Response(response: 403, description: 'Rôle système protégé ou accès interdit', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Rôle introuvable',                        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Rôle en cours d\'utilisation',            content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $role = Role::withCount('users')->find($id);
        if (! $role) {
            return $this->apiResponse(false, 'Rôle introuvable.', [], 404);
        }

        if (in_array($role->name, self::PROTECTED_NAMES)) {
            return $this->apiResponse(false, 'Les rôles système (admin, passenger, driver) sont protégés et ne peuvent pas être supprimés.', [], 403);
        }

        if ($role->users_count > 0) {
            return $this->apiResponse(false, "Ce rôle est attribué à {$role->users_count} utilisateur(s). Réaffectez-les avant de supprimer ce rôle.", [], 409);
        }

        $role->delete();

        return $this->apiResponse(true, 'Rôle supprimé avec succès.');
    }
}
