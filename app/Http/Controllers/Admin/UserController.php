<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '👥 Admin — Utilisateurs', description: 'CRUD complet des comptes utilisateurs (Back-Office)')]
class UserController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function userStatus(User $user): string
    {
        if ($user->is_blocked)    return 'Suspendu';
        if (! $user->is_verified) return 'Inactif';
        return 'Actif';
    }

    private function verificationLabel(?string $kycStatus): string
    {
        return match ($kycStatus) {
            'approved' => 'Vérifié',
            'rejected' => 'Rejeté',
            default    => 'En attente',
        };
    }

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function docStatus(?string $fileField, string $kycStatus): string
    {
        if ($kycStatus === 'rejected') return 'rejected';
        if ($kycStatus === 'approved') return 'ok';
        return $fileField ? 'ok' : 'pending';
    }

    private function format(User $user): array
    {
        $profile   = $user->profile;
        $firstName = $profile?->first_name ?? '';
        $lastName  = $profile?->last_name  ?? '';
        $isDriver  = $user->role?->name === 'driver';
        $vehicle   = $isDriver ? $user->vehicle : null;
        $kycStatus = $profile?->kyc_status ?? 'pending';

        Carbon::setLocale('fr');

        return [
            'id'           => $user->uuid,
            'name'         => trim("{$firstName} {$lastName}") ?: $user->phone,
            'phone'        => $user->phone,
            'email'        => $profile?->email ?? null,
            'avatar'       => $this->fileUrl($profile?->selfie_front),
            'type'         => $isDriver ? 'Conducteur' : 'Passager',
            'vehicle'      => $vehicle ? "{$vehicle->brand} {$vehicle->model}" : null,
            'plate'        => $vehicle?->license_plate ?? null,
            'selfies' => [
                'front' => $this->fileUrl($profile?->selfie_front),
                'left'  => $this->fileUrl($profile?->selfie_left),
                'right' => $this->fileUrl($profile?->selfie_right),
            ],
            'idCard' => [
                'front' => $this->fileUrl($profile?->id_card_front),
                'back'  => $this->fileUrl($profile?->id_card_back),
            ],
            'documents' => $isDriver ? [
                'permis'     => ['status' => $this->docStatus($profile?->driving_license_photo, $kycStatus), 'url' => $this->fileUrl($profile?->driving_license_photo)],
                'carteGrise' => ['status' => $this->docStatus($vehicle?->registration_doc, $kycStatus),       'url' => $this->fileUrl($vehicle?->registration_doc)],
                'assurance'  => ['status' => $this->docStatus($vehicle?->insurance_doc, $kycStatus),          'url' => $this->fileUrl($vehicle?->insurance_doc)],
            ] : null,
            'score'        => $profile?->kyc_matching_score ?? 0,
            'status'       => $this->userStatus($user),
            'verification' => $this->verificationLabel($profile?->kyc_status),
            'lastActivity' => $user->updated_at?->diffForHumans(),
        ];
    }

    private function usersQuery()
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['passenger', 'driver']))
            ->with(['role', 'profile', 'vehicle']);
    }

    // =========================================================================
    //  METRICS  GET /api/admin/users/metrics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/users/metrics',
        operationId: 'adminUserMetrics',
        summary: '[ADMIN] Métriques globales utilisateurs',
        description: 'Retourne les 4 cartes statistiques de la page Gestion des Utilisateurs.',
        tags: ['👥 Admin — Utilisateurs'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Métriques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Métriques utilisateurs récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total_users',        type: 'integer', example: 3420),
                                new OA\Property(property: 'total_trips',        type: 'integer', example: 8750),
                                new OA\Property(property: 'verification_rate',  type: 'number',  example: 78.4),
                                new OA\Property(property: 'blocked_or_rejected',type: 'integer', example: 53),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function metrics(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $base  = fn () => $this->usersQuery();
        $total = $base()->count();

        $verified = $base()
            ->where('is_blocked', false)
            ->whereHas('profile', fn ($q) => $q->where('kyc_status', 'approved'))
            ->count();

        $blockedOrRejected = $base()
            ->where(function ($q) {
                $q->where('is_blocked', true)
                  ->orWhereHas('profile', fn ($q) => $q->where('kyc_status', 'rejected'));
            })
            ->count();

        $totalTrips       = Trip::count();
        $verificationRate = $total > 0 ? round(($verified / $total) * 100, 1) : 0;

        return $this->apiResponse(true, 'Métriques utilisateurs récupérées.', [
            'total_users'         => $total,
            'total_trips'         => $totalTrips,
            'verification_rate'   => $verificationRate,
            'blocked_or_rejected' => $blockedOrRejected,
        ]);
    }

    // =========================================================================
    //  INDEX  GET /api/admin/users
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/users',
        operationId: 'adminUserIndex',
        summary: '[ADMIN] Liste paginée des utilisateurs',
        description: 'Retourne tous les passagers et conducteurs avec pagination, recherche et filtres.',
        tags: ['👥 Admin — Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'search',   in: 'query', schema: new OA\Schema(type: 'string'), description: 'Recherche nom, téléphone ou email'),
            new OA\Parameter(name: 'role_id',  in: 'query', schema: new OA\Schema(type: 'integer'), description: '0 = tous, 2 = passagers, 3 = conducteurs'),
            new OA\Parameter(name: 'status',   in: 'query', schema: new OA\Schema(type: 'string', enum: ['Actif', 'Inactif', 'Suspendu'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des utilisateurs'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $perPage  = min((int) $request->input('per_page', 10), 100);
        $search   = trim($request->input('search', ''));
        $roleId   = (int) $request->input('role_id', 0);
        $status   = $request->input('status', '');

        $query = $this->usersQuery();

        if ($roleId > 0) {
            $query->where('role_id', $roleId);
        }

        if ($status !== '') {
            match ($status) {
                'Suspendu' => $query->where('is_blocked', true),
                'Actif'    => $query->where('is_blocked', false)->where('is_verified', true),
                'Inactif'  => $query->where('is_blocked', false)->where('is_verified', false),
                default    => null,
            };
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                  ->orWhereHas('profile', function ($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name',  'like', "%{$search}%")
                        ->orWhere('email',      'like', "%{$search}%");
                  });
            });
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->apiResponse(true, 'Utilisateurs récupérés.', [
            'data'         => $paginated->map(fn ($u) => $this->format($u))->values(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  SHOW  GET /api/admin/users/{uuid}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/users/{uuid}',
        operationId: 'adminUserShow',
        summary: '[ADMIN] Détail d\'un utilisateur',
        tags: ['👥 Admin — Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur trouvé'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $user = $this->usersQuery()->where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Utilisateur récupéré.', $this->format($user));
    }

    // =========================================================================
    //  STORE  POST /api/admin/users
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/users',
        operationId: 'adminUserStore',
        summary: '[ADMIN] Créer un utilisateur',
        description: 'Crée un compte utilisateur directement depuis le Back-Office sans passer par le flux OTP/KYC mobile.',
        tags: ['👥 Admin — Utilisateurs'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'phone'],
                properties: [
                    new OA\Property(property: 'name',         type: 'string', example: 'Marie Dupont', description: 'Prénom et nom (séparés par un espace)'),
                    new OA\Property(property: 'phone',        type: 'string', example: '+22997123456'),
                    new OA\Property(property: 'type',         type: 'string', enum: ['Passager', 'Conducteur'], example: 'Passager'),
                    new OA\Property(property: 'status',       type: 'string', enum: ['Actif', 'Inactif'], example: 'Actif'),
                    new OA\Property(property: 'verification', type: 'string', enum: ['En attente', 'Vérifié'], example: 'En attente'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Utilisateur créé'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'         => ['required', 'string', 'max:200'],
            'phone'        => ['required', 'string', 'unique:users,phone'],
            'type'         => ['nullable', 'in:Passager,Conducteur'],
            'status'       => ['nullable', 'in:Actif,Inactif'],
            'verification' => ['nullable', 'in:En attente,Vérifié'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        $roleName  = $request->input('type', 'Passager') === 'Conducteur' ? 'driver' : 'passenger';
        $role      = Role::where('name', $roleName)->first();
        $isVerif   = $request->input('verification', 'En attente') === 'Vérifié';
        $isBlocked = false;
        $isActif   = $request->input('status', 'Actif') === 'Actif';
        $kycStatus = $isVerif ? 'approved' : 'pending';

        // Split "Prénom Nom"
        $parts     = explode(' ', trim($request->name), 2);
        $firstName = Str::title(strtolower($parts[0]));
        $lastName  = strtoupper($parts[1] ?? $parts[0]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'uuid'        => (string) Str::uuid(),
                'phone'       => $request->phone,
                'role_id'     => $role?->id ?? 2,
                'is_verified' => $isVerif && $isActif,
                'is_blocked'  => $isBlocked,
            ]);

            Profile::create([
                'user_id'    => $user->id,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'gender'     => 'M',
                'city'       => '',
                'neighborhood'=> '',
                'kyc_status' => $kycStatus,
                'approved_at'=> $isVerif ? now() : null,
            ]);

            DB::commit();

            $user->load(['role', 'profile']);

            return $this->apiResponse(true, 'Utilisateur créé avec succès.', $this->format($user), 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->apiResponse(false, 'Erreur lors de la création.', ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    //  UPDATE  PUT /api/admin/users/{uuid}
    // =========================================================================

    #[OA\Put(
        path: '/api/admin/users/{uuid}',
        operationId: 'adminUserUpdate',
        summary: '[ADMIN] Modifier un utilisateur',
        description: 'Met à jour nom, téléphone, type, statut et vérification d\'un utilisateur.',
        tags: ['👥 Admin — Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',         type: 'string', example: 'Marie Dupont'),
                    new OA\Property(property: 'phone',        type: 'string', example: '+22997123456'),
                    new OA\Property(property: 'type',         type: 'string', enum: ['Passager', 'Conducteur']),
                    new OA\Property(property: 'status',       type: 'string', enum: ['Actif', 'Inactif', 'Suspendu']),
                    new OA\Property(property: 'verification', type: 'string', enum: ['Vérifié', 'En attente', 'Rejeté']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur mis à jour'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function update(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $user = $this->usersQuery()->where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'         => ['sometimes', 'string', 'max:200'],
            'phone'        => ['sometimes', 'string', 'unique:users,phone,' . $user->id],
            'type'         => ['sometimes', 'in:Passager,Conducteur'],
            'status'       => ['sometimes', 'in:Actif,Inactif,Suspendu'],
            'verification' => ['sometimes', 'in:Vérifié,En attente,Rejeté'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides.', $validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            // — Téléphone
            if ($request->filled('phone')) {
                $user->update(['phone' => $request->phone]);
            }

            // — Type (rôle)
            if ($request->filled('type')) {
                $roleName = $request->type === 'Conducteur' ? 'driver' : 'passenger';
                $role     = Role::where('name', $roleName)->first();
                if ($role) {
                    $user->update(['role_id' => $role->id]);
                }
            }

            // — Statut
            if ($request->filled('status')) {
                $isBlocked  = $request->status === 'Suspendu';
                $isVerified = $request->status === 'Actif';
                $user->update([
                    'is_blocked'    => $isBlocked,
                    'is_verified'   => $isVerified,
                    'blocked_until' => $isBlocked ? now()->addYears(10) : null,
                ]);
            }

            // — Vérification KYC
            if ($request->filled('verification')) {
                $kycStatus = match ($request->verification) {
                    'Vérifié'    => 'approved',
                    'Rejeté'     => 'rejected',
                    default      => 'pending',
                };
                $user->profile?->update([
                    'kyc_status'  => $kycStatus,
                    'approved_at' => $kycStatus === 'approved' ? now() : null,
                ]);
            }

            // — Nom
            if ($request->filled('name')) {
                $parts     = explode(' ', trim($request->name), 2);
                $firstName = Str::title(strtolower($parts[0]));
                $lastName  = strtoupper($parts[1] ?? $parts[0]);
                $user->profile?->update([
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                ]);
            }

            DB::commit();

            $user->refresh()->load(['role', 'profile']);

            return $this->apiResponse(true, 'Utilisateur mis à jour avec succès.', $this->format($user));

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->apiResponse(false, 'Erreur lors de la mise à jour.', [], 500);
        }
    }

    // =========================================================================
    //  DESTROY  DELETE /api/admin/users/{uuid}
    // =========================================================================

    #[OA\Delete(
        path: '/api/admin/users/{uuid}',
        operationId: 'adminUserDestroy',
        summary: '[ADMIN] Supprimer un utilisateur',
        description: 'Supprime définitivement le compte et toutes ses données associées (profil, véhicule, tokens).',
        tags: ['👥 Admin — Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur supprimé'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $user = User::where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        DB::beginTransaction();
        try {
            Vehicle::where('user_id', $user->id)->delete();
            Profile::where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            $user->delete();

            DB::commit();

            return $this->apiResponse(true, 'Utilisateur supprimé définitivement.');

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->apiResponse(false, 'Erreur lors de la suppression.', [], 500);
        }
    }

    // =========================================================================
    //  APPROVE KYC  PUT /api/admin/users/{uuid}/approve-kyc
    // =========================================================================

    #[OA\Put(
        path: '/api/admin/users/{uuid}/approve-kyc',
        operationId: 'adminUserApproveKyc',
        summary: '[ADMIN] Approuver le KYC',
        tags: ['👥 Admin — Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'KYC approuvé'),
            new OA\Response(response: 422, description: 'KYC déjà traité'),
        ]
    )]
    public function approveKyc(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $user = $this->usersQuery()->where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        if ($user->profile?->kyc_status === 'approved') {
            return $this->apiResponse(false, 'Le KYC de cet utilisateur est déjà approuvé.', [], 422);
        }

        DB::transaction(function () use ($user) {
            $user->profile?->update([
                'kyc_status'  => 'approved',
                'approved_at' => now(),
            ]);
            $user->update(['is_verified' => true]);
            Vehicle::where('user_id', $user->id)->update(['is_approved' => true]);
        });

        $user->refresh()->load(['role', 'profile']);

        return $this->apiResponse(true, 'KYC approuvé avec succès.', $this->format($user));
    }

    // =========================================================================
    //  REJECT KYC  PUT /api/admin/users/{uuid}/reject-kyc
    // =========================================================================

    #[OA\Put(
        path: '/api/admin/users/{uuid}/reject-kyc',
        operationId: 'adminUserRejectKyc',
        summary: '[ADMIN] Rejeter le KYC',
        tags: ['👥 Admin — Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'KYC rejeté'),
            new OA\Response(response: 422, description: 'KYC déjà rejeté'),
        ]
    )]
    public function rejectKyc(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $user = $this->usersQuery()->where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        if ($user->profile?->kyc_status === 'rejected') {
            return $this->apiResponse(false, 'Le KYC de cet utilisateur est déjà rejeté.', [], 422);
        }

        $user->profile?->update(['kyc_status' => 'rejected']);
        $user->update(['is_verified' => false]);

        $user->refresh()->load(['role', 'profile']);

        return $this->apiResponse(true, 'KYC rejeté.', $this->format($user));
    }

    // =========================================================================
    //  SUSPEND  PUT /api/admin/users/{uuid}/suspend
    // =========================================================================

    #[OA\Put(
        path: '/api/admin/users/{uuid}/suspend',
        operationId: 'adminUserSuspend',
        summary: '[ADMIN] Suspendre un utilisateur',
        tags: ['👥 Admin — Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur suspendu'),
            new OA\Response(response: 422, description: 'Déjà suspendu'),
        ]
    )]
    public function suspend(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $user = $this->usersQuery()->where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        if ($user->is_blocked) {
            return $this->apiResponse(false, 'Cet utilisateur est déjà suspendu.', [], 422);
        }

        $user->update(['is_blocked' => true, 'blocked_until' => now()->addYears(10)]);
        $user->refresh()->load(['role', 'profile']);

        return $this->apiResponse(true, 'Utilisateur suspendu.', $this->format($user));
    }

    // =========================================================================
    //  UNSUSPEND  PUT /api/admin/users/{uuid}/unsuspend
    // =========================================================================

    #[OA\Put(
        path: '/api/admin/users/{uuid}/unsuspend',
        operationId: 'adminUserUnsuspend',
        summary: '[ADMIN] Lever la suspension',
        tags: ['👥 Admin — Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Suspension levée'),
            new OA\Response(response: 422, description: 'Utilisateur non suspendu'),
        ]
    )]
    public function unsuspend(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $user = $this->usersQuery()->where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        if (! $user->is_blocked) {
            return $this->apiResponse(false, 'Cet utilisateur n\'est pas suspendu.', [], 422);
        }

        $user->update(['is_blocked' => false, 'blocked_until' => null]);
        $user->refresh()->load(['role', 'profile']);

        return $this->apiResponse(true, 'Suspension levée.', $this->format($user));
    }
}
