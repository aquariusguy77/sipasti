<?php

namespace Database\Seeders;

use App\Models\Indicator;
use Illuminate\Database\Seeder;

/**
 * Sepuluh indikator pada Table 2, dimuat sebagai data rujukan.
 *
 * Seluruh ambang diturunkan dari Pedoman Direktur Jenderal Imigrasi Nomor
 * IMI-190.GR.03.11 Tahun 2024 tentang Pelaksanaan Pendetensian Orang Asing di
 * Ruang Detensi Imigrasi, Rumah Detensi Imigrasi dan Tempat Lain, kecuali I10
 * yang batas luarnya bersumber pada Pasal 85 Undang-Undang Nomor 6 Tahun 2011.
 *
 * Pedoman menegaskan bahwa Hari berarti hari kalender, sehingga seluruh
 * perhitungan memakai hari kalender, bukan hari kerja.
 */
class IndicatorSeeder extends Seeder
{
    public function run(): void
    {
        $indicators = [
            [
                'code' => 'I1',
                'name' => 'Batas tinggal di Ruang Detensi Kantor Imigrasi terlampaui',
                'mode' => 'exceeded',
                'threshold_value' => '7',
                'threshold_unit' => 'hari',
                'anchor' => 'detention_order_date',
                'normative_basis' => 'Pedoman 2024 Bab III.6.a dan 6.d',
                'triggered_action' => 'Ingatkan unit asal mengajukan pemindahan ke Rudenim',
            ],
            [
                'code' => 'I2',
                'name' => 'Batas tinggal di Ruang Detensi Ditjen Imigrasi terlampaui',
                'mode' => 'exceeded',
                'threshold_value' => '30',
                'threshold_unit' => 'hari',
                'anchor' => 'detention_order_date',
                'normative_basis' => 'Pedoman 2024 Bab III.6.c dan 6.d',
                'triggered_action' => 'Terbitkan permohonan pemindahan ke Rudenim',
            ],
            [
                'code' => 'I3',
                'name' => 'Deteni tidak memiliki dokumen perjalanan',
                'mode' => 'condition',
                'threshold_value' => null,
                'threshold_unit' => null,
                'anchor' => null,
                'normative_basis' => 'Pedoman 2024 Bab X.1.b dan format Kartu Deteni',
                'triggered_action' => 'Terbitkan surat pemberitahuan kepada perwakilan negara',
            ],
            [
                'code' => 'I4',
                'name' => 'Perwakilan negara belum merespons permintaan dokumen',
                'mode' => 'exceeded',
                'threshold_value' => '30',
                'threshold_unit' => 'hari',
                'anchor' => 'mission_letter_date',
                'normative_basis' => 'Analogi batas 30 hari Pedoman 2024 Bab III.6.c',
                'triggered_action' => 'Eskalasi kepada Direktorat Kerja Sama Keimigrasian',
            ],
            [
                'code' => 'I5',
                'name' => 'Sumber pembiayaan pemulangan belum tersedia',
                'mode' => 'condition',
                'threshold_value' => null,
                'threshold_unit' => null,
                'anchor' => null,
                'normative_basis' => 'Pedoman 2024 Bab XII.1',
                'triggered_action' => 'Ajukan pembebanan pada anggaran satuan kerja atau Ditjen Imigrasi',
            ],
            [
                'code' => 'I6',
                'name' => 'Tenggat meninggalkan wilayah Indonesia terlampaui',
                'mode' => 'exceeded',
                'threshold_value' => '7',
                'threshold_unit' => 'hari',
                'anchor' => 'deportation_decision_date',
                'normative_basis' => 'Format Keputusan TAK Deportasi, Lampiran B Pedoman 2024',
                'triggered_action' => 'Telusuri sebab tertahan dan catat pada berkas perkara',
            ],
            [
                'code' => 'I7',
                'name' => 'Keputusan TAK deportasi mendekati akhir masa berlaku',
                'mode' => 'advance',
                'threshold_value' => '7',
                'threshold_unit' => 'hari',
                'anchor' => 'deportation_decision_valid_until',
                'normative_basis' => 'Pedoman 2024 Bab X.4',
                'triggered_action' => 'Siapkan penerbitan ulang oleh Kepala Rudenim',
            ],
            [
                'code' => 'I8',
                'name' => 'Hak penjaminan dicabut dan tenggat terlampaui',
                'mode' => 'exceeded',
                'threshold_value' => '14',
                'threshold_unit' => 'hari',
                'anchor' => 'guarantee_revoked_at',
                'normative_basis' => 'Pedoman 2024 Bab XII.2.c dan 2.d',
                'triggered_action' => 'Jalankan mekanisme deportasi dan penangkalan',
            ],
            [
                'code' => 'I9',
                'name' => 'Tidak ada perubahan tahap selama satu bulan',
                'mode' => 'reached',
                'threshold_value' => '30',
                'threshold_unit' => 'hari',
                'anchor' => 'last_stage_change',
                'normative_basis' => 'Analogi kewajiban lapor bulanan Pedoman 2024 Bab V.3.b',
                'triggered_action' => 'Tinjau perkara dan tetapkan langkah berikutnya',
            ],
            [
                'code' => 'I10',
                'name' => 'Durasi detensi mendekati batas Pasal 85',
                'mode' => 'milestone',
                // Ambang antara bersifat usulan desain, bukan turunan normatif.
                // Hanya batas sepuluh tahun yang bersumber pada Pasal 85.
                'threshold_value' => '1,3,5,9',
                'threshold_unit' => 'tahun',
                'anchor' => 'detention_order_date',
                'normative_basis' => 'Pasal 85 UU 6/2011 sebagai batas luar, ambang antara merupakan usulan desain',
                'triggered_action' => 'Tinjauan menyeluruh dan pertimbangan pengeluaran dengan kewajiban lapor',
            ],
        ];

        foreach ($indicators as $indicator) {
            Indicator::updateOrCreate(
                ['code' => $indicator['code']],
                $indicator + ['active' => true],
            );
        }
    }
}
