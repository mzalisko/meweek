<?php

namespace App\Http\Middleware;

use App\Admin\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(AccessControl::class)->canUseAdmin($request->user()), 403);

        return $next($request);
    }
}
