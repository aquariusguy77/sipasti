<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Detainee;
use App\Models\ImmigrationCase;
use App\Services\AuditLogger;
use App\Services\RetentionPolicy;
use Database\Seeders\LegalStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Aturan retensi (NFR5).
 *
 * Pola tiga hari yang sama dengan pengujian indikator: sehari sebelum, tepat
 * pada, dan sehari sesudah jangka retensi berakhir.
 */
class RetentionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $asOf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalStatusSeeder::class);
        config(['sipasti.retention_years' => 5]);
        $this->asOf = Carbon::parse('2026-10-07');
    }

    private function detaineeWithCase(?string $closedAt, string $state = 'closed'): Detainee
    {
        $detainee = Detainee::create([
            'full_name' => 'Nama Asli Bentukan',
            'sex' => 'L',
            'birth_place' => 'Kota Bentukan',
            'birth_date' => '1990-01-01',
            'nationality' => 'Negara Uji',
            'travel_document_no' => 'TD-BENTUKAN',
        ]);

        $this->addCase($detainee, $closedAt, $state);

        return $detainee;
    }

    private function addCase(Detainee $detainee, ?string $closedAt, string $state): ImmigrationCase
    {
        return ImmigrationCase::create([
            'case_number' => 'UJI-'.uniqid(),
            'detainee_id' => $detainee->id,
            'legal_status_code' => 'DETENI',
            'detention_order_no' => 'SPR-UJI',
            'detention_order_date' => Carbon::parse('2020-01-01'),
            'current_stage' => 8,
            'state' => $state,
            'closed_at' => $closedAt,
            'closure_reason' => $closedAt ? 'deported' : null,
        ]);
    }

    #[Test]
    #[DataProvider('retentionBoundary')]
    public function it_anonymises_only_once_the_retention_period_has_run(string $closedAt, bool $expected): void
    {
        $detainee = $this->detaineeWithCase($closedAt);

        app(RetentionPolicy::class)->apply($this->asOf);

        $this->assertSame($expected, $detainee->fresh()->anonymised_at !== null);
    }

    public static function retentionBoundary(): array
    {
        return [
            'sehari sebelum lima tahun' => ['2021-10-08', false],
            'tepat lima tahun' => ['2021-10-07', true],
            'sehari sesudah lima tahun' => ['2021-10-06', true],
        ];
    }

    #[Test]
    public function an_open_case_holds_back_anonymisation(): void
    {
        $detainee = $this->detaineeWithCase('2019-01-01');
        $this->addCase($detainee, null, 'open');

        $this->assertCount(0, app(RetentionPolicy::class)->due($this->asOf));
    }

    #[Test]
    public function a_recent_second_case_holds_back_anonymisation(): void
    {
        $detainee = $this->detaineeWithCase('2019-01-01');
        $this->addCase($detainee, '2025-01-01', 'closed');

        $this->assertCount(0, app(RetentionPolicy::class)->due($this->asOf));
    }

    #[Test]
    public function another_detainees_open_case_does_not_hold_back_anonymisation(): void
    {
        $due = $this->detaineeWithCase('2020-06-01');
        $this->detaineeWithCase(null, 'open');

        $this->assertSame([$due->id], app(RetentionPolicy::class)->due($this->asOf)->pluck('id')->all());
    }

    #[Test]
    public function it_removes_identity_but_keeps_the_case_record(): void
    {
        $detainee = $this->detaineeWithCase('2020-06-01');
        $case = $detainee->cases()->first();

        app(RetentionPolicy::class)->apply($this->asOf, 'system:uji');

        $detainee->refresh();
        $this->assertSame('ANONIM-'.$detainee->id, $detainee->full_name);
        $this->assertNull($detainee->birth_place);
        $this->assertNull($detainee->birth_date);
        $this->assertNull($detainee->travel_document_no);

        $this->assertNotNull($case->fresh());
        $this->assertSame('2020-06-01', $case->fresh()->closed_at->toDateString());

        $this->assertDatabaseHas('audit_logs', [
            'actor' => 'system:uji',
            'action' => 'retention.anonymised',
            'entity_id' => (string) $detainee->id,
        ]);
    }

    #[Test]
    public function it_never_touches_the_audit_trail(): void
    {
        $detainee = $this->detaineeWithCase('2020-06-01');
        app(AuditLogger::class)->read('petugas_uji', 'ImmigrationCase', $detainee->cases()->first()->id);
        $before = AuditLog::count();

        app(RetentionPolicy::class)->apply($this->asOf);

        // Satu catatan baru untuk anonimisasi, tidak ada yang hilang.
        $this->assertSame($before + 1, AuditLog::count());
    }

    #[Test]
    public function it_runs_once_per_detainee(): void
    {
        $this->detaineeWithCase('2020-06-01');

        $this->assertCount(1, app(RetentionPolicy::class)->apply($this->asOf));
        $this->assertCount(0, app(RetentionPolicy::class)->apply($this->asOf->copy()->addMonth()));
    }

    #[Test]
    public function the_dry_run_command_changes_nothing(): void
    {
        $detainee = $this->detaineeWithCase('2020-06-01');

        $this->artisan('sipasti:retention', ['--as-of' => '2026-10-07', '--dry-run' => true])
            ->expectsOutputToContain('1 deteni akan dianonimkan')
            ->assertSuccessful();

        $this->assertNull($detainee->fresh()->anonymised_at);

        $this->artisan('sipasti:retention', ['--as-of' => '2026-10-07'])->assertSuccessful();

        $this->assertNotNull($detainee->fresh()->anonymised_at);
    }
}
