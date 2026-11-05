<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;

/** Autentikasi perangkat ESP32 lewat header X-Device-Key. */
class DeviceKey
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-Device-Key');
        $device = $key ? Device::where('api_key_hash', Device::hashKey($key))->first() : null;

        if (! $device) {
            return response()->json(['message' => 'Invalid device key'], 401);
        }

        $device->forceFill(['last_seen_at' => now()])->save(); // heartbeat
        $request->attributes->set('relay_device', $device);

        return $next($request);
    }
}
