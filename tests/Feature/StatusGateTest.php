<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Detainee;
use App\Models\ImmigrationCase;
use App\Services\Exceptions\StatusGateException;
use App\Services\StatusGate;
use Database\Seeders\LegalStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pengujian gerbang status (FR9, subbagian 4.3.4).
 *
 * Gerbang berada di lapis aplikasi. Penolakan terjadi tanpa bergantung pada
 * antarmuka, dan setiap penolakan tercatat pada log audit.
 */
class StatusGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalStatusSeeder::class);
    }

    private function caseWithStatus(string $status): ImmigrationCase
    {
        $detainee = Detainee::create([
            'full_name' => 'Deteni Uji',
            'sex' => 'L',
            'nationality' => 'Negara Uji',
        ]);

        return ImmigrationCase::create([
            'case_number' => 'UJI-'.uniqid(),
            'detainee_id' => $detainee->id,
            'legal_status_code' => $status,
            'detention_order_no' => 'SPR-UJI',
            'detention_order_date' => now()->subDays(10),
            'detention_room_type' => 'KANIM',
            'current_stage' => 4,
            'state' => 'open',
        ]);
    }

    #[Test]
    public function it_allows_deportation_for_a_detainee_under_administrative_action(): void
    {
        $case = $this->caseWithStatus('DETENI');

        app(StatusGate::class)->assertDeportationAllowed($case);

        $this->assertTrue(app(StatusGate::class)->deportationAllowed($case));
    }

    #[Test]
    #[DataProvider('protectedStatuses')]
    public function it_refuses_deportation_for_protected_status(string $status): void
    {
        $case = $this->caseWithStatus($status);

        $this->expectException(StatusGateException::class);

        app(StatusGate::class)->assertDeportationAllowed($case, 'petugas_uji');
    }

    public static function protectedStatuses(): array
    {
        return [
            'klaim perlindungan tertunda' => ['PROTECTION_PENDING'],
            'pengungsi terverifikasi' => ['REFUGEE_RECOGNISED'],
            'korban perdagangan orang' => ['TRAFFICKING_VICTIM'],
        ];
    }

    #[Test]
    public function it_writes_every_refusal_to_the_audit_trail(): void
    {
        $case = $this->caseWithStatus('REFUGEE_RECOGNISED');

        try {
            app(StatusGate::class)->assertDeportationAllowed($case, 'petugas_uji');
        } catch (StatusGateException) {
            // Penolakan memang diharapkan.
        }

        $this->assertDatabaseHas('audit_logs', [
            'actor' => 'petugas_uji',
            'action' => 'deportation.refused',
            'entity' => 'ImmigrationCase',
            'entity_id' => (string) $case->id,
        ]);
    }

    #[Test]
    public function it_refuses_a_status_change_without_a_determination_reference(): void
    {
        $case = $this->caseWithStatus('DETENI');

        $this->expectException(StatusGateException::class);

        app(StatusGate::class)->changeStatus(
            case: $case,
            newStatusCode: 'REFUGEE_RECOGNISED',
            determinationRef: null,
            determinationAuthority: null,
            determinationDate: null,
            actor: 'petugas_uji',
        );
    }

    #[Test]
    public function it_accepts_a_status_change_carrying_a_determination_reference(): void
    {
        $case = $this->caseWithStatus('DETENI');

        app(StatusGate::class)->changeStatus(
            case: $case,
            newStatusCode: 'REFUGEE_RECOGNISED',
            determinationRef: 'UNHCR-2026-00123',
            determinationAuthority: 'UNHCR',
            determinationDate: '2026-09-01',
            actor: 'petugas_uji',
        );

        $this->assertSame('REFUGEE_RECOGNISED', $case->fresh()->legal_status_code);
        $this->assertFalse(app(StatusGate::class)->deportationAllowed($case->fresh()));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'status.changed',
            'entity_id' => (string) $case->id,
        ]);
    }

    #[Test]
    public function a_refused_status_change_leaves_the_marker_untouched(): void
    {
        $case = $this->caseWithStatus('DETENI');

        try {
            app(StatusGate::class)->changeStatus($case, 'REFUGEE_RECOGNISED', null, null, null, 'petugas_uji');
        } catch (StatusGateException) {
            // Penolakan memang diharapkan.
        }

        $this->assertSame('DETENI', $case->fresh()->legal_status_code);
        $this->assertSame(1, AuditLog::where('action', 'status.change_refused')->count());
    }
}
