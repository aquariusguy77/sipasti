<?php

/**
 * Konfigurasi SIPASTI.
 *
 * Ambang indikator tidak disimpan di sini. Ambang adalah data rujukan pada tabel
 * indicators (subbagian 4.3.1). Berkas ini hanya memuat hal yang bersifat
 * konfigurasi purwarupa: label tahap, matriks peran, dan masa retensi.
 */
return [

    /*
     * Tanggal acuan bagi antarmuka dan perintah terjadwal. Kosong berarti hari
     * ini. Isi dengan tanggal tetap (misalnya 2026-10-07) untuk demonstrasi agar
     * perkara simulasi tetap berada tepat di sekitar setiap tenggat.
     *
     * Mesin indikator sendiri tetap tidak pernah memanggil waktu saat ini.
     */
    'reference_date' => env('SIPASTI_REFERENCE_DATE'),

    /*
     * Delapan tahap alur sesuai rekonstruksi pada subbagian 4.1.
     *
     * Tahap 1 sampai 3 berlangsung sebelum pelimpahan ke Rudenim, sehingga batas
     * Ruang Detensi (I1, I2) hanya berlaku sebelum tahap 4. Tahap 7 dan 8 adalah
     * jalur deportasi dan karenanya dijaga gerbang status.
     */
    'stages' => [
        1 => 'Penindakan dan keputusan pendetensian',
        2 => 'Penempatan di Ruang Detensi',
        3 => 'Pengajuan pemindahan ke Rudenim',
        4 => 'Penerimaan dan registrasi di Rudenim',
        5 => 'Pemeriksaan dan penentuan langkah',
        6 => 'Pengurusan dokumen perjalanan',
        7 => 'Penyiapan deportasi',
        8 => 'Pelaksanaan deportasi',
    ],

    // Tahap yang hanya boleh dimasuki bila status hukum mengizinkan deportasi.
    'deportation_stages' => [7, 8],

    'stall_causes' => [
        'travel_document' => 'Dokumen perjalanan',
        'funding' => 'Pembiayaan',
        'transport' => 'Transportasi',
        'external_response' => 'Respons pihak luar',
    ],

    /*
     * Alasan penutupan perkara. Penutupan berjenis deportasi melewati gerbang
     * status, sama seperti tahap 7 dan 8.
     */
    'closure_reasons' => [
        'deported' => 'Dideportasi',
        'repatriated' => 'Dipulangkan melalui mekanisme pemulangan',
        'protection_referral' => 'Dialihkan ke penanganan perlindungan',
        'released_reporting' => 'Dikeluarkan dengan kewajiban lapor',
        'other' => 'Lainnya',
    ],

    /*
     * Pembatasan akses berdasarkan peran (NFR2).
     *
     * Prinsipnya perlu-tahu. Auditor membaca log audit dan laporan agregat,
     * tetapi tidak membaca identitas deteni. Admin mengelola ambang indikator,
     * tetapi tidak membaca berkas perkara.
     */
    'roles' => [
        'petugas' => 'Petugas Rudenim',
        'penyelia' => 'Penyelia (Kepala Seksi)',
        'kepala' => 'Kepala Rudenim',
        'auditor' => 'Auditor internal',
        'admin' => 'Administrator sistem',
    ],

    'abilities' => [
        'case.view' => ['petugas', 'penyelia', 'kepala'],
        'case.register' => ['petugas', 'penyelia'],
        'case.update' => ['petugas', 'penyelia'],
        'case.close' => ['penyelia', 'kepala'],
        'alert.review' => ['penyelia', 'kepala'],
        'status.change' => ['penyelia'],
        'indicators.run' => ['penyelia', 'admin'],
        'indicators.manage' => ['admin'],
        'report.view' => ['penyelia', 'kepala', 'auditor'],
        'audit.view' => ['auditor', 'admin'],
    ],

    /*
     * Aturan retensi (NFR5).
     *
     * Identitas deteni dianonimkan setelah seluruh perkaranya ditutup selama
     * jangka ini. Data perkara (tanggal, tahap, sebab tertahan) dipertahankan
     * agar laporan durasi tahap tetap dapat dihitung. Log audit tidak pernah
     * dihapus. Angka bawaan merupakan usulan desain, bukan turunan normatif,
     * dan perlu diselaraskan dengan jadwal retensi arsip instansi.
     */
    'retention_years' => (int) env('SIPASTI_RETENTION_YEARS', 5),

];
