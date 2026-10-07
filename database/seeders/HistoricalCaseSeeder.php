<?php

namespace Database\Seeders;

use App\Models\Detainee;
use App\Models\ImmigrationCase;
use App\Models\StageEvent;
use App\Models\StallCause;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Perkara simulasi yang sudah ditutup, untuk laporan durasi tahap (FR8) dan
 * demonstrasi aturan retensi (NFR5).
 *
 * Perkara pada SimulatedCaseSeeder berpindah tahap setiap hari karena dirancang
 * untuk pengujian batas. Perkara di sini memiliki durasi tahap yang beragam dan
 * sebab tertahan yang tercatat, sehingga laporan durasi tahap memperlihatkan
 * di mana perkara tertahan. Seluruh data bersifat bentukan.
 */
class HistoricalCaseSeeder extends Seeder
{
    public function run(?Carbon $asOf = null): void
    {
        $asOf ??= Carbon::parse('2026-10-07');

        // [nomor, kebangsaan, tanggal pendetensian, durasi tahap 1..8 dalam hari,
        //  sebab tertahan per tahap, alasan penutupan]
        $cases = [
            ['HIST-2026-01', 'Negara A', $asOf->copy()->subDays(160), [1, 5, 3, 2, 14, 45, 20, 1], [6 => 'travel_document', 7 => 'funding'], 'deported'],
            ['HIST-2026-02', 'Negara B', $asOf->copy()->subDays(130), [1, 6, 2, 1, 10, 38, 12, 1], [6 => 'external_response'], 'deported'],
            ['HIST-2026-03', 'Negara C', $asOf->copy()->subDays(210), [1, 8, 4, 2, 21, 90, 30, 2], [6 => 'external_response', 7 => 'transport'], 'deported'],
            ['HIST-2026-04', 'Negara D', $asOf->copy()->subDays(95), [1, 4, 2, 1, 7, 25, 9, 1], [7 => 'funding'], 'deported'],
            ['HIST-2026-05', 'Negara E', $asOf->copy()->subDays(80), [1, 3, 2, 1, 30], [5 => 'external_response'], 'released_reporting'],
            ['HIST-2025-01', 'Negara F', $asOf->copy()->subDays(400), [1, 7, 3, 2, 18, 120, 40, 1], [6 => 'travel_document', 7 => 'funding'], 'deported'],
            // Ditutup lebih dari lima tahun lalu, sehingga identitasnya menjadi
            // calon anonimisasi aturan retensi.
            ['HIST-2020-01', 'Negara G', Carbon::parse('2020-02-03'), [1, 6, 3, 2, 12, 60, 15, 1], [6 => 'travel_document'], 'deported'],
            ['HIST-2020-02', 'Negara H', Carbon::parse('2020-05-11'), [1, 5, 2, 1, 25], [], 'protection_referral'],
        ];

        foreach ($cases as [$number, $nationality, $orderDate, $durations, $causes, $reason]) {
            $this->makeClosedCase($number, $nationality, $orderDate, $durations, $causes, $reason);
        }
    }

    private function makeClosedCase(
        string $number,
        string $nationality,
        Carbon $orderDate,
        array $durations,
        array $causes,
        string $reason,
    ): void {
        $detainee = Detainee::create([
            'full_name' => 'Deteni Simulasi '.$number,
            'sex' => 'L',
            'nationality' => $nationality,
            'travel_document_no' => 'TD-'.$number,
            'sending_agency' => 'Kantor Imigrasi Simulasi',
            'provision_violated' => 'Pasal 78 ayat (3) UU 6/2011',
            'biometric_captured' => true,
        ]);

        $lastStage = count($durations);
        $closedAt = $orderDate->copy()->addDays(array_sum($durations));

        $case = ImmigrationCase::create([
            'case_number' => $number,
            'detainee_id' => $detainee->id,
            'legal_status_code' => $reason === 'protection_referral' ? 'PROTECTION_PENDING' : 'DETENI',
            'status_determination_ref' => 'SIM-REF-'.$number,
            'status_determination_authority' => 'Pejabat Imigrasi',
            'status_determination_date' => $orderDate,
            'detention_order_no' => 'SPR-'.$number,
            'detention_order_date' => $orderDate,
            'detention_room_type' => 'KANIM',
            'current_stage' => $lastStage,
            'state' => 'closed',
            'closed_at' => $closedAt,
            'closure_reason' => $reason,
        ]);

        $cursor = $orderDate->copy();

        foreach ($durations as $i => $days) {
            $stage = $i + 1;
            $end = $cursor->copy()->addDays($days);

            StageEvent::create([
                'immigration_case_id' => $case->id,
                'stage_code' => $stage,
                'opened_at' => $cursor,
                'closed_at' => $end,
                'responsible_unit' => 'Unit Simulasi',
            ]);

            if (isset($causes[$stage])) {
                StallCause::create([
                    'immigration_case_id' => $case->id,
                    'stage_code' => $stage,
                    'cause_code' => $causes[$stage],
                    'recorded_at' => $cursor->copy()->addDays(intdiv($days, 2)),
                ]);
            }

            $cursor = $end;
        }
    }
}
