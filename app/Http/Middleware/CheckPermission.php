<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * Route-level permission gate. Usage: ->middleware('permission:patients')
     * (defaults to the 'view' action) or ->middleware('permission:patients,delete').
     *
     * This is the backend enforcement layer described in the RBAC spec: a
     * hidden sidebar item must not mean an unprotected endpoint, so every
     * admin route carries this regardless of how the request arrives (UI
     * click, typed URL, or a raw API call).
     */
    public function handle(Request $request, Closure $next, string $module, string $action = 'view')
    {
        $user = $request->user();

        if (!$user || !$user->is_active) {
            abort(403, 'Access denied.');
        }

        if (!$user->hasPermission($module, $action)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
