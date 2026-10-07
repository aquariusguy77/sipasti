<?php

namespace Database\Seeders;

use App\Models\Detainee;
use App\Models\ImmigrationCase;
use App\Models\MissionContact;
use App\Models\StageEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Perkara simulasi untuk demonstrasi dan pengujian.
 *
 * Seluruh data bersifat bentukan. Purwarupa tidak pernah memproses data pribadi
 * deteni yang sebenarnya, sesuai posisi etis pada subbagian 3.7.
 *
 * Tanggal acuan disuntikkan, bukan diambil dari jam sistem, agar perkara dapat
 * ditempatkan tepat sebelum, tepat pada, dan tepat sesudah setiap tenggat.
 */
class SimulatedCaseSeeder extends Seeder
{
    public function run(?Carbon $asOf = null): void
    {
        $asOf ??= Carbon::parse('2026-10-07');

        // Perkara 1 sampai 3: batas Ruang Detensi Kantor Imigrasi, hari ke-6, 7, 8.
        foreach ([6, 7, 8] as $i => $days) {
            $this->makeCase(
                number: 'SIM-KANIM-'.($i + 1),
                nationality: 'Negara A',
                detentionOrderDate: $asOf->copy()->subDays($days),
                roomType: 'KANIM',
                stage: 2,
                travelDocument: 'TD-'.($i + 1),
            );
        }

        // Perkara 4 sampai 6: batas Ruang Detensi Ditjen Imigrasi, hari ke-29, 30, 31.
        foreach ([29, 30, 31] as $i => $days) {
            $this->makeCase(
                number: 'SIM-DITJEN-'.($i + 1),
                nationality: 'Negara B',
                detentionOrderDate: $asOf->copy()->subDays($days),
                roomType: 'DITJENIM',
                stage: 2,
                travelDocument: 'TD-D'.($i + 1),
            );
        }

        // Perkara 7: tanpa dokumen perjalanan, sudah di Rudenim, surat ke
        // perwakilan negara terkirim 31 hari lalu tanpa respons.
        $case = $this->makeCase(
            number: 'SIM-NODOC-1',
            nationality: 'Negara C',
            detentionOrderDate: $asOf->copy()->subDays(60),
            roomType: 'KANIM',
            stage: 6,
            travelDocument: null,
        );
        MissionContact::create([
            'immigration_case_id' => $case->id,
            'mission' => 'Kedutaan Besar Negara C',
            'letter_no' => 'W.1-IMI.GR.03.01-SIM-001',
            'letter_date' => $asOf->copy()->subDays(31),
        ]);

        // Perkara 8: keputusan deportasi terbit 8 hari lalu, belum dieksekusi.
        $this->makeCase(
            number: 'SIM-DEPORT-1',
            nationality: 'Negara D',
            detentionOrderDate: $asOf->copy()->subDays(40),
            roomType: 'KANIM',
            stage: 7,
            travelDocument: null,
            deportationDecisionDate: $asOf->copy()->subDays(8),
            deportationValidUntil: $asOf->copy()->addDays(7),
        );

        // Perkara 9: hak penjaminan dicabut 15 hari lalu.
        $this->makeCase(
            number: 'SIM-JAMIN-1',
            nationality: 'Negara E',
            detentionOrderDate: $asOf->copy()->subDays(50),
            roomType: 'KANIM',
            stage: 5,
            travelDocument: 'TD-E1',
            guaranteeRevokedAt: $asOf->copy()->subDays(15),
        );

        // Perkara 10: pembiayaan habis tanpa sumber tersedia.
        $this->makeCase(
            number: 'SIM-BIAYA-1',
            nationality: 'Negara F',
            detentionOrderDate: $asOf->copy()->subDays(70),
            roomType: 'KANIM',
            stage: 7,
            travelDocument: 'TD-F1',
            fundingExhausted: true,
        );

        // Perkara 11: durasi detensi melewati tonggak satu tahun.
        $this->makeCase(
            number: 'SIM-PASAL85-1',
            nationality: 'Negara G',
            detentionOrderDate: $asOf->copy()->subYears(1)->subDay(),
            roomType: 'KANIM',
            stage: 6,
            travelDocument: null,
        );

        // Perkara 12: status perlindungan tertunda, dipakai menguji gerbang status.
        $this->makeCase(
            number: 'SIM-LINDUNG-1',
            nationality: 'Negara H',
            detentionOrderDate: $asOf->copy()->subDays(20),
            roomType: 'KANIM',
            stage: 4,
            travelDocument: null,
            legalStatus: 'PROTECTION_PENDING',
        );
    }

    private function makeCase(
        string $number,
        string $nationality,
        Carbon $detentionOrderDate,
        string $roomType,
        int $stage,
        ?string $travelDocument,
        ?Carbon $deportationDecisionDate = null,
        ?Carbon $deportationValidUntil = null,
        ?Carbon $guaranteeRevokedAt = null,
        bool $fundingExhausted = false,
        string $legalStatus = 'DETENI',
    ): ImmigrationCase {
        $detainee = Detainee::create([
            'full_name' => 'Deteni Simulasi '.$number,
            'sex' => 'L',
            'nationality' => $nationality,
            'travel_document_no' => $travelDocument,
            'sending_agency' => 'Kantor Imigrasi Simulasi',
            'provision_violated' => 'Pasal 122 huruf a UU 6/2011',
            'biometric_captured' => true,
        ]);

        $case = ImmigrationCase::create([
            'case_number' => $number,
            'detainee_id' => $detainee->id,
            'legal_status_code' => $legalStatus,
            'status_determination_ref' => 'SIM-REF-'.$number,
            'status_determination_authority' => 'Pejabat Imigrasi',
            'status_determination_date' => $detentionOrderDate,
            'detention_order_no' => 'SPR-'.$number,
            'detention_order_date' => $detentionOrderDate,
            'detention_room_type' => $roomType,
            'deportation_decision_no' => $deportationDecisionDate ? 'KEP-'.$number : null,
            'deportation_decision_date' => $deportationDecisionDate,
            'deportation_decision_valid_until' => $deportationValidUntil,
            'funding_exhausted' => $fundingExhausted,
            'guarantee_revoked_at' => $guaranteeRevokedAt,
            'current_stage' => $stage,
            'state' => 'open',
        ]);

        for ($s = 1; $s <= $stage; $s++) {
            StageEvent::create([
                'immigration_case_id' => $case->id,
                'stage_code' => $s,
                'opened_at' => $detentionOrderDate->copy()->addDays($s - 1),
                'closed_at' => $s < $stage ? $detentionOrderDate->copy()->addDays($s) : null,
                'responsible_unit' => 'Unit Simulasi',
            ]);
        }

        return $case;
    }
}
