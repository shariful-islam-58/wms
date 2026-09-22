<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWmsRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => [],
            ], 401);
        }

        if (! $user->hasWmsAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'WMS role required.',
                'errors' => [],
            ], 403);
        }

        if ($roles !== [] && ! in_array($user->wms_role?->value, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient WMS permissions.',
                'errors' => [],
            ], 403);
        }

        return $next($request);
    }
}
