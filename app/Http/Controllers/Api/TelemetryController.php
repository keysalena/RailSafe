<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\GateLog;
use App\Models\LogSensor;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    /**
     * POST /api/gate-log : format yang dikirim firmware Node2.ino saat ini.
     * Body: {"trigger_type":"AUTOMATIC|MANUAL","confidence_score":0.93,"notes":"..."}
     */
    public function gateLog(Request $request)
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'trigger_type' => 'required|in:AUTOMATIC,MANUAL',
            'confidence_score' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:255',
            'gate_action' => 'nullable|in:CLOSED,OPENED,HELD',
        ]);

        $notes = $data['notes'] ?? null;
        // firmware hanya mengirim satu jenis log untuk tutup dan buka; bedanya ada di teks notes
        $action = $data['gate_action'] ?? ($notes && preg_match('/dibuka/i', $notes) ? 'OPENED' : 'CLOSED');

        $score = $data['confidence_score'] ?? null;
        if ($data['trigger_type'] === 'MANUAL') {
            $score = null;                       // firmware mengirim 1.0 sebagai nilai pengisi untuk tombol manual
        } elseif ($score !== null && $score <= 1) {
            $score = $score * 100;               // firmware mengirim skala 0-1
        }

        $log = GateLog::create([
            'event_id' => sprintf('GL-%s-%04d', now()->format('ymd-His'), random_int(0, 9999)),
            'crossing_id' => $device->crossing_id,
            'device_id' => $device->id,
            'trigger_type' => $data['trigger_type'],
            'gate_action' => $action,
            'confidence_score' => $score,
            'notes' => $notes,
            'logged_at' => now(),   // ESP32 tidak punya jam; pakai waktu server
        ]);

        $device->update(['last_seen_at' => now(), 'connection_status' => 'ACTIVE']);

        return response()->json(['ok' => true, 'id' => $log->id, 'event_id' => $log->event_id], 201);
    }

    /**
     * POST /api/telemetry : format lengkap sesuai proposal (respons petugas, rintangan).
     * Idempoten berdasarkan event_id.
     */
    public function store(Request $request)
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'event_id' => 'required|string|max:40',
            'event_type' => 'required|in:MANUAL_OK,FAIL_SAFE_AUTOGATE,CRITICAL_OBSTACLE',
            'control_mode' => 'required|in:MANUAL,AUTO',
            'guard_response' => 'nullable|in:CONFIRMED,NO_RESPONSE',
            'response_time_ms' => 'nullable|integer|min:0',
            'obstacle_detected' => 'nullable|boolean',
            'confidence' => 'nullable|numeric|between:0,100',
            'notes' => 'nullable|string|max:255',
            'event_timestamp' => 'required|date',
            'device_id' => 'nullable|string|max:30',
            'sensor_status' => 'nullable|string|max:30',
        ]);

        $existing = GateLog::where('event_id', $data['event_id'])->first();
        if ($existing && $existing->crossing_id !== $device->crossing_id) {
            return response()->json(['message' => 'event_id sudah dipakai pos lain'], 409);
        }

        $log = GateLog::updateOrCreate(['event_id' => $data['event_id']], [
            'crossing_id' => $device->crossing_id,
            'device_id' => $device->id,
            'trigger_type' => $data['control_mode'] === 'AUTO' ? 'AUTOMATIC' : 'MANUAL',
            'gate_action' => $data['event_type'] === 'CRITICAL_OBSTACLE' ? 'HELD' : 'CLOSED',
            'confidence_score' => $data['confidence'] ?? null,
            'notes' => $data['notes'] ?? null,
            'guard_response' => $data['guard_response'] ?? null,
            'response_time_ms' => $data['response_time_ms'] ?? null,
            'obstacle_detected' => $data['obstacle_detected'] ?? null,
            'logged_at' => Carbon::parse($data['event_timestamp']),
        ]);

        $device->update(['last_seen_at' => now(), 'connection_status' => 'ACTIVE']);

        return response()->json(['ok' => true, 'event_id' => $log->event_id], $log->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * POST /api/heartbeat
     * Dikirim berkala (disarankan tiap 30-60 detik) oleh Node 1 dan Node 2.
     */
    public function heartbeat(Request $request)
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'battery_level' => 'nullable|integer|between:0,100',
            'rssi' => 'nullable|integer|between:-200,0',
            'distance_cm' => 'nullable|numeric|min:0',
            'ml_label' => 'nullable|in:IDLE,TRUK,KERETA',
            'ml_confidence' => 'nullable|numeric|between:0,100',
            'firmware_version' => 'nullable|string|max:20',
            'sensors' => 'nullable|array|max:10',
            'sensors.*.type' => 'required|in:MPU6050,LORA,ULTRASONIC_1,ULTRASONIC_2,SERVO',
            'sensors.*.state' => 'required|in:ACTIVE,STANDBY,OFF',
            'sensors.*.health' => 'required|in:OK,FAULT,UNKNOWN',
            'sensors.*.detail' => 'nullable|string|max:255',
        ]);

        $sensors = collect($data['sensors'] ?? []);
        $fault = $sensors->contains(fn ($s) => $s['health'] === 'FAULT');

        $device->update(array_filter([
            'battery_level' => $data['battery_level'] ?? null,
            'rssi' => $data['rssi'] ?? null,
            'distance_cm' => $data['distance_cm'] ?? null,
            'ml_label' => $data['ml_label'] ?? null,
            'ml_confidence' => $data['ml_confidence'] ?? null,
            'firmware_version' => $data['firmware_version'] ?? null,
        ], fn ($v) => $v !== null) + [
            'connection_status' => $fault ? 'FAULT' : 'ACTIVE',
            'last_seen_at' => now(),
        ]);

        // simpan baris log_sensor hanya jika state/health berubah, agar tabel tidak membengkak
        foreach ($sensors as $s) {
            $last = LogSensor::where('device_id', $device->id)->where('sensor_type', $s['type'])
                ->orderByDesc('reported_at')->orderByDesc('id')->first();
            if (! $last || $last->operational_state !== $s['state'] || $last->health_status !== $s['health']) {
                LogSensor::create([
                    'device_id' => $device->id,
                    'sensor_type' => $s['type'],
                    'operational_state' => $s['state'],
                    'health_status' => $s['health'],
                    'detail' => $s['detail'] ?? null,
                    'reported_at' => now(),
                ]);
            }
        }

        return response()->json(['ok' => true, 'server_time' => now()->toIso8601String()]);
    }

    /** GET /api/config : parameter yang diatur superadmin, boleh diambil ESP32 saat boot. */
    public function config()
    {
        return response()->json(Setting::all_());
    }
}
