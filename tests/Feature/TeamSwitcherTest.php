<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

it('switches the current team and redirects to home', function () {
    $user = User::factory()->create();
    $secondTeam = Team::factory()->create();
    $user->teams()->attach($secondTeam, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->put('/team/switch', ['team_id' => $secondTeam->id])
        ->assertRedirect('/')
        ->assertSessionHas('success');

    expect($user->fresh()->current_team_id)->toBe($secondTeam->id);
});

it('returns the team matching current_team_id', function () {
    $user = User::factory()->create();
    $secondTeam = Team::factory()->create();
    $user->teams()->attach($secondTeam, ['role' => TeamRole::Member->value]);

    $user->update(['current_team_id' => $secondTeam->id]);

    expect($user->fresh()->currentTeam()->id)->toBe($secondTeam->id);
});

it('falls back to first team when current_team_id is null', function () {
    $user = User::factory()->create();
    $firstTeam = $user->teams->first();

    expect($user->current_team_id)->toBeNull();
    expect($user->currentTeam()->id)->toBe($firstTeam->id);
});

it('falls back to first team when current_team_id references a team user is not a member of', function () {
    $user = User::factory()->create();
    $firstTeam = $user->teams->first();
    $otherTeam = Team::factory()->create();

    $user->update(['current_team_id' => $otherTeam->id]);

    expect($user->fresh()->currentTeam()->id)->toBe($firstTeam->id);
});

it('prevents switching to a team the user is not a member of', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    $this->actingAs($user)
        ->put('/team/switch', ['team_id' => $otherTeam->id])
        ->assertForbidden();

    expect($user->fresh()->current_team_id)->toBeNull();
});

it('requires authentication to switch teams', function () {
    $team = Team::factory()->create();

    $this->put('/team/switch', ['team_id' => $team->id])
        ->assertRedirect('/login');
});

it('validates team_id is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/team/switch', [])
        ->assertSessionHasErrors('team_id');
});

it('validates team_id exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/team/switch', ['team_id' => 99999])
        ->assertSessionHasErrors('team_id');
});

it('shares current team and teams list via inertia', function () {
    $user = User::factory()->create();
    $firstTeam = $user->teams->first();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('auth.currentTeam')
            ->where('auth.currentTeam.id', $firstTeam->id)
            ->where('auth.currentTeam.name', $firstTeam->name)
            ->has('auth.teams', 1)
            ->where('auth.teams.0.id', $firstTeam->id)
        );
});

it('shares all teams the user belongs to', function () {
    $user = User::factory()->create();
    $secondTeam = Team::factory()->create();
    $user->teams()->attach($secondTeam, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('auth.teams', 2)
        );
});

it('throws exception when switching to non-member team via model', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    expect(fn () => $user->switchTeam($otherTeam))
        ->toThrow(InvalidArgumentException::class, 'User is not a member of this team.');
});

it('updates team settings for the current team after switching', function () {
    $user = User::factory()->create();
    $secondTeam = Team::factory()->create(['name' => 'Second Team']);
    $user->teams()->attach($secondTeam, ['role' => TeamRole::Owner->value]);

    $user->switchTeam($secondTeam);

    $this->actingAs($user->fresh())
        ->get('/settings/team')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('team.id', $secondTeam->id)
            ->where('team.name', 'Second Team')
        );
});
