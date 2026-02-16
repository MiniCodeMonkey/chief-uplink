<?php

namespace App\Http\Controllers\Api;

use App\Events\ChiefMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\CachedProjectState;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MessageIngestionController extends Controller
{
    /**
     * Allowed message types that the CLI can send.
     */
    public const ALLOWED_TYPES = [
        'project_state',
        'claude_output',
        'prd_output',
        'run_complete',
        'run_paused',
        'run_progress',
        'error',
        'clone_complete',
        'clone_progress',
        'session_expired',
        'quota_exhausted',
        'state_snapshot',
        'project_list',
        'settings',
        'log_lines',
    ];

    /**
     * Maximum number of messages per batch.
     */
    public const MAX_MESSAGES_PER_BATCH = 50;

    /**
     * Maximum request body size in bytes (512KB).
     */
    public const MAX_REQUEST_SIZE = 524288;

    /**
     * Batch deduplication TTL in seconds (5 minutes).
     */
    public const BATCH_DEDUP_TTL = 300;

    public function __construct(
        protected WebSocketMessageBuffer $messageBuffer,
    ) {}

    /**
     * POST /api/device/messages
     *
     * Ingest a batch of messages from the CLI.
     */
    public function ingest(Request $request): JsonResponse
    {
        $deviceId = $request->attributes->get('device_id');
        $userId = $request->attributes->get('user_id');
        $device = $request->attributes->get('device_authorization');
        $sessionId = $device->session_id;

        // Validate request size
        if ($request->header('Content-Length') > self::MAX_REQUEST_SIZE) {
            return response()->json([
                'error' => 'request_too_large',
                'message' => 'Request body exceeds maximum size of 512KB.',
            ], 413);
        }

        $request->validate([
            'batch_id' => 'required|string|uuid',
            'messages' => 'required|array|max:'.self::MAX_MESSAGES_PER_BATCH,
            'messages.*.type' => 'required|string',
        ]);

        $batchId = $request->input('batch_id');
        $messages = $request->input('messages');

        // Deduplicate: return cached response if batch already processed
        $cacheKey = "batch:{$deviceId}:{$batchId}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            Log::debug('Returning cached response for duplicate batch', [
                'device_id' => $deviceId,
                'batch_id' => $batchId,
            ]);

            return response()->json($cached);
        }

        // Validate all message types before processing any
        foreach ($messages as $index => $message) {
            $type = $message['type'] ?? null;
            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                return response()->json([
                    'error' => 'unknown_message_type',
                    'message' => "Unknown message type '{$type}' at index {$index}.",
                ], 422);
            }
        }

        // Update heartbeat timestamp — message ingestion acts as implicit heartbeat
        $device->update(['last_heartbeat_at' => now()]);

        // Process each message
        $accepted = 0;
        foreach ($messages as $message) {
            $type = $message['type'];

            // Handle project_state: update cached project state
            if ($type === 'project_state') {
                $this->handleProjectState($deviceId, $message);
            }

            // Buffer the message for browser replay on reconnect
            if ($sessionId) {
                try {
                    $this->messageBuffer->buffer($deviceId, $sessionId, $message);
                } catch (\Throwable $e) {
                    Log::warning('Failed to buffer message', [
                        'device_id' => $deviceId,
                        'type' => $type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Broadcast to browser via Reverb
            ChiefMessageReceived::dispatch($deviceId, $userId, $message);

            $accepted++;
        }

        $response = [
            'accepted' => $accepted,
            'batch_id' => $batchId,
            'session_id' => $sessionId,
        ];

        // Cache the response for deduplication
        Cache::put($cacheKey, $response, self::BATCH_DEDUP_TTL);

        Log::debug('Message batch ingested', [
            'device_id' => $deviceId,
            'batch_id' => $batchId,
            'accepted' => $accepted,
        ]);

        return response()->json($response);
    }

    /**
     * Handle a project_state message — update cached project state.
     *
     * Replicates ChiefServerController::handleProjectState() logic.
     */
    protected function handleProjectState(int $deviceId, array $message): void
    {
        $projects = $message['payload']['projects'] ?? [];

        if (! is_array($projects)) {
            Log::warning('Invalid project_state payload', [
                'device_id' => $deviceId,
            ]);

            return;
        }

        $incomingSlugs = collect($projects)->pluck('project_slug')->filter()->toArray();

        // Remove cached projects that are no longer reported
        CachedProjectState::where('device_authorization_id', $deviceId)
            ->when(count($incomingSlugs) > 0, function ($query) use ($incomingSlugs) {
                $query->whereNotIn('project_slug', $incomingSlugs);
            })
            ->delete();

        // Upsert each project's state
        foreach ($projects as $project) {
            if (! is_array($project) || empty($project['project_slug'])) {
                continue;
            }

            CachedProjectState::updateOrCreate(
                [
                    'device_authorization_id' => $deviceId,
                    'project_slug' => $project['project_slug'],
                ],
                [
                    'project_name' => $project['project_name'] ?? $project['project_slug'],
                    'git_branch' => $project['git_branch'] ?? null,
                    'last_commit_hash' => $project['last_commit_hash'] ?? null,
                    'last_commit_message' => $project['last_commit_message'] ?? null,
                    'status' => $project['status'] ?? 'idle',
                    'current_prd_name' => $project['current_prd_name'] ?? null,
                    'stories_completed' => $project['stories_completed'] ?? 0,
                    'stories_total' => $project['stories_total'] ?? 0,
                    'story_details' => $project['story_details'] ?? null,
                    'active_sessions' => $project['active_sessions'] ?? 0,
                    'recent_activity' => $project['recent_activity'] ?? null,
                ]
            );
        }

        Log::info('Project state updated via message ingestion', [
            'device_id' => $deviceId,
            'project_count' => count($projects),
        ]);
    }
}
