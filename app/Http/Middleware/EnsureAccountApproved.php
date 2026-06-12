<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        if ($user->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte a été suspendu. Contactez l\'assistance.',
                'body'    => [],
            ], 403);
        }

        if (! $user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est en attente de validation par un administrateur.',
                'body'    => ['status' => 'pending_approval'],
            ], 403);
        }

        return $next($request);
    }
}
