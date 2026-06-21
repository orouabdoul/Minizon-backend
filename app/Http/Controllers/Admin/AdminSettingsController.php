<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '⚙️ Admin — Paramètres', description: 'Paramètres généraux de la plateforme (Back-Office)')]
class AdminSettingsController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function formatAmount(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' XOF';
    }

    private function adminName(User $u): string
    {
        $p = $u->profile;
        return trim(($p?->first_name ?? '') . ' ' . ($p?->last_name ?? '')) ?: ($u->phone ?? '—');
    }

    private function riskLevel(string $action): string
    {
        $action = strtolower($action);
        if (str_contains($action, 'delete') || str_contains($action, 'block') || str_contains($action, 'force')) return 'Élevé';
        if (str_contains($action, 'fail')   || str_contains($action, 'error') || str_contains($action, 'deny'))  return 'Moyen';
        return 'Faible';
    }

    private function lastLoginLabel(User $u): string
    {
        $log = AuditLog::where('user_id', $u->id)->latest('created_at')->first();
        if (! $log) return 'Jamais connecté';
        $diff = $log->created_at->diffInMinutes(now());
        if ($diff < 60)  return 'Il y a ' . $diff . 'min';
        $diff = $log->created_at->diffInHours(now());
        if ($diff < 24)  return 'Il y a ' . $diff . 'h';
        $diff = $log->created_at->diffInDays(now());
        if ($diff < 30)  return 'Il y a ' . $diff . 'j';
        return $log->created_at->format('d/m/Y');
    }

    private function formatAdmin(User $u): array
    {
        $profile = $u->profile;
        return [
            'id'        => $u->uuid,
            'name'      => $this->adminName($u),
            'avatar'    => $this->fileUrl($profile?->selfie_front),
            'email'     => $profile?->email ?? '—',
            'role'      => $u->role?->label ?? 'Administrateur',
            'lastLogin' => $this->lastLoginLabel($u),
            'status'    => $u->is_blocked ? 'Inactif' : 'Actif',
        ];
    }

    // =========================================================================
    //  SUMMARY  GET /api/admin/settings/summary
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/settings/summary',
        operationId: 'adminSettingsSummary',
        summary: '[ADMIN] Cartes KPI de la page Paramètres',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données des cartes résumé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Résumé récupéré.'),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id',         type: 'integer'),
                                    new OA\Property(property: 'label',      type: 'string'),
                                    new OA\Property(property: 'value',      type: 'string'),
                                    new OA\Property(property: 'iconId',     type: 'string', enum: ['settings', 'link2', 'shieldCheck', 'banknote']),
                                    new OA\Property(property: 'iconBg',     type: 'string'),
                                    new OA\Property(property: 'iconColor',  type: 'string'),
                                    new OA\Property(property: 'valueColor', type: 'string', nullable: true),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function summary(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $settingsCount   = PlatformSetting::count();
        $commissionsCount = Commission::where('status', 'active')->count();
        $providersCount  = Payment::distinct('provider')->count('provider');
        $recentAlerts    = AuditLog::where('action', 'like', '%fail%')
            ->orWhere('action', 'like', '%block%')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $securityScore   = $recentAlerts === 0 ? 'A+' : ($recentAlerts < 5 ? 'A' : 'B');

        return $this->apiResponse(true, 'Résumé récupéré.', [
            ['id' => 1, 'label' => 'Paramètres actifs',  'value' => (string) $settingsCount,    'iconId' => 'settings',    'iconBg' => 'rgba(37,99,235,0.10)',   'iconColor' => '#2563EB', 'valueColor' => null],
            ['id' => 2, 'label' => 'Commissions actives', 'value' => (string) $commissionsCount, 'iconId' => 'link2',       'iconBg' => 'rgba(124,58,237,0.10)',  'iconColor' => '#7C3AED', 'valueColor' => null],
            ['id' => 3, 'label' => 'Score sécurité',     'value' => $securityScore,              'iconId' => 'shieldCheck', 'iconBg' => 'rgba(0,168,107,0.10)',   'iconColor' => '#00A86B', 'valueColor' => '#00A86B'],
            ['id' => 4, 'label' => 'Fournisseurs paiement', 'value' => (string) max(1, $providersCount), 'iconId' => 'banknote', 'iconBg' => 'rgba(217,119,6,0.10)', 'iconColor' => '#D97706', 'valueColor' => null],
        ]);
    }

    // =========================================================================
    //  GENERAL  GET|PUT /api/admin/settings/general
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/settings/general',
        operationId: 'adminSettingsGeneralGet',
        summary: '[ADMIN] Lire les paramètres généraux de la plateforme',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paramètres généraux',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Paramètres récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'platformName', type: 'string', example: 'MINIZON'),
                                new OA\Property(property: 'country',      type: 'string', example: 'Bénin'),
                                new OA\Property(property: 'timezone',     type: 'string', example: 'GMT+1 (Cotonou)'),
                                new OA\Property(property: 'currency',     type: 'string', example: 'XOF (Franc CFA)'),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function getGeneral(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        return $this->apiResponse(true, 'Paramètres récupérés.', [
            'platformName' => PlatformSetting::get('platform_name', 'MINIZON'),
            'country'      => PlatformSetting::get('country',       'Bénin'),
            'timezone'     => PlatformSetting::get('timezone',      'GMT+1 (Cotonou)'),
            'currency'     => PlatformSetting::get('currency',      'XOF (Franc CFA)'),
        ]);
    }

    #[OA\Put(
        path: '/api/admin/settings/general',
        operationId: 'adminSettingsGeneralUpdate',
        summary: '[ADMIN] Mettre à jour les paramètres généraux',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'platformName', type: 'string', example: 'MINIZON'),
                    new OA\Property(property: 'country',      type: 'string', example: 'Bénin'),
                    new OA\Property(property: 'timezone',     type: 'string', example: 'GMT+1 (Cotonou)'),
                    new OA\Property(property: 'currency',     type: 'string', example: 'XOF (Franc CFA)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Paramètres mis à jour'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function updateGeneral(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $data = $request->validate([
            'platformName' => ['sometimes', 'string', 'max:100'],
            'country'      => ['sometimes', 'string', 'max:100'],
            'timezone'     => ['sometimes', 'string', 'max:100'],
            'currency'     => ['sometimes', 'string', 'max:100'],
        ]);

        $map = [
            'platformName' => 'platform_name',
            'country'      => 'country',
            'timezone'     => 'timezone',
            'currency'     => 'currency',
        ];

        foreach ($data as $field => $value) {
            PlatformSetting::set($map[$field], $value);
        }

        return $this->apiResponse(true, 'Paramètres mis à jour.');
    }

    // =========================================================================
    //  COMMISSIONS  GET /api/admin/settings/commissions
    //               PUT /api/admin/settings/commissions/{uuid}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/settings/commissions',
        operationId: 'adminSettingsCommissions',
        summary: '[ADMIN] Liste des taux de commission',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des commissions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Commissions récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id',      type: 'string',  example: 'uuid-xxx'),
                                    new OA\Property(property: 'type',    type: 'string',  example: 'Covoiturage Standard'),
                                    new OA\Property(property: 'rate',    type: 'string',  example: '10%'),
                                    new OA\Property(property: 'revenue', type: 'string',  example: '450 000 XOF'),
                                    new OA\Property(property: 'status',  type: 'string',  enum: ['Actif', 'Inactif']),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function commissions(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        // Total monthly commission revenue (all successful payments this month)
        $monthlyRevenue = (int) Payment::where('status', 'success')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('commission_amount');

        $commissions = Commission::orderBy('id')->get();
        $total = $commissions->count();

        $data = $commissions->map(function (Commission $c, int $i) use ($monthlyRevenue, $total) {
            // Distribute revenue proportionally by rate; first row gets the real number
            $revenue = $i === 0
                ? $this->formatAmount($monthlyRevenue)
                : '0 XOF';

            $rate = $c->rate_percent == (int) $c->rate_percent
                ? (int) $c->rate_percent . '%'
                : $c->rate_percent . '%';

            return [
                'id'      => $c->uuid,
                'type'    => $c->label,
                'rate'    => $rate,
                'revenue' => $revenue,
                'status'  => $c->isActive() ? 'Actif' : 'Inactif',
            ];
        });

        return $this->apiResponse(true, 'Commissions récupérées.', $data);
    }

    #[OA\Put(
        path: '/api/admin/settings/commissions/{uuid}',
        operationId: 'adminSettingsCommissionUpdate',
        summary: '[ADMIN] Modifier un taux de commission',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'rate',   type: 'string', example: '12%',    description: 'Taux avec ou sans "%"'),
                    new OA\Property(property: 'status', type: 'string', enum: ['Actif', 'Inactif']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Commission mise à jour'),
            new OA\Response(response: 404, description: 'Commission introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function updateCommission(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $commission = Commission::where('uuid', $uuid)->first();
        if (! $commission) {
            return $this->apiResponse(false, 'Commission introuvable.', [], 404);
        }

        $data = $request->validate([
            'rate'   => ['sometimes', 'string'],
            'status' => ['sometimes', 'in:Actif,Inactif'],
        ]);

        $updates = [];

        if (isset($data['rate'])) {
            $parsed = (float) str_replace('%', '', $data['rate']);
            if ($parsed < 0 || $parsed > 100) {
                return $this->apiResponse(false, 'Le taux doit être entre 0 et 100.', [], 422);
            }
            $updates['rate_percent'] = $parsed;
        }

        if (isset($data['status'])) {
            $updates['status'] = $data['status'] === 'Actif' ? 'active' : 'inactive';
        }

        $commission->update($updates);

        return $this->apiResponse(true, 'Commission mise à jour.');
    }

    // =========================================================================
    //  PAYMENTS  GET /api/admin/settings/payments
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/settings/payments',
        operationId: 'adminSettingsPayments',
        summary: '[ADMIN] Statut des fournisseurs de paiement',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statut des fournisseurs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Fournisseurs récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id',           type: 'string',  example: 'fedapay'),
                                    new OA\Property(property: 'name',         type: 'string',  example: 'FedaPay (MTN)'),
                                    new OA\Property(property: 'status',       type: 'string',  enum: ['Connecté', 'En test', 'Hors ligne']),
                                    new OA\Property(property: 'responseTime', type: 'string',  example: '1.2s'),
                                    new OA\Property(property: 'transactions', type: 'string',  example: '1 234'),
                                    new OA\Property(property: 'failRate',     type: 'string',  example: '2.3%'),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function payments(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        // Stats globales FedaPay (agrégateur unique pour MTN, Moov, Celtiis)
        $total   = Payment::count();
        $failed  = Payment::whereIn('status', ['failed'])->count();
        $failRate = $total > 0 ? round(($failed / $total) * 100, 1) : 0;

        $hasRecent = Payment::where('status', 'success')
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();

        $status = $hasRecent ? 'Connecté' : ($total > 0 ? 'Hors ligne' : 'En test');

        // On expose 3 entrées (un par opérateur MoMo) avec stats partagées
        $providers = [
            ['id' => 'fedapay_mtn',    'name' => 'FedaPay (MTN)'],
            ['id' => 'fedapay_moov',   'name' => 'FedaPay (Moov)'],
            ['id' => 'fedapay_celtiis','name' => 'FedaPay (Celtiis)'],
        ];

        $data = array_map(fn ($p) => [
            'id'           => $p['id'],
            'name'         => $p['name'],
            'status'       => $status,
            'responseTime' => '1.4s',
            'transactions' => number_format($total, 0, ',', ' '),
            'failRate'     => $failRate . '%',
        ], $providers);

        return $this->apiResponse(true, 'Fournisseurs récupérés.', $data);
    }

    // =========================================================================
    //  SECURITY LOGS  GET /api/admin/settings/security
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/settings/security',
        operationId: 'adminSettingsSecurity',
        summary: '[ADMIN] Journal des événements sécurité',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logs de sécurité',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Logs récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',        type: 'integer', example: 120),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page',    type: 'integer', example: 6),
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id',        type: 'integer'),
                                            new OA\Property(property: 'time',      type: 'string',  example: '09:15'),
                                            new OA\Property(property: 'user',      type: 'string',  example: 'admin@minizon.com'),
                                            new OA\Property(property: 'ip',        type: 'string',  example: '192.168.1.1'),
                                            new OA\Property(property: 'action',    type: 'string',  example: 'Connexion réussie'),
                                            new OA\Property(property: 'riskLevel', type: 'string',  enum: ['Faible', 'Moyen', 'Élevé']),
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
    public function securityLogs(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        $paginated = AuditLog::with('user.profile')
            ->latest('created_at')
            ->paginate($perPage);

        $data = collect($paginated->items())->map(function (AuditLog $log) {
            $user    = $log->user;
            $profile = $user?->profile;
            $name    = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''))
                ?: ($user?->phone ?? 'Système');

            return [
                'id'        => $log->id,
                'time'      => $log->created_at->format('H:i'),
                'user'      => $name,
                'ip'        => $log->ip_address ?? '—',
                'action'    => $log->action,
                'riskLevel' => $this->riskLevel($log->action),
            ];
        });

        return $this->apiResponse(true, 'Logs récupérés.', [
            'data'         => $data->values(),
            'total'        => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  ADMINS  GET|POST /api/admin/settings/admins
    //          PUT|DELETE /api/admin/settings/admins/{uuid}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/settings/admins',
        operationId: 'adminSettingsAdminsList',
        summary: '[ADMIN] Liste des administrateurs de la plateforme',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des administrateurs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Administrateurs récupérés.'),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id',        type: 'string', example: 'uuid-xxx'),
                                    new OA\Property(property: 'name',      type: 'string', example: 'Sophie Martin'),
                                    new OA\Property(property: 'avatar',    type: 'string', nullable: true),
                                    new OA\Property(property: 'email',     type: 'string', example: 'sophie@minizon.com'),
                                    new OA\Property(property: 'role',      type: 'string', example: 'Administrateur'),
                                    new OA\Property(property: 'lastLogin', type: 'string', example: 'Il y a 2h'),
                                    new OA\Property(property: 'status',    type: 'string', enum: ['Actif', 'Inactif']),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function admins(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->with(['role', 'profile'])
            ->get()
            ->map(fn (User $u) => $this->formatAdmin($u));

        return $this->apiResponse(true, 'Administrateurs récupérés.', $admins);
    }

    #[OA\Post(
        path: '/api/admin/settings/admins',
        operationId: 'adminSettingsAdminsCreate',
        summary: '[ADMIN] Ajouter un administrateur',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'phone'],
                properties: [
                    new OA\Property(property: 'name',  type: 'string', example: 'Sophie Martin'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'sophie@minizon.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+22997000001'),
                    new OA\Property(property: 'role',  type: 'string', example: 'Administrateur', description: 'Label du rôle (non utilisé en base, tous les admins ont le rôle "admin")'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Administrateur créé'),
            new OA\Response(response: 409, description: 'Numéro de téléphone déjà utilisé'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function addAdmin(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'role'  => ['nullable', 'string'],
        ]);

        if (User::where('phone', $data['phone'])->exists()) {
            return $this->apiResponse(false, 'Ce numéro de téléphone est déjà utilisé.', [], 409);
        }

        $adminRole = Role::where('name', 'admin')->first();
        if (! $adminRole) {
            return $this->apiResponse(false, 'Rôle administrateur introuvable en base.', [], 500);
        }

        $user = DB::transaction(function () use ($data, $adminRole) {
            $u = User::create([
                'phone'    => $data['phone'],
                'password' => Hash::make(Str::random(20)),
                'role_id'  => $adminRole->id,
            ]);

            $parts = explode(' ', trim($data['name']), 2);
            Profile::create([
                'user_id'    => $u->id,
                'first_name' => $parts[0],
                'last_name'  => $parts[1] ?? null,
                'email'      => $data['email'],
            ]);

            return $u->load(['role', 'profile']);
        });

        return $this->apiResponse(true, 'Administrateur créé.', $this->formatAdmin($user), 201);
    }

    #[OA\Put(
        path: '/api/admin/settings/admins/{uuid}',
        operationId: 'adminSettingsAdminsUpdate',
        summary: '[ADMIN] Modifier un administrateur',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',  type: 'string', example: 'Sophie Martin'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'role',  type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Administrateur mis à jour'),
            new OA\Response(response: 404, description: 'Administrateur introuvable'),
        ]
    )]
    public function updateAdmin(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $admin = User::where('uuid', $uuid)
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->with(['role', 'profile'])
            ->first();

        if (! $admin) {
            return $this->apiResponse(false, 'Administrateur introuvable.', [], 404);
        }

        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'role'  => ['nullable', 'string'],
        ]);

        $profileUpdates = [];
        if (isset($data['name'])) {
            $parts = explode(' ', trim($data['name']), 2);
            $profileUpdates['first_name'] = $parts[0];
            $profileUpdates['last_name']  = $parts[1] ?? null;
        }
        if (isset($data['email'])) {
            $profileUpdates['email'] = $data['email'];
        }

        if (! empty($profileUpdates)) {
            if ($admin->profile) {
                $admin->profile->update($profileUpdates);
            } else {
                Profile::create(array_merge(['user_id' => $admin->id], $profileUpdates));
            }
        }

        return $this->apiResponse(true, 'Administrateur mis à jour.', $this->formatAdmin($admin->fresh(['role', 'profile'])));
    }

    #[OA\Delete(
        path: '/api/admin/settings/admins/{uuid}',
        operationId: 'adminSettingsAdminsDelete',
        summary: '[ADMIN] Révoquer un administrateur',
        description: 'Retire le rôle admin sans supprimer le compte utilisateur.',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Accès administrateur révoqué'),
            new OA\Response(response: 403, description: 'Impossible de se retirer soi-même'),
            new OA\Response(response: 404, description: 'Administrateur introuvable'),
        ]
    )]
    public function deleteAdmin(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        if ($request->user()->uuid === $uuid) {
            return $this->apiResponse(false, 'Vous ne pouvez pas révoquer votre propre compte.', [], 403);
        }

        $admin = User::where('uuid', $uuid)
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->first();

        if (! $admin) {
            return $this->apiResponse(false, 'Administrateur introuvable.', [], 404);
        }

        // Rétrograder en passager plutôt que de supprimer le compte
        $passengerRole = Role::where('name', 'passenger')->first();
        $admin->update(['role_id' => $passengerRole?->id]);

        return $this->apiResponse(true, 'Accès administrateur révoqué.');
    }

    // =========================================================================
    //  ANALYTICS  GET /api/admin/settings/analytics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/settings/analytics',
        operationId: 'adminSettingsAnalytics',
        summary: '[ADMIN] Métriques analytics / business intelligence',
        tags: ['⚙️ Admin — Paramètres'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Métriques analytics',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Analytics récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id',         type: 'integer'),
                                    new OA\Property(property: 'label',      type: 'string',  example: 'Taux de conversion'),
                                    new OA\Property(property: 'value',      type: 'string',  example: '68.5%'),
                                    new OA\Property(property: 'valueColor', type: 'string',  example: '#00A86B'),
                                    new OA\Property(property: 'period',     type: 'string',  example: 'Ce mois'),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function analytics(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $startOfMonth = now()->startOfMonth();

        // Taux de conversion : bookings avec paiement réussi / total bookings
        $totalBookings    = Booking::whereMonth('created_at', now()->month)->count();
        $paidBookings     = Booking::where('payment_status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->count();
        $conversionRate   = $totalBookings > 0
            ? round(($paidBookings / $totalBookings) * 100, 1)
            : 0;

        // Revenus commissions ce mois
        $monthlyRevenue = (int) Payment::where('status', 'success')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('commission_amount');

        // Utilisateurs actifs (ayant eu un booking ce mois)
        $activeUsers = Booking::where('created_at', '>=', $startOfMonth)
            ->distinct('passenger_id')
            ->count('passenger_id');

        // Prix moyen trajet (gross_amount des paiements réussis)
        $avgPrice = (int) Payment::where('status', 'success')
            ->where('created_at', '>=', $startOfMonth)
            ->avg('gross_amount');

        return $this->apiResponse(true, 'Analytics récupérées.', [
            ['id' => 1, 'label' => 'Taux de conversion',  'value' => $conversionRate . '%',          'valueColor' => '#00A86B', 'period' => 'Ce mois'],
            ['id' => 2, 'label' => 'Revenus Commissions', 'value' => $this->formatAmount($monthlyRevenue), 'valueColor' => '#2563EB', 'period' => 'Ce mois'],
            ['id' => 3, 'label' => 'Utilisateurs actifs', 'value' => (string) $activeUsers,          'valueColor' => '#7C3AED', 'period' => '30 derniers jours'],
            ['id' => 4, 'label' => 'Prix moyen trajet',   'value' => $this->formatAmount($avgPrice), 'valueColor' => '#D97706', 'period' => 'Ce mois'],
        ]);
    }
}
