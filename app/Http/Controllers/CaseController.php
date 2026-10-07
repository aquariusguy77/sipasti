<?php

namespace App\Http\Controllers;

use App\Models\ImmigrationCase;
use App\Models\LegalStatus;
use App\Models\MissionContact;
use App\Models\StallCause;
use App\Services\AuditLogger;
use App\Services\CaseCloser;
use App\Services\CaseRegistry;
use App\Services\Exceptions\ReviewRequiredException;
use App\Services\Exceptions\StatusGateException;
use App\Services\StageTracker;
use App\Services\StatusGate;
use App\Support\ReferenceDate;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Berkas perkara dan aksi atasnya.
 *
 * Pengendali ini hanya meneruskan masukan ke lapis layanan. Gerbang status,
 * kewajiban alasan peninjauan, dan pencatatan audit seluruhnya berada di lapis
 * layanan, sehingga tidak ada aksi di layar yang dapat melewatinya.
 */
class CaseController extends Controller
{
    public function show(Request $request, ImmigrationCase $case, AuditLogger $audit, StatusGate $gate): View
    {
        $case->load([
            'detainee', 'legalStatus', 'stallCauses', 'missionContacts',
            'stageEvents' => fn ($q) => $q->orderBy('opened_at')->orderBy('id'),
            'alerts' => fn ($q) => $q->with('indicator', 'reviews')->orderByRaw("state = 'closed'")->latest('raised_at'),
        ]);

        // Pembacaan berkas perkara dicatat, karena paparan data detensi muncul
        // dari akses, bukan hanya dari perubahan (NFR3).
        $audit->read($request->user()->auditActor(), 'ImmigrationCase', $case->id);

        $asOf = ReferenceDate::resolve();

        return view('cases.show', [
            'case' => $case,
            'asOf' => $asOf,
            'stages' => config('sipasti.stages'),
            'deportationAllowed' => $gate->deportationAllowed($case),
            'legalStatuses' => LegalStatus::all(),
        ]);
    }

    public function create(): View
    {
        return view('cases.create', [
            'legalStatuses' => LegalStatus::all(),
            'stages' => config('sipasti.stages'),
            'asOf' => ReferenceDate::resolve(),
        ]);
    }

    public function store(Request $request, CaseRegistry $registry): RedirectResponse
    {
        $nonDeportationStages = array_diff(array_keys(config('sipasti.stages')), config('sipasti.deportation_stages'));

        $data = $request->validate([
            'case_number' => ['required', 'string', 'max:100', 'unique:immigration_cases,case_number'],
            'full_name' => ['required', 'string', 'max:255'],
            'sex' => ['required', Rule::in(['L', 'P'])],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'nationality' => ['required', 'string', 'max:100'],
            'travel_document_no' => ['nullable', 'string', 'max:100'],
            'travel_document_place' => ['nullable', 'string', 'max:255'],
            'travel_document_date' => ['nullable', 'date'],
            'sending_agency' => ['nullable', 'string', 'max:255'],
            'provision_violated' => ['nullable', 'string', 'max:255'],
            'biometric_captured' => ['nullable', 'boolean'],
            'legal_status_code' => ['required', Rule::exists('legal_statuses', 'code')],
            // Status selain deteni biasa menuntut rujukan penetapan sejak
            // registrasi, sama seperti pada perubahan status.
            'status_determination_ref' => ['required_unless:legal_status_code,DETENI', 'nullable', 'string', 'max:255'],
            'status_determination_authority' => ['required_unless:legal_status_code,DETENI', 'nullable', 'string', 'max:255'],
            'status_determination_date' => ['required_unless:legal_status_code,DETENI', 'nullable', 'date'],
            'detention_order_no' => ['required', 'string', 'max:255'],
            'detention_order_date' => ['required', 'date'],
            'detention_room_type' => ['nullable', Rule::in(['KANIM', 'DITJENIM'])],
            'initial_stage' => ['required', 'integer', Rule::in($nonDeportationStages)],
            'stage_opened_at' => ['required', 'date', 'after_or_equal:detention_order_date'],
        ]);

        $data['biometric_captured'] = $request->boolean('biometric_captured');

        $case = $registry->register(
            detainee: array_intersect_key($data, array_flip(CaseRegistry::DETAINEE_FIELDS)),
            case: array_intersect_key($data, array_flip([
                'case_number', 'legal_status_code', 'status_determination_ref',
                'status_determination_authority', 'status_determination_date',
                'detention_order_no', 'detention_order_date', 'detention_room_type',
            ])),
            initialStage: (int) $data['initial_stage'],
            stageOpenedAt: Carbon::parse($data['stage_opened_at']),
            actor: $request->user()->auditActor(),
        );

        return redirect()->route('cases.show', $case)->with('status', 'Perkara terdaftar.');
    }

