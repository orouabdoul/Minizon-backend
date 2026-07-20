<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Modération des évaluations — Back-Office Admin (ReviewsScreen).
 *
 * Endpoints :
 *   GET   /api/admin/reviews/stats        — KPIs
 *   GET   /api/admin/reviews              — liste filtrée
 *   PATCH /api/admin/reviews/{uuid}/status — changer statut (visible|masqué|signalé)
 *   DELETE /api/admin/reviews/{uuid}       — supprimer définitivement
 */
class AdminReviewController extends Controller
{
    // =========================================================================
    //  GET /api/admin/reviews/stats
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/reviews/stats',
        operationId: 'adminReviewStats',
        summary: 'KPIs des évaluations',
        tags: ['👑 Admin — Modération des avis'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total',     type: 'integer', example: 248),
                                new OA\Property(property: 'avgRating', type: 'string',  example: '4.2'),
                                new OA\Property(property: 'signalé',   type: 'integer', example: 5),
                                new OA\Property(property: 'masqué',    type: 'integer', example: 12),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function stats(): JsonResponse
    {
        $avg = Review::avg('rating');

        return $this->apiResponse(true, 'Statistiques.', [
            'total'     => Review::count(),
            'avgRating' => $avg ? number_format((float) $avg, 1) : '0.0',
            'signalé'   => Review::where('status', 'signalé')->count(),
            'masqué'    => Review::where('status', 'masqué')->count(),
        ]);
    }

    // =========================================================================
    //  GET /api/admin/reviews
    // =========================================================================

    #[OA\Get(
        path: '/api/admin/reviews',
        operationId: 'adminReviewList',
        summary: 'Liste filtrée des évaluations',
        description: 'Retourne tous les avis avec info auteur, cible, trajet. Filtrable par statut, direction (rôle de l\'auteur) et note.',
        tags: ['👑 Admin — Modération des avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search',    in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status',    in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'visible', 'masqué', 'signalé'], default: 'all')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'passager_vers_conducteur', 'conducteur_vers_passager'], default: 'all')),
            new OA\Parameter(name: 'rating',    in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', '1', '2', '3', '4', '5'], default: 'all')),
            new OA\Parameter(name: 'per_page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 30)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des avis'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $q = Review::with([
            'reviewer.profile',
            'reviewer.role',
            'reviewee.profile',
            'trip',
        ])->orderByDesc('report_count')->orderByDesc('created_at');

        // Filtre statut
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $q->where('status', $request->input('status'));
        }

        // Filtre note
        if ($request->filled('rating') && $request->input('rating') !== 'all') {
            $q->where('rating', (int) $request->input('rating'));
        }

        // Filtre direction (rôle du reviewer)
        if ($request->filled('direction') && $request->input('direction') !== 'all') {
            $roleName = $request->input('direction') === 'passager_vers_conducteur' ? 'passenger' : 'driver';
            $q->whereHas('reviewer.role', fn ($r) => $r->where('name', $roleName));
        }

        // Recherche texte
        if ($request->filled('search')) {
            $s = $request->input('search');
            $q->where(function ($sub) use ($s) {
                $sub->where('comment', 'like', "%{$s}%")
                    ->orWhereHas('reviewer.profile', fn ($p) =>
                        $p->where('first_name', 'like', "%{$s}%")
                          ->orWhere('last_name',  'like', "%{$s}%")
                    )
                    ->orWhereHas('reviewee.profile', fn ($p) =>
                        $p->where('first_name', 'like', "%{$s}%")
                          ->orWhere('last_name',  'like', "%{$s}%")
                    );
            });
        }

        $perPage   = min((int) $request->input('per_page', 30), 100);
        $paginated = $q->paginate($perPage);

        return $this->apiResponse(true, 'Évaluations.', [
            'reviews'  => collect($paginated->items())->map(fn (Review $r) => $this->formatReview($r)),
            'total'    => $paginated->total(),
            'page'     => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
        ]);
    }

    // =========================================================================
    //  PATCH /api/admin/reviews/{uuid}/status
    // =========================================================================

    #[OA\Patch(
        path: '/api/admin/reviews/{uuid}/status',
        operationId: 'adminReviewSetStatus',
        summary: 'Changer le statut de modération d\'un avis',
        tags: ['👑 Admin — Modération des avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['visible', 'masqué', 'signalé'], example: 'masqué'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut mis à jour'),
            new OA\Response(response: 404, description: 'Avis introuvable'),
        ]
    )]
    public function setStatus(Request $request, string $uuid): JsonResponse
    {
        $review = Review::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|in:visible,masqué,signalé',
        ]);

        $oldStatus = $review->status;
        $review->update(['status' => $validated['status']]);

        AuditLog::record(
            action:      'review.status',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'modif_parametre',
            severity:    $validated['status'] === 'signalé' ? 'avertissement' : 'info',
            description: "Avis #{$review->uuid} : statut {$oldStatus} → {$validated['status']}",
            targetType:  'review',
            targetName:  "Avis " . strtoupper(substr($review->uuid, 0, 8)),
            userAgent:   $request->userAgent(),
        );

        return $this->apiResponse(true, 'Statut mis à jour.', [
            'status' => $review->status,
        ]);
    }

    // =========================================================================
    //  DELETE /api/admin/reviews/{uuid}
    // =========================================================================

    #[OA\Delete(
        path: '/api/admin/reviews/{uuid}',
        operationId: 'adminReviewDelete',
        summary: 'Supprimer définitivement un avis',
        tags: ['👑 Admin — Modération des avis'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Avis supprimé'),
            new OA\Response(response: 404, description: 'Avis introuvable'),
        ]
    )]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $review = Review::where('uuid', $uuid)->firstOrFail();

        AuditLog::record(
            action:      'review.delete',
            userId:      auth()->id(),
            ip:          $request->ip(),
            actionType:  'suppression',
            severity:    'avertissement',
            description: "Avis #{$uuid} supprimé (note {$review->rating}/5)",
            targetType:  'review',
            targetName:  "Avis " . strtoupper(substr($uuid, 0, 8)),
            userAgent:   $request->userAgent(),
        );

        $review->delete();

        return $this->apiResponse(true, 'Avis supprimé.');
    }

    // =========================================================================
    //  HELPER PRIVÉ
    // =========================================================================

    private function formatReview(Review $r): array
    {
        $reviewerProfile = $r->reviewer?->profile;
        $revieweeProfile = $r->reviewee?->profile;
        $reviewerRole    = $r->reviewer?->role?->name; // 'passenger' | 'driver'

        $authorName = $this->profileName($reviewerProfile, $r->reviewer?->phone);
        $targetName = $this->profileName($revieweeProfile, $r->reviewee?->phone);

        $direction = $reviewerRole === 'passenger'
            ? 'passager_vers_conducteur'
            : 'conducteur_vers_passager';

        $tripShortId = $r->trip ? 'TRIP-' . strtoupper(substr($r->trip->uuid, 0, 8)) : '—';

        return [
            'id'           => $r->uuid,
            'authorName'   => $authorName,
            'authorAvatar' => $this->avatar($reviewerProfile, $authorName),
            'targetName'   => $targetName,
            'targetAvatar' => $this->avatar($revieweeProfile, $targetName),
            'direction'    => $direction,
            'rating'       => $r->rating,
            'comment'      => $r->comment ?? '',
            'status'       => $r->status ?? 'visible',
            'tripId'       => $tripShortId,
            'createdAt'    => $r->created_at->toIso8601String(),
            'reportCount'  => $r->report_count ?? 0,
        ];
    }

    private function profileName($profile, ?string $phone): string
    {
        $name = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));
        return ! empty($name) ? $name : ($phone ?? 'Utilisateur');
    }

    private function avatar($profile, string $name): string
    {
        return $profile?->selfie_front
            ? asset('storage/' . $profile->selfie_front)
            : 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=00A86B&color=fff';
    }
}
