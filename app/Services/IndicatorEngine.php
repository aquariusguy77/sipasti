<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\ImmigrationCase;
use App\Models\Indicator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Mesin indikator modul peringatan dini (subbagian 4.3.2).
 *
 * Dua aturan mengikat seluruh kelas ini.
 *
 * Pertama, tanggal acuan selalu masuk sebagai parameter. Kelas ini tidak pernah
 * memanggil waktu saat ini. Tanpa aturan ini, pengujian batas pada hari ke-6,
 * ke-7, dan ke-8 mustahil dijalankan.
 *
 * Kedua, objek prediksi adalah perkara. Seluruh pembacaan diambil dari
 * ImmigrationCase dan turunannya. Tidak ada pembacaan dari Detainee, kecuali
 * keberadaan nomor dokumen perjalanan, yang merupakan keadaan berkas perkara
 * dan bukan sifat orangnya.
 */
class IndicatorEngine
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * Mengevaluasi seluruh perkara terbuka terhadap seluruh indikator aktif.
     *
     * @return Collection<int, Alert>
     */
    public function run(Carbon $asOf, string $actor = 'system'): Collection
    {
        $indicators = Indicator::where('active', true)->get();

        $cases = ImmigrationCase::with([
            'detainee', 'legalStatus', 'stageEvents', 'missionContacts', 'alerts',
        ])->where('state', 'open')->get();

        $raised = collect();

        foreach ($cases as $case) {
            foreach ($indicators as $indicator) {
                $tier = $this->evaluate($case, $indicator, $asOf);

                if ($tier === null) {
                    continue;
                }

                $alert = $this->record($case, $indicator, $tier, $asOf, $actor);

                if ($alert !== null) {
                    $raised->push($alert);
                }
            }
        }

        return $raised;
    }

    /**
     * Menetapkan tingkat peringatan satu indikator atas satu perkara.
     * Mengembalikan null bila ambang belum tercapai.
     */
    public function evaluate(ImmigrationCase $case, Indicator $indicator, Carbon $asOf): ?string
    {
        if (! $this->applies($case, $indicator)) {
            return null;
        }

        return match ($indicator->mode) {
            'exceeded' => $this->exceeded($case, $indicator, $asOf),
            'reached' => $this->reached($case, $indicator, $asOf),
            'advance' => $this->advance($case, $indicator, $asOf),
            'milestone' => $this->milestone($case, $indicator, $asOf),
            'condition' => $this->condition($case, $indicator),
            default => null,
        };
    }

    /**
     * Tenggat yang terlampaui.
     *
     * Perhatian muncul sehari sebelum tenggat berakhir. Peringatan muncul pada
     * hari sesudah tenggat berakhir, bukan pada hari terakhir yang masih sah.
     * Sistem yang menandai hari terakhir sebagai pelanggaran akan memperlakukan
     * tindakan yang sah sebagai kelalaian.
     */
    private function exceeded(ImmigrationCase $case, Indicator $indicator, Carbon $asOf): ?string
    {
        $anchor = $this->anchorDate($case, $indicator);

        if ($anchor === null) {
            return null;
        }

        $days = $anchor->diffInDays($asOf);
        $threshold = $indicator->threshold();

        if ($days > $threshold) {
            return 'peringatan';
        }

        if ($days >= $threshold - 1) {
            return 'perhatian';
        }

        return null;
    }

    /**
     * Ambang yang tercapai, tanpa tingkat perhatian mendahului.
     */
    private function reached(ImmigrationCase $case, Indicator $indicator, Carbon $asOf): ?string
    {
        $anchor = $this->anchorDate($case, $indicator);

        if ($anchor === null) {
            return null;
        }

        return $anchor->diffInDays($asOf) >= $indicator->threshold() ? 'peringatan' : null;
    }

    /**
     * Peringatan dini sebelum suatu tanggal berakhir.
     */
    private function advance(ImmigrationCase $case, Indicator $indicator, Carbon $asOf): ?string
    {
        $anchor = $this->anchorDate($case, $indicator);

        if ($anchor === null) {
            return null;
        }

        $remaining = $asOf->diffInDays($anchor, false);

        return ($remaining >= 0 && $remaining <= $indicator->threshold()) ? 'perhatian' : null;
    }

    /**
     * Tonggak durasi detensi, dihitung dari tanggal keputusan pendetensian.
     */
    private function milestone(ImmigrationCase $case, Indicator $indicator, Carbon $asOf): ?string
    {
        $anchor = $this->anchorDate($case, $indicator);

        if ($anchor === null) {
            return null;
        }

        foreach ($indicator->thresholds() as $years) {
            if ($anchor->copy()->addYears($years)->lessThanOrEqualTo($asOf)) {
                return 'kritis';
            }
        }

        return null;
    }

    /**
     * Indikator yang dievaluasi dari keadaan perkara, bukan dari waktu.
     */
    private function condition(ImmigrationCase $case, Indicator $indicator): ?string
    {
        return match ($indicator->code) {
            // I3, deteni tidak memiliki dokumen perjalanan.
            'I3' => $case->hasTravelDocument() ? null : 'peringatan',
            // I5, hierarki pembiayaan habis tanpa sumber yang tersedia.
            'I5' => ($case->funding_exhausted && blank($case->funding_source)) ? 'peringatan' : null,
            default => null,
        };
    }

    /**
     * Sebagian indikator hanya berlaku pada keadaan perkara tertentu.
     */
    private function applies(ImmigrationCase $case, Indicator $indicator): bool
    {
        // Gerbang status berlaku pula atas mesin indikator, bukan hanya atas
        // alur deportasi. Indikator yang tindakannya merupakan langkah dalam
        // jalur pemulangan tidak boleh terbit bagi orang berstatus dilindungi.
        //
        // I3 dan I4 adalah contoh paling tajam. Keduanya memicu pemberitahuan
        // kepada perwakilan negara asal. Bagi orang yang mengajukan atau telah
        // memperoleh perlindungan, pemberitahuan semacam itu membahayakan yang
        // bersangkutan dan menyentuh prinsip non-refoulement.
        //
        // I9 dan I10 tetap berlaku bagi semua status, karena keduanya mengukur
        // lamanya penahanan dan menuntut peninjauan, bukan pemulangan.
        if (in_array($indicator->code, ['I3', 'I4', 'I5', 'I6'], true)
            && ! ($case->legalStatus?->allows_deportation ?? false)) {
            return false;
        }

        return match ($indicator->code) {
            // Batas Ruang Detensi hanya berlaku sebelum perkara dilimpahkan
            // ke Rumah Detensi Imigrasi, yaitu sebelum tahap empat.
            'I1' => $case->detention_room_type === 'KANIM' && $case->current_stage < 4,
            'I2' => $case->detention_room_type === 'DITJENIM' && $case->current_stage < 4,
            // Tenggat meninggalkan wilayah Indonesia hanya berlaku bila
            // keputusan deportasi sudah terbit dan belum dieksekusi.
            'I6' => filled($case->deportation_decision_date) && $case->current_stage < 8,
            default => true,
        };
    }

    /**
     * Tanggal jangkar perhitungan, ditentukan oleh kolom anchor pada tabel
     * indicators. Jangkar adalah data, bukan logika.
     */
    private function anchorDate(ImmigrationCase $case, Indicator $indicator): ?Carbon
    {
        return match ($indicator->anchor) {
            'detention_order_date' => $case->detention_order_date,
            'deportation_decision_date' => $case->deportation_decision_date,
            'deportation_decision_valid_until' => $case->deportation_decision_valid_until,
            'guarantee_revoked_at' => $case->guarantee_revoked_at,
            'mission_letter_date' => $case->openMissionContact()?->letter_date,
            'last_stage_change' => $case->lastStageChange(),
            default => null,
        };
    }

    /**
     * Menuliskan peringatan. Peringatan terbuka yang sudah ada hanya dinaikkan
     * tingkatnya, tidak digandakan.
     */
    private function record(
        ImmigrationCase $case,
        Indicator $indicator,
        string $tier,
        Carbon $asOf,
        string $actor,
    ): ?Alert {
        $existing = Alert::where('immigration_case_id', $case->id)
            ->where('indicator_code', $indicator->code)
            ->where('state', 'open')
            ->first();

        if ($existing !== null) {
            if ($this->rank($tier) > $this->rank($existing->tier)) {
                $existing->update(['tier' => $tier, 'raised_at' => $asOf]);
                $this->audit->write($actor, 'alert.escalated', 'Alert', $existing->id, $indicator->code);

                return $existing;
            }

            return null;
        }

        $alert = Alert::create([
            'immigration_case_id' => $case->id,
            'indicator_code' => $indicator->code,
            'raised_at' => $asOf,
            'tier' => $tier,
            'state' => 'open',
        ]);

        $this->audit->write($actor, 'alert.raised', 'Alert', $alert->id, $indicator->code);

        return $alert;
    }

    private function rank(string $tier): int
    {
        return array_search($tier, Alert::TIERS, true) ?: 0;
    }
}
