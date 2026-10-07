@extends('layouts.app')

@section('title', 'Indikator · SIPASTI')

@section('content')
<div class="page-head">
    <div>
        <h1>Indikator peringatan dini</h1>
        <div class="muted">Ambang disimpan sebagai data. Perubahan pedoman diterapkan dengan menyunting baris di bawah ini, dan setiap suntingan tercatat pada log audit.</div>
    </div>
    @can('indicators.run')
        <form method="post" action="{{ route('indicators.run') }}">
            @csrf
            <button type="submit">Jalankan evaluasi indikator</button>
        </form>
    @endcan
</div>

@foreach ($indicators as $indicator)
    <div class="card">
        <form method="post" action="{{ route('indicators.update', $indicator) }}">
            @csrf @method('put')
            <h3>{{ $indicator->code }} · {{ $indicator->name }}</h3>
            <div class="small muted">Mode {{ $indicator->mode }} · jangkar {{ $indicator->anchor ?? 'keadaan perkara' }}</div>
            <div class="inline">
                <div style="max-width:200px">
                    <label>Ambang @if ($indicator->threshold_unit) ({{ $indicator->threshold_unit }}) @endif</label>
                    <input name="threshold_value" value="{{ $indicator->threshold_value }}" @disabled($indicator->mode === 'condition')>
                </div>
                <div><label>Dasar normatif</label><input name="normative_basis" value="{{ $indicator->normative_basis }}"></div>
                <div><label>Tindakan yang dipicu</label><input name="triggered_action" value="{{ $indicator->triggered_action }}"></div>
                <div style="flex:0">
                    <label class="check"><input type="checkbox" name="active" value="1" @checked($indicator->active)> Aktif</label>
                </div>
                <div style="flex:0"><button type="submit">Simpan</button></div>
            </div>
        </form>
    </div>
@endforeach
@endsection
