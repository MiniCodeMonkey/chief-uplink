<?php

namespace App\Http\Controllers;

use App\Models\ManagedServer;
use App\Models\SshKey;
use App\Services\GitHubKeyService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GitHubKeyController extends Controller
{
    public function __construct(public GitHubKeyService $gitHubKeyService) {}

    /**
     * Add the server's SSH key to the user's GitHub account.
     */
    public function store(Request $request, ManagedServer $server): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();

        // Ensure the server belongs to the user's current team
        if ($server->team_id !== $team->id) {
            abort(403);
        }

        $sshKey = $server->sshKey;

        if (! $sshKey) {
            return back()->with('error', 'No SSH key associated with this server.');
        }

        if (! $user->github_token) {
            return back()->with('error', 'GitHub account not connected. Please log in with GitHub first.');
        }

        // Check if token has required scope
        if (! $this->gitHubKeyService->hasPublicKeyScope($user->github_token)) {
            // Store the SSH key ID in session for after re-auth
            session(['github_key_ssh_key_id' => $sshKey->id]);

            return redirect()->route('github.keys.authorize');
        }

        return $this->attemptAddKey($user->github_token, $sshKey);
    }

    /**
     * Redirect to GitHub with incremental admin:public_key scope.
     */
    public function authorize(): SymfonyRedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['read:user', 'user:email', 'admin:public_key'])
            ->redirect();
    }

    /**
     * Handle the GitHub OAuth callback with the new scope.
     */
    public function callback(Request $request): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $user = $request->user();

        // Update the token with the new scope
        $user->update([
            'github_token' => $githubUser->token,
        ]);

        // If we have a pending SSH key to add, do it now
        $sshKeyId = session()->pull('github_key_ssh_key_id');

        if ($sshKeyId) {
            $team = $user->currentTeam();
            $sshKey = $team->sshKeys()->find($sshKeyId);

            if ($sshKey) {
                return $this->attemptAddKey($githubUser->token, $sshKey, redirect: true);
            }
        }

        return redirect()->route('servers.index')
            ->with('success', 'GitHub permissions updated.');
    }

    /**
     * Try to add the SSH key to GitHub, returning appropriate redirect.
     */
    private function attemptAddKey(string $token, SshKey $sshKey, bool $redirect = false): RedirectResponse
    {
        try {
            $this->gitHubKeyService->addKeyToGitHub($token, $sshKey);

            $response = $redirect
                ? redirect()->route('servers.index')
                : back();

            return $response->with('success', 'SSH key added to GitHub successfully.');
        } catch (RequestException $e) {
            $message = 'Failed to add SSH key to GitHub.';

            if ($e->response->status() === 422) {
                $message = 'This key already exists on your GitHub account.';
            }

            $response = $redirect
                ? redirect()->route('servers.index')
                : back();

            return $response->with('error', $message);
        }
    }
}
