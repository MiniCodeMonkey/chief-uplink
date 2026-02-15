<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailProvided
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->email === null) {
            if (! $request->routeIs('email-capture.*', 'logout')) {
                return redirect()->route('email-capture.show');
            }
        }

        return $next($request);
    }
}
