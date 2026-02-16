<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Prefer cookie (set client-side for instant application), fall back to DB preference
        $appearance = $request->cookie('appearance');

        if (! $appearance && $request->user()) {
            $appearance = $request->user()->theme_preference;
        }

        View::share('appearance', $appearance ?? 'system');

        return $next($request);
    }
}
