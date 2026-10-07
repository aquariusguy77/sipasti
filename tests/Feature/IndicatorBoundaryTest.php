<?php

namespace Tests\Feature;

use App\Models\Detainee;
use App\Models\ImmigrationCase;
use App\Models\Indicator;
use App\Models\MissionContact;
use App\Models\StageEvent;
use App\Services\IndicatorEngine;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\LegalStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pengujian batas indikator, sesuai protokol pada subbagian 4.5.
 *
 * Aturan menyeluruh: peringatan hanya muncul pada hari sesudah tenggat
 * berakhir, bukan pada hari terakhir yang masih sah. Pengujian memakai pola
 * tiga hari, yaitu sehari sebelum, tepat pada, dan sehari sesudah tenggat.
 */
class IndicatorBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $asOf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LegalStatusSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $this->asOf = Carbon::parse('2026-10-07');
    }

    private function engine(): IndicatorEngine
    {
        return app(IndicatorEngine::class);
    }

    private function makeCase(array $attributes = [], ?string $travelDocument = 'TD-1'): ImmigrationCase
    {
        $detainee = Detainee::create([
            'full_name' => 'Deteni Uji',
            'sex' => 'L',
            'nationality' => 'Negara Uji',
            'travel_document_no' => $travelDocument,
        ]);

        return ImmigrationCase::create(array_merge([
            'case_number' => 'UJI-'.uniqid(),
            'detainee_id' => $detainee->id,
            'legal_status_code' => 'DETENI',
            'detention_order_no' => 'SPR-UJI',
            'detention_order_date' => $this->asOf->copy()->subDays(1),
            'detention_room_type' => 'KANIM',
            'current_stage' => 2,
            'state' => 'open',
        ], $attributes));
    }

    private function tier(ImmigrationCase $case, string $indicatorCode): ?string
    {
        $case->load(['detainee', 'stageEvents', 'missionContacts']);

        return $this->engine()->evaluate($case, Indicator::findOrFail($indicatorCode), $this->asOf);
    }

    /**
     * I1, batas tujuh hari pada Ruang Detensi Kantor Imigrasi.
     * Hari ke-6 dan ke-7 masih sah, hari ke-8 melampaui.
     */
    #[Test]
    #[DataProvider('kanimBoundary')]
    public function it_applies_the_seven_day_detention_room_limit(int $days, ?string $expected): void
    {
        $case = $this->makeCase([
            'detention_order_date' => $this->asOf->copy()->subDays($days),
            'detention_room_type' => 'KANIM',
        ]);

        $this->assertSame($expected, $this->tier($case, 'I1'));
    }

    public static function kanimBoundary(): array
    {
        return [
            'hari ke-5, belum ada peringatan' => [5, null],
            'hari ke-6, perhatian' => [6, 'perhatian'],
            'hari ke-7, masih perhatian karena hari terakhir masih sah' => [7, 'perhatian'],
            'hari ke-8, peringatan karena tenggat terlampaui' => [8, 'peringatan'],
        ];
    }

    /**
     * I2, batas tiga puluh hari pada Ruang Detensi Direktorat Jenderal Imigrasi.
     */
    #[Test]
    #[DataProvider('ditjenBoundary')]
    public function it_applies_the_thirty_day_detention_room_limit(int $days, ?string $expected): void
    {
        $case = $this->makeCase([
            'detention_order_date' => $this->asOf->copy()->subDays($days),
            'detention_room_type' => 'DITJENIM',
        ]);

        $this->assertSame($expected, $this->tier($case, 'I2'));
    }

    public static function ditjenBoundary(): array
    {
        return [
            'hari ke-29, perhatian' => [29, 'perhatian'],
            'hari ke-30, masih perhatian' => [30, 'perhatian'],
            'hari ke-31, peringatan' => [31, 'peringatan'],
        ];
    }

    /**
     * I3, ketiadaan dokumen perjalanan, terdeteksi saat registrasi.
     */
    #[Test]
    public function it_flags_a_case_without_a_travel_document(): void
    {
        $withDocument = $this->makeCase(travelDocument: 'TD-ADA');
        $withoutDocument = $this->makeCase(travelDocument: null);

        $this->assertNull($this->tier($withDocument, 'I3'));
        $this->assertSame('peringatan', $this->tier($withoutDocument, 'I3'));
    }

    /**
     * I4, perwakilan negara belum merespons dalam tiga puluh hari.
     */
    #[Test]
    #[DataProvider('missionBoundary')]
    public function it_escalates_when_the_foreign_mission_does_not_respond(int $days, ?string $expected): void
    {
        $case = $this->makeCase(travelDocument: null);

        MissionContact::create([
            'immigration_case_id' => $case->id,
            'mission' => 'Kedutaan Besar Negara Uji',
            'letter_no' => 'SURAT-UJI',
            'letter_date' => $this->asOf->copy()->subDays($days),
        ]);

        $this->assertSame($expected, $this->tier($case, 'I4'));
    }

    public static function missionBoundary(): array
    {
        return [
            'hari ke-29, perhatian' => [29, 'perhatian'],
            'hari ke-30, masih perhatian' => [30, 'perhatian'],
            'hari ke-31, peringatan' => [31, 'peringatan'],
        ];
    }

    /**
     * I5, hierarki pembiayaan habis tanpa sumber tersedia.
     */
    #[Test]
    public function it_flags_an_exhausted_funding_hierarchy(): void
    {
        $funded = $this->makeCase(['funding_exhausted' => true, 'funding_source' => 'Penjamin']);
        $unfunded = $this->makeCase(['funding_exhausted' => true, 'funding_source' => null]);

        $this->assertNull($this->tier($funded, 'I5'));
        $this->assertSame('peringatan', $this->tier($unfunded, 'I5'));
    }

    /**
     * I6, tenggat tujuh hari meninggalkan wilayah Indonesia.
     */
    #[Test]
    #[DataProvider('departureBoundary')]
    public function it_applies_the_seven_day_departure_order(int $days, ?string $expected): void
    {
        $case = $this->makeCase([
            'current_stage' => 7,
            'deportation_decision_no' => 'KEP-UJI',
            'deportation_decision_date' => $this->asOf->copy()->subDays($days),
        ]);

        $this->assertSame($expected, $this->tier($case, 'I6'));
    }

    public static function departureBoundary(): array
    {
        return [
            'hari ke-6, perhatian' => [6, 'perhatian'],
            'hari ke-7, masih perhatian' => [7, 'perhatian'],
            'hari ke-8, peringatan' => [8, 'peringatan'],
        ];
    }

    /**
     * I7, keputusan deportasi mendekati akhir masa berlaku.
     */
    #[Test]
    #[DataProvider('expiryBoundary')]
    public function it_warns_before_the_deportation_decision_lapses(int $daysRemaining, ?string $expected): void
    {
        $case = $this->makeCase([
            'current_stage' => 7,
            'deportation_decision_no' => 'KEP-UJI',
            'deportation_decision_date' => $this->asOf->copy()->subDays(100),
            'deportation_decision_valid_until' => $this->asOf->copy()->addDays($daysRemaining),
        ]);

        $this->assertSame($expected, $this->tier($case, 'I7'));
    }

    public static function expiryBoundary(): array
    {
        return [
            'delapan hari sebelum berakhir, belum ada peringatan' => [8, null],
            'tujuh hari sebelum berakhir, perhatian' => [7, 'perhatian'],
            'enam hari sebelum berakhir, perhatian' => [6, 'perhatian'],
        ];
    }

    /**
     * I8, hak penjaminan dicabut dan tenggat empat belas hari terlampaui.
     */
    #[Test]
    #[DataProvider('guaranteeBoundary')]
    public function it_applies_the_fourteen_day_guarantee_period(int $days, ?string $expected): void
    {
        $case = $this->makeCase([
            'guarantee_revoked_at' => $this->asOf->copy()->subDays($days),
        ]);

        $this->assertSame($expected, $this->tier($case, 'I8'));
    }

    public static function guaranteeBoundary(): array
    {
        return [
            'hari ke-13, perhatian' => [13, 'perhatian'],
            'hari ke-14, masih perhatian' => [14, 'perhatian'],
            'hari ke-15, peringatan' => [15, 'peringatan'],
        ];
    }

    /**
     * I9, tidak ada perubahan tahap selama tiga puluh hari.
     */
    #[Test]
    #[DataProvider('stagnationBoundary')]
    public function it_flags_a_case_without_stage_movement(int $days, ?string $expected): void
    {
        $case = $this->makeCase([
            'detention_order_date' => $this->asOf->copy()->subDays(90),
            'current_stage' => 5,
        ]);

        StageEvent::create([
            'immigration_case_id' => $case->id,
            'stage_code' => 5,
            'opened_at' => $this->asOf->copy()->subDays($days),
        ]);

        $this->assertSame($expected, $this->tier($case, 'I9'));
    }

    public static function stagnationBoundary(): array
    {
        return [
            'hari ke-29, belum ada peringatan' => [29, null],
            'hari ke-30, peringatan' => [30, 'peringatan'],
            'hari ke-31, peringatan' => [31, 'peringatan'],
        ];
    }

    /**
     * I10, tonggak durasi detensi menuju batas Pasal 85.
     */
    #[Test]
    #[DataProvider('article85Boundary')]
    public function it_raises_review_points_toward_the_article_85_ceiling(string $offset, ?string $expected): void
    {
        $case = $this->makeCase([
            'detention_order_date' => Carbon::parse($offset),
            'current_stage' => 6,
        ]);

        $this->assertSame($expected, $this->tier($case, 'I10'));
    }

    public static function article85Boundary(): array
    {
        return [
            'sehari sebelum tahun pertama' => ['2025-10-08', null],
            'tepat pada tahun pertama' => ['2025-10-07', 'kritis'],
            'sehari sesudah tahun pertama' => ['2025-10-06', 'kritis'],
            'tepat pada tahun ketiga' => ['2023-10-07', 'kritis'],
        ];
    }

    /**
     * Gerbang status berlaku pula atas mesin indikator.
     *
     * Indikator yang tindakannya merupakan langkah dalam jalur pemulangan tidak
     * boleh terbit bagi orang berstatus dilindungi. I3 dan I4 adalah contoh
     * paling tajam, karena keduanya memicu pemberitahuan kepada perwakilan
     * negara asal.
     */
    #[Test]
    #[DataProvider('protectedStatusCodes')]
    public function it_withholds_removal_pathway_indicators_for_protected_status(string $status): void
    {
        $case = $this->makeCase(['legal_status_code' => $status], travelDocument: null);

        $this->assertNull($this->tier($case, 'I3'));

        MissionContact::create([
            'immigration_case_id' => $case->id,
            'mission' => 'Kedutaan Besar Negara Uji',
            'letter_no' => 'SURAT-UJI',
            'letter_date' => $this->asOf->copy()->subDays(45),
        ]);

        $this->assertNull($this->tier($case->fresh(), 'I4'));
    }

    public static function protectedStatusCodes(): array
    {
        return [
            'klaim perlindungan tertunda' => ['PROTECTION_PENDING'],
            'pengungsi terverifikasi' => ['REFUGEE_RECOGNISED'],
            'korban perdagangan orang' => ['TRAFFICKING_VICTIM'],
        ];
    }

    /**
     * Indikator durasi tetap berlaku bagi semua status, karena mengukur lamanya
     * penahanan dan menuntut peninjauan, bukan pemulangan.
     */
    #[Test]
    public function it_still_reviews_detention_duration_for_protected_status(): void
    {
        $case = $this->makeCase([
            'legal_status_code' => 'REFUGEE_RECOGNISED',
            'detention_order_date' => $this->asOf->copy()->subYears(2),
            'current_stage' => 4,
        ], travelDocument: null);

        $this->assertSame('kritis', $this->tier($case, 'I10'));
    }

    /**
     * NFR1, perhitungan memakai hari kalender, bukan hari kerja.
     *
     * Pedoman 2024 menegaskan bahwa Hari berarti hari kalender. Sistem yang
     * menghitung hari kerja akan menerbitkan peringatan terlambat.
     */
    #[Test]
    public function it_counts_calendar_days_across_weekends(): void
    {
        // 1 Oktober 2026 jatuh pada hari Kamis. Hari kalender ketujuh adalah
        // 8 Oktober, melintasi satu akhir pekan penuh.
        $this->asOf = Carbon::parse('2026-10-09');

        $case = $this->makeCase([
            'detention_order_date' => Carbon::parse('2026-10-01'),
            'detention_room_type' => 'KANIM',
        ]);

        // Delapan hari kalender telah lewat, sehingga tenggat tujuh hari
        // terlampaui meskipun hari kerjanya baru enam.
        $this->assertSame('peringatan', $this->tier($case, 'I1'));
    }

    /**
     * Mesin indikator tidak boleh memanggil waktu saat ini.
     */
    #[Test]
    public function it_depends_only_on_the_injected_reference_date(): void
    {
        $case = $this->makeCase([
            'detention_order_date' => $this->asOf->copy()->subDays(8),
            'detention_room_type' => 'KANIM',
        ]);

        $case->load(['detainee', 'stageEvents', 'missionContacts']);
        $indicator = Indicator::findOrFail('I1');

        // Tanggal acuan mundur tiga hari, sehingga baru lima hari berjalan.
        $tooEarly = $this->engine()->evaluate($case, $indicator, $this->asOf->copy()->subDays(3));
        // Tanggal acuan mundur dua hari, sehingga enam hari berjalan.
        $approaching = $this->engine()->evaluate($case, $indicator, $this->asOf->copy()->subDays(2));
        // Tanggal acuan hari ini, sehingga delapan hari berjalan.
        $exceeded = $this->engine()->evaluate($case, $indicator, $this->asOf);

        $this->assertNull($tooEarly);
        $this->assertSame('perhatian', $approaching);
        $this->assertSame('peringatan', $exceeded);
    }
}
