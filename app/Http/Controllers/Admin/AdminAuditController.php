<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

/**
 * Journal d'audit & sécurité — Back-Office Admin.
 *
 * Endpoints :
 *   GET /api/admin/audit/logs    — liste paginée + filtrée
 *   GET /api/admin/audit/admins  — liste des admins (pour le filtre)
 *   GET /api/admin/audit/export  — export CSV ou PDF
 */
class AdminAuditController extends Controller
{
    // =========================================================================
    //  GET /api/admin/audit/logs
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/audit/logs',
        operationId: 'adminAuditLogsFull',
        summary: 'Journal d\'audit — liste des entrées',
        description: 'Retourne les entrées du journal d\'audit, filtrables par sévérité, type d\'action, administrateur et recherche texte. Triées par date décroissante.',
        tags: ['👑 Admin — Audit & Sécurité'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search',      in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'severity',    in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'info', 'avertissement', 'critique'], default: 'all')),
            new OA\Parameter(name: 'action_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'all')),
            new OA\Parameter(name: 'admin_id',    in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'all')),
            new OA\Parameter(name: 'date_from',   in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to',     in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page',    in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Entrées du journal',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'logs',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/AuditLogEntry')
                                ),
                                new OA\Property(property: 'total',    type: 'integer', example: 230),
                                new OA\Property(property: 'page',     type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 50),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function logs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 50), 200);

        $query = AuditLog::with('user.profile')
            ->orderByDesc('created_at');

        // Filtre sévérité
        if ($request->filled('severity') && $request->input('severity') !== 'all') {
            $query->where('severity', $request->input('severity'));
        }

        // Filtre type d'action
        if ($request->filled('action_type') && $request->input('action_type') !== 'all') {
            $query->where('action_type', $request->input('action_type'));
        }

        // Filtre admin
        if ($request->filled('admin_id') && $request->input('admin_id') !== 'all') {
            $query->where('user_id', $request->input('admin_id'));
        }

        // Filtre date
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Recherche texte (description ou target_name ou IP)
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('description', 'like', "%{$s}%")
                  ->orWhere('target_name', 'like', "%{$s}%")
                  ->orWhere('ip_address', 'like', "%{$s}%")
                  ->orWhereHas('user.profile', fn ($p) =>
                      $p->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name',  'like', "%{$s}%")
                  );
            });
        }

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(fn (AuditLog $log) => $this->formatLog($log));

        return $this->apiResponse(true, 'Journal d\'audit.', [
            'logs'     => $items,
            'total'    => $paginated->total(),
            'page'     => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
        ]);
    }

    // =========================================================================
    //  GET /api/admin/audit/admins
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/audit/admins',
        operationId: 'adminAuditAdmins',
        summary: 'Liste des administrateurs (filtre du journal)',
        tags: ['👑 Admin — Audit & Sécurité'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Administrateurs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'admins',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id',     type: 'string'),
                                            new OA\Property(property: 'name',   type: 'string', example: 'Ali Yarou'),
                                            new OA\Property(property: 'avatar', type: 'string', format: 'uri'),
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
    public function admins(): JsonResponse
    {
        $admins = User::with('profile')
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->orderBy('id')
            ->get()
            ->map(function (User $u) {
                $profile = $u->profile;
                $name    = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
                if (empty($name)) $name = $u->phone ?? 'Admin';

                $avatar = $profile?->selfie_front
                    ? asset('storage/' . $profile->selfie_front)
                    : 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=374151&color=fff';

                return ['id' => (string) $u->id, 'name' => $name, 'avatar' => $avatar];
            });

        return $this->apiResponse(true, 'Administrateurs.', ['admins' => $admins]);
    }

    // =========================================================================
    //  GET /api/admin/audit/export
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/audit/export',
        operationId: 'adminAuditExport',
        summary: 'Exporter le journal d\'audit (CSV/PDF)',
        description: 'Retourne un fichier CSV (compatible Excel) ou PDF. Les mêmes filtres que /logs sont supportés. Le format PDF retourne également un CSV — générer un vrai PDF nécessite une librairie dédiée.',
        tags: ['👑 Admin — Audit & Sécurité'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'format',      in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['excel', 'pdf'], default: 'excel')),
            new OA\Parameter(name: 'severity',    in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'action_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'admin_id',    in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date_from',   in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to',     in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search',      in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fichier CSV téléchargeable'),
        ]
    )]
    public function export(Request $request): Response
    {
        // Enregistrer l'export lui-même dans le journal
        AuditLog::record(
            action:      'audit.export',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'export_donnees',
            severity:    'info',
            description: 'Export du journal d\'audit (' . $request->input('format', 'excel') . ')',
            userAgent:   $request->userAgent(),
        );

        $query = AuditLog::with('user.profile')->orderByDesc('created_at');

        if ($request->filled('severity') && $request->input('severity') !== 'all') {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->filled('action_type') && $request->input('action_type') !== 'all') {
            $query->where('action_type', $request->input('action_type'));
        }
        if ($request->filled('admin_id') && $request->input('admin_id') !== 'all') {
            $query->where('user_id', $request->input('admin_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('description',  'like', "%{$s}%")
                  ->orWhere('target_name', 'like', "%{$s}%")
                  ->orWhere('ip_address',  'like', "%{$s}%");
            });
        }

        $logs    = $query->limit(5000)->get();
        $format  = $request->input('format', 'excel');
        $filename = 'audit_minizon_' . now()->format('Ymd_His') . '.csv';

        // BOM UTF-8 pour que Excel ouvre correctement les caractères accentués
        $bom = "\xEF\xBB\xBF";

        $headers = ['Date / Heure', 'Administrateur', 'Type', 'Description', 'Cible', 'Adresse IP', 'Sévérité'];
        $rows    = $logs->map(function (AuditLog $log) {
            $profile  = $log->user?->profile;
            $adminName = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
            if (empty($adminName)) $adminName = $log->user?->phone ?? '—';

            return [
                $log->created_at->setTimezone('Africa/Porto-Novo')->format('d/m/Y H:i:s'),
                $adminName,
                $log->action_type ?? $log->action,
                $log->description ?? '—',
                $log->target_name ?? '—',
                $log->ip_address,
                $log->severity,
            ];
        });

        $csv = $bom . implode(';', $headers) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(
                fn ($cell) => '"' . str_replace('"', '""', (string) $cell) . '"',
                $row
            )) . "\n";
        }

        $mime = $format === 'pdf' ? 'application/pdf' : 'text/csv';

        return response($csv, 200, [
            'Content-Type'        => $mime . '; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    // =========================================================================
    //  HELPER PRIVÉ
    // =========================================================================

    private function formatLog(AuditLog $log): array
    {
        $profile   = $log->user?->profile;
        $adminName = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
        if (empty($adminName)) {
            $adminName = $log->user?->phone ?? 'Système';
        }

        $adminAvatar = $profile?->selfie_front
            ? asset('storage/' . $profile->selfie_front)
            : 'https://ui-avatars.com/api/?name=' . urlencode($adminName) . '&background=374151&color=fff';

        return [
            'id'          => $log->id,
            'timestamp'   => $log->created_at->toIso8601String(),
            'adminName'   => $adminName,
            'adminAvatar' => $adminAvatar,
            'actionType'  => $log->action_type ?? $log->action,
            'description' => $log->description ?? $log->action,
            'targetName'  => $log->target_name,
            'ipAddress'   => $log->ip_address,
            'severity'    => $log->severity ?? 'info',
        ];
    }
}

// ── OpenAPI schema ─────────────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'AuditLogEntry',
    properties: [
        new OA\Property(property: 'id',          type: 'integer',  example: 1),
        new OA\Property(property: 'timestamp',   type: 'string',   format: 'date-time'),
        new OA\Property(property: 'adminName',   type: 'string',   example: 'Ali Yarou'),
        new OA\Property(property: 'adminAvatar', type: 'string',   format: 'uri'),
        new OA\Property(property: 'actionType',  type: 'string',   example: 'approbation_conducteur'),
        new OA\Property(property: 'description', type: 'string',   example: 'Conducteur Koffi Mensah approuvé'),
        new OA\Property(property: 'targetName',  type: 'string',   nullable: true, example: 'Koffi Mensah'),
        new OA\Property(property: 'ipAddress',   type: 'string',   example: '102.188.45.12'),
        new OA\Property(property: 'severity',    type: 'string',   enum: ['info', 'avertissement', 'critique']),
    ]
)]
class _AuditLogEntrySchema {}
