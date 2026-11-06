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

        // --- status palang (perkiraan dari log terakhir; firmware belum mengirim log saat palang terbuka otomatis) ---
        $last = GateLog::whereIn('crossing_id', $ids)->orderByDesc('logged_at')->orderByDesc('id')->first();
        $lastAuto = GateLog::whereIn('crossing_id', $ids)->where('trigger_type', 'AUTOMATIC')->where('gate_action', 'CLOSED')
            ->orderByDesc('logged_at')->first();
        $gate = ['state' => 'OPEN', 'mode' => null, 'remaining' => null, 'last_confidence' => $lastAuto?->confidence_score];
        if ($last && $last->gate_action === 'CLOSED') {
            if ($last->trigger_type === 'MANUAL') {
                $gate['state'] = 'CLOSED';
                $gate['mode'] = 'MANUAL';
            } else {
                $left = $reopen - (now()->timestamp - $last->logged_at->timestamp);
                if ($left > 0) $gate = ['state' => 'CLOSED', 'mode' => 'AUTO', 'remaining' => $left] + $gate;
            }
        }

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
            'gate' => $gate, 'live' => $live,
            'devices' => $devices->map(fn ($d) => [
                'code' => $d->device_code, 'name' => $d->name, 'type' => $d->type, 'status' => $d->status,
                'seen_s' => $d->last_seen_at ? now()->timestamp - $d->last_seen_at->timestamp : null,
                'battery' => $d->battery_level, 'crossing' => $d->crossing->code,
            ]),
            'sensors' => $sensors, 'recent' => $recent,
        ]);
    }
}
