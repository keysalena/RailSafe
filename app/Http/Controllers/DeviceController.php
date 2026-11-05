<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\GateCommand;
use App\Models\GateState;
use App\Models\SensorTelemetry;
use Illuminate\Http\Request;

/**
 * Endpoint untuk perangkat (Node 2 sebagai gateway Wi-Fi, meneruskan data Node 1 dari LoRa).
 * Semua route dilindungi middleware DeviceKey.
 */
class DeviceController extends Controller
{
    // POST /api/device/telemetry
    public function telemetry(Request $request)
    {
        $data = $request->validate([
            'device_id'       => 'required|exists:devices,device_id',
            'vibration_class' => 'required|in:idle,truk,kereta',
            'confidence'      => 'required|numeric|between:0,100',
            'battery_percent' => 'nullable|integer|between:0,100',
            'battery_voltage' => 'nullable|numeric|between:0,20',
            'lora_rssi'       => 'nullable|integer|between:-200,0',
            'lora_snr'        => 'nullable|numeric',
            'distance_cm'     => 'nullable|numeric|min:0',
            'timestamp'       => 'nullable|date',
        ]);

        $device = Device::where('device_id', $data['device_id'])->first();
        $device->forceFill(['last_seen_at' => now()])->save();

        $row = SensorTelemetry::create([
            'device_id'       => $device->id,
            'vibration_class' => $data['vibration_class'],
            'confidence'      => $data['confidence'],
            'battery_percent' => $data['battery_percent'] ?? null,
            'battery_voltage' => $data['battery_voltage'] ?? null,
            'lora_rssi'       => $data['lora_rssi'] ?? null,
            'lora_snr'        => $data['lora_snr'] ?? null,
            'distance_cm'     => $data['distance_cm'] ?? null,
            'recorded_at'     => $data['timestamp'] ?? now(),
        ]);

        return response()->json(['ok' => true, 'id' => $row->id], 201);
    }

    // POST /api/device/alert  (Node 1 mengirim ALERT_KERETA)
    public function alert(Request $request)
    {
        $data = $request->validate([
            'event_id'   => 'required|string|max:50',
            'device_id'  => 'required|exists:devices,device_id',
            'event_type' => 'nullable|in:ALERT_KERETA',
            'confidence' => 'nullable|numeric|between:0,100',
            'timestamp'  => 'nullable|date',
        ]);

        $device = Device::where('device_id', $data['device_id'])->first();

        // firstOrCreate -> aman untuk retry / pesan LoRa yang diterima berulang
        $alert = Alert::firstOrCreate(
            ['event_id' => $data['event_id']],
            [
                'device_id'    => $device->id,
                'event_type'   => 'ALERT_KERETA',
                'confidence'   => $data['confidence'] ?? null,
                'status'       => 'active',
                'triggered_at' => $data['timestamp'] ?? now(),
            ]
        );

        return response()->json(['ok' => true, 'id' => $alert->id, 'duplicate' => ! $alert->wasRecentlyCreated], 201);
    }

    // POST /api/device/gate-status  (Node 2 melaporkan posisi palang)
    public function gateStatus(Request $request)
    {
        $data = $request->validate([
            'state' => 'required|in:open,closed,opening,closing',
            'mode'  => 'required|in:auto,manual,remote',
        ]);

        $gate = GateState::updateOrCreate(
            ['device_id' => $request->attributes->get('relay_device')->id],
            ['state' => $data['state'], 'mode' => $data['mode']]
        );

        return response()->json(['ok' => true, 'state' => $gate->state]);
    }

    // GET /api/device/commands/pending  (Node 2 polling perintah override dari browser)
    public function pendingCommands(Request $request)
    {
        $device = $request->attributes->get('relay_device');

        $commands = GateCommand::where('device_id', $device->id)
            ->where('status', 'pending')->orderBy('id')->get(['id', 'command']);

        GateCommand::whereIn('id', $commands->pluck('id'))->update(['status' => 'sent', 'sent_at' => now()]);

        return response()->json(['commands' => $commands]);
    }

    // POST /api/device/commands/{command}/ack
    public function ackCommand(Request $request, GateCommand $command)
    {
        $data = $request->validate(['status' => 'required|in:executed,failed']);

        abort_unless($command->device_id === $request->attributes->get('relay_device')->id, 403);

        $command->update(['status' => $data['status'], 'executed_at' => now()]);

        if ($data['status'] === 'executed') {
            GateState::where('device_id', $command->device_id)->update([
                'state' => $command->command === 'CLOSE' ? 'closed' : 'open',
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
