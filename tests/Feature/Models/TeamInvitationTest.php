<?php

use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Support\Str;

it('belongs to a team', function () {
    $team = Team::factory()->create();

    $invitation = TeamInvitation::create([
        'team_id' => $team->id,
        'email' => 'invite@example.com',
        'token' => Str::random(64),
    ]);

    expect($invitation->team)->toBeInstanceOf(Team::class);
    expect($invitation->team->id)->toBe($team->id);
});

it('is pending when not accepted', function () {
    $team = Team::factory()->create();

    $invitation = TeamInvitation::create([
        'team_id' => $team->id,
        'email' => 'invite@example.com',
        'token' => Str::random(64),
    ]);

    expect($invitation->isPending())->toBeTrue();
});

it('is not pending when accepted', function () {
    $team = Team::factory()->create();

    $invitation = TeamInvitation::create([
        'team_id' => $team->id,
        'email' => 'invite@example.com',
        'token' => Str::random(64),
        'accepted_at' => now(),
    ]);

    expect($invitation->isPending())->toBeFalse();
});
