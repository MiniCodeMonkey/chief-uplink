<?php

use App\Models\Device;
use App\Models\Prd;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

it('renders the prd chat page for team members', function () {
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
        ->get("/prds/{$prd->id}/chat")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Prds/Chat')
            ->where('prd.title', 'My PRD')
            ->has('project')
            ->has('chatHistory')
            ->has('device')
        );
});

it('requires authentication', function () {
    $prd = Prd::factory()->create();

    $this->get("/prds/{$prd->id}/chat")
        ->assertRedirect('/login');
});

it('returns 403 for non-team members', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $device = Device::factory()->create(['team_id' => $otherTeam->id]);
    $prd = Prd::factory()->create(['device_id' => $device->id]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}/chat")
        ->assertForbidden();
});

it('loads chat history from encrypted array', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $prd = Prd::factory()->withChatHistory()->create([
        'device_id' => $device->id,
    ]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}/chat")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Prds/Chat')
            ->has('chatHistory', 2)
            ->where('chatHistory.0.role', 'user')
            ->where('chatHistory.0.content', 'What should we build?')
            ->where('chatHistory.1.role', 'assistant')
        );
});

it('returns empty chat history when none exists', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $prd = Prd::factory()->create([
        'device_id' => $device->id,
        'chat_history' => null,
    ]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}/chat")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('chatHistory', [])
        );
});

it('includes device info in response', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->connected()->create([
        'team_id' => $team->id,
        'name' => 'Dev MacBook',
    ]);
    $prd = Prd::factory()->create(['device_id' => $device->id]);

    $this->actingAs($user)
        ->get("/prds/{$prd->id}/chat")
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
        ->get("/prds/{$prd->id}/chat")
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
        ->get("/prds/{$prd->id}/chat")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('project', null)
        );
});
