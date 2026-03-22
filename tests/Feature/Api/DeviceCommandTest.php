<?php

use App\Enums\CommandType;
use App\Enums\TeamRole;
use App\Models\Device;
use App\Models\PendingCommand;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

describe('POST /api/devices/{device}/commands', function () {
    it('creates a pending command for an offline device', function () {
        Redis::shouldReceive('publish')->never();

        $user = User::factory()->create();
        $team = $user->currentTeam();
        $device = Device::factory()->create(['team_id' => $team->id, 'connected' => false]);

        $this->actingAs($user);

        $response = $this->postJson("/api/devices/{$device->id}/commands", [
            'type' => 'cmd.prd.create',
            'payload' => ['name' => 'My PRD'],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message_id', 'type', 'status'])
            ->assertJson([
                'type' => 'cmd.prd.create',
                'status' => 'pending',
            ]);

        expect($response->json('message_id'))->toBeString()->not->toBeEmpty();

        $this->assertDatabaseHas('pending_commands', [
            'device_id' => $device->id,
            'type' => 'cmd.prd.create',
            'message_id' => $response->json('message_id'),
        ]);
    });

    it('publishes to Redis when device is connected', function () {
        $user = User::factory()->create();
        $team = $user->currentTeam();
        $device = Device::factory()->create(['team_id' => $team->id, 'connected' => true]);

        Redis::shouldReceive('publish')
            ->once()
            ->withArgs(function ($channel, $message) use ($device) {
                return $channel === "device-commands:{$device->id}"
                    && json_decode($message, true)['command_id'] > 0;
            });

        $this->actingAs($user);

        $response = $this->postJson("/api/devices/{$device->id}/commands", [
            'type' => 'cmd.run.start',
            'payload' => ['project_id' => 'abc-123'],
        ]);

        $response->assertCreated()
            ->assertJson(['status' => 'sent']);
    });

    it('rejects unauthenticated requests', function () {
        $device = Device::factory()->create();

        $response = $this->postJson("/api/devices/{$device->id}/commands", [
            'type' => 'cmd.prd.create',
            'payload' => [],
        ]);

        $response->assertUnauthorized();
    });

    it('rejects requests from users not in the device team', function () {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();
        $device = Device::factory()->create(['team_id' => $otherTeam->id]);

        $this->actingAs($user);

        $response = $this->postJson("/api/devices/{$device->id}/commands", [
            'type' => 'cmd.prd.create',
            'payload' => [],
        ]);

        $response->assertForbidden();
    });

    it('allows team members (non-owners) to send commands', function () {
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        $owner = User::factory()->create();
        $team = $owner->currentTeam();

        $member = User::factory()->create();
        $member->teams()->detach();
        $member->ownedTeams()->delete();
        $team->users()->attach($member->id, ['role' => TeamRole::Member->value]);

        $device = Device::factory()->create(['team_id' => $team->id, 'connected' => false]);

        $this->actingAs($member);

        $response = $this->postJson("/api/devices/{$device->id}/commands", [
            'type' => 'cmd.settings.get',
        ]);

        $response->assertCreated();
    });

    it('rejects invalid command types', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['team_id' => $user->currentTeam()->id]);

        $this->actingAs($user);

        $response = $this->postJson("/api/devices/{$device->id}/commands", [
            'type' => 'invalid.command.type',
            'payload' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });

    it('accepts all supported command types', function () {
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        $user = User::factory()->create();
        $device = Device::factory()->create([
            'team_id' => $user->currentTeam()->id,
            'connected' => false,
        ]);

        $this->actingAs($user);

        foreach (CommandType::cases() as $commandType) {
            $response = $this->postJson("/api/devices/{$device->id}/commands", [
                'type' => $commandType->value,
                'payload' => ['test' => true],
            ]);

            $response->assertCreated();
        }

        expect(PendingCommand::where('device_id', $device->id)->count())
            ->toBe(count(CommandType::cases()));
    });

    it('generates unique message_id for each command', function () {
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        $user = User::factory()->create();
        $device = Device::factory()->create([
            'team_id' => $user->currentTeam()->id,
            'connected' => false,
        ]);

        $this->actingAs($user);

        $messageIds = [];

        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson("/api/devices/{$device->id}/commands", [
                'type' => 'cmd.prd.create',
                'payload' => ['index' => $i],
            ]);

            $messageIds[] = $response->json('message_id');
        }

        expect(array_unique($messageIds))->toHaveCount(3);
    });

    it('accepts commands without payload', function () {
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        $user = User::factory()->create();
        $device = Device::factory()->create([
            'team_id' => $user->currentTeam()->id,
            'connected' => false,
        ]);

        $this->actingAs($user);

        $response = $this->postJson("/api/devices/{$device->id}/commands", [
            'type' => 'cmd.files.list',
        ]);

        $response->assertCreated();

        $command = PendingCommand::where('message_id', $response->json('message_id'))->first();
        expect($command->payload)->toBe([]);
    });

    it('returns 404 for non-existent device', function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/devices/99999/commands', [
            'type' => 'cmd.prd.create',
            'payload' => [],
        ]);

        $response->assertNotFound();
    });
});
