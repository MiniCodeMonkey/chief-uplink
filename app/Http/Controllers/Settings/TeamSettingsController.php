<?php

namespace App\Http\Controllers\Settings;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\InviteTeamMemberRequest;
use App\Http\Requests\Settings\RemoveTeamMemberRequest;
use App\Http\Requests\Settings\TransferOwnershipRequest;
use App\Http\Requests\Settings\UpdateTeamNameRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        $members = $team->users()->get()->map(fn (User $member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'avatar_url' => $member->avatar_url,
            'role' => $member->pivot->role,
        ]);

        return Inertia::render('Settings/Team', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'owner_id' => $team->owner_id,
            ],
            'members' => $members,
            'isOwner' => $user->isOwnerOf($team),
        ]);
    }

    public function updateName(UpdateTeamNameRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam();

        $team->update(['name' => $request->validated('name')]);

        return back()->with('success', 'Team name updated.');
    }

    public function removeMember(RemoveTeamMemberRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam();
        $memberId = $request->validated('user_id');

        $team->users()->detach($memberId);

        return back()->with('success', 'Member removed.');
    }

    public function invite(InviteTeamMemberRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam();
        $email = $request->validated('email');

        $existingInvite = $team->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->exists();

        if ($existingInvite) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email.']);
        }

        $alreadyMember = $team->users()->where('email', $email)->exists();

        if ($alreadyMember) {
            return back()->withErrors(['email' => 'This user is already a team member.']);
        }

        $team->invitations()->create([
            'email' => $email,
            'token' => bin2hex(random_bytes(32)),
        ]);

        return back()->with('success', 'Invitation sent to '.$email.'.');
    }

    public function transferOwnership(TransferOwnershipRequest $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();
        $newOwnerId = $request->validated('user_id');

        $team->update(['owner_id' => $newOwnerId]);

        $team->users()->updateExistingPivot($newOwnerId, ['role' => TeamRole::Owner->value]);
        $team->users()->updateExistingPivot($user->id, ['role' => TeamRole::Member->value]);

        return back()->with('success', 'Ownership transferred.');
    }
}
