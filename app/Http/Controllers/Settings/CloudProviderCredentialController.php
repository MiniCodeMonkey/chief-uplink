<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCloudProviderCredentialRequest;
use App\Http\Requests\Settings\UpdateCloudProviderCredentialRequest;
use App\Models\CloudProviderCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CloudProviderCredentialController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        return Inertia::render('Settings/Credentials', [
            'credentials' => $team->cloudProviderCredentials()
                ->orderBy('name')
                ->get()
                ->map(fn (CloudProviderCredential $credential) => [
                    'id' => $credential->id,
                    'name' => $credential->name,
                    'provider' => $credential->provider->value,
                ]),
            'sshKeys' => $team->sshKeys()
                ->orderBy('name')
                ->get()
                ->map(fn ($key) => [
                    'id' => $key->id,
                    'name' => $key->name,
                    'public_key' => $key->public_key,
                ]),
            'isOwner' => $user->isOwnerOf($team),
        ]);
    }

    public function store(StoreCloudProviderCredentialRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam();

        $team->cloudProviderCredentials()->create($request->validated());

        return back()->with('success', 'Cloud provider credential added.');
    }

    public function update(UpdateCloudProviderCredentialRequest $request, CloudProviderCredential $credential): RedirectResponse
    {
        $team = $request->user()->currentTeam();

        if ($credential->team_id !== $team->id) {
            abort(403);
        }

        $data = $request->validated();

        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }

        $credential->update($data);

        return back()->with('success', 'Cloud provider credential updated.');
    }

    public function destroy(Request $request, CloudProviderCredential $credential): RedirectResponse
    {
        $team = $request->user()->currentTeam();

        if ($credential->team_id !== $team->id) {
            abort(403);
        }

        if (! $request->user()->isOwnerOf($team)) {
            abort(403);
        }

        $credential->delete();

        return back()->with('success', 'Cloud provider credential deleted.');
    }
}
