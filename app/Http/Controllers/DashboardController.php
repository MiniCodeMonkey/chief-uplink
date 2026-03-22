<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        if (! $request->user()) {
            return Inertia::render('Welcome');
        }

        $lastVisitedUrl = $request->session()->get('last_visited_url');

        if ($lastVisitedUrl) {
            return redirect($lastVisitedUrl);
        }

        return Inertia::render('Dashboard');
    }
}
