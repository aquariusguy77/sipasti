@extends('layouts.app')

@section('title', 'Registrasi perkara · SIPASTI')

@php $v = fn ($k, $default = null) => old($k, $default); @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Registrasi perkara</h1>
        <div class="muted">Medan identitas dibatasi pada isian Kartu Deteni dan berita acara pendetensian.</div>
    </div>
</div>

<form method="post" action="{{ route('cases.store') }}">
    @csrf
    <div class="grid grid-2">
        <div class="card">
            <h2>Identitas deteni</h2>
            <label>Nama lengkap</label>
            <input name="full_name" value="{{ $v('full_name') }}" required>
            <div class="inline">
                <div>
                    <label>Jenis kelamin</label>
                    <select name="sex">
                        <option value="L" @selected($v('sex') === 'L')>L</option>
                        <option value="P" @selected($v('sex') === 'P')>P</option>
                    </select>
                </div>
                <div><label>Kewarganegaraan</label><input name="nationality" value="{{ $v('nationality') }}" required></div>
            </div>
            <div class="inline">
                <div><label>Tempat lahir</label><input name="birth_place" value="{{ $v('birth_place') }}"></div>
                <div><label>Tanggal lahir</label><input type="date" name="birth_date" value="{{ $v('birth_date') }}"></div>
            </div>
            <div class="inline">
                <div><label>Nomor dokumen perjalanan</label><input name="travel_document_no" value="{{ $v('travel_document_no') }}"></div>
                <div><label>Tempat terbit</label><input name="travel_document_place" value="{{ $v('travel_document_place') }}"></div>
                <div><label>Tanggal terbit</label><input type="date" name="travel_document_date" value="{{ $v('travel_document_date') }}"></div>
            </div>
            <label>Instansi pengirim</label>
            <input name="sending_agency" value="{{ $v('sending_agency') }}">
            <label>Ketentuan yang dilanggar</label>
            <input name="provision_violated" value="{{ $v('provision_violated') }}">
            <label class="check" style="margin-top:10px"><input type="checkbox" name="biometric_captured" value="1" @checked($v('biometric_captured'))> Sidik jari dan foto telah diambil sesuai pedoman (hanya penanda)</label>
        </div>

        <div class="card">
            <h2>Perkara</h2>
            <label>Nomor perkara</label>
            <input name="case_number" value="{{ $v('case_number') }}" required>
            <div class="inline">
                <div><label>Nomor keputusan pendetensian</label><input name="detention_order_no" value="{{ $v('detention_order_no') }}" required></div>
                <div><label>Tanggal keputusan pendetensian</label><input type="date" name="detention_order_date" value="{{ $v('detention_order_date', $asOf->toDateString()) }}" required></div>
            </div>
            <label>Ruang detensi asal</label>
            <select name="detention_room_type">
                <option value="">–</option>
                <option value="KANIM" @selected($v('detention_room_type') === 'KANIM')>Ruang Detensi Kantor Imigrasi</option>
                <option value="DITJENIM" @selected($v('detention_room_type') === 'DITJENIM')>Ruang Detensi Ditjen Imigrasi</option>
            </select>
            <div class="inline">
                <div>
                    <label>Tahap awal</label>
                    <select name="initial_stage">
                        @foreach ($stages as $code => $label)
                            @continue(in_array($code, config('sipasti.deportation_stages'), true))
                            <option value="{{ $code }}" @selected((int) $v('initial_stage', 1) === $code)>{{ $code }}. {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Tahap dibuka pada</label><input type="date" name="stage_opened_at" value="{{ $v('stage_opened_at', $asOf->toDateString()) }}" required></div>
            </div>

            <h3 style="margin-top:16px">Status hukum</h3>
            <select name="legal_status_code">
                @foreach ($legalStatuses as $status)
                    <option value="{{ $status->code }}" @selected($v('legal_status_code', 'DETENI') === $status->code)>{{ $status->code }} · {{ $status->name }}</option>
                @endforeach
            </select>
            <p class="small muted">Status selain DETENI menuntut rujukan penetapan.</p>
            <div class="inline">
                <div><label>Nomor rujukan penetapan</label><input name="status_determination_ref" value="{{ $v('status_determination_ref') }}"></div>
                <div><label>Otoritas</label><input name="status_determination_authority" value="{{ $v('status_determination_authority') }}"></div>
                <div><label>Tanggal penetapan</label><input type="date" name="status_determination_date" value="{{ $v('status_determination_date') }}"></div>
            </div>
        </div>
    </div>
    <button type="submit">Daftarkan perkara</button>
</form>
@endsection
