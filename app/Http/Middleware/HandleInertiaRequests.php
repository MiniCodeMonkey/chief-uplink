<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => $request->user() ? [
                'user' => $request->user()->only('id', 'name', 'email', 'avatar_url', 'theme_preference'),
                'currentTeam' => [
                    'id' => $request->user()->currentTeam()->id,
                    'name' => $request->user()->currentTeam()->name,
                ],
                'teams' => $request->user()->teams()->get()->map(fn ($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                ]),
            ] : null,
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
