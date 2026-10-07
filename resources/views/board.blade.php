@extends('layouts.app')

@section('title', 'Papan perkara · SIPASTI')

@section('content')
<div class="page-head">
    <div>
        <h1>Papan perkara</h1>
        <div class="muted">Tanggal acuan {{ $asOf->translatedFormat('d F Y') }} · {{ $caseCount }} perkara terbuka ditampilkan</div>
    </div>
    @can('indicators.run')
        <form method="post" action="{{ route('indicators.run') }}">
            @csrf
            <button type="submit">Jalankan evaluasi indikator</button>
        </form>
    @endcan
</div>

<div class="summary" style="margin-bottom:16px">
    @foreach (['kritis', 'peringatan', 'perhatian'] as $t)
        <a class="stat" style="text-decoration:none;color:inherit" href="{{ route('board', ['tier' => $t]) }}">
            <strong>{{ $tierCounts[$t] ?? 0 }}</strong>
            @include('partials.tier', ['tier' => $t])
        </a>
    @endforeach
</div>

<form class="card inline" method="get" action="{{ route('board') }}">
    <div>
        <label for="q">Nomor perkara</label>
        <input id="q" name="q" value="{{ $filters['q'] }}" placeholder="mis. SIM-KANIM">
    </div>
    <div>
        <label for="tier">Tingkat peringatan</label>
        <select id="tier" name="tier">
            <option value="">Semua</option>
            @foreach (['kritis', 'peringatan', 'perhatian'] as $t)
                <option value="{{ $t }}" @selected($filters['tier'] === $t)>{{ ucfirst($t) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="indicator">Indikator</label>
        <select id="indicator" name="indicator">
            <option value="">Semua</option>
            @foreach ($indicators as $i)
                <option value="{{ $i->code }}" @selected($filters['indicator'] === $i->code)>{{ $i->code }} · {{ $i->name }}</option>
            @endforeach
        </select>
    </div>
    <div style="flex:0">
        <button type="submit">Saring</button>
    </div>
    @if (array_filter($filters))
        <div style="flex:0"><a class="btn secondary" href="{{ route('board') }}">Atur ulang</a></div>
    @endif
</form>

<p class="muted small">
    Kartu memuat keadaan berkas perkara, bukan identitas deteni. Kolom berwarna ungu adalah tahap jalur deportasi yang dijaga gerbang status.
</p>

<div class="board">
    @foreach ($stages as $code => $label)
        @php $cards = $columns->get($code, collect()); @endphp
        <section @class(['column', 'gated' => in_array($code, config('sipasti.deportation_stages'), true)])>
            <h3><span>{{ $code }}. {{ $label }}</span><span class="count">{{ $cards->count() }}</span></h3>
            @foreach ($cards as $case)
                <a href="{{ route('cases.show', $case) }}" class="case-card {{ $case->board_tier ? 't-'.$case->board_tier : '' }}">
                    <div class="num">{{ $case->case_number }}</div>
                    <div class="meta">Hari ke-{{ $case->board_days }} detensi · {{ $case->legal_status_code }}</div>
                    @if ($case->alerts->isNotEmpty() || ! $case->legalStatus?->allows_deportation)
                        <div class="badges">
                            @unless ($case->legalStatus?->allows_deportation)
                                <span class="badge protected">dilindungi</span>
                            @endunless
                            @foreach ($case->alerts->sortBy(fn ($a) => (int) substr($a->indicator_code, 1)) as $alert)
                                <span class="badge tier-{{ $alert->tier }}" title="{{ $alert->tier }}">{{ $alert->indicator_code }}</span>
                            @endforeach
                        </div>
                    @endif
                </a>
            @endforeach
        </section>
    @endforeach
</div>

<div class="card" style="margin-top:16px">
    <h2>Peringatan terbuka ({{ $openAlerts->count() }})</h2>
    @if ($openAlerts->isEmpty())
        <p class="muted">Tidak ada peringatan terbuka untuk saringan ini.</p>
    @else
        <table>
            <thead>
                <tr><th>Tingkat</th><th>Perkara</th><th>Indikator</th><th>Terbit</th><th>Tindakan yang dipicu</th></tr>
            </thead>
            <tbody>
                @foreach ($openAlerts as $alert)
                    <tr>
                        <td>@include('partials.tier', ['tier' => $alert->tier])</td>
                        <td><a href="{{ route('cases.show', $alert->immigration_case_id) }}">{{ $alert->immigrationCase->case_number }}</a></td>
                        <td><strong>{{ $alert->indicator_code }}</strong> {{ $alert->indicator->name }}</td>
                        <td>{{ $alert->raised_at->format('d-m-Y') }}</td>
                        <td>{{ $alert->indicator->triggered_action }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