    public function update(Request $request, ImmigrationCase $case, CaseRegistry $registry): RedirectResponse
    {
        $data = $request->validate([
            'detention_room_type' => ['nullable', Rule::in(['KANIM', 'DITJENIM'])],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'funding_exhausted' => ['nullable', 'boolean'],
            'guarantee_revoked_at' => ['nullable', 'date'],
            'deportation_decision_no' => ['nullable', 'string', 'max:255'],
            'deportation_decision_date' => ['nullable', 'date', 'required_with:deportation_decision_no'],
            'deportation_decision_valid_until' => ['nullable', 'date', 'after_or_equal:deportation_decision_date'],
            'travel_document_no' => ['nullable', 'string', 'max:100'],
            'travel_document_place' => ['nullable', 'string', 'max:255'],
            'travel_document_date' => ['nullable', 'date'],
        ]);

        $data['funding_exhausted'] = $request->boolean('funding_exhausted');

        return $this->attempt($case, fn () => $registry->update(
            $case,
            array_intersect_key($data, array_flip(CaseRegistry::CASE_FIELDS)),
            array_intersect_key($data, array_flip(['travel_document_no', 'travel_document_place', 'travel_document_date'])),
            $request->user()->auditActor(),
        ), 'Data perkara diperbarui.');
    }

    public function advance(Request $request, ImmigrationCase $case, StageTracker $tracker): RedirectResponse
    {
        $data = $request->validate([
            'to_stage' => ['required', 'integer', Rule::in(array_keys(config('sipasti.stages')))],
            'date' => ['required', 'date'],
            'responsible_unit' => ['nullable', 'string', 'max:255'],
            'output_document' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt($case, fn () => $tracker->advance(
            $case,
            (int) $data['to_stage'],
            Carbon::parse($data['date']),
            $data['responsible_unit'] ?? null,
            $data['output_document'] ?? null,
            $request->user()->auditActor(),
        ), 'Tahap perkara diperbarui.');
    }

    public function stallCause(Request $request, ImmigrationCase $case, StageTracker $tracker): RedirectResponse
    {
        $data = $request->validate([
            'cause_code' => ['required', Rule::in(StallCause::CAUSES)],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->attempt($case, fn () => $tracker->recordStallCause(
            $case, $data['cause_code'], Carbon::parse($data['date']), $data['note'] ?? null, $request->user()->auditActor(),
        ), 'Sebab tertahan dicatat.');
    }

    public function missionLetter(Request $request, ImmigrationCase $case, StageTracker $tracker): RedirectResponse
    {
        $data = $request->validate([
            'mission' => ['required', 'string', 'max:255'],
            'letter_no' => ['required', 'string', 'max:255'],
            'letter_date' => ['required', 'date'],
        ]);

        return $this->attempt($case, fn () => $tracker->recordMissionLetter(
            $case, $data['mission'], $data['letter_no'], Carbon::parse($data['letter_date']), $request->user()->auditActor(),
        ), 'Surat kepada perwakilan negara dicatat.');
    }

    public function missionResponse(Request $request, MissionContact $contact, StageTracker $tracker): RedirectResponse
    {
        $data = $request->validate([
            'response_date' => ['required', 'date', 'after_or_equal:'.$contact->letter_date->toDateString()],
            'response_type' => ['required', 'string', 'max:255'],
        ]);

        $tracker->recordMissionResponse(
            $contact, Carbon::parse($data['response_date']), $data['response_type'], $request->user()->auditActor(),
        );

        return redirect()->route('cases.show', $contact->immigration_case_id)->with('status', 'Respons perwakilan negara dicatat.');
    }

    public function changeStatus(Request $request, ImmigrationCase $case, StatusGate $gate): RedirectResponse
    {
        $data = $request->validate([
            'legal_status_code' => ['required', Rule::exists('legal_statuses', 'code')],
            'status_determination_ref' => ['nullable', 'string', 'max:255'],
            'status_determination_authority' => ['nullable', 'string', 'max:255'],
            'status_determination_date' => ['nullable', 'date'],
        ]);

        // Kelengkapan rujukan sengaja tidak divalidasi di sini, agar penolakan
        // terjadi di gerbang status dan tercatat pada log audit.
        return $this->attempt($case, fn () => $gate->changeStatus(
            $case,
            $data['legal_status_code'],
            $data['status_determination_ref'] ?? null,
            $data['status_determination_authority'] ?? null,
            $data['status_determination_date'] ?? null,
            $request->user()->auditActor(),
        ), 'Penanda status hukum diperbarui.');
    }

    public function close(Request $request, ImmigrationCase $case, CaseCloser $closer): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(config('sipasti.closure_reasons')))],
            'date' => ['required', 'date'],
        ]);

        return $this->attempt($case, fn () => $closer->close(
            $case, Carbon::parse($data['date']), $data['reason'], $request->user()->auditActor(),
        ), 'Perkara ditutup.');
    }

    /**
     * Penolakan dari lapis layanan dikembalikan ke layar sebagai pesan,
     * bukan sebagai galat, karena penolakan adalah perilaku yang diharapkan.
     */
    private function attempt(ImmigrationCase $case, Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (StatusGateException|ReviewRequiredException|InvalidArgumentException $e) {
            return redirect()->route('cases.show', $case)->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('cases.show', $case)->with('status', $success);
    }
}
