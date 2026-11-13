<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Crossing;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CrossingController extends Controller
{
    public function index()
    {
        return view('admin.crossings', [
            'crossings' => Crossing::withCount('devices')->orderBy('code')->get(),
            'devices'   => Device::with('crossing')->orderBy('device_code')->get(),
        ]);
    }

    public function storeCrossing(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:crossings,code',
        ]);

        $crossing = Crossing::create($data);
        AuditLog::record('create_crossing', 'crossing', $crossing->id);

        return back()->with('status', 'Pos perlintasan ditambahkan.');
    }

    public function updateCrossing(Request $request, Crossing $crossing)
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('crossings', 'code')->ignore($crossing->id),
            ],
        ]);

        $crossing->update($data);
        AuditLog::record('update_crossing', 'crossing', $crossing->id);

        return redirect()->route('admin.crossings')->with('status', 'Pos perlintasan diperbarui.');
    }

    public function destroyCrossing(Crossing $crossing)
    {
        // Pos tidak boleh dihapus selama masih ada perangkat yang tersambung.
        // `status` adalah accessor di model Device (bukan kolom DB; kolomnya `connection_status`),
        // jadi dicek lewat koleksi agar konsisten dengan destroyDevice() dan tampilan.
        if ($crossing->devices->contains(fn ($d) => $d->status === 'ACTIVE')) {
            return back()->with('error', 'Tidak dapat menghapus pos yang masih memiliki perangkat tersambung.');
        }

        $crossingId = $crossing->id;

        DB::transaction(function () use ($crossing) {
            $crossing->devices()->delete();
            $crossing->delete();
        });

        AuditLog::record('delete_crossing', 'crossing', $crossingId);

        return back()->with('status', 'Pos beserta perangkatnya berhasil dihapus.');
    }

    public function storeDevice(Request $request)
    {
        $data = $request->validate([
            'crossing_id' => 'required|exists:crossings,id',
            'device_code' => 'required|string|max:30|unique:devices,device_code',
            'type'        => 'required|in:SENSOR_NODE,GATE_CONTROLLER',
            'name'        => 'nullable|string|max:100',
        ]);

        $device = Device::create($data + ['api_token_hash' => '']);
        $token  = $device->issueToken();
        AuditLog::record('create_device', 'device', $device->id);

        return back()
            ->with('status', 'Perangkat ditambahkan.')
            ->with('token', ['code' => $device->device_code, 'value' => $token]);
    }

    public function rotateToken(Device $device)
    {
        $token = $device->issueToken();
        AuditLog::record('rotate_token', 'device', $device->id);

        return back()
            ->with('status', 'Token diganti. Token lama tidak berlaku lagi.')
            ->with('token', ['code' => $device->device_code, 'value' => $token]);
    }

    public function destroyDevice(Device $device)
    {
        if ($device->status === 'ACTIVE') {
            return back()->with('error', 'Tidak dapat menghapus perangkat yang sedang tersambung.');
        }

        $deviceId = $device->id;
        $device->delete();
        AuditLog::record('delete_device', 'device', $deviceId);

        return back()->with('status', 'Perangkat berhasil dihapus.');
    }
}