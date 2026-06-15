<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuditLogController extends Controller
{
    // =========================================================================
    //  ADMIN — Journal d'audit de sécurité
    //  GET /api/admin/audit-logs
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/audit-logs',
        operationId: 'adminAuditLogs',
        summary: '[ADMIN] Journal d\'audit de sécurité',
        description: "Retourne le journal des événements sensibles de la plateforme (tentatives OTP, connexions admin, actions critiques).\n\nFiltres disponibles : `action`, `user_uuid`, `ip_address`, `from`, `to`.",
        tags: ['👑 Administration'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'action',     in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filtrer par type d\'action (ex: otp_failed, admin_login)'),
            new OA\Parameter(name: 'user_uuid',  in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'UUID de l\'utilisateur concerné'),
            new OA\Parameter(name: 'ip_address', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Adresse IP source'),
            new OA\Parameter(name: 'from',       in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2025-01-01', description: 'Date de début (incluse)'),
            new OA\Parameter(name: 'to',         in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2025-12-31', description: 'Date de fin (incluse)'),
            new OA\Parameter(name: 'per_page',   in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Journal d\'audit paginé'),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès réservé aux administrateurs.', [], 403);
        }

        $perPage = min((int) $request->input('per_page', 50), 200);

        $query = AuditLog::with(['user:id,uuid,phone'])
            ->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        if ($request->filled('user_uuid')) {
            $query->whereHas('user', fn ($q) => $q->where('uuid', $request->user_uuid));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return $this->apiResponse(true, 'Journal d\'audit récupéré.', $query->paginate($perPage));
    }

}
