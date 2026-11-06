<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;

class DeviceToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $device = $token
            ? Device::where('api_token_hash', Device::hashToken($token))->where('is_active', true)->first()
            : null;

        if (! $device) {
            return response()->json(['message' => 'Token perangkat tidak valid'], 401);
        }

        $request->attributes->set('device', $device);
        return $next($request);
    }
}
