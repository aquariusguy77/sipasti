<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\ImmigrationCase;
use App\Models\StageEvent;
use App\Models\User;
use App\Services\IndicatorEngine;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\LegalStatusSeeder;
use Database\Seeders\SimulatedCaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Papan perkara dan aksi atas berkas perkara (FR7).
 *
 * Pengujian di sini memastikan bahwa antarmuka tidak membuka jalan pintas di
 * sekitar gerbang status dan kewajiban alasan peninjauan. Setiap aksi dikirim
 * melalui HTTP, sama seperti dari layar.
 */
class CaseBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $clerk;
    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sipasti.reference_date' => '2026-10-07']);

        $this->seed([LegalStatusSeeder::class, IndicatorSeeder::class, SimulatedCaseSeeder::class]);
        app(IndicatorEngine::class)->run(Carbon::parse('2026-10-07'));

        $this->clerk = User::factory()->role('petugas')->create(['email' => 'petugas@uji.test']);
        $this->supervisor = User::factory()->role('penyelia')->create(['email' => 'penyelia@uji.test']);
    }

    private function case(string $number): ImmigrationCase
    {
        return ImmigrationCase::where('case_number', $number)->firstOrFail();
    }

    #[Test]
    public function the_board_groups_open_cases_by_stage_without_detainee_identity(): void
    {
        $response = $this->actingAs($this->clerk)->get('/board')->assertOk();

        $response->assertSee('SIM-KANIM-3');
        $response->assertSee('SIM-LINDUNG-1');
        $response->assertSee('Penempatan di Ruang Detensi');

        // Kartu dan daftar peringatan memuat keadaan perkara, bukan sifat orang.
        $response->assertDontSee('Deteni Simulasi');
        $response->assertDontSee('Negara A');
    }

    #[Test]
    public function the_board_filters_by_alert_tier(): void
    {
        $response = $this->actingAs($this->clerk)->get('/board?tier=kritis')->assertOk();

        $response->assertSee('SIM-PASAL85-1');
        $response->assertDontSee('SIM-KANIM-1');
    }

    #[Test]
    public function opening_a_case_file_is_recorded_as_a_read(): void
    {
        $case = $this->case('SIM-NODOC-1');

        $this->actingAs($this->clerk)->get("/cases/{$case->id}")
            ->assertOk()
            ->assertSee('Deteni Simulasi SIM-NODOC-1');

        $this->assertDatabaseHas('audit_logs', [
            'actor' => 'petugas:petugas@uji.test',
            'action' => 'read',
            'entity' => 'ImmigrationCase',
            'entity_id' => (string) $case->id,
        ]);
    }

    #[Test]
    public function a_clerk_registers_a_case_with_its_first_stage_event(): void
    {
        $this->actingAs($this->clerk)->post('/cases', [
            'case_number' => 'BARU-001',
            'full_name' => 'Deteni Bentukan',
            'sex' => 'P',
            'nationality' => 'Negara Uji',
            'legal_status_code' => 'DETENI',
            'detention_order_no' => 'SPR-BARU-001',
            'detention_order_date' => '2026-10-05',
            'detention_room_type' => 'KANIM',
            'initial_stage' => 1,
            'stage_opened_at' => '2026-10-05',
        ])->assertRedirect();

        $case = $this->case('BARU-001');
        $this->assertSame(1, $case->stageEvents()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'case.registered', 'entity_id' => (string) $case->id]);
    }

    #[Test]
    public function a_protected_status_cannot_be_registered_without_a_determination_reference(): void
    {
        $this->actingAs($this->clerk)->post('/cases', [
            'case_number' => 'BARU-002',
            'full_name' => 'Deteni Bentukan',
            'sex' => 'L',
            'nationality' => 'Negara Uji',
            'legal_status_code' => 'REFUGEE_RECOGNISED',
            'detention_order_no' => 'SPR-BARU-002',
            'detention_order_date' => '2026-10-05',
            'initial_stage' => 4,
            'stage_opened_at' => '2026-10-05',
        ])->assertSessionHasErrors('status_determination_ref');

        $this->assertDatabaseMissing('immigration_cases', ['case_number' => 'BARU-002']);
    }

    #[Test]
    public function a_case_cannot_be_registered_directly_into_a_deportation_stage(): void
    {
        $this->actingAs($this->clerk)->post('/cases', [
            'case_number' => 'BARU-003',
            'full_name' => 'Deteni Bentukan',
            'sex' => 'L',
            'nationality' => 'Negara Uji',
            'legal_status_code' => 'DETENI',
            'detention_order_no' => 'SPR-BARU-003',
            'detention_order_date' => '2026-10-05',
            'initial_stage' => 7,
            'stage_opened_at' => '2026-10-05',
        ])->assertSessionHasErrors('initial_stage');
    }

    #[Test]
    public function moving_a_stage_closes_the_running_event_and_opens_a_new_one(): void
    {
        $case = $this->case('SIM-KANIM-3');

        $this->actingAs($this->clerk)->post("/cases/{$case->id}/stage", [
            'to_stage' => 3,
            'date' => '2026-10-07',
        ])->assertSessionHas('status');

        $case->refresh();
        $this->assertSame(3, (int) $case->current_stage);
        $this->assertSame(1, $case->stageEvents()->whereNull('closed_at')->count());
        $this->assertSame('2026-10-07', StageEvent::where('immigration_case_id', $case->id)->where('stage_code', 2)->first()->closed_at->toDateString());
    }

    #[Test]
    public function the_screen_cannot_move_a_protected_case_into_the_deportation_stages(): void
    {
        $case = $this->case('SIM-LINDUNG-1');

        $this->actingAs($this->clerk)->post("/cases/{$case->id}/stage", [
            'to_stage' => 7,
            'date' => '2026-10-07',
        ])->assertSessionHas('error');

        $this->assertSame(4, (int) $case->fresh()->current_stage);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deportation.refused', 'entity_id' => (string) $case->id]);
    }

    #[Test]
    public function the_screen_cannot_write_to_the_home_mission_of_a_protected_person(): void
    {
        $case = $this->case('SIM-LINDUNG-1');

        $this->actingAs($this->clerk)->post("/cases/{$case->id}/mission-contacts", [
            'mission' => 'Kedutaan Besar Negara H',
            'letter_no' => 'SURAT-UJI',
            'letter_date' => '2026-10-07',
        ])->assertSessionHas('error');

        $this->assertSame(0, $case->missionContacts()->count());
    }

    #[Test]
    public function the_screen_cannot_attach_a_deportation_decision_to_a_protected_case(): void
    {
        $case = $this->case('SIM-LINDUNG-1');

        $this->actingAs($this->clerk)->put("/cases/{$case->id}", [
            'deportation_decision_no' => 'KEP-UJI',
            'deportation_decision_date' => '2026-10-07',
        ])->assertSessionHas('error');

        $this->assertNull($case->fresh()->deportation_decision_no);
    }

    #[Test]
    public function updating_case_data_records_field_names_not_values(): void
    {
        $case = $this->case('SIM-NODOC-1');

        $this->actingAs($this->clerk)->put("/cases/{$case->id}", [
            'travel_document_no' => 'SPLP-RAHASIA-123',
        ])->assertSessionHas('status');

        $this->assertSame('SPLP-RAHASIA-123', $case->fresh()->detainee->travel_document_no);

        $log = AuditLog::where('action', 'case.updated')->latest('id')->first();
        $this->assertStringContainsString('deteni.travel_document_no', $log->detail);
        $this->assertStringNotContainsString('SPLP-RAHASIA-123', $log->detail);
    }

    #[Test]
    public function closing_an_alert_from_the_screen_still_requires_reasoning(): void
    {
        $alert = Alert::where('indicator_code', 'I4')->firstOrFail();

        $this->actingAs($this->supervisor)->post("/alerts/{$alert->id}/close", [
            'decision' => 'lanjutkan',
            'reasoning' => '',
        ])->assertSessionHas('error');

        $this->assertSame('open', $alert->fresh()->state);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alert.close_refused', 'entity_id' => (string) $alert->id]);

        $this->actingAs($this->supervisor)->post("/alerts/{$alert->id}/close", [
            'decision' => 'eskalasi ke Direktorat Kerja Sama Keimigrasian',
            'reasoning' => 'Surat pertama tidak berbalas setelah 31 hari.',
        ])->assertSessionHas('status');

        $this->assertSame('closed', $alert->fresh()->state);
        $this->assertSame('penyelia', $alert->reviews()->first()->reviewer_role);
    }

    #[Test]
    public function a_case_with_open_alerts_cannot_be_closed(): void
    {
        $case = $this->case('SIM-KANIM-3');

        $this->actingAs($this->supervisor)->post("/cases/{$case->id}/close", [
            'reason' => 'released_reporting',
            'date' => '2026-10-07',
        ])->assertSessionHas('error');

        $this->assertSame('open', $case->fresh()->state);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case.close_refused', 'entity_id' => (string) $case->id]);
    }

    #[Test]
    public function a_reviewed_case_can_be_closed_and_leaves_the_board(): void
    {
        $case = $this->case('SIM-KANIM-3');

        foreach ($case->alerts()->where('state', 'open')->get() as $alert) {
            $this->actingAs($this->supervisor)->post("/alerts/{$alert->id}/close", [
                'decision' => 'tidak ada tindakan lanjutan',
                'reasoning' => 'Deteni dikeluarkan dengan kewajiban lapor.',
            ]);
        }

        $this->actingAs($this->supervisor)->post("/cases/{$case->id}/close", [
            'reason' => 'released_reporting',
            'date' => '2026-10-07',
        ])->assertSessionHas('status');

        $case->refresh();
        $this->assertSame('closed', $case->state);
        $this->assertSame(0, $case->stageEvents()->whereNull('closed_at')->count());

        $this->actingAs($this->supervisor)->get('/board')->assertDontSee('SIM-KANIM-3');
    }

    #[Test]
    public function a_protected_case_cannot_be_closed_as_deported(): void
    {
        $case = $this->case('SIM-LINDUNG-1');

        $this->actingAs($this->supervisor)->post("/cases/{$case->id}/close", [
            'reason' => 'deported',
            'date' => '2026-10-07',
        ])->assertSessionHas('error');

        $this->assertSame('open', $case->fresh()->state);
    }

    #[Test]
    public function a_status_change_from_the_screen_without_reference_is_refused_and_logged(): void
    {
        $case = $this->case('SIM-KANIM-1');

        $this->actingAs($this->supervisor)->post("/cases/{$case->id}/status", [
            'legal_status_code' => 'PROTECTION_PENDING',
        ])->assertSessionHas('error');

        $this->assertSame('DETENI', $case->fresh()->legal_status_code);
        $this->assertDatabaseHas('audit_logs', ['action' => 'status.change_refused', 'entity_id' => (string) $case->id]);
    }

    #[Test]
    public function a_supervisor_can_run_the_engine_from_the_board(): void
    {
        $this->actingAs($this->supervisor)->post('/indicators/run')->assertSessionHas('status');

        // Peringatan yang sudah terbuka tidak digandakan.
        $this->assertSame(1, Alert::where('indicator_code', 'I10')->count());
    }
}
