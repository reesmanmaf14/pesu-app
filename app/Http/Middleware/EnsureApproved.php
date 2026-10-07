<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pending and rejected accounts may only see their status page (checked on every request). */
class EnsureApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isApproved()) {
            if ($request->expectsJson()) {
                abort(403, 'Your account has not been approved yet.');
            }

            return redirect()->route('account.status');
        }

        return $next($request);
    }
}
