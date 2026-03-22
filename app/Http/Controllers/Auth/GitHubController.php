<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GitHubController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['read:user', 'user:email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $user = $this->findOrCreateUser($githubUser);

        Auth::login($user, remember: true);

        return redirect('/');
    }

    /**
     * Find existing user by github_id or email, or create a new one.
     */
    private function findOrCreateUser(\Laravel\Socialite\Two\User $githubUser): User
    {
        // First, try to find by github_id
        $user = User::query()->where('github_id', $githubUser->getId())->first();

        if ($user) {
            $user->update([
                'github_token' => $githubUser->token,
                'avatar_url' => $githubUser->getAvatar(),
            ]);

            return $user;
        }

        // Second, try to find by email and link GitHub account
        $user = User::query()->where('email', $githubUser->getEmail())->first();

        if ($user) {
            $user->update([
                'github_id' => $githubUser->getId(),
                'github_token' => $githubUser->token,
                'avatar_url' => $githubUser->getAvatar(),
            ]);

            return $user;
        }

        // Third, create a new user
        $user = User::create([
            'name' => $githubUser->getName() ?? $githubUser->getNickname(),
            'email' => $githubUser->getEmail(),
            'github_id' => $githubUser->getId(),
            'github_token' => $githubUser->token,
            'avatar_url' => $githubUser->getAvatar(),
        ]);

        $user->currentTeam();

        return $user;
    }
}
