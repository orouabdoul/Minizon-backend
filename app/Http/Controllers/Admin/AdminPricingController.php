<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PromoCode;
use App\Models\TariffRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Tarifs & Promotions — Back-Office Admin (PricingScreen).
 *
 * Endpoints :
 *   GET   /api/admin/pricing/tariffs          — liste des règles tarifaires
 *   PATCH /api/admin/pricing/tariffs/{uuid}   — modifier la valeur d'un tarif
 *   PATCH /api/admin/pricing/tariffs/{uuid}/toggle — activer/désactiver
 *   GET   /api/admin/pricing/promos           — liste des codes promo
 *   POST  /api/admin/pricing/promos           — créer un code promo
 *   PATCH /api/admin/pricing/promos/{uuid}/toggle — activer/désactiver
 *   DELETE /api/admin/pricing/promos/{uuid}   — supprimer
 */
class AdminPricingController extends Controller
{
    // =========================================================================
    //  GET /api/admin/pricing/tariffs
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/pricing/tariffs',
        operationId: 'adminPricingTariffs',
        summary: 'Liste des règles tarifaires',
        tags: ['👑 Admin — Tarifs & Promotions'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Règles tarifaires',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'tariffs',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/TariffRule')
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function tariffs(): JsonResponse
    {
        $tariffs = TariffRule::orderBy('id')->get()->map(fn (TariffRule $t) => [
            'id'          => $t->uuid,
            'key'         => $t->key,
            'name'        => $t->name,
            'description' => $t->description ?? '',
            'value'       => $t->value,
            'unit'        => $t->unit,
            'active'      => $t->active,
        ]);

        return $this->apiResponse(true, 'Règles tarifaires.', ['tariffs' => $tariffs]);
    }

    // =========================================================================
    //  PATCH /api/admin/pricing/tariffs/{uuid}
    // =========================================================================

