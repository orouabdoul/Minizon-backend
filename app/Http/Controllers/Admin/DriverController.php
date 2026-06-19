<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '🚗 Admin — Conducteurs', description: 'Gestion et validation des conducteurs')]
class DriverController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    /** Dérive le statut frontend à partir des flags BDD. */
    private function driverStatus(User $driver): string
    {
        if ($driver->is_blocked) {
            return 'Suspendu';
        }

        return match ($driver->profile?->kyc_status) {
            'approved' => 'Vérifié',
            'rejected' => 'Rejeté',
            default    => 'En attente',
        };
    }

    /**
     * Statut d'un document individuel.
     * - Si le kyc global est rejeté  → rejected
     * - Si le kyc global est approuvé → ok
     * - Sinon : ok si le fichier est uploadé, pending sinon
     */
    private function docStatus(?string $fileField, string $kycStatus): string
    {
        if ($kycStatus === 'rejected') return 'rejected';
        if ($kycStatus === 'approved') return 'ok';
        return $fileField ? 'ok' : 'pending';
    }

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    /** Sérialise un User driver en tableau frontend. */
    private function format(User $driver): array
    {
        $profile   = $driver->profile;
        $vehicle   = $driver->vehicle;
        $kycStatus = $profile?->kyc_status ?? 'pending';

        $firstName = $profile?->first_name ?? '';
        $lastName  = $profile?->last_name  ?? '';

        return [
            'id'       => $driver->uuid,
            'name'     => trim("{$firstName} {$lastName}") ?: $driver->phone,
            'avatar'   => $this->fileUrl($profile?->selfie_front),
            'driverId' => 'DRV-' . strtoupper(substr($driver->uuid, 0, 8)),
            'phone'    => $driver->phone,
            'email'    => $profile?->email ?? null,
            'vehicle'  => $vehicle ? "{$vehicle->brand} {$vehicle->model}" : null,
            'plate'    => $vehicle?->license_plate ?? null,
            'selfies' => [
                'front' => $this->fileUrl($profile?->selfie_front),
                'left'  => $this->fileUrl($profile?->selfie_left),
                'right' => $this->fileUrl($profile?->selfie_right),
            ],
            'idCard' => [
                'front' => $this->fileUrl($profile?->id_card_front),
                'back'  => $this->fileUrl($profile?->id_card_back),
            ],
            'documents' => [
                'permis' => [
                    'status' => $this->docStatus($profile?->driving_license_photo, $kycStatus),
                    'url'    => $this->fileUrl($profile?->driving_license_photo),
                ],
                'carteGrise' => [
                    'status' => $this->docStatus($vehicle?->registration_doc, $kycStatus),
                    'url'    => $this->fileUrl($vehicle?->registration_doc),
                ],
                'assurance' => [
                    'status' => $this->docStatus($vehicle?->insurance_doc, $kycStatus),
                    'url'    => $this->fileUrl($vehicle?->insurance_doc),
                ],
            ],
            'score'  => $profile?->kyc_matching_score ?? 0,
            'status' => $this->driverStatus($driver),
        ];
    }

    /** Query de base : users avec role driver + relations eager-loadées. */
    private function driversQuery()
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'driver'))
            ->with(['profile', 'vehicle']);
    }

    /** Applique le filtre de statut frontend sur la query. */
    private function applyStatusFilter($query, ?string $status)
    {
        if (! $status || $status === '') {
            return;
        }

        if ($status === 'Suspendu') {
            $query->where('is_blocked', true);
            return;
        }

        $kycMap = [
            'En attente' => 'pending',
            'Vérifié'    => 'approved',
            'Rejeté'     => 'rejected',
        ];

        $kyc = $kycMap[$status] ?? null;

        if ($kyc) {
            $query->where('is_blocked', false)
                  ->whereHas('profile', fn ($q) => $q->where('kyc_status', $kyc));
        }
    }

    // =========================================================================
    //  METRICS  GET /api/admin/drivers/metrics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/drivers/metrics',
        operationId: 'adminDriverMetrics',
        summary: '[ADMIN] Métriques des conducteurs',
        description: 'Retourne les 4 cartes de statistiques affichées en haut du tableau de bord conducteurs.',
        tags: ['🚗 Admin — Conducteurs'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Métriques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Métriques conducteurs récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',             type: 'integer', example: 120),
                                new OA\Property(property: 'pending',           type: 'integer', example: 14),
                                new OA\Property(property: 'verified',          type: 'integer', example: 98),
                                new OA\Property(property: 'suspended_rejected',type: 'integer', example: 8),
                                new OA\Property(property: 'validation_rate',   type: 'number',  example: 81.7),
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

        $base     = $this->driversQuery();
        $total    = (clone $base)->count();
        $pending  = (clone $base)->where('is_blocked', false)
                                  ->whereHas('profile', fn ($q) => $q->where('kyc_status', 'pending'))
                                  ->count();
        $verified = (clone $base)->where('is_blocked', false)
                                  ->whereHas('profile', fn ($q) => $q->where('kyc_status', 'approved'))
                                  ->count();
        $suspendedRejected = $total - $pending - $verified;

        $validationRate = $total > 0 ? round(($verified / $total) * 100, 1) : 0;

        return $this->apiResponse(true, 'Métriques conducteurs récupérées.', [
            'total'              => $total,
            'pending'            => $pending,
            'verified'           => $verified,
            'suspended_rejected' => $suspendedRejected,
            'validation_rate'    => $validationRate,
        ]);
    }

    // =========================================================================
    //  INDEX  GET /api/admin/drivers
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/drivers',
        operationId: 'adminDriverIndex',
        summary: '[ADMIN] Lister les conducteurs',
        description: 'Liste paginée des conducteurs avec filtres par statut et recherche textuelle.',
        tags: ['🚗 Admin — Conducteurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'search',   in: 'query', schema: new OA\Schema(type: 'string'),
                description: 'Recherche par nom, téléphone ou email'),
            new OA\Parameter(name: 'status',   in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['En attente', 'Vérifié', 'Rejeté', 'Suspendu']),
                description: 'Filtre par statut'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des conducteurs'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $search   = trim($request->input('search', ''));
        $status   = $request->input('status', '');
        $perPage  = min((int) $request->input('per_page', 10), 100);

        $query = $this->driversQuery();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                  ->orWhereHas('profile', function ($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name',  'like', "%{$search}%")
                        ->orWhere('email',       'like', "%{$search}%");
                  });
            });
        }

        $this->applyStatusFilter($query, $status);

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->apiResponse(true, 'Conducteurs récupérés.', [
            'data'         => $paginated->map(fn ($d) => $this->format($d))->values(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  SHOW  GET /api/admin/drivers/{uuid}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/drivers/{uuid}',
        operationId: 'adminDriverShow',
        summary: '[ADMIN] Détail d\'un conducteur',
        tags: ['🚗 Admin — Conducteurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conducteur trouvé'),
            new OA\Response(response: 404, description: 'Conducteur introuvable'),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $driver = $this->driversQuery()->where('uuid', $uuid)->first();

        if (! $driver) {
            return $this->apiResponse(false, 'Conducteur introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Conducteur récupéré.', $this->format($driver));
    }

    // =========================================================================
    //  VALIDATE  PUT /api/admin/drivers/{uuid}/validate
    // =========================================================================

    #[OA\Put(
        path: '/api/admin/drivers/{uuid}/validate',
        operationId: 'adminDriverValidate',
        summary: '[ADMIN] Valider un conducteur',
        description: 'Approuve la demande de vérification d\'un conducteur (kyc_status → approved).',
        tags: ['🚗 Admin — Conducteurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conducteur validé'),
            new OA\Response(response: 404, description: 'Conducteur introuvable'),
            new OA\Response(response: 422, description: 'Statut incompatible (déjà validé, suspendu…)'),
        ]
    )]
    public function validate(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $driver = $this->driversQuery()->where('uuid', $uuid)->first();

        if (! $driver) {
            return $this->apiResponse(false, 'Conducteur introuvable.', [], 404);
        }

        if ($driver->is_blocked) {
            return $this->apiResponse(false, 'Impossible de valider un compte suspendu.', [], 422);
        }

        if ($driver->profile?->kyc_status === 'approved') {
            return $this->apiResponse(false, 'Ce conducteur est déjà vérifié.', [], 422);
        }

        DB::transaction(function () use ($driver) {
            $driver->update(['is_verified' => true]);
            $driver->profile?->update([
                'kyc_status'  => 'approved',
                'approved_at' => now(),
            ]);
        });

        $driver->refresh()->load(['profile', 'vehicle']);

        return $this->apiResponse(true, 'Conducteur validé avec succès.', $this->format($driver));
    }

    // =========================================================================
    //  REJECT  PUT /api/admin/drivers/{uuid}/reject
    // =========================================================================

    #[OA\Put(
        path: '/api/admin/drivers/{uuid}/reject',
        operationId: 'adminDriverReject',
        summary: '[ADMIN] Rejeter un conducteur',
        description: 'Refuse la demande de vérification d\'un conducteur (kyc_status → rejected).',
        tags: ['🚗 Admin — Conducteurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conducteur rejeté'),
            new OA\Response(response: 404, description: 'Conducteur introuvable'),
            new OA\Response(response: 422, description: 'Statut incompatible'),
        ]
    )]
    public function reject(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $driver = $this->driversQuery()->where('uuid', $uuid)->first();

        if (! $driver) {
            return $this->apiResponse(false, 'Conducteur introuvable.', [], 404);
        }

        if ($driver->is_blocked) {
            return $this->apiResponse(false, 'Impossible de rejeter un compte suspendu.', [], 422);
        }

        if ($driver->profile?->kyc_status === 'rejected') {
            return $this->apiResponse(false, 'Ce conducteur est déjà rejeté.', [], 422);
        }

        $driver->profile?->update(['kyc_status' => 'rejected']);

        $driver->refresh()->load(['profile', 'vehicle']);

        return $this->apiResponse(true, 'Conducteur rejeté.', $this->format($driver));
    }
}
