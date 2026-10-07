<?php

namespace Tests\Feature;

use App\Models\Detainee;
use App\Models\ImmigrationCase;
use App\Models\StageEvent;
use App\Models\StallCause;
use App\Models\User;
use App\Services\StageDurationReport;
use Database\Seeders\LegalStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan durasi tahap (FR8).
 *
 * Durasi dihitung dari peristiwa tahap dalam hari kalender. Tahap yang masih
 * berjalan diukur sampai tanggal acuan, bukan sampai jam sistem.
 */
class StageDurationReportTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $asOf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalStatusSeeder::class);
        $this->asOf = Carbon::parse('2026-10-07');
    }

    /**
     * @param  array<int, array{0:int, 1:string, 2:?string}>  $events  [tahap, dibuka, ditutup]
     */
    private function caseWithEvents(array $events): ImmigrationCase
    {
        $detainee = Detainee::create(['full_name' => 'Deteni Uji', 'sex' => 'L', 'nationality' => 'Negara Uji']);

        $case = ImmigrationCase::create([
            'case_number' => 'UJI-'.uniqid(),
            'detainee_id' => $detainee->id,
            'legal_status_code' => 'DETENI',
            'detention_order_no' => 'SPR-UJI',
            'detention_order_date' => Carbon::parse($events[0][1]),
            'current_stage' => end($events)[0],
            'state' => 'open',
        ]);

        foreach ($events as [$stage, $opened, $closed]) {
            StageEvent::create([
                'immigration_case_id' => $case->id,
                'stage_code' => $stage,
                'opened_at' => Carbon::parse($opened),
                'closed_at' => $closed ? Carbon::parse($closed) : null,
            ]);
        }

        return $case;
    }

    private function row(int $stage): array
    {
        return app(StageDurationReport::class)->build($this->asOf)->firstWhere('stage', $stage);
    }

    #[Test]
    public function it_reports_mean_median_and_max_of_completed_stages(): void
    {
        $this->caseWithEvents([[6, '2026-06-01', '2026-06-11'], [7, '2026-06-11', '2026-06-20']]);   // 10 hari
        $this->caseWithEvents([[6, '2026-06-01', '2026-07-01'], [7, '2026-07-01', '2026-07-05']]);   // 30 hari
        $this->caseWithEvents([[6, '2026-06-01', '2026-08-30'], [7, '2026-08-30', '2026-09-01']]);   // 90 hari

        $row = $this->row(6);

        $this->assertSame(3, $row['completed_count']);
        $this->assertEquals(43.3, $row['completed_mean']);
        $this->assertEquals(30, $row['completed_median']);
        $this->assertSame(90, $row['completed_max']);
    }

    #[Test]
    public function it_measures_running_stages_up_to_the_reference_date(): void
    {
        $this->caseWithEvents([[5, '2026-09-01', '2026-09-07'], [6, '2026-09-07', null]]);

        $row = $this->row(6);

        $this->assertSame(0, $row['completed_count']);
        $this->assertSame(1, $row['ongoing_count']);
        $this->assertSame(30, $row['ongoing_max']);

        // Tanggal acuan lain, hasil lain. Laporan tidak membaca jam sistem.
        $later = app(StageDurationReport::class)->build(Carbon::parse('2026-10-17'))->firstWhere('stage', 6);
        $this->assertSame(40, $later['ongoing_max']);
    }

    #[Test]
    public function it_counts_days_on_the_calendar_not_working_days(): void
    {
        // Jumat 2 Oktober sampai Senin 5 Oktober 2026: tiga hari kalender.
        $this->caseWithEvents([[2, '2026-10-02', '2026-10-05'], [3, '2026-10-05', null]]);

        $this->assertSame(3, $this->row(2)['completed_max']);
    }

    #[Test]
    public function it_tallies_stall_causes_per_stage_from_the_closed_list(): void
    {
        $case = $this->caseWithEvents([[6, '2026-06-01', null]]);

        foreach (['travel_document', 'travel_document', 'external_response'] as $cause) {
            StallCause::create([
                'immigration_case_id' => $case->id,
                'stage_code' => 6,
                'cause_code' => $cause,
                'recorded_at' => Carbon::parse('2026-07-01'),
            ]);
        }

        $causes = $this->row(6)['stall_causes'];

        $this->assertSame(2, $causes['travel_document']);
        $this->assertSame(1, $causes['external_response']);
        $this->assertSame(0, $causes['funding']);
    }

    #[Test]
    public function the_period_filter_keeps_only_stages_overlapping_it(): void
    {
        $this->caseWithEvents([[6, '2026-01-01', '2026-02-01'], [7, '2026-02-01', '2026-02-10']]);
        $this->caseWithEvents([[6, '2026-08-01', '2026-08-21'], [7, '2026-08-21', '2026-08-30']]);

        $row = app(StageDurationReport::class)
            ->build($this->asOf, Carbon::parse('2026-07-01'), Carbon::parse('2026-09-30'))
            ->firstWhere('stage', 6);

        $this->assertSame(1, $row['completed_count']);
        $this->assertSame(20, $row['completed_max']);
    }

    #[Test]
    public function the_report_exports_as_csv_without_detainee_identity(): void
    {
        config(['sipasti.reference_date' => '2026-10-07']);
        $this->caseWithEvents([[6, '2026-06-01', '2026-06-11'], [7, '2026-06-11', null]]);

        $response = $this->actingAs(User::factory()->role('kepala')->create())
            ->get('/reports/stage-durations?format=csv')
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('tahap,nama_tahap,selesai_jumlah', $csv);
        $this->assertStringContainsString('"Pengurusan dokumen perjalanan",1,10', $csv);
        $this->assertStringNotContainsString('Deteni Uji', $csv);
    }
}
