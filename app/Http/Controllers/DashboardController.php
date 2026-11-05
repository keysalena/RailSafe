<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\GateCommand;
use App\Models\GateState;
use App\Models\SensorTelemetry;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    // GET /api/dashboard/live  -> dipolling browser tiap ±2 detik
    public function live()
    {
        $node1 = Device::where('type', 'node1')->first();
        $node2 = Device::where('type', 'node2')->first();

        $latest = $node1
            ? SensorTelemetry::where('device_id', $node1->id)->latest('recorded_at')->first()
            : null;

        $trend = $node1
            ? SensorTelemetry::where('device_id', $node1->id)->latest('recorded_at')->limit(20)
                ->get(['confidence', 'vibration_class', 'recorded_at'])->reverse()->values()
            : [];

        $alert = Alert::where('status', 'active')->latest('triggered_at')->first();
        $gate  = $node2 ? GateState::where('device_id', $node2->id)->first() : null;
        $pending = $node2
            ? GateCommand::where('device_id', $node2->id)->whereIn('status', ['pending', 'sent'])->exists()
            : false;

        $rssi = $latest?->lora_rssi;

        $recent = Alert::latest('triggered_at')->limit(5)
            ->get(['id', 'event_id', 'confidence', 'status', 'triggered_at']);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'telemetry' => $latest ? [
                'vibration_class' => $latest->vibration_class,
                'confidence'      => $latest->confidence,
                'recorded_at'     => $latest->recorded_at->toIso8601String(),
            ] : null,
            'hardware' => $latest ? [
                'battery_percent' => $latest->battery_percent,
                'battery_voltage' => $latest->battery_voltage,
                'lora_rssi'       => $rssi,
                'lora_quality'    => $rssi === null ? null : ($rssi >= -90 ? 'baik' : ($rssi >= -110 ? 'sedang' : 'lemah')),
                'distance_cm'     => $latest->distance_cm,
            ] : null,
            'trend' => $trend,
            'devices' => [
                'node1' => $node1 ? ['online' => $node1->isOnline(), 'last_seen_at' => $node1->last_seen_at?->toIso8601String()] : null,
                'node2' => $node2 ? ['online' => $node2->isOnline(), 'last_seen_at' => $node2->last_seen_at?->toIso8601String()] : null,
            ],
            'recent_alerts' => $recent,
            'alert' => $alert ? [
                'id'           => $alert->id,
                'event_id'     => $alert->event_id,
                'event_type'   => $alert->event_type,
                'confidence'   => $alert->confidence,
                'triggered_at' => $alert->triggered_at->toIso8601String(),
            ] : null,
            'gate' => [
                'state'   => $gate?->state ?? 'unknown',
                'mode'    => $gate?->mode ?? 'auto',
                'pending' => $pending,
            ],
        ]);
    }

    // POST /api/dashboard/alerts/{alert}/acknowledge  -> matikan banner & sirine
    public function acknowledge(Request $request, Alert $alert)
    {
        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $request->user()->id,
        ]);

        return response()->json(['ok' => true]);
    }

    // POST /api/dashboard/gate/command  {command: open|close}
    public function gateCommand(Request $request)
    {
        $data = $request->validate(['command' => 'required|in:open,close']);

        $node2 = Device::where('type', 'node2')->firstOrFail();

        // batalkan perintah lama yang belum diproses
        GateCommand::where('device_id', $node2->id)->where('status', 'pending')->update(['status' => 'failed']);

        $cmd = GateCommand::create([
            'device_id'    => $node2->id,
            'command'      => strtoupper($data['command']),
            'requested_by' => $request->user()->id,
        ]);

        GateState::updateOrCreate(
            ['device_id' => $node2->id],
            [
                'state'      => $data['command'] === 'close' ? 'closing' : 'opening',
                'mode'       => 'remote',
                'changed_by' => $request->user()->id,
            ]
        );

        return response()->json(['ok' => true, 'command_id' => $cmd->id], 201);
    }
}
