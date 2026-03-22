<?php

namespace App\Http\Controllers;

use App\Models\Prd;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PrdController extends Controller
{
    public function show(Request $request, Prd $prd): Response
    {
        $user = $request->user();
        $device = $prd->device;

        abort_unless(
            $user->isMemberOf($device->team),
            HttpResponse::HTTP_FORBIDDEN,
            'You are not a member of this team.'
        );

        $prd->load('project');

        return Inertia::render('Prds/Show', [
            'prd' => $prd,
            'project' => $prd->project,
            'device' => $device->only(['id', 'name', 'os', 'connected']),
        ]);
    }

    public function chat(Request $request, Prd $prd): Response
    {
        $user = $request->user();
        $device = $prd->device;

        abort_unless(
            $user->isMemberOf($device->team),
            HttpResponse::HTTP_FORBIDDEN,
            'You are not a member of this team.'
        );

        $prd->load('project');

        return Inertia::render('Prds/Chat', [
            'prd' => $prd,
            'project' => $prd->project,
            'chatHistory' => $prd->chat_history ?? [],
            'device' => $device->only(['id', 'name', 'os', 'connected']),
        ]);
    }
}
