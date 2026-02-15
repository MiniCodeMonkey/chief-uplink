<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmailCaptureController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->email !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/EmailCapture');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
        ]);

        $request->user()->update($validated);

        return redirect()->route('dashboard');
    }

    public function skip(Request $request): RedirectResponse
    {
        return redirect()->route('dashboard');
    }
}
