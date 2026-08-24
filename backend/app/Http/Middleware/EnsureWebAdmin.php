<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web portal is Super Admin only (full control + reports).
 * Manager / Project Manager / Field Executive use the Flutter app.
 */
class EnsureWebAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! ($user->isSuperAdmin() || $user->isAdmin())) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Web portal is for Super Admin only. Use the SEAS mobile app.',
                ], 403);
            }

            return redirect()->route('mobile.only');
        }

        return $next($request);
    }
}
