<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;

test('cached project state belongs to device authorization', function () {
    $device = DeviceAuthorization::factory()->create();
    $state = CachedProjectState::factory()->for($device)->create();

    expect($state->deviceAuthorization->id)->toBe($device->id);
});

test('cached project state uses custom table name', function () {
    $state = new CachedProjectState;
    expect($state->getTable())->toBe('cached_project_state');
});

test('cached project state casts stories_completed to integer', function () {
    $state = CachedProjectState::factory()->running()->create();
    expect($state->stories_completed)->toBeInt();
});

test('cached project state casts stories_total to integer', function () {
    $state = CachedProjectState::factory()->running()->create();
    expect($state->stories_total)->toBeInt();
});

test('cached project state casts story_details to array', function () {
    $state = CachedProjectState::factory()->running()->create();
    expect($state->story_details)->toBeArray();
});

test('cached project state casts active_sessions to integer', function () {
    $state = CachedProjectState::factory()->running()->create();
    expect($state->active_sessions)->toBeInt();
});

test('cached project state casts recent_activity to array', function () {
    $state = CachedProjectState::factory()->running()->create();
    expect($state->recent_activity)->toBeArray();
});

test('cached project state factory running state', function () {
    $state = CachedProjectState::factory()->running()->create();

    expect($state->status)->toBe('running');
    expect($state->current_prd_name)->not->toBeNull();
    expect($state->stories_completed)->toBeGreaterThan(0);
    expect($state->stories_total)->toBeGreaterThan(0);
    expect($state->stories_completed)->toBeLessThan($state->stories_total);
});

test('cached project state factory idle state', function () {
    $state = CachedProjectState::factory()->idle()->create();

    expect($state->status)->toBe('idle');
    expect($state->stories_completed)->toBe(12);
    expect($state->stories_total)->toBe(12);
});

test('cached project state factory error state', function () {
    $state = CachedProjectState::factory()->error()->create();

    expect($state->status)->toBe('error');
});

test('cached project state factory paused state', function () {
    $state = CachedProjectState::factory()->paused()->create();

    expect($state->status)->toBe('paused');
});

test('cached project state factory noPrd state', function () {
    $state = CachedProjectState::factory()->noPrd()->create();

    expect($state->status)->toBe('no_prd');
    expect($state->current_prd_name)->toBeNull();
});

test('story details contain expected structure', function () {
    $state = CachedProjectState::factory()->running()->create();

    foreach ($state->story_details as $story) {
        expect($story)->toHaveKeys(['id', 'title', 'status']);
        expect($story['id'])->toMatch('/^US-\d{3}$/');
        expect($story['status'])->toBeIn(['completed', 'in_progress', 'pending', 'failed']);
    }
});
