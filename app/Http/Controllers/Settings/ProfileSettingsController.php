<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Http\Requests\Settings\UpdateThemePreferenceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Profile', [
            'user' => $user->only('id', 'name', 'email', 'theme_preference'),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Profile updated.');
    }

    public function updateTheme(UpdateThemePreferenceRequest $request): RedirectResponse
    {
        $request->user()->update([
            'theme_preference' => $request->validated('theme_preference'),
        ]);

        return back()->with('success', 'Theme preference updated.');
    }
}
