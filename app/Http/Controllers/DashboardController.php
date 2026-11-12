<?php

namespace App\Http\Controllers;

use App\Models\Crossing;
use App\Models\Device;
use App\Models\GateLog;
use App\Models\LogSensor;
use App\Models\Setting;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Peringatan rel terhalang tetap tampil sampai ada log baru atau selama ini (detik). */
    private const OBSTACLE_ALERT_SECONDS = 300;

    /** Palang dianggap masih tertutup sampai ada log OPENED, tetapi tidak lebih lama dari ini (jaga-jaga paket hilang). */
    private const CLOSED_MAX_SECONDS = 900;

    public function index(Request $request)
    {
        $crossings = Crossing::whereIn('id', $request->user()->accessibleCrossingIds())->orderBy('code')->get();

        return view('dashboard', ['crossings' => $crossings, 'selected' => (int) $request->query('crossing')]);
    }

    /** JSON untuk pembaruan berkala (AJAX) di dashboard. */
    public function summary(Request $request)
    {
        $ids = $request->user()->scopedCrossingIds((int) $request->query('crossing'));
        $reopen = (int) Setting::get('gate_reopen_seconds');
        $today = now()->startOfDay();

        // --- penutupan hari ini ---
        $closedToday = GateLog::whereIn('crossing_id', $ids)->where('gate_action', 'CLOSED')
            ->where('logged_at', '>=', $today)->get();
        $auto = $closedToday->where('trigger_type', 'AUTOMATIC');
        $manual = $closedToday->where('trigger_type', 'MANUAL');
        $confs = $auto->pluck('confidence_score')->filter(fn ($v) => $v !== null);

        // --- per jam: hari ini vs rata-rata 7 hari sebelumnya ---
        $todayHours = array_fill(0, 24, 0);
        foreach ($closedToday as $e) $todayHours[$e->logged_at->hour]++;
        $avg = array_fill(0, 24, 0.0);
        GateLog::whereIn('crossing_id', $ids)->where('gate_action', 'CLOSED')
            ->whereBetween('logged_at', [$today->copy()->subDays(7), $today])->get(['logged_at'])
            ->each(function ($e) use (&$avg) { $avg[$e->logged_at->hour] += 1 / 7; });

        // --- 7 hari terakhir ---
        $week = GateLog::whereIn('crossing_id', $ids)->where('gate_action', 'CLOSED')
            ->where('logged_at', '>=', $today->copy()->subDays(6))->get()
            ->groupBy(fn ($e) => $e->logged_at->toDateString());
        $weekly = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $rows = $week->get($d->toDateString(), collect());
            $weekly[] = [
                'label' => $d->locale('id')->isoFormat('ddd'),
                'auto' => $rows->where('trigger_type', 'AUTOMATIC')->count(),
                'manual' => $rows->where('trigger_type', 'MANUAL')->count(),
            ];
        }

        // --- peringatan di tengah layar + status palang ---
        $countdown = (int) Setting::get('countdown_seconds');
        $nowTs = now()->timestamp;
        $pending = GateLog::whereIn('crossing_id', $ids)->where('gate_action', 'PENDING')
            ->where('logged_at', '>=', now()->subSeconds($countdown + 8))->orderByDesc('logged_at')->first();
        $last = GateLog::whereIn('crossing_id', $ids)->orderByDesc('updated_at')->orderByDesc('id')->first();
        $lastConf = GateLog::whereIn('crossing_id', $ids)->where('trigger_type', 'AUTOMATIC')
            ->whereNotNull('confidence_score')->orderByDesc('logged_at')->value('confidence_score');

        $alert = null;
        $gate = ['state' => 'OPEN', 'mode' => null, 'remaining' => null, 'last_confidence' => $lastConf];

        if ($pending) {
            // kereta terdeteksi, petugas diberi waktu menutup palang
            $left = max(0, $countdown - ($nowTs - $pending->logged_at->timestamp));
            $alert = ['type' => 'COUNTDOWN', 'id' => $pending->id, 'remaining' => $left, 'total' => $countdown, 'confidence' => $pending->confidence_score];
            $gate = ['state' => 'PENDING', 'remaining' => $left] + $gate;
        } elseif ($last && $last->gate_action === 'HELD' && $nowTs - $last->updated_at->timestamp <= self::OBSTACLE_ALERT_SECONDS) {
            // rel terhalang kendaraan/benda: palang ditahan terbuka
            $alert = ['type' => 'OBSTACLE', 'id' => $last->id, 'age' => $nowTs - $last->updated_at->timestamp, 'confidence' => $last->confidence_score];
            $gate = ['state' => 'HELD'] + $gate;
        } elseif ($last && $last->gate_action === 'CLOSED') {
            // Palang terbuka lagi hanya setelah firmware mengirim log OPENED (Node 1 memastikan kereta sudah lewat).
            if ($nowTs - $last->updated_at->timestamp <= self::CLOSED_MAX_SECONDS) {
                $gate = ['state' => 'CLOSED', 'mode' => $last->trigger_type === 'MANUAL' ? 'MANUAL' : 'AUTO'] + $gate;
            }
            if ($nowTs - $last->updated_at->timestamp <= 6) {   // pemberitahuan hasil, tampil sebentar
                $alert = ['type' => 'RESULT', 'id' => $last->id, 'mode' => $last->trigger_type === 'AUTOMATIC' ? 'AUTO' : 'MANUAL',
                    'guard' => $last->guard_response, 'response_ms' => $last->response_time_ms, 'total' => $countdown];
            }
        }
        // CANCELLED: tidak ada peringatan. Hitung mundur / peringatan rintangan langsung hilang karena tidak ada lagi PENDING/HELD.

        // --- perangkat dan sensor ---
        $devices = Device::with('crossing')->whereIn('crossing_id', $ids)->orderBy('device_code')->get();
        $n1 = $devices->firstWhere('type', 'SENSOR_NODE');
        $n2 = $devices->firstWhere('type', 'GATE_CONTROLLER');
        $live = [
            'rssi' => $n2?->rssi ?? $n1?->rssi,
            'distance_cm' => $n2?->distance_cm !== null ? (float) $n2->distance_cm : null,
            'battery' => $n1?->battery_level,
        ];
        $sensors = LogSensor::whereIn('device_id', $devices->pluck('id'))
            ->orderByDesc('reported_at')->orderByDesc('id')->get()
            ->unique(fn ($s) => $s->device_id.'-'.$s->sensor_type)->values()
            ->map(fn ($s) => [
                'device' => $devices->firstWhere('id', $s->device_id)?->device_code,
                'type' => $s->sensor_type, 'state' => $s->operational_state,
                'health' => $s->health_status, 'detail' => $s->detail,
            ]);

        $recent = GateLog::whereIn('crossing_id', $ids)->orderByDesc('logged_at')->orderByDesc('id')->limit(8)->get()
            ->map(fn ($e) => [
                'id' => $e->event_id, 'url' => route('events.show', $e->id),
                'date' => $e->logged_at->format('d/m'), 'time' => $e->logged_at->format('H:i'),
                'trigger' => $e->trigger_type, 'status' => $e->status_text, 'tone' => $e->tone,
                'confidence' => $e->confidence_score, 'notes' => $e->notes,
            ]);

        return response()->json([
            'now_hour' => now()->hour,
            'kpi' => [
                'total' => $closedToday->count(), 'auto' => $auto->count(), 'manual' => $manual->count(),
                'avg_conf' => $confs->count() ? round($confs->avg(), 1) : null,
            ],
            'hourly' => ['today' => $todayHours, 'avg' => array_map(fn ($v) => round($v, 2), $avg)],
            'weekly' => $weekly,
            'gate' => $gate, 'alert' => $alert, 'live' => $live,
            'devices' => $devices->map(fn ($d) => [
                'code' => $d->device_code, 'name' => $d->name, 'type' => $d->type, 'status' => $d->status,
                'seen_s' => $d->last_seen_at ? now()->timestamp - $d->last_seen_at->timestamp : null,
                'battery' => $d->battery_level, 'crossing' => $d->crossing->code,
                'ml_label' => $d->status === 'ACTIVE' ? $d->ml_label : null,
                'ml_confidence' => $d->status === 'ACTIVE' && $d->ml_confidence !== null ? (float) $d->ml_confidence : null,
                'distance_cm' => $d->distance_cm !== null ? (float) $d->distance_cm : null,
            ]),
            'sensors' => $sensors, 'recent' => $recent,
        ]);
    }
}