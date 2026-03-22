<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerRequest;
use App\Jobs\ProvisionServerJob;
use App\Models\CloudProviderCredential;
use App\Models\ManagedServer;
use App\Services\CloudProvider\CloudProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function show(Request $request, ManagedServer $server): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        if ($server->team_id !== $team->id) {
            abort(403);
        }

        $server->load(['credential', 'sshKey', 'devices']);

        return Inertia::render('Servers/Show', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status->value,
                'ip_address' => $server->ip_address,
                'provider' => $server->provider->value,
                'region_id' => $server->region_id,
                'size_id' => $server->size_id,
                'credential_name' => $server->credential->name,
                'ssh_key' => $server->sshKey ? [
                    'id' => $server->sshKey->id,
                    'name' => $server->sshKey->name,
                    'public_key' => $server->sshKey->public_key,
                ] : null,
                'devices' => $server->devices->map(fn ($device) => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'connected' => $device->connected,
                ]),
            ],
            'isOwner' => $user->isOwnerOf($team),
            'hasGitHubToken' => (bool) $user->github_token,
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        $servers = $team->managedServers()
            ->with(['credential', 'devices'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($server) => [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status->value,
                'ip_address' => $server->ip_address,
                'provider' => $server->provider->value,
                'region_id' => $server->region_id,
                'size_id' => $server->size_id,
                'credential_name' => $server->credential->name,
                'devices' => $server->devices->map(fn ($device) => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'connected' => $device->connected,
                ]),
            ]);

        return Inertia::render('Servers/Index', [
            'servers' => $servers,
            'isOwner' => $user->isOwnerOf($team),
        ]);
    }

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
