<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Crossing;
use App\Models\EventNote;
use App\Models\LogPerlintasan;
use Illuminate\Http\Request;

class EventController extends Controller
{
    private function filtered(Request $r)
    {
        $ids = $r->user()->scopedCrossingIds((int) $r->query('crossing'));
        $from = $r->query('from', today()->subDays(6)->toDateString());
        $to = $r->query('to', today()->toDateString());
        $mode = $r->query('mode');
        $resp = $r->query('response');

        return LogPerlintasan::with(['kontrol', 'crossing', 'notes'])->whereIn('crossing_id', $ids)
            ->whereDate('detected_at', '>=', $from)->whereDate('detected_at', '<=', $to)
            ->when(in_array($mode, ['MANUAL', 'AUTO'], true), fn ($q) => $q->whereHas('kontrol', fn ($k) => $k->where('control_mode', $mode)))
            ->when(in_array($resp, ['CONFIRMED', 'NO_RESPONSE'], true), fn ($q) => $q->whereHas('kontrol', fn ($k) => $k->where('guard_response', $resp)))
            ->orderByDesc('detected_at');
    }

    public function index(Request $request)
    {
        $base = $this->filtered($request);
        $count = fn ($col, $val) => (clone $base)->whereHas('kontrol', fn ($k) => $k->where($col, $val))->count();

        $stats = [
            'total' => (clone $base)->count(),
            'manual' => $count('control_mode', 'MANUAL'),
            'auto' => $count('control_mode', 'AUTO'),
            'no_response' => $count('guard_response', 'NO_RESPONSE'),
        ];

        return view('events.index', [
            'events' => $base->simplePaginate(15)->withQueryString(),
            'stats' => $stats,
            'crossings' => Crossing::whereIn('id', $request->user()->accessibleCrossingIds())->orderBy('code')->get(),
            'f' => [
                'from' => $request->query('from', today()->subDays(6)->toDateString()),
                'to' => $request->query('to', today()->toDateString()),
                'mode' => $request->query('mode'), 'response' => $request->query('response'),
                'crossing' => $request->query('crossing'),
            ],
        ]);
    }

    /** Ekspor CSV (UTF-8 dengan BOM agar langsung terbaca di Excel). */
    public function export(Request $request)
    {
        AuditLog::record('export_report', 'events', null, $request->query());
        $q = $this->filtered($request);

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Waktu', 'ID Kejadian', 'Pos', 'Pemicu', 'Probabilitas (%)', 'Respons Petugas', 'Waktu Respons (dtk)', 'Rintangan', 'Status Palang', 'Catatan Operator']);
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $e) {
                    $k = $e->kontrol;
                    fputcsv($out, [
                        $e->detected_at->format('Y-m-d H:i:s'), $e->event_id, $e->crossing->code,
                        $k ? ($k->control_mode === 'AUTO' ? 'Otomatis' : 'Manual') : '-',
                        $e->confidence, $k ? ($k->guard_response === 'CONFIRMED' ? 'Menekan tombol' : 'Tidak ada respons') : '-',
                        $k && $k->response_time_ms !== null ? round($k->response_time_ms / 1000, 1) : '',
                        $k ? ($k->obstacle_detected ? 'Terdeteksi' : 'Bebas') : '-',
                        $k->gate_text ?? 'Menunggu', $e->notes->last()->note ?? '',
                    ]);
                }
            });
            fclose($out);
        }, 'riwayat-railsafe-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Request $request, LogPerlintasan $event)
    {
        abort_unless(in_array($event->crossing_id, $request->user()->accessibleCrossingIds(), true), 404);
        $event->load(['kontrol', 'crossing', 'device', 'notes.user']);

        return view('events.show', ['e' => $event]);
    }

    public function storeNote(Request $request, LogPerlintasan $event)
    {
        abort_unless(in_array($event->crossing_id, $request->user()->accessibleCrossingIds(), true), 404);
        $data = $request->validate(['note' => 'required|string|max:500']);

        EventNote::create(['event_id' => $event->event_id, 'user_id' => $request->user()->id, 'note' => $data['note']]);
        AuditLog::record('add_note', 'event', $event->event_id);

        return back()->with('status', 'Catatan disimpan.');
    }
}
