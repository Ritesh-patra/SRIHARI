<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /** Map route role aliases → accepted user.role values */
    private array $aliases = [
        'super_admin' => ['super_admin'],
        'admin' => ['admin', 'super_admin'],
        'project_manager' => ['project_manager', 'admin', 'super_admin'],
        'manager' => ['manager', 'supervisor', 'project_manager', 'admin', 'super_admin'],
        'field_executive' => ['field_executive', 'surveyor', 'admin', 'super_admin'],
        // legacy
        'supervisor' => ['manager', 'supervisor', 'project_manager', 'admin', 'super_admin'],
        'surveyor' => ['field_executive', 'surveyor', 'admin', 'super_admin'],
    ];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Unauthorized for this action.');
        }

        $allowed = [];
        foreach ($roles as $role) {
            $allowed = array_merge($allowed, $this->aliases[$role] ?? [$role]);
        }

        if (! in_array($user->role, array_unique($allowed), true)) {
            abort(403, 'Unauthorized for this action.');
        }

        return $next($request);
    }
}
