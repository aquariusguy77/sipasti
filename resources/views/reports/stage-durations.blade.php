@extends('layouts.app')

@section('title', 'Laporan durasi tahap · SIPASTI')

@php $maxMean = max(1, $rows->max('completed_mean') ?? 1); @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Laporan durasi tahap</h1>
        <div class="muted">Tanggal acuan {{ $asOf->translatedFormat('d F Y') }}. Durasi dalam hari kalender, dihitung dari peristiwa tahap.</div>
    </div>
    <a class="btn secondary" href="{{ route('reports.stage-durations', array_filter($filters) + ['format' => 'csv']) }}">Unduh CSV</a>
</div>

<form class="card inline" method="get">
    <div><label>Dari</label><input type="date" name="from" value="{{ $filters['from'] }}"></div>
    <div><label>Sampai</label><input type="date" name="to" value="{{ $filters['to'] }}"></div>
    <div style="flex:0"><button type="submit">Terapkan</button></div>
</form>

<div class="card">
    <h2>Durasi per tahap</h2>
    <table>
        <thead>
            <tr>
                <th>Tahap</th>
                <th class="num">Selesai</th>
                <th class="num">Rerata</th>
                <th class="bar-cell"></th>
                <th class="num">Median</th>
                <th class="num">Maks</th>
                <th class="num">Berjalan</th>
                <th class="num">Terlama berjalan</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['stage'] }}. {{ $row['label'] }}</td>
                <td class="num">{{ $row['completed_count'] }}</td>
                <td class="num">{{ $row['completed_mean'] ?? '–' }}</td>
                <td class="bar-cell">
                    @if ($row['completed_mean'])
                        <div class="bar" style="width: {{ round($row['completed_mean'] / $maxMean * 100) }}%"></div>
                    @endif
                </td>
                <td class="num">{{ $row['completed_median'] ?? '–' }}</td>
                <td class="num">{{ $row['completed_max'] ?? '–' }}</td>
                <td class="num">{{ $row['ongoing_count'] }}</td>
                <td class="num">{{ $row['ongoing_max'] ?? '–' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Sebab tertahan per tahap</h2>
    <p class="small muted">Daftar sebab bersifat tertutup agar dapat dibandingkan antar perkara.</p>
    <table>
        <thead>
            <tr>
                <th>Tahap</th>
                @foreach ($causes as $label)
                    <th class="num">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['stage'] }}. {{ $row['label'] }}</td>
                @foreach ($row['stall_causes'] as $count)
                    <td class="num">{{ $count ?: '' }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<p class="small muted">Laporan ini bersifat agregat dan tidak memuat identitas deteni.</p>
@endsection
