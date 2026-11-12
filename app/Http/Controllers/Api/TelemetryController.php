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
    /** Skala keyakinan: firmware mengirim 0-1 (AUTOMATIC) atau 1.0 sebagai pengisi (MANUAL). */
    private function normalizeScore(string $trigger, $score): ?float
    {
        if ($score === null || $trigger === 'MANUAL') return null;
        $score = (float) $score;
        if ($score <= 0) return null;
        return $score <= 1 ? $score * 100 : $score;
    }

    /** Baris PENDING milik perangkat yang masih dalam jendela hitung mundur. */
    private function openPending(Device $device): ?GateLog
    {
        $window = (int) Setting::get('countdown_seconds') + 30;

        return GateLog::where('device_id', $device->id)->where('gate_action', 'PENDING')
            ->where('logged_at', '>=', now()->subSeconds($window))->orderByDesc('logged_at')->first();
    }

    /**
     * POST /api/alert : Node 2 menerima sinyal kereta dan memulai hitung mundur untuk petugas.
     * Body: {"confidence_score":0.93,"notes":"..."}  (keduanya opsional)
     */
    public function alert(Request $request)
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $data = $request->validate(['confidence_score' => 'nullable|numeric|min:0|max:100', 'notes' => 'nullable|string|max:255']);

        $device->update(['last_seen_at' => now(), 'connection_status' => 'ACTIVE']);

        if ($existing = $this->openPending($device)) {   // alert berulang tidak membuat baris ganda
            return response()->json(['ok' => true, 'id' => $existing->id, 'event_id' => $existing->event_id, 'duplicate' => true]);
        }

        $log = GateLog::create([
            'event_id' => sprintf('GL-%s-%04d', now()->format('ymd-His'), random_int(0, 9999)),
            'crossing_id' => $device->crossing_id,
            'device_id' => $device->id,
            'trigger_type' => 'AUTOMATIC',
            'gate_action' => 'PENDING',
            'confidence_score' => $this->normalizeScore('AUTOMATIC', $data['confidence_score'] ?? null),
            'notes' => $data['notes'] ?? 'Getaran kereta terdeteksi, menunggu petugas',
            'logged_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $log->id, 'event_id' => $log->event_id], 201);
    }

    /**
     * POST /api/gate-log : hasil aksi palang.
     * Body: trigger_type (AUTOMATIC|MANUAL), confidence_score, notes,
     *       gate_action (CLOSED|OPENED|HELD|CANCELLED, opsional), obstacle_detected (opsional).
     * CANCELLED: getaran kereta berhenti (STATUS_CLEAR) sebelum palang ditutup, hitung mundur atau
     * penahanan karena rintangan dibatalkan.
     * Jika ada alert PENDING yang masih berjalan, baris itu diselesaikan (bukan dibuat baru).
     */
    public function gateLog(Request $request)
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'trigger_type' => 'required|in:AUTOMATIC,MANUAL',
            'confidence_score' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:255',
            'gate_action' => 'nullable|in:CLOSED,OPENED,HELD,CANCELLED',
            'obstacle_detected' => 'nullable|boolean',
        ]);

        $trigger = $data['trigger_type'];
        $notes = $data['notes'] ?? null;
        $action = $data['gate_action'] ?? ($notes && preg_match('/dibuka/i', $notes) ? 'OPENED' : 'CLOSED');
        $score = $this->normalizeScore($trigger, $data['confidence_score'] ?? null);
        $obstacle = array_key_exists('obstacle_detected', $data) ? (bool) $data['obstacle_detected'] : null;

        $device->update(['last_seen_at' => now(), 'connection_status' => 'ACTIVE']);

        if ($action === 'CANCELLED') {
            return $this->cancel($device, $notes);
        }

        $pending = in_array($action, ['CLOSED', 'HELD'], true) ? $this->openPending($device) : null;

        if ($pending) {
            $manual = $trigger === 'MANUAL';
            $pending->update([
                'trigger_type' => $trigger,
                'gate_action' => $action,
                'confidence_score' => $pending->confidence_score ?? $score,
                'notes' => $notes ?? $pending->notes,
                'guard_response' => $manual ? 'CONFIRMED' : 'NO_RESPONSE',
                'response_time_ms' => $manual ? max(0, (now()->timestamp - $pending->logged_at->timestamp) * 1000) : null,
                'obstacle_detected' => $obstacle ?? ($manual ? null : false),
            ]);
            $log = $pending;
        } else {
            $log = GateLog::create([
                'event_id' => sprintf('GL-%s-%04d', now()->format('ymd-His'), random_int(0, 9999)),
                'crossing_id' => $device->crossing_id,
                'device_id' => $device->id,
                'trigger_type' => $trigger,
                'gate_action' => $action,
                'confidence_score' => $score,
                'notes' => $notes,
                'obstacle_detected' => $obstacle,
                'logged_at' => now(),   // ESP32 tidak punya jam; pakai waktu server
            ]);
        }

        return response()->json(['ok' => true, 'id' => $log->id, 'event_id' => $log->event_id], 201);
    }

    /** Pembatalan dari firmware: hitung mundur (PENDING) atau penahanan rintangan (HELD) tidak jadi dilanjutkan. */
    private function cancel(Device $device, ?string $notes)
    {
        $msg = $notes ?: 'Kereta tidak terdeteksi lagi, penutupan dibatalkan';

        if ($pending = $this->openPending($device)) {
            $pending->update(['gate_action' => 'CANCELLED', 'notes' => $msg, 'guard_response' => null, 'response_time_ms' => null]);
            return response()->json(['ok' => true, 'id' => $pending->id, 'event_id' => $pending->event_id]);
        }

        $last = GateLog::where('device_id', $device->id)->orderByDesc('updated_at')->orderByDesc('id')->first();
        if ($last && $last->gate_action === 'HELD') {
            // baris HELD tetap sebagai catatan kejadian; baris baru menandai penahanan berakhir
            $log = GateLog::create([
                'event_id' => sprintf('GL-%s-%04d', now()->format('ymd-His'), random_int(0, 9999)),
                'crossing_id' => $device->crossing_id, 'device_id' => $device->id,
                'trigger_type' => 'AUTOMATIC', 'gate_action' => 'CANCELLED',
                'notes' => $msg, 'obstacle_detected' => false, 'logged_at' => now(),
            ]);
            return response()->json(['ok' => true, 'id' => $log->id, 'event_id' => $log->event_id], 201);
        }

        return response()->json(['ok' => true, 'ignored' => true]);   // tidak ada yang perlu dibatalkan
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

        // log_sensor = riwayat perubahan state/health. Jika tidak berubah, baris terakhir diperbarui
        // (detail dan waktu laporan) sehingga tabel tidak membengkak tetapi dashboard tetap segar.
        foreach ($sensors as $s) {
            $last = LogSensor::where('device_id', $device->id)->where('sensor_type', $s['type'])
                ->orderByDesc('reported_at')->orderByDesc('id')->first();
            if ($last && $last->operational_state === $s['state'] && $last->health_status === $s['health']) {
                $last->update(['detail' => $s['detail'] ?? $last->detail, 'reported_at' => now()]);
            } else {
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
