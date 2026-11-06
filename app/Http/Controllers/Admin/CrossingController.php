<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Crossing;
use App\Models\Device;
use Illuminate\Http\Request;

class CrossingController extends Controller
{
    public function index()
    {
        return view('admin.crossings', ['crossings' => Crossing::withCount('devices')->orderBy('code')->get(), 'devices' => Device::with('crossing')->orderBy('device_code')->get()]);
    }

    public function storeCrossing(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:30|unique:crossings,code', 'name' => 'required|string|max:150', 'address' => 'nullable|string|max:255']);
        $c = Crossing::create($data);
        AuditLog::record('create_crossing', 'crossing', $c->id);

        return back()->with('status', 'Pos perlintasan ditambahkan.');
    }

    public function storeDevice(Request $request)
    {
        $data = $request->validate([
            'crossing_id' => 'required|exists:crossings,id',
            'device_code' => 'required|string|max:30|unique:devices,device_code',
            'type' => 'required|in:SENSOR_NODE,GATE_CONTROLLER',
            'name' => 'nullable|string|max:100',
        ]);
        $device = Device::create($data + ['api_token_hash' => '']);
        $token = $device->issueToken();
        AuditLog::record('create_device', 'device', $device->id);

        return back()->with('status', 'Perangkat ditambahkan.')->with('token', ['code' => $device->device_code, 'value' => $token]);
    }

    public function rotateToken(Device $device)
    {
        $token = $device->issueToken();
        AuditLog::record('rotate_token', 'device', $device->id);

        return back()->with('status', 'Token diganti. Token lama tidak berlaku lagi.')->with('token', ['code' => $device->device_code, 'value' => $token]);
    }
}
