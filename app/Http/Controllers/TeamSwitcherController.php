<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamSwitcherController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
        ]);

        $team = Team::findOrFail($validated['team_id']);
        $user = $request->user();

        if (! $user->isMemberOf($team)) {
            abort(403, 'You are not a member of this team.');
        }

        $user->switchTeam($team);

        return redirect('/')->with('success', 'Switched to '.$team->name.'.');
    }
}
