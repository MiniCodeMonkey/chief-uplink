<?php

namespace App\WebSocket;

use App\Events\ChiefMessageReceived;
use App\Models\CachedProjectState;
use App\Services\ServerConnectionManager;
use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Servers\Reverb\Connection;
use Psr\Http\Message\RequestInterface;

class ChiefServerController
{
    public function __construct(
        protected ServerConnectionManager $connectionManager,
    ) {}

    /**
     * Handle a new WebSocket connection from a chief server.
     *
     * The chief server must send a "hello" message as its first message
     * containing the access token for authentication.
     */
    public function __invoke(RequestInterface $request, Connection $connection): void
    {
        $connectionId = $connection->id();

        $connection->withMaxMessageSize(65536);

        // Register the connection object for sending messages back to chief
        $this->connectionManager->registerConnectionObject($connectionId, $connection);

        $connection->onMessage(function ($message) use ($connection, $connectionId) {
            $this->handleMessage($connection, $connectionId, (string) $message);
        });

        $connection->onClose(function () use ($connectionId) {
            $this->handleClose($connectionId);
        });

        $connection->openBuffer();

        Log::debug('Chief server WebSocket connection opened', [
            'connection_id' => $connectionId,
        ]);
    }

    /**
     * Handle an incoming message from a chief server.
     */
    protected function handleMessage(Connection $connection, int $connectionId, string $rawMessage): void
    {
        $message = json_decode($rawMessage, true);

        if (! is_array($message)) {
            Log::warning('Invalid JSON message from chief server', [
                'connection_id' => $connectionId,
            ]);

            return;
        }

        // If not yet authenticated, only accept "hello" messages
        if (! $this->connectionManager->isAuthenticated($connectionId)) {
            if (($message['type'] ?? null) !== 'hello') {
                Log::warning('Message received before hello from chief server', [
                    'connection_id' => $connectionId,
                    'type' => $message['type'] ?? 'unknown',
                ]);

                return;
            }

            $result = $this->connectionManager->handleHello($connectionId, $message);
            $connection->send(json_encode($result['response']));

            if (! $result['success']) {
                $connection->close();
            }

            return;
        }

        // Connection is authenticated — handle other message types
        $deviceId = $this->connectionManager->getDeviceId($connectionId);
        $userId = $this->connectionManager->getUserId($connectionId);
        $type = $message['type'] ?? 'unknown';

        // Handle project_state messages: overwrite cached project state with fresh data
        if ($type === 'project_state' && $deviceId) {
            $this->handleProjectState($deviceId, $message);
        }

        // Buffer the message for browser replay on reconnect
        $this->connectionManager->bufferMessage($deviceId, $message);

        // Broadcast the message to the browser via Reverb
        if ($deviceId && $userId) {
            ChiefMessageReceived::dispatch($deviceId, $userId, $message);
        }

        Log::debug('Message from authenticated chief server relayed to browser', [
            'connection_id' => $connectionId,
            'device_id' => $deviceId,
            'type' => $type,
        ]);
    }

    /**
     * Handle a project_state message from a chief server.
     *
     * Overwrites cached_project_state with fresh data from the server.
     * The payload contains an array of projects with their current state.
     */
    protected function handleProjectState(int $deviceId, array $message): void
    {
        $projects = $message['payload']['projects'] ?? [];

        if (! is_array($projects)) {
            Log::warning('Invalid project_state payload from chief server', [
                'device_id' => $deviceId,
            ]);

            return;
        }

        // Get slugs from the incoming data to know which projects to keep
        $incomingSlugs = collect($projects)->pluck('project_slug')->filter()->toArray();

        // Remove cached projects that are no longer reported by the server
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

        Log::info('Project state updated from chief server', [
            'device_id' => $deviceId,
            'project_count' => count($projects),
        ]);
    }

    /**
     * Handle a chief server connection being closed.
     */
    protected function handleClose(int $connectionId): void
    {
        $this->connectionManager->handleDisconnect($connectionId);
    }
}
