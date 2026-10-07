@extends('layouts.app')

@section('title', 'Log audit · SIPASTI')

@section('content')
<div class="page-head">
    <div>
        <h1>Log audit</h1>
        <div class="muted">Hanya-tambah. Pemicu basis data menolak perintah ubah dan hapus.</div>
    </div>
</div>

<form class="card inline" method="get">
    <div><label>Pelaku</label><input name="actor" value="{{ $filters['actor'] ?? '' }}"></div>
    <div>
        <label>Aksi</label>
        <select name="action">
            <option value="">Semua</option>
            @foreach ($actions as $action)
                <option @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
            @endforeach
        </select>
    </div>
    <div><label>Entitas</label><input name="entity" value="{{ $filters['entity'] ?? '' }}"></div>
    <div><label>ID entitas</label><input name="entity_id" value="{{ $filters['entity_id'] ?? '' }}"></div>
    <div style="flex:0"><button type="submit">Saring</button></div>
</form>

<div class="card">
    <table>
        <thead><tr><th>#</th><th>Waktu</th><th>Pelaku</th><th>Aksi</th><th>Entitas</th><th>Rincian</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="mono small">{{ $log->id }}</td>
                <td class="small">{{ $log->created_at }}</td>
                <td class="mono small">{{ $log->actor }}</td>
                <td><span class="badge neutral">{{ $log->action }}</span></td>
                <td class="small">{{ $log->entity }} {{ $log->entity_id }}</td>
                <td class="small">{{ $log->detail }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Tidak ada catatan.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if ($logs->hasPages())
        <ul class="pagination">
            @if ($logs->previousPageUrl())<li><a href="{{ $logs->previousPageUrl() }}">← Sebelumnya</a></li>@endif
            <li><span>Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}</span></li>
            @if ($logs->nextPageUrl())<li><a href="{{ $logs->nextPageUrl() }}">Berikutnya →</a></li>@endif
        </ul>
    @endif
</div>
@endsection
