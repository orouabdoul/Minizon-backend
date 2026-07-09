<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isAdmin() && $user->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte a été suspendu. Contactez l\'assistance.',
                'body'    => [
                    'account_status' => 'suspended',
                    'blocked_until'  => $user->blocked_until?->toIso8601String(),
                ],
            ], 403);
        }

        return $next($request);
    }
}
