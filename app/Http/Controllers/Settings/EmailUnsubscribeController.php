<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailUnsubscribeController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired unsubscribe link.');
        }

        $preferences = $user->notification_preferences ?? [];
        $preferences['email'] = false;
        $user->notification_preferences = $preferences;
        $user->save();

        return redirect()->route('login')->with('success', 'Email notifications disabled.');
    }
}
