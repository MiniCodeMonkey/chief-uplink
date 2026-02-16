<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $preferences = $user->notification_preferences ?? [];
        $preferences['email'] = $validated['email'];
        $user->notification_preferences = $preferences;
        $user->save();

        return response()->json(['success' => true]);
    }
}