    #[OA\Patch(
        path: '/api/admin/pricing/tariffs/{uuid}',
        operationId: 'adminPricingUpdateTariff',
        summary: 'Modifier la valeur d\'un tarif',
        tags: ['👑 Admin — Tarifs & Promotions'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['value'],
                properties: [
                    new OA\Property(property: 'value', type: 'number', example: 60),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tarif mis à jour'),
            new OA\Response(response: 404, description: 'Tarif introuvable'),
        ]
    )]
    public function updateTariff(Request $request, string $uuid): JsonResponse
    {
        $rule = TariffRule::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'value' => 'required|numeric|min:0|max:999999',
        ]);

        $oldValue = $rule->value;
        $rule->update(['value' => $validated['value']]);

        AuditLog::record(
            action:      'tariff.update',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'modif_parametre',
            severity:    'avertissement',
            description: "Tarif « {$rule->name} » modifié : {$oldValue} → {$validated['value']} {$rule->unit}",
            targetType:  'tariff',
            targetName:  $rule->name,
            userAgent:   $request->userAgent(),
        );

        return $this->apiResponse(true, 'Tarif mis à jour.', [
            'tariff' => [
                'id'    => $rule->uuid,
                'value' => $rule->value,
                'unit'  => $rule->unit,
            ],
        ]);
    }

    // =========================================================================
    //  PATCH /api/admin/pricing/tariffs/{uuid}/toggle
    // =========================================================================

    #[OA\Patch(
        path: '/api/admin/pricing/tariffs/{uuid}/toggle',
        operationId: 'adminPricingToggleTariff',
        summary: 'Activer ou désactiver une règle tarifaire',
        tags: ['👑 Admin — Tarifs & Promotions'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Statut basculé'),
            new OA\Response(response: 404, description: 'Tarif introuvable'),
        ]
    )]
    public function toggleTariff(Request $request, string $uuid): JsonResponse
    {
        $rule = TariffRule::where('uuid', $uuid)->firstOrFail();
        $rule->update(['active' => ! $rule->active]);

        AuditLog::record(
            action:      'tariff.toggle',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'modif_parametre',
            severity:    'info',
            description: "Règle « {$rule->name} » " . ($rule->active ? 'activée' : 'désactivée'),
            targetType:  'tariff',
            targetName:  $rule->name,
            userAgent:   $request->userAgent(),
        );

        return $this->apiResponse(true, $rule->active ? 'Règle activée.' : 'Règle désactivée.', [
            'active' => $rule->active,
        ]);
    }

    // =========================================================================
    //  GET /api/admin/pricing/promos
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/pricing/promos',
        operationId: 'adminPricingPromos',
        summary: 'Liste des codes promo',
        tags: ['👑 Admin — Tarifs & Promotions'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Codes promo',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'promos',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/PromoCode')
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function promos(): JsonResponse
    {
        $promos = PromoCode::orderByDesc('created_at')->get()->map(fn (PromoCode $p) => $this->formatPromo($p));

        return $this->apiResponse(true, 'Codes promo.', ['promos' => $promos]);
    }

    // =========================================================================
    //  POST /api/admin/pricing/promos
    // =========================================================================

    #[OA\Post(
        path: '/api/admin/pricing/promos',
        operationId: 'adminPricingCreatePromo',
        summary: 'Créer un code promo',
        tags: ['👑 Admin — Tarifs & Promotions'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'discount', 'expires_at'],
                properties: [
                    new OA\Property(property: 'code',        type: 'string', example: 'ETE2026'),
                    new OA\Property(property: 'discount',    type: 'integer', minimum: 1, maximum: 100, example: 20),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Promo été 2026'),
                    new OA\Property(property: 'expires_at',  type: 'string', format: 'date', example: '2026-08-31'),
                    new OA\Property(property: 'usage_limit', type: 'integer', minimum: 1, example: 500),
                    new OA\Property(property: 'active',      type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Code promo créé'),
            new OA\Response(response: 422, description: 'Code déjà existant ou données invalides'),
        ]
    )]
    public function createPromo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:30|uppercase|unique:promo_codes,code',
            'discount'    => 'required|integer|min:1|max:100',
            'description' => 'nullable|string|max:255',
            'expires_at'  => 'required|date|after:today',
            'usage_limit' => 'nullable|integer|min:1',
            'active'      => 'nullable|boolean',
        ]);

        $promo = PromoCode::create([
            'code'        => strtoupper($validated['code']),
            'discount'    => $validated['discount'],
            'description' => $validated['description'] ?? null,
            'expires_at'  => $validated['expires_at'],
            'usage_limit' => $validated['usage_limit'] ?? 500,
            'active'      => $validated['active'] ?? true,
        ]);

        AuditLog::record(
            action:      'promo.create',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'modif_parametre',
            severity:    'info',
            description: "Code promo « {$promo->code} » créé ({$promo->discount}% jusqu'au " . $promo->expires_at->format('d/m/Y') . ')',
            targetType:  'promo',
            targetName:  $promo->code,
            userAgent:   $request->userAgent(),
        );

        return $this->apiResponse(true, 'Code promo créé.', ['promo' => $this->formatPromo($promo)], 201);
    }

    // =========================================================================
    //  PATCH /api/admin/pricing/promos/{uuid}/toggle
    // =========================================================================

    #[OA\Patch(
        path: '/api/admin/pricing/promos/{uuid}/toggle',
        operationId: 'adminPricingTogglePromo',
        summary: 'Activer ou désactiver un code promo',
        tags: ['👑 Admin — Tarifs & Promotions'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Statut basculé'),
            new OA\Response(response: 404, description: 'Code promo introuvable'),
        ]
    )]
    public function togglePromo(Request $request, string $uuid): JsonResponse
    {
        $promo = PromoCode::where('uuid', $uuid)->firstOrFail();
        $promo->update(['active' => ! $promo->active]);

        return $this->apiResponse(true, $promo->active ? 'Code promo activé.' : 'Code promo désactivé.', [
            'active' => $promo->active,
        ]);
    }

    // =========================================================================
    //  DELETE /api/admin/pricing/promos/{uuid}
    // =========================================================================

    #[OA\Delete(
        path: '/api/admin/pricing/promos/{uuid}',
        operationId: 'adminPricingDeletePromo',
        summary: 'Supprimer un code promo',
        tags: ['👑 Admin — Tarifs & Promotions'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Code promo supprimé'),
            new OA\Response(response: 404, description: 'Code promo introuvable'),
        ]
    )]
    public function deletePromo(Request $request, string $uuid): JsonResponse
    {
        $promo = PromoCode::where('uuid', $uuid)->firstOrFail();
        $code  = $promo->code;
        $promo->delete();

        AuditLog::record(
            action:      'promo.delete',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'suppression',
            severity:    'avertissement',
            description: "Code promo « {$code} » supprimé",
            targetType:  'promo',
            targetName:  $code,
            userAgent:   $request->userAgent(),
        );

        return $this->apiResponse(true, 'Code promo supprimé.');
    }

    // =========================================================================
    //  HELPER PRIVÉ
    // =========================================================================

    private function formatPromo(PromoCode $p): array
    {
        return [
            'id'          => $p->uuid,
            'code'        => $p->code,
            'discount'    => $p->discount,
            'description' => $p->description ?? '',
            'expiresAt'   => $p->expires_at->toDateString(),
            'usageLimit'  => $p->usage_limit,
            'usageCount'  => $p->usage_count,
            'active'      => $p->active,
        ];
    }
}

// ── OpenAPI schemas ────────────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'TariffRule',
    properties: [
        new OA\Property(property: 'id',          type: 'string', format: 'uuid'),
        new OA\Property(property: 'key',         type: 'string', example: 'base_rate_per_km'),
        new OA\Property(property: 'name',        type: 'string', example: 'Tarif de base'),
        new OA\Property(property: 'description', type: 'string', example: 'Prix par kilomètre parcouru'),
        new OA\Property(property: 'value',       type: 'number', format: 'float', example: 50),
        new OA\Property(property: 'unit',        type: 'string', example: 'FCFA/km'),
        new OA\Property(property: 'active',      type: 'boolean', example: true),
    ]
)]
class _TariffRuleSchema {}

#[OA\Schema(
    schema: 'PromoCode',
    properties: [
        new OA\Property(property: 'id',          type: 'string', format: 'uuid'),
        new OA\Property(property: 'code',        type: 'string', example: 'ETE2026'),
        new OA\Property(property: 'discount',    type: 'integer', example: 20),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'expiresAt',   type: 'string', format: 'date', example: '2026-08-31'),
        new OA\Property(property: 'usageLimit',  type: 'integer', example: 500),
        new OA\Property(property: 'usageCount',  type: 'integer', example: 42),
        new OA\Property(property: 'active',      type: 'boolean', example: true),
    ]
)]
class _PromoCodeSchema {}
