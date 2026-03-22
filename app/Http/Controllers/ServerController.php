<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerRequest;
use App\Jobs\ProvisionServerJob;
use App\Models\CloudProviderCredential;
use App\Services\CloudProvider\CloudProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        return Inertia::render('Servers/Create', [
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
        ]);
    }

    public function store(StoreServerRequest $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();

        $credential = $team->cloudProviderCredentials()->findOrFail($request->validated('credential_id'));
        $team->sshKeys()->findOrFail($request->validated('ssh_key_id'));

        $server = $team->managedServers()->create([
            ...$request->validated(),
            'provider' => $credential->provider->value,
            'status' => 'provisioning',
        ]);

        ProvisionServerJob::dispatch($server);

        return redirect('/servers')->with('success', 'Server is being provisioned.');
    }

    public function regions(Request $request, CloudProviderCredential $credential): JsonResponse
    {
        $team = $request->user()->currentTeam();

        if ($credential->team_id !== $team->id) {
            abort(403);
        }

        $provider = CloudProviderFactory::make($credential->provider, $credential->api_key);

        return response()->json($provider->listRegions());
    }

    public function sizes(Request $request, CloudProviderCredential $credential): JsonResponse
    {
        $team = $request->user()->currentTeam();

        if ($credential->team_id !== $team->id) {
            abort(403);
        }

        $provider = CloudProviderFactory::make($credential->provider, $credential->api_key);

        return response()->json($provider->listSizes());
    }
}
