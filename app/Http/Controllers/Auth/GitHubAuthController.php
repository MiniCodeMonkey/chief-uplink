<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;

class GitHubAuthController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $user = User::withTrashed()->where('github_id', $githubUser->getId())->first();

        if ($user && $user->trashed()) {
            return redirect()->route('login')->with('status', 'This account has been deleted.');
        }

        $user = User::updateOrCreate(
            ['github_id' => $githubUser->getId()],
            [
                'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'github_username' => $githubUser->getNickname(),
                'avatar_url' => $githubUser->getAvatar(),
                'email' => $githubUser->getEmail() ?? optional(User::where('github_id', $githubUser->getId())->first())->email,
            ]
        );

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(): RedirectResponse
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
