<?php

use App\Services\WebSocket\MessageValidator;

function contractFixture(string $path): array
{
    $fullPath = base_path('../chief/contract/fixtures/'.$path);

    return json_decode(file_get_contents($fullPath), true);
}

// --- Envelope validation ---

it('validates a valid minimal envelope', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('envelope/valid_minimal.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('validates a valid envelope with payload', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('envelope/valid_with_payload.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('rejects envelope missing type', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('envelope/invalid_missing_type.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('rejects envelope missing id', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('envelope/invalid_missing_id.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('rejects envelope with extra fields', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('envelope/invalid_extra_field.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

// --- Control message validation ---

it('validates a valid ack message', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('control/valid_ack.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('rejects ack missing ref_id', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('control/invalid_ack_missing_ref.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('validates a valid error message', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('control/valid_error.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('rejects error missing code', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('control/invalid_error_missing_code.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('validates a valid welcome message', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('control/valid_welcome.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('rejects welcome missing payload', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('control/invalid_welcome_missing_payload.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

// --- State message validation ---

it('validates a valid sync message', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('state/valid_sync.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('rejects sync missing projects', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('state/invalid_sync_missing_projects.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('validates a valid prd-updated message', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('state/valid_prd_updated.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('validates a valid run-completed message', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('state/valid_run_completed.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('rejects run-completed missing result', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('state/invalid_run_completed_missing_result.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

// --- Type validation (embedded objects) ---

it('validates embedded project type in sync payload', function () {
    $validator = app(MessageValidator::class);

    $message = [
        'type' => 'sync',
        'id' => '550e8400-e29b-41d4-a716-446655440020',
        'device_id' => 'device-abc-123',
        'timestamp' => '2026-03-22T12:00:00Z',
        'payload' => [
            'projects' => [contractFixture('types/valid_project.json')],
            'prds' => [],
            'runs' => [],
        ],
    ];

    $result = $validator->validate($message);

    expect($result['valid'])->toBeTrue();
});

it('rejects sync with invalid embedded project missing path', function () {
    $validator = app(MessageValidator::class);

    $message = [
        'type' => 'sync',
        'id' => '550e8400-e29b-41d4-a716-446655440020',
        'device_id' => 'device-abc-123',
        'timestamp' => '2026-03-22T12:00:00Z',
        'payload' => [
            'projects' => [contractFixture('types/invalid_project_missing_path.json')],
            'prds' => [],
            'runs' => [],
        ],
    ];

    $result = $validator->validate($message);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('rejects sync with invalid embedded prd missing status', function () {
    $validator = app(MessageValidator::class);

    $message = [
        'type' => 'sync',
        'id' => '550e8400-e29b-41d4-a716-446655440020',
        'device_id' => 'device-abc-123',
        'timestamp' => '2026-03-22T12:00:00Z',
        'payload' => [
            'projects' => [],
            'prds' => [contractFixture('types/invalid_prd_missing_status.json')],
            'runs' => [],
        ],
    ];

    $result = $validator->validate($message);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('rejects sync with invalid embedded run missing prd_id', function () {
    $validator = app(MessageValidator::class);

    $message = [
        'type' => 'sync',
        'id' => '550e8400-e29b-41d4-a716-446655440020',
        'device_id' => 'device-abc-123',
        'timestamp' => '2026-03-22T12:00:00Z',
        'payload' => [
            'projects' => [],
            'prds' => [],
            'runs' => [contractFixture('types/invalid_run_missing_prd_id.json')],
        ],
    ];

    $result = $validator->validate($message);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

// --- Command message validation ---

it('validates a valid prd-create command', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('cmd/valid_prd_create.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('rejects prd-create command missing title', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('cmd/invalid_prd_create_missing_title.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('validates a valid run-start command', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('cmd/valid_run_start.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('rejects run-start command missing prd_id', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('cmd/invalid_run_start_missing_prd_id.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

// --- Error response contains validation details ---

it('returns validation error details for invalid messages', function () {
    $validator = app(MessageValidator::class);
    $fixture = contractFixture('envelope/invalid_missing_type.json');

    $result = $validator->validate($fixture);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toBeArray()
        ->and($result['errors'])->each->toBeString();
});
