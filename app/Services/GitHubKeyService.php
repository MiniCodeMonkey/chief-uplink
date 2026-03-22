<?php

namespace App\Services;

use App\Models\SshKey;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GitHubKeyService
{
    /**
     * Add an SSH public key to the authenticated user's GitHub account.
     *
     * @throws RequestException
     */
    public function addKeyToGitHub(string $githubToken, SshKey $sshKey): array
    {
        $response = Http::withToken($githubToken)
            ->post('https://api.github.com/user/keys', [
                'title' => "chief-uplink: {$sshKey->name}",
                'key' => $sshKey->public_key,
            ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Check if the GitHub token has the admin:public_key scope.
     */
    public function hasPublicKeyScope(string $githubToken): bool
    {
        $response = Http::withToken($githubToken)
            ->get('https://api.github.com/user');

        if ($response->failed()) {
            return false;
        }

        $scopes = $response->header('X-OAuth-Scopes');

        if (! $scopes) {
            return false;
        }

        $scopeList = array_map('trim', explode(',', $scopes));

        return in_array('admin:public_key', $scopeList);
    }
}
