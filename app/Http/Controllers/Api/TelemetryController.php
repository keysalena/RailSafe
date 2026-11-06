<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\LogKontrol;
use App\Models\LogPerlintasan;
use App\Models\LogSensor;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    /**
     * POST /api/telemetry
     * Dipanggil Node 2: saat sinyal kereta diterima (TRAIN_INBOUND) dan saat siklus kontrol selesai.
     * Idempoten: event_id yang sama tidak membuat baris ganda, sehingga aman dikirim ulang.
     */
    public function store(Request $request)
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'event_id' => 'required|string|max:40',
            'event_type' => 'required|in:TRAIN_INBOUND,MANUAL_OK,FAIL_SAFE_AUTOGATE,CRITICAL_OBSTACLE',
            'control_mode' => 'required_unless:event_type,TRAIN_INBOUND|nullable|in:MANUAL,AUTO',
            'guard_response' => 'required_unless:event_type,TRAIN_INBOUND|nullable|in:CONFIRMED,NO_RESPONSE',
            'response_time_ms' => 'nullable|integer|min:0',
            'obstacle_detected' => 'nullable|boolean',
            'sensor_status' => 'nullable|string|max:30',
            'confidence' => 'nullable|numeric|between:0,100',
            'event_timestamp' => 'required|date',
            'device_id' => 'nullable|string|max:30', // diabaikan: perangkat dikenali dari token
        ]);

        $ts = Carbon::parse($data['event_timestamp']);

        $event = LogPerlintasan::firstOrCreate(
            ['event_id' => $data['event_id']],
            [
                'crossing_id' => $device->crossing_id,
                'device_id' => $device->id,
                'detected_at' => $ts,
                'confidence' => $data['confidence'] ?? null,
            ]
        );

        if ($event->crossing_id !== $device->crossing_id) {
            return response()->json(['message' => 'event_id sudah dipakai pos lain'], 409);
        }

        if ($data['event_type'] !== 'TRAIN_INBOUND') {
            $auto = $data['control_mode'] === 'AUTO';
            LogKontrol::updateOrCreate(
                ['event_id' => $event->event_id],
                [
                    'device_id' => $device->id,
                    'control_mode' => $data['control_mode'],
                    'guard_response' => $data['guard_response'],
                    'response_time_ms' => $data['response_time_ms'] ?? null,
                    'obstacle_checked' => $auto,
                    'obstacle_detected' => $auto ? (bool) ($data['obstacle_detected'] ?? false) : null,
                    'action' => LogKontrol::ACTIONS[$data['event_type']],
                    'emergency_alarm' => $data['event_type'] === 'CRITICAL_OBSTACLE',
                    'status_label' => $data['event_type'],
                    'sensor_status' => $data['sensor_status'] ?? null,
                    'completed_at' => $ts,
                ]
            );
        }

        return response()->json(['ok' => true, 'event_id' => $event->event_id], $event->wasRecentlyCreated ? 201 : 200);
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
