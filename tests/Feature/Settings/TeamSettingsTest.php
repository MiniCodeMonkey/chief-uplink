<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

it('renders team settings page for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings/team');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Settings/Team')
        ->has('team')
        ->has('members')
        ->has('isOwner')
    );
});

it('shows team name and members with roles', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($owner)->get('/settings/team');

    $response->assertInertia(fn ($page) => $page
        ->where('team.name', $team->name)
        ->where('isOwner', true)
        ->has('members', 2)
    );
});

it('shows isOwner as false for non-owner members', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    // Remove member's own default team and add them to the owner's team
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($member)->get('/settings/team');

    $response->assertInertia(fn ($page) => $page
        ->where('isOwner', false)
    );
});

it('allows owner to update team name', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $response = $this->actingAs($owner)->put('/settings/team/name', [
        'name' => 'New Team Name',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Team name updated.');
    expect($team->fresh()->name)->toBe('New Team Name');
});

it('prevents non-owner from updating team name', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($member)->put('/settings/team/name', [
        'name' => 'Hacked Name',
    ]);

    $response->assertForbidden();
    expect($team->fresh()->name)->not->toBe('Hacked Name');
});

it('validates team name is required', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->put('/settings/team/name', [
        'name' => '',
    ]);

    $response->assertSessionHasErrors('name');
});

it('allows owner to remove a member', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($owner)->delete('/settings/team/members', [
        'user_id' => $member->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Member removed.');
    expect($team->users()->where('users.id', $member->id)->exists())->toBeFalse();
});

it('prevents non-owner from removing a member', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $anotherMember = User::factory()->create();
    $team->users()->attach($anotherMember, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($member)->delete('/settings/team/members', [
        'user_id' => $anotherMember->id,
    ]);

    $response->assertForbidden();
});

it('prevents owner from removing themselves', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->delete('/settings/team/members', [
        'user_id' => $owner->id,
    ]);

    $response->assertForbidden();
});

it('allows owner to transfer ownership', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($owner)->put('/settings/team/transfer', [
        'user_id' => $member->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Ownership transferred.');

    $team->refresh();
    expect($team->owner_id)->toBe($member->id);
    expect($team->users()->where('users.id', $member->id)->first()->pivot->role)->toBe('owner');
    expect($team->users()->where('users.id', $owner->id)->first()->pivot->role)->toBe('member');
});

it('prevents non-owner from transferring ownership', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($member)->put('/settings/team/transfer', [
        'user_id' => $owner->id,
    ]);

    $response->assertForbidden();
});

it('prevents owner from transferring ownership to themselves', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->put('/settings/team/transfer', [
        'user_id' => $owner->id,
    ]);

    $response->assertForbidden();
});

it('allows owner to invite a team member by email', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $response = $this->actingAs($owner)->post('/settings/team/invite', [
        'email' => 'newmember@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('team_invitations', [
        'team_id' => $team->id,
        'email' => 'newmember@example.com',
    ]);
});

it('prevents duplicate pending invitations', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $team->invitations()->create([
        'email' => 'existing@example.com',
        'token' => bin2hex(random_bytes(32)),
    ]);

    $response = $this->actingAs($owner)->post('/settings/team/invite', [
        'email' => 'existing@example.com',
    ]);

    $response->assertSessionHasErrors('email');
});

it('prevents inviting existing team members', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create(['email' => 'member@example.com']);
    $team->users()->attach($member, ['role' => 'member']);

    $response = $this->actingAs($owner)->post('/settings/team/invite', [
        'email' => 'member@example.com',
    ]);

    $response->assertSessionHasErrors('email');
});

it('prevents non-owner from inviting members', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => 'member']);

    $response = $this->actingAs($member)->post('/settings/team/invite', [
        'email' => 'someone@example.com',
    ]);

    $response->assertForbidden();
});

it('validates invite email is required', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->post('/settings/team/invite', [
        'email' => '',
    ]);

    $response->assertSessionHasErrors('email');
});

it('requires authentication to access team settings', function () {
    $response = $this->get('/settings/team');

    $response->assertRedirect('/login');
});
