<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '🚗 Admin — Véhicules', description: 'Vérification et approbation des véhicules (Back-Office)')]
class AdminVehicleController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** Frontend status label : 'Actif' | 'En inspection' | 'Suspendu' | 'Rejeté' */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'approved'  => 'Actif',
            'rejected'  => 'Rejeté',
            'suspended' => 'Suspendu',
            default     => 'En inspection',
        };
    }

    /** Map frontend French status label → DB enum value */
    private function dbStatus(string $label): string
    {
        return match ($label) {
            'Actif'         => 'approved',
            'En inspection' => 'pending',
            'Suspendu'      => 'suspended',
            'Rejeté'        => 'rejected',
            default         => $label, // accept raw DB value too
        };
    }

    /** Per-document status derived from vehicle verification state */
    private function docStatus(string $vStatus, ?string $docPath): string
    {
        if (! $docPath) return 'rejected';
        return match ($vStatus) {
            'approved'  => 'ok',
            'suspended' => 'ok',   // was previously approved
            'rejected'  => 'rejected',
            default     => 'pending',
        };
    }

    private function driverName(Vehicle $v): string
    {
        $profile = $v->user?->profile;
        return trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''))
            ?: ($v->user?->phone ?? '—');
    }

    private function format(Vehicle $v): array
    {
        $owner   = $v->user;
        $profile = $owner?->profile;
        $type    = $v->vehicleType;
        $vStatus = $v->verification_status ?? 'pending';

        $driverId = $owner?->uuid
            ? 'DRV-' . strtoupper(substr($owner->uuid, 0, 8))
            : '—';

        // trips_count comes from withCount('trips') on the query
        $tripsCount = (int) ($v->trips_count ?? 0);

        // average rating from eager-loaded reviews
        $rating = $owner?->reviewsReceived
            ? round((float) $owner->reviewsReceived->avg('rating'), 1)
            : 0.0;

        $verifierName = null;
        if ($v->verified_by && $v->verifier) {
            $vp = $v->verifier->profile;
            $verifierName = trim(($vp?->first_name ?? '') . ' ' . ($vp?->last_name ?? ''))
                ?: ($v->verifier->phone ?? null);
        }

        return [
            'id'          => (string) $v->id,
            'vehicleId'   => 'VEH-' . str_pad($v->id, 8, '0', STR_PAD_LEFT),

            // Identité véhicule
            'make'        => $v->brand,
            'model'       => $v->model,
            'year'        => $v->year,
            'color'       => $v->color,
            'seats'       => $v->available_seats,
            'plate'       => $v->license_plate,
            'type'        => $type?->name ?? 'Berline',
            'typeSlug'    => $type?->slug ?? 'berline',
            'vehiclePhoto'=> $this->fileUrl($v->vehicle_photo),

            // Statut de vérification
            'status'             => $this->statusLabel($vStatus),
            'verificationStatus' => $vStatus,
            'isApproved'         => (bool) $v->is_approved,
            'rejectionReason'    => $v->rejection_reason,
            'verifiedAt'         => $v->verified_at?->format('d/m/Y H:i'),
            'verifiedBy'         => $verifierName,
            'registeredAt'       => $v->created_at?->format('d/m/Y'),

            // Conducteur propriétaire
            'driverUuid'   => $owner?->uuid,
            'driverId'     => $driverId,
            'driverName'   => $this->driverName($v),
            'driverPhone'  => $owner?->phone ?? '—',
            'driverAvatar' => $this->fileUrl($profile?->selfie_front),

            // Statistiques
            'trips'  => $tripsCount,
            'rating' => $rating,

            // Documents — status dérivé de l'état de vérification global
            'documents' => [
                'carteGrise'    => $this->docStatus($vStatus, $v->registration_doc),
                'carteGriseUrl' => $this->fileUrl($v->registration_doc),
                'assurance'     => $this->docStatus($vStatus, $v->insurance_doc),
                'assuranceUrl'  => $this->fileUrl($v->insurance_doc),
                'visite'        => $this->docStatus($vStatus, $v->technical_control_doc),
                'visiteUrl'     => $this->fileUrl($v->technical_control_doc),
            ],
        ];
    }

    private function baseQuery()
    {
        return Vehicle::with([
            'user.profile',
            'user.reviewsReceived',
            'vehicleType',
            'verifier.profile',
        ])->withCount('trips');
    }

    // =========================================================================
    //  METRICS  GET /api/admin/vehicles/metrics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/vehicles/metrics',
        operationId: 'adminVehicleMetrics',
        summary: '[ADMIN] Compteurs pour les KPI de la page Véhicules',
        tags: ['🚗 Admin — Véhicules'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Métriques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Métriques récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',      type: 'integer', example: 120, description: 'Total véhicules'),
                                new OA\Property(property: 'actif',      type: 'integer', example: 95,  description: 'Véhicules actifs (approuvés)'),
                                new OA\Property(property: 'inspection', type: 'integer', example: 18,  description: 'En attente de vérification'),
                                new OA\Property(property: 'suspendu',   type: 'integer', example: 7,   description: 'Suspendus + Rejetés'),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function metrics(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $counts = Vehicle::selectRaw(
            'COUNT(*) as total,
             SUM(CASE WHEN verification_status = "approved"  THEN 1 ELSE 0 END) as actif,
             SUM(CASE WHEN verification_status = "pending"   THEN 1 ELSE 0 END) as inspection,
             SUM(CASE WHEN verification_status IN ("rejected","suspended") THEN 1 ELSE 0 END) as suspendu'
        )->first();

        return $this->apiResponse(true, 'Métriques récupérées.', [
            'total'      => (int) $counts->total,
            'actif'      => (int) $counts->actif,
            'inspection' => (int) $counts->inspection,
            'suspendu'   => (int) $counts->suspendu,
        ]);
    }

    // =========================================================================
    //  INDEX  GET /api/admin/vehicles
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/vehicles',
        operationId: 'adminVehicleIndex',
        summary: '[ADMIN] Liste paginée des véhicules',
        tags: ['🚗 Admin — Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'status', in: 'query',
                description: 'Filtre par statut (libellé FR ou valeur DB)',
                schema: new OA\Schema(type: 'string', enum: ['Actif', 'En inspection', 'Suspendu', 'Rejeté', 'approved', 'pending', 'rejected', 'suspended'])
            ),
            new OA\Parameter(
                name: 'type', in: 'query',
                description: 'Filtre par type de véhicule (Berline, SUV, Moto…)',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'search', in: 'query',
                description: 'Recherche dans marque, modèle, plaque, nom/téléphone conducteur',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des véhicules',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Véhicules récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',        type: 'integer', example: 120),
                                new OA\Property(property: 'per_page',     type: 'integer', example: 15),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page',    type: 'integer', example: 8),
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id',           type: 'string',  example: '1'),
                                            new OA\Property(property: 'vehicleId',    type: 'string',  example: 'VEH-00000001'),
                                            new OA\Property(property: 'make',         type: 'string',  example: 'Toyota'),
                                            new OA\Property(property: 'model',        type: 'string',  example: 'Camry'),
                                            new OA\Property(property: 'year',         type: 'integer', nullable: true, example: 2021),
                                            new OA\Property(property: 'color',        type: 'string',  example: 'Gris'),
                                            new OA\Property(property: 'seats',        type: 'integer', example: 4),
                                            new OA\Property(property: 'plate',        type: 'string',  example: 'RB 1234 X'),
                                            new OA\Property(property: 'type',         type: 'string',  example: 'Berline'),
                                            new OA\Property(property: 'typeSlug',     type: 'string',  example: 'berline'),
                                            new OA\Property(property: 'vehiclePhoto', type: 'string',  nullable: true),
                                            new OA\Property(property: 'status',       type: 'string',  enum: ['Actif', 'En inspection', 'Suspendu', 'Rejeté']),
                                            new OA\Property(property: 'verificationStatus', type: 'string', enum: ['pending', 'approved', 'rejected', 'suspended']),
                                            new OA\Property(property: 'rejectionReason', type: 'string', nullable: true),
                                            new OA\Property(property: 'verifiedAt',   type: 'string',  nullable: true),
                                            new OA\Property(property: 'registeredAt', type: 'string',  example: '20/06/2026'),
                                            new OA\Property(property: 'driverUuid',   type: 'string',  nullable: true),
                                            new OA\Property(property: 'driverId',     type: 'string',  example: 'DRV-A3B4C5D6'),
                                            new OA\Property(property: 'driverName',   type: 'string',  example: 'Kofi Mensah'),
                                            new OA\Property(property: 'driverPhone',  type: 'string',  example: '+22997000001'),
                                            new OA\Property(property: 'driverAvatar', type: 'string',  nullable: true),
                                            new OA\Property(property: 'trips',        type: 'integer', example: 42),
                                            new OA\Property(property: 'rating',       type: 'number',  example: 4.7),
                                            new OA\Property(
                                                property: 'documents',
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'carteGrise',    type: 'string', enum: ['ok', 'pending', 'rejected']),
                                                    new OA\Property(property: 'carteGriseUrl', type: 'string', nullable: true),
                                                    new OA\Property(property: 'assurance',     type: 'string', enum: ['ok', 'pending', 'rejected']),
                                                    new OA\Property(property: 'assuranceUrl',  type: 'string', nullable: true),
                                                    new OA\Property(property: 'visite',        type: 'string', enum: ['ok', 'pending', 'rejected']),
                                                    new OA\Property(property: 'visiteUrl',     type: 'string', nullable: true),
                                                ]
                                            ),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $q = $this->baseQuery();

        // Filtre statut (accepte libellé FR ou valeur DB)
        if ($status = $request->input('status')) {
            $q->where('verification_status', $this->dbStatus($status));
        }

        // Filtre type (par nom : Berline, SUV, etc.)
        if ($type = $request->input('type')) {
            $q->whereHas('vehicleType', fn ($sub) =>
                $sub->where('name', $type)->orWhere('slug', strtolower($type))
            );
        }

        // Recherche texte
        if ($search = trim($request->input('search', ''))) {
            $q->where(function ($sub) use ($search) {
                $sub->where('brand',          'like', "%{$search}%")
                    ->orWhere('model',         'like', "%{$search}%")
                    ->orWhere('license_plate', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) =>
                        $u->where('phone', 'like', "%{$search}%")
                          ->orWhereHas('profile', fn ($p) =>
                              $p->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name',  'like', "%{$search}%")
                          )
                    );
            });
        }

        $perPage   = min((int) $request->input('per_page', 15), 100);
        $paginated = $q->orderByDesc('created_at')->paginate($perPage);

        return $this->apiResponse(true, 'Véhicules récupérés.', [
            'data'         => collect($paginated->items())->map(fn ($v) => $this->format($v))->values(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  SHOW  GET /api/admin/vehicles/{id}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/vehicles/{id}',
        operationId: 'adminVehicleShow',
        summary: '[ADMIN] Fiche complète d\'un véhicule avec documents',
        tags: ['🚗 Admin — Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fiche véhicule'),
            new OA\Response(response: 404, description: 'Véhicule introuvable'),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $vehicle = $this->baseQuery()->find($id);

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Véhicule récupéré.', $this->format($vehicle));
    }

    // =========================================================================
    //  APPROVE  POST /api/admin/vehicles/{id}/approve
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/vehicles/{id}/approve',
        operationId: 'adminVehicleApprove',
        summary: '[ADMIN] Approuver / Réactiver un véhicule',
        description: 'Valide le véhicule (pending → approved) ou le réactive après suspension/rejet. Passe `is_approved` → true.',
        tags: ['🚗 Admin — Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Véhicule approuvé / réactivé'),
            new OA\Response(response: 404, description: 'Véhicule introuvable'),
            new OA\Response(response: 422, description: 'Véhicule déjà actif'),
        ]
    )]
    public function approve(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $vehicle = $this->baseQuery()->find($id);

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        if ($vehicle->isApproved()) {
            return $this->apiResponse(false, 'Ce véhicule est déjà actif.', [], 422);
        }

        $vehicle->update([
            'verification_status' => 'approved',
            'is_approved'         => true,
            'rejection_reason'    => null,
            'verified_at'         => now(),
            'verified_by'         => $request->user()->id,
        ]);

        return $this->apiResponse(true, 'Véhicule approuvé.', $this->format($vehicle->fresh([
            'user.profile', 'user.reviewsReceived', 'vehicleType', 'verifier.profile',
        ])));
    }

    // =========================================================================
    //  REJECT  POST /api/admin/vehicles/{id}/reject
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/vehicles/{id}/reject',
        operationId: 'adminVehicleReject',
        summary: '[ADMIN] Rejeter un véhicule',
        description: 'Rejette le dossier (documents manquants ou non conformes). `is_approved` → false.',
        tags: ['🚗 Admin — Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', nullable: true, example: "Documents d'assurance expirés.", description: 'Motif du rejet (optionnel)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Véhicule rejeté'),
            new OA\Response(response: 404, description: 'Véhicule introuvable'),
            new OA\Response(response: 422, description: 'Véhicule déjà rejeté'),
        ]
    )]
    public function reject(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $vehicle = $this->baseQuery()->find($id);

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        if ($vehicle->isRejected()) {
            return $this->apiResponse(false, 'Ce véhicule est déjà rejeté.', [], 422);
        }

        $vehicle->update([
            'verification_status' => 'rejected',
            'is_approved'         => false,
            'rejection_reason'    => $data['reason'] ?? null,
            'verified_at'         => now(),
            'verified_by'         => $request->user()->id,
        ]);

        return $this->apiResponse(true, 'Véhicule rejeté.', $this->format($vehicle->fresh([
            'user.profile', 'user.reviewsReceived', 'vehicleType', 'verifier.profile',
        ])));
    }

    // =========================================================================
    //  SUSPEND  POST /api/admin/vehicles/{id}/suspend
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/vehicles/{id}/suspend',
        operationId: 'adminVehicleSuspend',
        summary: '[ADMIN] Suspendre un véhicule',
        description: 'Suspend temporairement un véhicule (ex : contrôle technique expiré). `is_approved` → false.',
        tags: ['🚗 Admin — Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', nullable: true, example: 'Contrôle technique expiré.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Véhicule suspendu'),
            new OA\Response(response: 404, description: 'Véhicule introuvable'),
            new OA\Response(response: 422, description: 'Véhicule déjà suspendu'),
        ]
    )]
    public function suspend(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $vehicle = $this->baseQuery()->find($id);

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        if ($vehicle->isSuspended()) {
            return $this->apiResponse(false, 'Ce véhicule est déjà suspendu.', [], 422);
        }

        $vehicle->update([
            'verification_status' => 'suspended',
            'is_approved'         => false,
            'rejection_reason'    => $data['reason'] ?? null,
            'verified_at'         => now(),
            'verified_by'         => $request->user()->id,
        ]);

        return $this->apiResponse(true, 'Véhicule suspendu.', $this->format($vehicle->fresh([
            'user.profile', 'user.reviewsReceived', 'vehicleType', 'verifier.profile',
        ])));
    }

    // =========================================================================
    //  REINSTATE  POST /api/admin/vehicles/{id}/reinstate
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/vehicles/{id}/reinstate',
        operationId: 'adminVehicleReinstate',
        summary: '[ADMIN] Remettre un véhicule en file d\'attente',
        description: 'Repasse un véhicule rejeté ou suspendu à `pending` pour nouvelle vérification.',
        tags: ['🚗 Admin — Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Véhicule remis en attente'),
            new OA\Response(response: 422, description: 'Véhicule déjà en attente ou actif'),
        ]
    )]
    public function reinstate(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $vehicle = $this->baseQuery()->find($id);

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        if ($vehicle->isPending() || $vehicle->isApproved()) {
            return $this->apiResponse(false, 'Ce véhicule est déjà ' . $this->statusLabel($vehicle->verification_status ?? 'pending') . '.', [], 422);
        }

        $vehicle->update([
            'verification_status' => 'pending',
            'is_approved'         => false,
            'rejection_reason'    => null,
            'verified_at'         => null,
            'verified_by'         => null,
        ]);

        return $this->apiResponse(true, 'Véhicule remis en attente de vérification.', $this->format($vehicle->fresh([
            'user.profile', 'user.reviewsReceived', 'vehicleType',
        ])));
    }

    // =========================================================================
    //  DESTROY  DELETE /api/admin/vehicles/{id}
    // =========================================================================

    #[OA\Delete(
        path: '/api/admin/vehicles/{id}',
        operationId: 'adminVehicleDestroy',
        summary: '[ADMIN] Supprimer un véhicule',
        description: 'Supprime définitivement un véhicule. Bloqué si le véhicule est sur un trajet actif ou en attente.',
        tags: ['🚗 Admin — Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Véhicule supprimé'),
            new OA\Response(response: 404, description: 'Véhicule introuvable'),
            new OA\Response(response: 422, description: 'Véhicule utilisé sur un trajet actif'),
        ]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        $hasActiveTrips = Trip::where('vehicle_id', $id)
            ->whereIn('status', ['pending', 'active'])
            ->exists();

        if ($hasActiveTrips) {
            return $this->apiResponse(false, 'Ce véhicule est rattaché à un trajet actif ou en attente.', [], 422);
        }

        $vehicle->delete();

        return $this->apiResponse(true, 'Véhicule supprimé.');
    }
}
