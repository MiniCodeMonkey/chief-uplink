<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSshKeyRequest;
use App\Http\Requests\Settings\UpdateSshKeyRequest;
use App\Models\SshKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SshKeyController extends Controller
{
    public function store(StoreSshKeyRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam();

        $team->sshKeys()->create($request->validated());

        return back()->with('success', 'SSH key added.');
    }

    public function update(UpdateSshKeyRequest $request, SshKey $sshKey): RedirectResponse
    {
        $team = $request->user()->currentTeam();

        if ($sshKey->team_id !== $team->id) {
            abort(403);
        }

        $sshKey->update($request->validated());

        return back()->with('success', 'SSH key updated.');
    }

    public function destroy(Request $request, SshKey $sshKey): RedirectResponse
    {
        $team = $request->user()->currentTeam();

        if ($sshKey->team_id !== $team->id) {
            abort(403);
        }

        if (! $request->user()->isOwnerOf($team)) {
            abort(403);
        }

        $sshKey->delete();

        return back()->with('success', 'SSH key deleted.');
    }
}
