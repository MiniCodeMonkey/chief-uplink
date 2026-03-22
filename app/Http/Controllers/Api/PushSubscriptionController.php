<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint' => $request->validated('endpoint')],
            [
                'user_id' => $request->user()->id,
                'p256dh_key' => $request->validated('keys.p256dh'),
                'auth_token' => $request->validated('keys.auth'),
            ]
        );

        return response()->json([
            'id' => $subscription->id,
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'url'],
        ]);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        return response()->json(null, 204);
    }
}
