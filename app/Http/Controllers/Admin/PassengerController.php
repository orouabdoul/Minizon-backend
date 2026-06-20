<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '🧑 Admin — Passagers', description: 'Supervision et gestion des passagers')]
class PassengerController extends Controller
{
    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function passengersQuery()
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'passenger'))
            ->with('profile')
            ->withCount('bookings')
            ->withSum(
                ['payments as total_spending' => fn ($q) => $q->whereIn('status', ['locked', 'success'])],
                'gross_amount'
            )
            ->withAvg('reviewsReceived as avg_rating', 'rating');
    }

    private function passengerStatus(User $user): string
    {
        if ($user->is_blocked) return 'Suspendu';
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

    private function trustScore(User $user): int
    {
        return max(0, 100 - ($user->penalty_points * 15));
    }

    private function riskLevel(User $user): string
    {
        $score = $this->trustScore($user);
        if ($user->penalty_points >= 5 || $score < 25) return 'Élevé';
        if ($user->penalty_points >= 2 || $score < 60) return 'Moyen';
        return 'Faible';
    }

    private function formatAmount(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' XOF';
    }

    private function activityStatus(User $user): string
    {
        return $user->updated_at?->gt(Carbon::now()->subMinutes(30)) ? 'En ligne' : 'Hors ligne';
    }

    private function format(User $user): array
    {
        $profile   = $user->profile;
        $firstName = $profile?->first_name ?? '';
        $lastName  = $profile?->last_name  ?? '';

        return [
            'id'          => $user->uuid,
            'name'        => trim("{$firstName} {$lastName}") ?: $user->phone,
            'passengerId' => 'PSG-' . strtoupper(substr($user->uuid, 0, 8)),
            'phone'       => $user->phone,
            'email'       => $profile?->email ?? null,
            'city'        => $profile?->city  ?? null,
            'avatar'      => $this->fileUrl($profile?->selfie_front),
            'selfies' => [
                'front' => $this->fileUrl($profile?->selfie_front),
                'left'  => $this->fileUrl($profile?->selfie_left),
                'right' => $this->fileUrl($profile?->selfie_right),
            ],
            'idCard' => [
                'front' => $this->fileUrl($profile?->id_card_front),
                'back'  => $this->fileUrl($profile?->id_card_back),
            ],
            'score'         => $profile?->kyc_matching_score ?? 0,
            'verification'  => $this->verificationLabel($profile?->kyc_status),
            'reservations'  => $user->bookings_count ?? 0,
            'spending'      => $this->formatAmount((int) ($user->total_spending ?? 0)),
            'rating'        => $user->avg_rating ? round((float) $user->avg_rating, 1) : null,
            'trustScore'    => $this->trustScore($user),
            'createdAt'     => $user->created_at?->translatedFormat('d M Y'),
            'createdAgo'    => $user->created_at?->diffForHumans(),
            'lastActivity'  => $user->updated_at?->translatedFormat('d M Y'),
            'activityStatus'=> $this->activityStatus($user),
            'status'        => $this->passengerStatus($user),
            'riskLevel'     => $this->riskLevel($user),
        ];
    }

    private function applyFilters($query, Request $request): void
    {
        $city   = $request->input('city',   '');
        $risk   = $request->input('risk',   '');
        $status = $request->input('status', '');
        $verif  = $request->input('verif',  '');
        $search = trim($request->input('search', ''));

        if ($city !== '') {
            $query->whereHas('profile', fn ($q) => $q->where('city', $city));
        }

        if ($risk !== '') {
            match ($risk) {
                'Faible' => $query->where('penalty_points', '<', 2),
                'Moyen'  => $query->whereBetween('penalty_points', [2, 4]),
                'Élevé'  => $query->where('penalty_points', '>=', 5),
                default  => null,
            };
        }

        if ($status !== '') {
            match ($status) {
                'Actif'    => $query->where('is_blocked', false)->where('is_verified', true),
                'Inactif'  => $query->where('is_blocked', false)->where('is_verified', false),
                'Suspendu' => $query->where('is_blocked', true),
                default    => null,
            };
        }

        if ($verif !== '') {
            $query->whereHas('profile', fn ($q) => $q->where('kyc_status', $verif));
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
    }

    // =========================================================================
    //  METRICS  GET /api/admin/passengers/metrics
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/passengers/metrics',
        operationId: 'adminPassengerMetrics',
        summary: '[ADMIN] Métriques des passagers',
        description: 'Retourne les 8 KPI affichés en haut du tableau de bord passagers.',
        tags: ['🧑 Admin — Passagers'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Métriques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Métriques passagers récupérées.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',             type: 'integer', example: 2487),
                                new OA\Property(property: 'active',            type: 'integer', example: 1934),
                                new OA\Property(property: 'new_this_month',    type: 'integer', example: 312),
                                new OA\Property(property: 'bookings_month',    type: 'integer', example: 847),
                                new OA\Property(property: 'cancellation_rate', type: 'number',  example: 8.3),
                                new OA\Property(property: 'incidents',         type: 'integer', example: 23),
                                new OA\Property(property: 'avg_rating',        type: 'number',  example: 4.2),
                                new OA\Property(property: 'suspended',         type: 'integer', example: 45),
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

        $base = fn () => User::whereHas('role', fn ($q) => $q->where('name', 'passenger'));

        $total          = $base()->count();
        $active         = $base()->where('is_blocked', false)->where('is_verified', true)->count();
        $suspended      = $base()->where('is_blocked', true)->count();
        $newThisMonth   = $base()->whereMonth('created_at', now()->month)
                                  ->whereYear('created_at', now()->year)
                                  ->count();

        $passengerIds   = $base()->pluck('id');

        $bookingsMonth  = Booking::whereIn('passenger_id', $passengerIds)
                                  ->whereMonth('created_at', now()->month)
                                  ->whereYear('created_at', now()->year)
                                  ->count();

        $totalBookings      = Booking::whereIn('passenger_id', $passengerIds)->count();
        $cancelledBookings  = Booking::whereIn('passenger_id', $passengerIds)
                                      ->where('status', 'cancelled')
                                      ->count();
        $cancellationRate   = $totalBookings > 0
            ? round(($cancelledBookings / $totalBookings) * 100, 1)
            : 0;

        $incidents   = $base()->where('penalty_points', '>', 0)->count();
        $avgRating   = \App\Models\Review::whereIn('reviewee_id', $passengerIds)->avg('rating');

        return $this->apiResponse(true, 'Métriques passagers récupérées.', [
            'total'             => $total,
            'active'            => $active,
            'new_this_month'    => $newThisMonth,
            'bookings_month'    => $bookingsMonth,
            'cancellation_rate' => $cancellationRate,
            'incidents'         => $incidents,
            'avg_rating'        => $avgRating ? round((float) $avgRating, 1) : null,
            'suspended'         => $suspended,
        ]);
    }

    // =========================================================================
    //  INDEX  GET /api/admin/passengers
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/passengers',
        operationId: 'adminPassengerIndex',
        summary: '[ADMIN] Lister les passagers',
        description: 'Liste paginée des passagers avec filtres par ville, risque, statut, vérification et recherche.',
        tags: ['🧑 Admin — Passagers'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page',     in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search',   in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'city',     in: 'query', schema: new OA\Schema(type: 'string'), description: 'Filtre par ville (ex: Cotonou)'),
            new OA\Parameter(name: 'risk',     in: 'query', schema: new OA\Schema(type: 'string', enum: ['Faible', 'Moyen', 'Élevé'])),
            new OA\Parameter(name: 'status',   in: 'query', schema: new OA\Schema(type: 'string', enum: ['Actif', 'Inactif', 'Suspendu'])),
            new OA\Parameter(name: 'verif',    in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des passagers'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $query   = $this->passengersQuery();

        $this->applyFilters($query, $request);

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        Carbon::setLocale('fr');

        return $this->apiResponse(true, 'Passagers récupérés.', [
            'data'         => $paginated->map(fn ($p) => $this->format($p))->values(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // =========================================================================
    //  SHOW  GET /api/admin/passengers/{uuid}
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/passengers/{uuid}',
        operationId: 'adminPassengerShow',
        summary: '[ADMIN] Détail d\'un passager',
        tags: ['🧑 Admin — Passagers'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Passager trouvé'),
            new OA\Response(response: 404, description: 'Passager introuvable'),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        Carbon::setLocale('fr');

        $user = $this->passengersQuery()->where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Passager introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Passager récupéré.', $this->format($user));
    }

    // =========================================================================
    //  SUSPEND  PUT /api/admin/passengers/{uuid}/suspend
    // =========================================================================

    #[OA\Put(
        path: '/api/admin/passengers/{uuid}/suspend',
        operationId: 'adminPassengerSuspend',
        summary: '[ADMIN] Suspendre un passager',
        description: 'Bloque le compte passager (is_blocked → true). Il ne peut plus effectuer de réservations.',
        tags: ['🧑 Admin — Passagers'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Passager suspendu'),
            new OA\Response(response: 404, description: 'Passager introuvable'),
            new OA\Response(response: 422, description: 'Déjà suspendu'),
        ]
    )]
    public function suspend(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $user = $this->passengersQuery()->where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Passager introuvable.', [], 404);
        }

        if ($user->is_blocked) {
            return $this->apiResponse(false, 'Ce passager est déjà suspendu.', [], 422);
        }

        $user->update(['is_blocked' => true]);
        $user->refresh()->load('profile');

        Carbon::setLocale('fr');

        return $this->apiResponse(true, 'Passager suspendu avec succès.', $this->format($user));
    }

    // =========================================================================
    //  UNSUSPEND  PUT /api/admin/passengers/{uuid}/unsuspend
    // =========================================================================

    #[OA\Put(
        path: '/api/admin/passengers/{uuid}/unsuspend',
        operationId: 'adminPassengerUnsuspend',
        summary: '[ADMIN] Réactiver un passager',
        description: 'Lève la suspension d\'un passager (is_blocked → false).',
        tags: ['🧑 Admin — Passagers'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Passager réactivé'),
            new OA\Response(response: 404, description: 'Passager introuvable'),
            new OA\Response(response: 422, description: 'Passager non suspendu'),
        ]
    )]
    public function unsuspend(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Action réservée aux administrateurs.', [], 403);
        }

        $user = $this->passengersQuery()->where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Passager introuvable.', [], 404);
        }

        if (! $user->is_blocked) {
            return $this->apiResponse(false, 'Ce passager n\'est pas suspendu.', [], 422);
        }

        $user->update(['is_blocked' => false]);
        $user->refresh()->load('profile');

        Carbon::setLocale('fr');

        return $this->apiResponse(true, 'Passager réactivé avec succès.', $this->format($user));
    }
}
