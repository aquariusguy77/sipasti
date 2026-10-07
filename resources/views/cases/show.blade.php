@extends('layouts.app')

@section('title', $case->case_number.' · SIPASTI')

@php
    $open = $case->state === 'open';
    $d = fn ($date) => $date?->format('d-m-Y') ?? '–';
    $events = $case->stageEvents;
@endphp

@section('content')
<div class="page-head">
    <div>
        <div class="small"><a href="{{ route('board') }}">← Papan perkara</a></div>
        <h1>{{ $case->case_number }}</h1>
        <div class="muted">
            @if ($open)
                <span class="badge neutral">terbuka</span>
            @else
                <span class="badge closed">ditutup {{ $d($case->closed_at) }} · {{ config('sipasti.closure_reasons.'.$case->closure_reason, $case->closure_reason) }}</span>
            @endif
            Tahap {{ $case->current_stage }}, {{ $stages[$case->current_stage] ?? '' }}
            · hari ke-{{ $case->detentionDays($open ? $asOf : $case->closed_at) }} detensi
            @unless ($deportationAllowed)
                · <span class="badge protected">dilindungi gerbang status</span>
            @endunless
        </div>
    </div>
</div>

<div class="grid grid-2">
<div>
    {{-- Peringatan dan peninjauan (FR10) --}}
    <div class="card">
        <h2>Peringatan</h2>
        @forelse ($case->alerts as $alert)
            <div @class(['alert-item', 'closed' => $alert->state === 'closed'])>
                <div>
                    @include('partials.tier', ['tier' => $alert->tier])
                    <strong>{{ $alert->indicator_code }}</strong> {{ $alert->indicator->name }}
                    @if ($alert->state === 'closed')
                        <span class="badge closed">ditutup</span>
                    @endif
                </div>
                <div class="small muted">
                    Terbit {{ $d($alert->raised_at) }} · Dasar: {{ $alert->indicator->normative_basis }}
                </div>
                <div class="small">Tindakan: {{ $alert->indicator->triggered_action }}</div>

                @foreach ($alert->reviews as $review)
                    <div class="small" style="margin-top:6px;padding:6px 8px;background:var(--bg);border-radius:4px">
                        <strong>Peninjauan {{ $d($review->reviewed_at) }}</strong> oleh {{ $review->reviewer_role }}:
                        {{ $review->decision }}<br>
                        <span class="muted">Alasan:</span> {{ $review->reasoning }}
                    </div>
                @endforeach

                @if ($alert->state === 'open')
                    @can('alert.review')
                        <details>
                            <summary>Tinjau dan tutup</summary>
                            <form method="post" action="{{ route('alerts.close', $alert) }}">
                                @csrf
                                <label>Keputusan</label>
                                <input name="decision" placeholder="mis. eskalasi ke Direktorat Kerja Sama Keimigrasian">
                                <label>Alasan peninjauan (wajib)</label>
                                <textarea name="reasoning"></textarea>
                                <label>Tanggal peninjauan</label>
                                <input type="date" name="reviewed_at" value="{{ $asOf->toDateString() }}">
                                <p><button type="submit">Tutup peringatan</button></p>
                            </form>
                        </details>
                    @endcan
                @endif
            </div>
        @empty
            <p class="muted">Belum ada peringatan untuk perkara ini.</p>
        @endforelse
    </div>

    {{-- Identitas, dibatasi NFR4 --}}
    <div class="card">
        <h2>Identitas deteni</h2>
        @if ($case->detainee->anonymised_at)
            <p class="muted">Identitas telah dianonimkan oleh aturan retensi pada {{ $d($case->detainee->anonymised_at) }}.</p>
        @endif
        <dl class="kv">
            <dt>Nama</dt><dd>{{ $case->detainee->full_name }}</dd>
            <dt>Jenis kelamin</dt><dd>{{ $case->detainee->sex }}</dd>
            <dt>Tempat, tanggal lahir</dt><dd>{{ $case->detainee->birth_place ?? '–' }}, {{ $d($case->detainee->birth_date) }}</dd>
            <dt>Kewarganegaraan</dt><dd>{{ $case->detainee->nationality }}</dd>
            <dt>Dokumen perjalanan</dt>
            <dd>
                @if ($case->hasTravelDocument())
                    {{ $case->detainee->travel_document_no }}
                    <span class="muted">{{ $case->detainee->travel_document_place }} {{ $d($case->detainee->travel_document_date) }}</span>
                @else
                    <span class="badge tier-peringatan">tidak ada</span>
                @endif
            </dd>
            <dt>Instansi pengirim</dt><dd>{{ $case->detainee->sending_agency ?? '–' }}</dd>
            <dt>Ketentuan dilanggar</dt><dd>{{ $case->detainee->provision_violated ?? '–' }}</dd>
            <dt>Biometrik diambil</dt><dd>{{ $case->detainee->biometric_captured ? 'Ya (penanda saja, data tidak disimpan)' : 'Belum' }}</dd>
        </dl>
        <p class="small muted">Pembukaan halaman ini tercatat pada log audit.</p>
    </div>

    {{-- Data perkara --}}
    <div class="card">
        <h2>Data perkara</h2>
        <dl class="kv">
            <dt>Status hukum</dt>
            <dd>
                <strong>{{ $case->legal_status_code }}</strong> {{ $case->legalStatus?->name }}<br>
                <span class="small muted">Rujukan {{ $case->status_determination_ref ?? '–' }} · {{ $case->status_determination_authority ?? '–' }} · {{ $d($case->status_determination_date) }}</span>
            </dd>
            <dt>Keputusan pendetensian</dt><dd>{{ $case->detention_order_no }}, {{ $d($case->detention_order_date) }}</dd>
            <dt>Ruang detensi asal</dt><dd>{{ $case->detention_room_type ?? '–' }}</dd>
            <dt>Keputusan deportasi</dt>
            <dd>{{ $case->deportation_decision_no ?? '–' }} @if ($case->deportation_decision_date) , {{ $d($case->deportation_decision_date) }}, berlaku s.d. {{ $d($case->deportation_decision_valid_until) }} @endif</dd>
            <dt>Sumber pembiayaan</dt><dd>{{ $case->funding_source ?? '–' }} @if ($case->funding_exhausted) <span class="badge tier-peringatan">hierarki habis</span> @endif</dd>
            <dt>Penjaminan dicabut</dt><dd>{{ $d($case->guarantee_revoked_at) }}</dd>
        </dl>

        @if ($open)
            @can('case.update')
                <details>
                    <summary>Perbarui data perkara</summary>
                    <form method="post" action="{{ route('cases.update', $case) }}">
                        @csrf @method('put')
                        <div class="inline">
                            <div>
                                <label>Ruang detensi asal</label>
                                <select name="detention_room_type">
                                    <option value="">–</option>
                                    @foreach (['KANIM', 'DITJENIM'] as $room)
                                        <option @selected($case->detention_room_type === $room)>{{ $room }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label>Penjaminan dicabut pada</label>
                                <input type="date" name="guarantee_revoked_at" value="{{ $case->guarantee_revoked_at?->toDateString() }}">
                            </div>
                        </div>
                        <div class="inline">
                            <div>
                                <label>Nomor dokumen perjalanan</label>
                                <input name="travel_document_no" value="{{ $case->detainee->travel_document_no }}">
                            </div>
                            <div>
                                <label>Tempat terbit</label>
                                <input name="travel_document_place" value="{{ $case->detainee->travel_document_place }}">
                            </div>
                            <div>
                                <label>Tanggal terbit</label>
                                <input type="date" name="travel_document_date" value="{{ $case->detainee->travel_document_date?->toDateString() }}">
                            </div>
                        </div>
                        <div class="inline">
                            <div>
                                <label>Sumber pembiayaan</label>
                                <input name="funding_source" value="{{ $case->funding_source }}">
                            </div>
                            <div>
                                <label class="check"><input type="checkbox" name="funding_exhausted" value="1" @checked($case->funding_exhausted)> Hierarki pembiayaan habis</label>
                            </div>
                        </div>
                        <fieldset style="margin-top:10px;border:1px solid var(--border);border-radius:4px">
                            <legend class="small muted">Keputusan TAK deportasi, dijaga gerbang status</legend>
                            <div class="inline">
                                <div><label>Nomor</label><input name="deportation_decision_no" value="{{ $case->deportation_decision_no }}"></div>
                                <div><label>Tanggal</label><input type="date" name="deportation_decision_date" value="{{ $case->deportation_decision_date?->toDateString() }}"></div>
                                <div><label>Berlaku s.d.</label><input type="date" name="deportation_decision_valid_until" value="{{ $case->deportation_decision_valid_until?->toDateString() }}"></div>
                            </div>
                        </fieldset>
                        <p><button type="submit">Simpan</button></p>
                    </form>
                </details>
            @endcan

            @can('status.change')
                <details>
                    <summary>Ubah penanda status hukum</summary>
                    <p class="small muted">Perubahan menuntut rujukan penetapan dari otoritas yang berwenang. Perubahan tanpa rujukan ditolak dan tercatat.</p>
                    <form method="post" action="{{ route('cases.status', $case) }}">
                        @csrf
                        <label>Status baru</label>
                        <select name="legal_status_code">
                            @foreach ($legalStatuses as $status)
                                <option value="{{ $status->code }}" @selected($status->code === $case->legal_status_code)>{{ $status->code }} · {{ $status->name }}</option>
                            @endforeach
                        </select>
                        <div class="inline">
                            <div><label>Nomor rujukan penetapan</label><input name="status_determination_ref"></div>
                            <div><label>Otoritas</label><input name="status_determination_authority" placeholder="mis. UNHCR"></div>
                            <div><label>Tanggal penetapan</label><input type="date" name="status_determination_date"></div>
                        </div>
                        <p><button type="submit">Ubah status</button></p>
                    </form>
                </details>
            @endcan
        @endif
    </div>
</div>

<div>
    {{-- Tahap sebagai peristiwa --}}
    <div class="card">
        <h2>Riwayat tahap</h2>
        <ul class="timeline">
            @foreach ($events as $event)
                @php
                    $end = $event->closed_at ?? ($open ? $asOf : $case->closed_at);
                    $days = (int) $event->opened_at->diffInDays($end);
                @endphp
                <li @class(['current' => $event->closed_at === null && $open])>
                    <span class="dot">{{ $event->stage_code }}</span>
                    <div>
                        {{ $stages[$event->stage_code] ?? 'Tahap '.$event->stage_code }}
                        <div class="small muted">
                            {{ $d($event->opened_at) }} – {{ $event->closed_at ? $d($event->closed_at) : 'berjalan' }}
                            @if ($event->responsible_unit) · {{ $event->responsible_unit }} @endif
                            @if ($event->output_document) · {{ $event->output_document }} @endif
                        </div>
                    </div>
                    <span class="small">{{ $days }} hari</span>
                </li>
            @endforeach
        </ul>

        @if ($open)
            @can('case.update')
                <details>
                    <summary>Pindahkan tahap</summary>
                    <form method="post" action="{{ route('cases.stage', $case) }}">
                        @csrf
                        <label>Tahap tujuan</label>
                        <select name="to_stage">
                            @foreach ($stages as $code => $label)
                                @continue($code === (int) $case->current_stage)
                                <option value="{{ $code }}" @selected($code === (int) $case->current_stage + 1)>
                                    {{ $code }}. {{ $label }}{{ in_array($code, config('sipasti.deportation_stages'), true) ? ' (dijaga gerbang status)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="inline">
                            <div><label>Tanggal</label><input type="date" name="date" value="{{ $asOf->toDateString() }}"></div>
                            <div><label>Unit penanggung jawab</label><input name="responsible_unit"></div>
                            <div><label>Dokumen keluaran</label><input name="output_document"></div>
                        </div>
                        <p><button type="submit">Pindahkan</button></p>
                    </form>
                </details>
            @endcan
        @endif
    </div>

    {{-- Sebab tertahan --}}
    <div class="card">
        <h2>Sebab tertahan</h2>
        @if ($case->stallCauses->isEmpty())
            <p class="muted">Belum ada sebab tertahan yang dicatat.</p>
        @else
            <table>
                <thead><tr><th>Tanggal</th><th>Tahap</th><th>Sebab</th><th>Catatan</th></tr></thead>
                <tbody>
                @foreach ($case->stallCauses->sortBy('recorded_at') as $cause)
                    <tr>
                        <td>{{ $d($cause->recorded_at) }}</td>
                        <td>{{ $cause->stage_code }}</td>
                        <td>{{ config('sipasti.stall_causes.'.$cause->cause_code) }}</td>
                        <td>{{ $cause->note }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($open)
            @can('case.update')
                <details>
                    <summary>Catat sebab tertahan</summary>
                    <form method="post" action="{{ route('cases.stall-causes', $case) }}">
                        @csrf
                        <div class="inline">
                            <div>
                                <label>Sebab (daftar tertutup)</label>
                                <select name="cause_code">
                                    @foreach (config('sipasti.stall_causes') as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label>Tanggal</label><input type="date" name="date" value="{{ $asOf->toDateString() }}"></div>
                        </div>
                        <label>Catatan</label>
                        <textarea name="note"></textarea>
                        <p><button type="submit">Catat</button></p>
                    </form>
                </details>
            @endcan
        @endif
    </div>

    {{-- Perwakilan negara --}}
    <div class="card">
        <h2>Kontak perwakilan negara</h2>
        @if ($case->missionContacts->isEmpty())
            <p class="muted">Belum ada surat kepada perwakilan negara.</p>
        @else
            <table>
                <thead><tr><th>Perwakilan</th><th>Surat</th><th>Respons</th></tr></thead>
                <tbody>
                @foreach ($case->missionContacts->sortBy('letter_date') as $contact)
                    <tr>
                        <td>{{ $contact->mission }}</td>
                        <td>{{ $contact->letter_no }}<br><span class="small muted">{{ $d($contact->letter_date) }}</span></td>
                        <td>
                            @if ($contact->response_date)
                                {{ $contact->response_type }}<br><span class="small muted">{{ $d($contact->response_date) }}</span>
                            @else
                                <span class="muted">belum ada, {{ (int) $contact->letter_date->diffInDays($asOf) }} hari</span>
                                @if ($open)
                                    @can('case.update')
                                        <details>
                                            <summary class="small">Catat respons</summary>
                                            <form method="post" action="{{ route('mission-contacts.response', $contact) }}">
                                                @csrf
                                                <input type="date" name="response_date" value="{{ $asOf->toDateString() }}">
                                                <input name="response_type" placeholder="mis. SPLP diterbitkan" style="margin-top:4px">
                                                <p><button type="submit">Simpan</button></p>
                                            </form>
                                        </details>
                                    @endcan
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($open)
            @can('case.update')
                @if ($deportationAllowed)
                    <details>
                        <summary>Catat surat kepada perwakilan negara</summary>
                        <form method="post" action="{{ route('cases.mission-contacts', $case) }}">
                            @csrf
                            <label>Perwakilan negara</label>
                            <input name="mission">
                            <div class="inline">
                                <div><label>Nomor surat</label><input name="letter_no"></div>
                                <div><label>Tanggal surat</label><input type="date" name="letter_date" value="{{ $asOf->toDateString() }}"></div>
                            </div>
                            <p><button type="submit">Catat surat</button></p>
                        </form>
                    </details>
                @else
                    <p class="small muted">Surat kepada perwakilan negara asal tidak tersedia untuk status {{ $case->legal_status_code }}, karena pemberitahuan semacam itu dapat membahayakan orang yang dilindungi.</p>
                @endif
            @endcan
        @endif
    </div>

    @if ($open)
        @can('case.close')
            <div class="card">
                <h2>Tutup perkara</h2>
                <p class="small muted">Perkara hanya dapat ditutup bila seluruh peringatannya telah ditinjau. Penutupan karena deportasi melewati gerbang status.</p>
                <form method="post" action="{{ route('cases.close', $case) }}">
                    @csrf
                    <div class="inline">
                        <div>
                            <label>Alasan penutupan</label>
                            <select name="reason">
                                @foreach (config('sipasti.closure_reasons') as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label>Tanggal</label><input type="date" name="date" value="{{ $asOf->toDateString() }}"></div>
                        <div style="flex:0"><button class="danger" type="submit">Tutup perkara</button></div>
                    </div>
                </form>
            </div>
        @endcan
    @endif
</div>
</div>
@endsection
