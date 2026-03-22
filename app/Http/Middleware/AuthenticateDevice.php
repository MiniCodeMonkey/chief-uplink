<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'missing_token'], 401);
        }

        $device = Device::findByToken($token);

        if (! $device) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        if ($device->token_expires_at && $device->token_expires_at->isPast()) {
            return response()->json(['error' => 'expired_token'], 401);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
