<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Crossing;
use App\Models\EventNote;
use App\Models\GateLog;
use Illuminate\Http\Request;

class EventController extends Controller
{
    private function filtered(Request $r)
    {
        $ids = $r->user()->scopedCrossingIds((int) $r->query('crossing'));
        $from = $r->query('from', today()->subDays(6)->toDateString());
        $to = $r->query('to', today()->toDateString());
        $trg = $r->query('trigger');
        $act = $r->query('action');

        return GateLog::with(['crossing', 'evaluations'])->whereIn('crossing_id', $ids)
            ->whereDate('logged_at', '>=', $from)->whereDate('logged_at', '<=', $to)
            ->when(in_array($trg, ['AUTOMATIC', 'MANUAL'], true), fn ($q) => $q->where('trigger_type', $trg))
            ->when(in_array($act, ['CLOSED', 'OPENED', 'HELD'], true), fn ($q) => $q->where('gate_action', $act))
            ->orderByDesc('logged_at')->orderByDesc('id');
    }

    public function index(Request $request)
    {
        $base = $this->filtered($request);
        $n = fn ($trigger, $action) => (clone $base)->where('trigger_type', $trigger)->where('gate_action', $action)->count();

        return view('events.index', [
            'events' => $base->simplePaginate(15)->withQueryString(),
            'stats' => [
                'total' => (clone $base)->count(),
                'auto' => $n('AUTOMATIC', 'CLOSED'),
                'manual' => $n('MANUAL', 'CLOSED'),
                'opened' => (clone $base)->where('gate_action', 'OPENED')->count(),
            ],
            'crossings' => Crossing::whereIn('id', $request->user()->accessibleCrossingIds())->orderBy('code')->get(),
            'f' => [
                'from' => $request->query('from', today()->subDays(6)->toDateString()),
                'to' => $request->query('to', today()->toDateString()),
                'trigger' => $request->query('trigger'), 'action' => $request->query('action'),
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
            fputcsv($out, ['Waktu', 'ID Kejadian', 'Pos', 'Pemicu', 'Status Palang', 'Probabilitas Sensor (%)', 'Catatan Sistem', 'Catatan Evaluasi']);
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $e) {
                    fputcsv($out, [
                        $e->logged_at->format('Y-m-d H:i:s'), $e->event_id, $e->crossing->code,
                        $e->trigger_type === 'AUTOMATIC' ? 'Otomatis' : 'Manual', $e->status_text,
                        $e->confidence_score, $e->notes, $e->evaluations->count() ? $e->evaluations->last()->note : '',
                    ]);
                }
            });
            fclose($out);
        }, 'riwayat-railsafe-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Request $request, GateLog $event)
    {
        abort_unless(in_array($event->crossing_id, $request->user()->accessibleCrossingIds(), true), 404);
        $event->load(['crossing', 'device', 'evaluations.user']);

        return view('events.show', ['e' => $event]);
    }

    public function storeNote(Request $request, GateLog $event)
    {
        abort_unless(in_array($event->crossing_id, $request->user()->accessibleCrossingIds(), true), 404);
        $data = $request->validate(['note' => 'required|string|max:500']);

        EventNote::create(['gate_log_id' => $event->id, 'user_id' => $request->user()->id, 'note' => $data['note']]);
        AuditLog::record('add_note', 'gate_log', $event->id);

        return back()->with('status', 'Catatan disimpan.');
    }
}
