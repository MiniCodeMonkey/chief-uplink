<?php

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Str;

it('belongs to an owner', function () {
    $team = Team::factory()->create();

    expect($team->owner)->toBeInstanceOf(User::class);
});

it('has users through pivot', function () {
    $user = User::factory()->create();
    $team = $user->teams->first();

    expect($team->users)->toHaveCount(1);
    expect($team->users->first()->id)->toBe($user->id);
});

it('has invitations', function () {
    $team = Team::factory()->create();

    TeamInvitation::create([
        'team_id' => $team->id,
        'email' => 'invite@example.com',
        'token' => Str::random(64),
    ]);

    expect($team->invitations)->toHaveCount(1);
});
