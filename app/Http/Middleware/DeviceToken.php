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

        if ($token) {
            $device = Device::where('api_token_hash', Device::hashToken($token))->where('is_active', true)->first();
        } elseif (! config('railsafe.require_token')) {
            // Mode demo: firmware tanpa header Authorization dianggap Node 2 (kontroler pos) pertama.
            $device = Device::where('type', 'GATE_CONTROLLER')->where('is_active', true)->orderBy('id')->first();
        } else {
            $device = null;
        }

        if (! $device) {
            return response()->json(['message' => 'Token perangkat tidak valid atau perangkat belum terdaftar'], 401);
        }

        $request->attributes->set('device', $device);
        return $next($request);
    }
}
