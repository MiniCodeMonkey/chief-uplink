<?php

use App\Models\Device;
use App\Models\Prd;
use App\Models\Run;
use App\Models\Team;
use App\Models\User;

it('renders the run live page for team members', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $prd = Prd::factory()->create(['device_id' => $device->id]);
    $run = Run::factory()->running()->create([
        'device_id' => $device->id,
        'prd_id' => $prd->id,
    ]);

    $this->actingAs($user)
        ->get("/runs/{$run->id}/live")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Runs/Live')
            ->has('run')
            ->has('device')
            ->has('prd')
            ->where('run.status', 'running')
        );
});

it('requires authentication for live view', function () {
    $run = Run::factory()->create();

    $this->get("/runs/{$run->id}/live")
        ->assertRedirect('/login');
});

it('returns 403 for non-team members on live view', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $device = Device::factory()->create(['team_id' => $otherTeam->id]);
    $run = Run::factory()->create(['device_id' => $device->id]);

    $this->actingAs($user)
        ->get("/runs/{$run->id}/live")
        ->assertForbidden();
});

it('includes device info on live view', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->connected()->create([
        'team_id' => $team->id,
        'name' => 'Dev MacBook',
    ]);
    $run = Run::factory()->create(['device_id' => $device->id]);

    $this->actingAs($user)
        ->get("/runs/{$run->id}/live")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('device', fn ($d) => $d
                ->where('id', $device->id)
                ->where('name', 'Dev MacBook')
                ->where('connected', true)
                ->has('os')
            )
        );
});

it('includes prd data on live view when run has a prd', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $prd = Prd::factory()->create([
        'device_id' => $device->id,
        'title' => 'My Feature PRD',
    ]);
    $run = Run::factory()->create([
        'device_id' => $device->id,
        'prd_id' => $prd->id,
    ]);

    $this->actingAs($user)
        ->get("/runs/{$run->id}/live")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('prd.title', 'My Feature PRD')
        );
});
