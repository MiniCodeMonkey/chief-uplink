<?php

use App\Models\Device;
use App\Models\Prd;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

it('renders the prd show page for team members', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $project = Project::factory()->create(['device_id' => $device->id]);
    $prd = Prd::factory()->create([
        'device_id' => $device->id,
        'project_id' => $project->id,
        'title' => 'My PRD',
    ]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Prds/Show')
            ->where('prd.title', 'My PRD')
            ->has('project')
            ->has('device')
        );
});

it('requires authentication for show page', function () {
    $prd = Prd::factory()->create();

    $this->get("/prds/{$prd->id}")
        ->assertRedirect('/login');
});

it('returns 403 for non-team members on show page', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $device = Device::factory()->create(['team_id' => $otherTeam->id]);
    $prd = Prd::factory()->create(['device_id' => $device->id]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}")
        ->assertForbidden();
});

it('includes prd content for markdown rendering', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $prd = Prd::factory()->create([
        'device_id' => $device->id,
        'content' => '# Test PRD Content',
    ]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Prds/Show')
            ->where('prd.content', '# Test PRD Content')
        );
});

it('includes prd status', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $prd = Prd::factory()->create([
        'device_id' => $device->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('prd.status', 'active')
        );
});

it('includes device info in show response', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->connected()->create([
        'team_id' => $team->id,
        'name' => 'Dev MacBook',
    ]);
    $prd = Prd::factory()->create(['device_id' => $device->id]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}")
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

it('includes project data when prd has a project', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $project = Project::factory()->create([
        'device_id' => $device->id,
        'name' => 'My Project',
    ]);
    $prd = Prd::factory()->create([
        'device_id' => $device->id,
        'project_id' => $project->id,
    ]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('project.name', 'My Project')
        );
});

it('returns null project when prd has no project', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $prd = Prd::factory()->create([
        'device_id' => $device->id,
        'project_id' => null,
    ]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('project', null)
        );
});
