<?php

namespace Database\Seeders;

use App\Models\LegalStatus;
use Illuminate\Database\Seeder;

/**
 * Status hukum yang dibaca gerbang status (subbagian 4.3.4).
 *
 * Hanya deteni yang dikenai tindakan administratif keimigrasian yang boleh
 * dialirkan ke proses deportasi. Orang dengan klaim perlindungan tertunda atau
 * status pengungsi terverifikasi dikecualikan. Korban perdagangan orang dan
 * penyelundupan manusia juga dikecualikan, karena Pasal 86 dan 87 Undang-Undang
 * Nomor 6 Tahun 2011 membebaskan mereka dari tindakan administratif keimigrasian
 * dan pedoman mengalirkannya ke mekanisme pemulangan.
 */
class LegalStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'code' => 'DETENI',
                'name' => 'Deteni dikenai tindakan administratif keimigrasian',
                'allows_deportation' => true,
                'allows_repatriation' => false,
                'note' => 'Pasal 83 UU 6/2011',
            ],
            [
                'code' => 'PROTECTION_PENDING',
                'name' => 'Klaim perlindungan tertunda',
                'allows_deportation' => false,
                'allows_repatriation' => false,
                'note' => 'Penentuan status berada pada otoritas di luar Rudenim',
            ],
            [
                'code' => 'REFUGEE_RECOGNISED',
                'name' => 'Pengungsi terverifikasi',
                'allows_deportation' => false,
                'allows_repatriation' => false,
                'note' => 'Perpres 125/2016 dan prinsip non-refoulement',
            ],
            [
                'code' => 'TRAFFICKING_VICTIM',
                'name' => 'Korban perdagangan orang atau penyelundupan manusia',
                'allows_deportation' => false,
                'allows_repatriation' => true,
                'note' => 'Pasal 86 dan 87 UU 6/2011, dialirkan ke pemulangan',
            ],
        ];

        foreach ($statuses as $status) {
            LegalStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
