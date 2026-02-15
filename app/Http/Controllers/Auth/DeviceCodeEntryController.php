<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OauthDeviceCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceCodeEntryController extends Controller
{
    /**
     * Show the device code entry page.
     */
    public function show(): Response
    {
        return Inertia::render('auth/DeviceCodeEntry');
    }

    /**
     * Verify the user code and show device details for confirmation.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'user_code' => 'required|string|size:9',
        ]);

        $userCode = strtoupper($request->input('user_code'));

        $code = OauthDeviceCode::where('user_code', $userCode)
            ->where('status', 'pending')
            ->first();

        if (! $code || $code->isExpired()) {
            if ($code && $code->isExpired()) {
                $code->update(['status' => 'expired']);
            }

            return back()->withErrors([
                'user_code' => $code ? 'This code has expired. Please request a new one from your device.' : 'Invalid code. Please check and try again.',
            ]);
        }

        return redirect()->route('oauth.device.confirm', ['code' => $userCode]);
    }

    /**
     * Show the authorization confirmation page.
     */
    public function confirm(Request $request, string $code): Response|RedirectResponse
    {
        $record = OauthDeviceCode::where('user_code', strtoupper($code))
            ->where('status', 'pending')
            ->first();

        if (! $record || $record->isExpired()) {
            return redirect()->route('oauth.device')
                ->withErrors(['user_code' => 'This code is no longer valid. Please request a new one from your device.']);
        }

        return Inertia::render('auth/DeviceCodeEntry', [
            'confirmDevice' => [
                'device_name' => $record->device_name,
                'user_code' => $record->user_code,
            ],
        ]);
    }

    /**
     * Authorize the device.
     */
    public function authorize(Request $request): RedirectResponse
    {
        $request->validate([
            'user_code' => 'required|string|size:9',
        ]);

        $userCode = strtoupper($request->input('user_code'));

        $code = OauthDeviceCode::where('user_code', $userCode)
            ->where('status', 'pending')
            ->first();

        if (! $code || $code->isExpired()) {
            return redirect()->route('oauth.device')
                ->withErrors(['user_code' => 'This code is no longer valid. Please request a new one from your device.']);
        }

        // Mark code as approved and assign the user
        $code->update([
            'status' => 'approved',
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('oauth.device')
            ->with('success', $code->device_name);
    }

    /**
     * Deny the device authorization.
     */
    public function deny(Request $request): RedirectResponse
    {
        $request->validate([
            'user_code' => 'required|string|size:9',
        ]);

        $userCode = strtoupper($request->input('user_code'));

        $code = OauthDeviceCode::where('user_code', $userCode)
            ->where('status', 'pending')
            ->first();

        if ($code) {
            $code->update([
                'status' => 'denied',
                'user_id' => $request->user()->id,
            ]);
        }

        return redirect()->route('oauth.device');
    }
}
