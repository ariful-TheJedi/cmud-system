<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Require branch assignment for billing workflows while preserving super-admin access. */
class RequireBillingBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Branch-specific billing is unavailable to non-admin staff without an assigned branch.
        if ($user !== null && ! $user->is_super_admin && $user->branch_id === null) {
            abort(403, 'A branch assignment is required to access billing.');
        }

        return $next($request);
    }
}
