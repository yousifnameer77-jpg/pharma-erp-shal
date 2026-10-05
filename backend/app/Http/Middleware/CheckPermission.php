<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route usage: ->middleware('permission:sales.create')
 *
 * The branch/warehouse scope for the check is read from request headers so the
 * same permission code can be enforced consistently whether the acting branch
 * comes from the user's own assignment (POS/pharmacist clients) or is chosen
 * explicitly (HQ back-office switching between branches).
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Account is inactive or unauthenticated.');
        }

        $branchId = $request->header('X-Branch-Id');
        $warehouseId = $request->header('X-Warehouse-Id');

        if (! $user->hasPermission($permission, $branchId, $warehouseId)) {
            abort(403, "Missing permission: {$permission}");
        }

        return $next($request);
    }
}
