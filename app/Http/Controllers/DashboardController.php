<?php

namespace App\Http\Controllers;

use App\Models\Crossing;
use App\Models\Device;
use App\Models\LogPerlintasan;
use App\Models\LogSensor;
use App\Models\Setting;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $crossings = Crossing::whereIn('id', $user->accessibleCrossingIds())->orderBy('code')->get();

        return view('dashboard', ['crossings' => $crossings, 'selected' => (int) $request->query('crossing')]);
    }

    /** JSON untuk pembaruan berkala (AJAX) di dashboard. */
    public function summary(Request $request)
    {
        $ids = $request->user()->scopedCrossingIds((int) $request->query('crossing'));
        $countdown = (int) Setting::get('countdown_seconds');
        $today = now()->startOfDay();

        // --- kejadian hari ini ---
        $events = LogPerlintasan::with('kontrol')->whereIn('crossing_id', $ids)
            ->where('detected_at', '>=', $today)->orderBy('detected_at')->get();
        $k = $events->pluck('kontrol')->filter();
        $manual = $k->where('control_mode', 'MANUAL')->count();
        $auto = $k->where('control_mode', 'AUTO')->count();

        // --- per jam: hari ini vs rata-rata 7 hari sebelumnya ---
        $todayHours = array_fill(0, 24, 0);
        foreach ($events as $e) $todayHours[$e->detected_at->hour]++;
        $avg = array_fill(0, 24, 0.0);
        LogPerlintasan::whereIn('crossing_id', $ids)
            ->whereBetween('detected_at', [$today->copy()->subDays(7), $today])->get(['detected_at'])
            ->each(function ($e) use (&$avg) { $avg[$e->detected_at->hour] += 1 / 7; });

        // --- 7 hari terakhir ---
        $since = $today->copy()->subDays(6);
        $week = LogPerlintasan::with('kontrol')->whereIn('crossing_id', $ids)
            ->where('detected_at', '>=', $since)->get()->groupBy(fn ($e) => $e->detected_at->toDateString());
        $weekly = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $rows = $week->get($d->toDateString(), collect());
            $weekly[] = [
                'label' => $d->locale('id')->isoFormat('ddd'),
                'total' => $rows->count(),
                'no_response' => $rows->filter(fn ($r) => $r->kontrol && $r->kontrol->guard_response === 'NO_RESPONSE')->count(),
            ];
        }

        // --- perangkat dan sensor ---
        $devices = Device::with('crossing')->whereIn('crossing_id', $ids)->orderBy('device_code')->get();
        $n1 = $devices->firstWhere('type', 'SENSOR_NODE');
        $n2 = $devices->firstWhere('type', 'GATE_CONTROLLER');
        $live = [
            'ml_label' => $n1 && $n1->status === 'ACTIVE' ? $n1->ml_label : null,
            'ml_confidence' => $n1?->ml_confidence !== null ? (float) $n1->ml_confidence : null,
            'battery' => $n1?->battery_level,
            'rssi' => $n2?->rssi ?? $n1?->rssi,
            'distance_cm' => $n2?->distance_cm !== null ? (float) $n2->distance_cm : null,
        ];
        $sensors = LogSensor::whereIn('device_id', $devices->pluck('id'))
            ->orderByDesc('reported_at')->orderByDesc('id')->get()
            ->unique(fn ($s) => $s->device_id.'-'.$s->sensor_type)->values()
            ->map(fn ($s) => [
                'device' => $devices->firstWhere('id', $s->device_id)?->device_code,
                'type' => $s->sensor_type, 'state' => $s->operational_state,
                'health' => $s->health_status, 'detail' => $s->detail,
            ]);

        // --- alarm: kereta terdeteksi, belum ada respons/aksi, masih dalam jendela hitung mundur ---
        $alert = null;
        $open = LogPerlintasan::whereIn('crossing_id', $ids)->doesntHave('kontrol')
            ->where('detected_at', '>=', now()->subSeconds($countdown + 3))->orderByDesc('detected_at')->first();
        if ($open) {
            $left = max(0, $countdown - (now()->timestamp - $open->detected_at->timestamp));
            $alert = ['event_id' => $open->event_id, 'confidence' => $open->confidence, 'remaining' => $left, 'total' => $countdown];
        }

        // --- tabel terbaru dan daftar perlu ditinjau ---
        $recent = LogPerlintasan::with('kontrol')->whereIn('crossing_id', $ids)->orderByDesc('detected_at')->limit(8)->get()
            ->map(fn ($e) => [
                'id' => $e->event_id, 'url' => route('events.show', $e->id),
                'time' => $e->detected_at->format('H:i'), 'date' => $e->detected_at->format('d/m'),
                'confidence' => $e->confidence, 'status' => $e->kontrol->status_label ?? 'TRAIN_INBOUND',
                'mode' => $e->kontrol->control_mode ?? null, 'guard' => $e->kontrol->guard_response ?? null,
                'response_ms' => $e->kontrol->response_time_ms ?? null, 'obstacle' => $e->kontrol->obstacle_detected ?? null,
            ]);
        $review = LogPerlintasan::with('kontrol')->whereIn('crossing_id', $ids)
            ->whereHas('kontrol', fn ($q) => $q->where('guard_response', 'NO_RESPONSE'))
            ->orderByDesc('detected_at')->limit(4)->get()
            ->map(fn ($e) => ['id' => $e->event_id, 'url' => route('events.show', $e->id),
                'date' => $e->detected_at->format('d M'), 'time' => $e->detected_at->format('H:i'), 'status' => $e->kontrol->status_label]);

        return response()->json([
            'now_hour' => now()->hour,
            'kpi' => [
                'total' => $events->count(), 'manual' => $manual, 'auto' => $auto,
                'no_response' => $k->where('guard_response', 'NO_RESPONSE')->count(),
                'obstacle' => $k->where('obstacle_detected', true)->count(),
                'manual_pct' => ($manual + $auto) ? (int) round($manual / ($manual + $auto) * 100) : 0,
            ],
            'hourly' => ['today' => $todayHours, 'avg' => array_map(fn ($v) => round($v, 2), $avg)],
            'weekly' => $weekly,
            'devices' => $devices->map(fn ($d) => [
                'code' => $d->device_code, 'name' => $d->name, 'type' => $d->type, 'status' => $d->status,
                'seen_s' => $d->last_seen_at ? now()->timestamp - $d->last_seen_at->timestamp : null,
                'battery' => $d->battery_level, 'crossing' => $d->crossing->code,
            ]),
            'sensors' => $sensors, 'live' => $live, 'alert' => $alert,
            'recent' => $recent, 'review' => $review,
        ]);
    }
}
