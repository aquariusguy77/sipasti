<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Detainee;
use App\Models\ImmigrationCase;
use App\Services\AlertCloser;
use App\Services\Exceptions\ReviewRequiredException;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\LegalStatusSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pengujian pertanggungjawaban: penutupan peringatan (FR10) dan sifat
 * hanya-tambah log audit (NFR3).
 *
 * Dua pengujian di sini menguji penolakan, bukan penerimaan. Di situlah
 * terbukti bahwa sistem menciptakan jejak pertanggungjawaban, bukan sekadar
 * notifikasi.
 */
class AccountabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalStatusSeeder::class);
        $this->seed(IndicatorSeeder::class);
    }

    private function alert(): Alert
    {
        $detainee = Detainee::create([
            'full_name' => 'Deteni Uji',
            'sex' => 'L',
            'nationality' => 'Negara Uji',
        ]);

        $case = ImmigrationCase::create([
            'case_number' => 'UJI-'.uniqid(),
            'detainee_id' => $detainee->id,
            'legal_status_code' => 'DETENI',
            'detention_order_no' => 'SPR-UJI',
            'detention_order_date' => Carbon::parse('2026-09-01'),
            'detention_room_type' => 'KANIM',
            'current_stage' => 4,
            'state' => 'open',
        ]);

        return Alert::create([
            'immigration_case_id' => $case->id,
            'indicator_code' => 'I3',
            'raised_at' => Carbon::parse('2026-10-01'),
            'tier' => 'peringatan',
            'state' => 'open',
        ]);
    }

    #[Test]
    public function it_refuses_to_close_an_alert_without_recorded_reasoning(): void
    {
        $alert = $this->alert();

        $this->expectException(ReviewRequiredException::class);

        app(AlertCloser::class)->close(
            alert: $alert,
            reviewedAt: Carbon::parse('2026-10-07'),
            reviewerRole: 'penyelia',
            decision: 'lanjutkan koordinasi',
            reasoning: null,
            actor: 'petugas_uji',
        );
    }

    #[Test]
    public function a_refused_closure_leaves_the_alert_open(): void
    {
        $alert = $this->alert();

        try {
            app(AlertCloser::class)->close($alert, Carbon::parse('2026-10-07'), 'penyelia', 'lanjutkan', null);
        } catch (ReviewRequiredException) {
            // Penolakan memang diharapkan.
        }

        $this->assertSame('open', $alert->fresh()->state);
        $this->assertDatabaseCount('reviews', 0);
    }

    #[Test]
    public function it_closes_an_alert_once_a_review_is_recorded(): void
    {
        $alert = $this->alert();

        $review = app(AlertCloser::class)->close(
            alert: $alert,
            reviewedAt: Carbon::parse('2026-10-07'),
            reviewerRole: 'penyelia',
            decision: 'eskalasi ke Direktorat Kerja Sama Keimigrasian',
            reasoning: 'Perwakilan negara belum merespons dua surat berturut-turut.',
            actor: 'petugas_uji',
        );

        $this->assertSame('closed', $alert->fresh()->state);
        $this->assertSame($alert->id, $review->alert_id);
        $this->assertNotEmpty($review->reasoning);
    }

    #[Test]
    public function the_audit_trail_rejects_updates(): void
    {
        $log = app(\App\Services\AuditLogger::class)->write('petugas_uji', 'read', 'ImmigrationCase', 1);

        $this->expectException(QueryException::class);

        DB::table('audit_logs')->where('id', $log->id)->update(['actor' => 'orang_lain']);
    }

    #[Test]
    public function the_audit_trail_rejects_deletes(): void
    {
        $log = app(\App\Services\AuditLogger::class)->write('petugas_uji', 'read', 'ImmigrationCase', 1);

        $this->expectException(QueryException::class);

        DB::table('audit_logs')->where('id', $log->id)->delete();
    }

    #[Test]
    public function it_records_reads_as_well_as_writes(): void
    {
        app(\App\Services\AuditLogger::class)->read('petugas_uji', 'ImmigrationCase', 7);

        $this->assertDatabaseHas('audit_logs', [
            'actor' => 'petugas_uji',
            'action' => 'read',
            'entity' => 'ImmigrationCase',
            'entity_id' => '7',
        ]);

        $this->assertSame(1, AuditLog::count());
    }
}
