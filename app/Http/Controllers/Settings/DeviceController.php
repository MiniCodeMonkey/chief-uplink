<?php

namespace App\Http\Controllers\Settings;

use App\Events\DeviceTokenRevoked;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        $devices = $request->user()
            ->deviceAuthorizations()
            ->whereNull('revoked_at')
            ->orderByDesc('is_online')
            ->orderByDesc('last_connected_at')
            ->get()
            ->map(fn ($device) => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'os' => $device->os,
                'arch' => $device->arch,
                'chief_version' => $device->chief_version,
                'last_connected_at' => $device->last_connected_at?->toISOString(),
                'last_ip' => $device->last_ip,
                'is_online' => $device->is_online,
            ]);

        return Inertia::render('settings/Devices', [
            'devices' => $devices,
        ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $device = $request->user()
            ->deviceAuthorizations()
            ->whereNull('revoked_at')
            ->findOrFail($id);

        $device->update([
            'revoked_at' => now(),
            'is_online' => false,
        ]);

        DeviceTokenRevoked::dispatch($device->id, $device->user_id);

        return back()->with('success', "Device \"{$device->device_name}\" has been deauthorized.");
    }
}
