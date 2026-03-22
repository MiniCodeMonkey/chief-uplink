<?php

namespace App\Http\Controllers;

use App\Models\Run;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RunController extends Controller
{
    public function show(Request $request, Run $run): Response
    {
        $user = $request->user();
        $device = $run->device;

        abort_unless(
            $user->isMemberOf($device->team),
            HttpResponse::HTTP_FORBIDDEN,
            'You are not a member of this team.'
        );

        $run->load('prd');

        return Inertia::render('Runs/Show', [
            'run' => $run,
            'prd' => $run->prd,
            'device' => $device->only(['id', 'name', 'os', 'connected']),
        ]);
    }
}
