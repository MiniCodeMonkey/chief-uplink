<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendCommandRequest;
use App\Models\Device;
use App\Models\PendingCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class DeviceCommandController extends Controller
{
    public function store(SendCommandRequest $request, Device $device): JsonResponse
    {
        $messageId = (string) Str::uuid();

        $command = PendingCommand::create([
            'device_id' => $device->id,
            'message_id' => $messageId,
            'type' => $request->validated('type'),
            'payload' => $request->validated('payload', []),
        ]);

        $status = 'pending';

        if ($device->connected) {
            Redis::publish("device-commands:{$device->id}", json_encode([
                'command_id' => $command->id,
            ]));
            $status = 'sent';
        }

        return response()->json([
            'message_id' => $messageId,
            'type' => $command->type,
            'status' => $status,
        ], 201);
    }
}
