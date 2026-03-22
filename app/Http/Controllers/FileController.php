<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FileController extends Controller
{
    public function show(Request $request, Device $device, ?string $path = null): Response
    {
        $user = $request->user();

        abort_unless(
            $user->isMemberOf($device->team),
            HttpResponse::HTTP_FORBIDDEN,
            'You are not a member of this team.'
        );

        return Inertia::render('Files/Browser', [
            'device' => $device->only(['id', 'name', 'os', 'connected']),
            'initialPath' => $path ?? '',
        ]);
    }
}
