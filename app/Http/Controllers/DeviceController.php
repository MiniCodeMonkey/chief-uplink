<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam();

        $devices = $team->devices()
            ->orderByDesc('connected')
            ->orderByDesc('last_seen_at')
            ->get(['id', 'name', 'os', 'arch', 'chief_version', 'last_seen_at', 'connected']);

        return Inertia::render('Devices/Index', [
            'devices' => $devices,
        ]);
    }
}
