<?php

use App\Models\DeviceAuthorization;
use App\Models\RunHistory;

test('run history belongs to device authorization', function () {
    $device = DeviceAuthorization::factory()->create();
    $run = RunHistory::factory()->for($device)->create();

    expect($run->deviceAuthorization->id)->toBe($device->id);
});

test('run history uses custom table name', function () {
    $run = new RunHistory;
    expect($run->getTable())->toBe('run_history');
});

test('run history casts stories_completed to integer', function () {
    $run = RunHistory::factory()->create();
    expect($run->stories_completed)->toBeInt();
});

test('run history casts stories_total to integer', function () {
    $run = RunHistory::factory()->create();
    expect($run->stories_total)->toBeInt();
});

test('run history casts story_results to array', function () {
    $run = RunHistory::factory()->create(['story_results' => [['id' => 'US-001', 'status' => 'completed']]]);
    expect($run->story_results)->toBeArray();
});

test('run history casts duration_seconds to integer', function () {
    $run = RunHistory::factory()->create();
    expect($run->duration_seconds)->toBeInt();
});

test('run history casts tokens_used to integer', function () {
    $run = RunHistory::factory()->create();
    expect($run->tokens_used)->toBeInt();
});

test('run history casts started_at to datetime', function () {
    $run = RunHistory::factory()->create();
    expect($run->started_at)->toBeInstanceOf(\DateTimeInterface::class);
});

test('run history casts finished_at to datetime', function () {
    $run = RunHistory::factory()->create();
    expect($run->finished_at)->toBeInstanceOf(\DateTimeInterface::class);
});

test('run history factory completed state', function () {
    $run = RunHistory::factory()->completed()->create();

    expect($run->status)->toBe('completed');
    expect($run->stories_completed)->toBe($run->stories_total);
    expect($run->error_message)->toBeNull();
});

test('run history factory failed state', function () {
    $run = RunHistory::factory()->failed()->create();

    expect($run->status)->toBe('failed');
    expect($run->error_message)->not->toBeNull();
});

test('run history factory paused state', function () {
    $run = RunHistory::factory()->paused()->create();

    expect($run->status)->toBe('paused');
    expect($run->finished_at)->toBeNull();
});

test('run history factory stopped state', function () {
    $run = RunHistory::factory()->stopped()->create();

    expect($run->status)->toBe('stopped');
});
