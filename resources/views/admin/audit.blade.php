@extends('layouts.app')
@section('title', 'Audit log')

@section('content')
<div class="top"><h1>Audit log</h1></div>
<section class="card">
  <div class="tw"><table>
    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    @forelse($logs as $l)
      <tr style="cursor:default">
        <td>{{ $l->created_at->format('d M H:i:s') }}</td>
        <td>{{ $l->user->name ?? '-' }}</td>
        <td class="mono">{{ $l->action }}</td>
        <td>{{ $l->target_type ? $l->target_type.' #'.$l->target_id : '-' }}</td>
        <td class="mono">{{ $l->meta ? \Illuminate\Support\Str::limit(json_encode($l->meta), 60) : '' }}</td>
        <td class="mono">{{ $l->ip_address }}</td>
      </tr>
    @empty
      <tr><td colspan="6">Belum ada aktivitas.</td></tr>
    @endforelse
    </tbody>
  </table></div>
  <div class="pager">
    @if($logs->previousPageUrl())<a class="btn sm ghost" href="{{ $logs->previousPageUrl() }}">Sebelumnya</a>@endif
    @if($logs->nextPageUrl())<a class="btn sm ghost" href="{{ $logs->nextPageUrl() }}">Berikutnya</a>@endif
  </div>
</section>
@endsection
