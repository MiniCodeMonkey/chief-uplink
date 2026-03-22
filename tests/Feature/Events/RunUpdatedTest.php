<?php

use App\Events\RunUpdated;
use App\Models\Run;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the correct private channel', function () {
    $run = Run::factory()->create();

    $event = new RunUpdated($run);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe("private-run.{$run->id}");
});

it('broadcasts run data with stories and status', function () {
    $run = Run::factory()->running()->create();

    $event = new RunUpdated($run);

    $data = $event->broadcastWith();
    expect($data)->toHaveKeys(['id', 'status', 'stories', 'started_at', 'completed_at']);
    expect($data['status'])->toBe('running');
    expect($data['stories'])->toBeArray();
});

it('broadcasts completed run with completed_at timestamp', function () {
    $run = Run::factory()->completed()->create();

    $event = new RunUpdated($run);

    $data = $event->broadcastWith();
    expect($data['status'])->toBe('completed');
    expect($data['completed_at'])->not->toBeNull();
});
