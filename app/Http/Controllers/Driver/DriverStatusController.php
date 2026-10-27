<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Retourne le statut de sanction du conducteur connecté.
 * Appelé au démarrage de l'app pour afficher un éventuel bandeau de suspension.
 *
 * GET /driver/status
 */
class DriverStatusController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $suspendedUntil = $user->suspended_until
            ? \Carbon\Carbon::parse($user->suspended_until)
            : null;

        $isSuspended = $suspendedUntil !== null && $suspendedUntil->isFuture();

        return $this->apiResponse(true, 'Statut conducteur.', [
            'warning_count'   => $user->warning_count   ?? 0,
            'visibility_score'=> $user->visibility_score ?? 100,
            'is_suspended'    => $isSuspended,
            'suspended_until' => $isSuspended ? $suspendedUntil->toIso8601String() : null,
        ]);
    }
}
