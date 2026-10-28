<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\DriverSuspended;
use App\Notifications\DriverWarningIssued;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des sanctions conducteur (admin uniquement).
 *
 * POST /admin/users/{uuid}/warn      → émettre un avertissement
 * POST /admin/users/{uuid}/suspend   → suspendre manuellement
 * POST /admin/users/{uuid}/unsuspend → lever une suspension
 */
class AdminSanctionController extends Controller
{

    private const VISIBILITY_PENALTIES = [1 => -10, 2 => -20];
    private const SUSPENSION_AT        = 3;
    private const SUSPENSION_DAYS      = 7;

    // =========================================================================
    //  POST /admin/users/{uuid}/warn
    // =========================================================================

    public function warn(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = User::where('uuid', $uuid)->firstOrFail();

        $newCount = ($user->warning_count ?? 0) + 1;
        $currentScore = $user->visibility_score ?? 100;

        $user->warning_count = $newCount;

        if (isset(self::VISIBILITY_PENALTIES[$newCount])) {
            $user->visibility_score = max(0, $currentScore + self::VISIBILITY_PENALTIES[$newCount]);
        }

        if ($newCount >= self::SUSPENSION_AT) {
            $suspendedUntil = now()->addDays(self::SUSPENSION_DAYS);
            $user->suspended_until = $suspendedUntil;
            $user->save();

            $user->notify(new DriverSuspended(
                reason: $request->input('reason'),
                suspendedUntil: $suspendedUntil->format('d/m/Y'),
                durationDays: self::SUSPENSION_DAYS,
            ));

            return $this->apiResponse(true, 'Conducteur suspendu (3e avertissement).', [
                'warning_count'   => $user->warning_count,
                'visibility_score'=> $user->visibility_score,
                'suspended_until' => $suspendedUntil->toIso8601String(),
            ]);
        }

        $user->save();

        $user->notify(new DriverWarningIssued(
            warningNumber: $newCount,
            reason: $request->input('reason'),
            visibilityScore: $user->visibility_score ?? 100,
        ));

        return $this->apiResponse(true, "Avertissement #{$newCount} émis.", [
            'warning_count'   => $user->warning_count,
            'visibility_score'=> $user->visibility_score,
            'suspended_until' => null,
        ]);
    }

    // =========================================================================
    //  POST /admin/users/{uuid}/suspend
    // =========================================================================

    public function suspend(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $request->validate([
            'reason'       => 'required|string|max:500',
            'duration_days'=> 'required|integer|min:1|max:365',
        ]);

        $user = User::where('uuid', $uuid)->firstOrFail();

        $suspendedUntil = now()->addDays($request->integer('duration_days'));
        $user->suspended_until = $suspendedUntil;
        $user->save();

        $user->notify(new DriverSuspended(
            reason: $request->input('reason'),
            suspendedUntil: $suspendedUntil->format('d/m/Y'),
            durationDays: $request->integer('duration_days'),
        ));

        return $this->apiResponse(true, 'Conducteur suspendu.', [
            'suspended_until' => $suspendedUntil->toIso8601String(),
        ]);
    }

    // =========================================================================
    //  POST /admin/users/{uuid}/unsuspend
    // =========================================================================

    public function unsuspend(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->apiResponse(false, 'Accès refusé.', [], 403);
        }

        $user = User::where('uuid', $uuid)->firstOrFail();
        $user->suspended_until = null;
        $user->save();

        return $this->apiResponse(true, 'Suspension levée.');
    }
}
