<?php

use App\Enums\TeamRole;
use App\Enums\ThemePreference;
use App\Models\Team;
use App\Models\User;

it('creates a default team via factory', function () {
    $user = User::factory()->create();

    expect($user->teams)->toHaveCount(1);
    expect($user->teams->first()->name)->toBe("{$user->name}'s Team");
    expect($user->teams->first()->pivot->role)->toBe(TeamRole::Owner->value);
});

it('has correct casts', function () {
    $user = User::factory()->create(['theme_preference' => ThemePreference::Dark]);

    $fresh = $user->fresh();

    expect($fresh->theme_preference)->toBe(ThemePreference::Dark);
});

it('can have nullable password for github-only users', function () {
    $user = User::factory()->githubOnly()->create();

    expect($user->password)->toBeNull();
    expect($user->github_id)->not->toBeNull();
});

it('returns owned teams', function () {
    $user = User::factory()->create();

    expect($user->ownedTeams)->toHaveCount(1);
    expect($user->ownedTeams->first()->owner_id)->toBe($user->id);
});

it('returns current team or creates a default one', function () {
    $user = User::factory()->create();

    $currentTeam = $user->currentTeam();

    expect($currentTeam)->toBeInstanceOf(Team::class);
    expect($currentTeam->name)->toBe("{$user->name}'s Team");
});

it('creates a default team when user has no teams', function () {
    $user = User::factory()->create();

    // Remove all teams
    $user->teams()->detach();
    $user->ownedTeams()->delete();
    $user->unsetRelation('teams');

    $currentTeam = $user->currentTeam();

    expect($currentTeam)->toBeInstanceOf(Team::class);
    expect($currentTeam->name)->toBe("{$user->name}'s Team");
    expect($user->isMemberOf($currentTeam))->toBeTrue();
});

it('determines if user is owner of a team', function () {
    $user = User::factory()->create();
    $team = $user->teams->first();

    expect($user->isOwnerOf($team))->toBeTrue();

    $otherTeam = Team::factory()->create();

    expect($user->isOwnerOf($otherTeam))->toBeFalse();
});

it('determines if user is member of a team', function () {
    $user = User::factory()->create();
    $team = $user->teams->first();

    expect($user->isMemberOf($team))->toBeTrue();

    $otherTeam = Team::factory()->create();

    expect($user->isMemberOf($otherTeam))->toBeFalse();
});

it('hides sensitive attributes', function () {
    $user = User::factory()->create();

    $array = $user->toArray();

    expect($array)->not->toHaveKey('password');
    expect($array)->not->toHaveKey('remember_token');
    expect($array)->not->toHaveKey('github_token');
});
