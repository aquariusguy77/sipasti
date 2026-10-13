# SIPASTI — purwarupa

Sistem Informasi Pemantauan Alur dan Status Detensi Imigrasi. Repositori ini
memuat inti purwarupa yang menanggung klaim artikel (mesin indikator, gerbang
status, penutupan peringatan, log audit hanya-tambah) beserta antarmuka papan
perkara (FR7), laporan durasi tahap (FR8), pembatasan akses berdasarkan peran
(NFR2), dan aturan retensi (NFR5).

Seluruh ambang diturunkan dari **Pedoman Direktur Jenderal Imigrasi Nomor
IMI-190.GR.03.11 Tahun 2024**, kecuali batas sepuluh tahun yang bersumber pada
Pasal 85 Undang-Undang Nomor 6 Tahun 2011.

## Cara memasang

Membutuhkan PHP 8.3 atau lebih baru dan Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve
```

Buka `http://127.0.0.1:8000` lalu masuk dengan salah satu akun demonstrasi.
Kata sandi seluruh akun adalah `password`.

| Akun | Peran | Yang dapat dilakukan |
| --- | --- | --- |
| `petugas@sipasti.test` | Petugas Rudenim | Papan perkara, registrasi, pindah tahap, sebab tertahan, surat perwakilan |
| `penyelia@sipasti.test` | Penyelia | Seluruh kemampuan petugas, meninjau peringatan, mengubah status, menutup perkara, laporan |
| `kepala@sipasti.test` | Kepala Rudenim | Papan perkara, meninjau peringatan, menutup perkara, laporan |
| `auditor@sipasti.test` | Auditor internal | Log audit dan laporan agregat, tanpa akses ke identitas deteni |
| `admin@sipasti.test` | Administrator | Menyunting ambang indikator dan membaca log audit, tanpa akses ke berkas perkara |

Matriks peran berada pada `config/sipasti.php` dan dapat diubah tanpa menyentuh kode.

SQLite sudah menjadi bawaan Laravel, sehingga tidak perlu memasang server basis
data. Pemicu log audit ditulis untuk SQLite dan MySQL.

### Tanggal acuan

`.env.example` menetapkan `SIPASTI_REFERENCE_DATE=2026-10-07`, sama dengan tanggal
acuan perkara simulasi, agar papan perkara memperlihatkan keadaan tepat di sekitar
setiap tenggat. Kosongkan nilai itu untuk memakai tanggal hari ini.

### Perintah terjadwal

```bash
php artisan sipasti:evaluate --as-of=2026-10-07   # mesin indikator, terjadwal setiap hari 06.00
php artisan sipasti:retention --dry-run           # calon anonimisasi, tanpa mengubah data
php artisan sipasti:retention                     # aturan retensi, terjadwal setiap tanggal 1
php artisan schedule:work                         # menjalankan penjadwal secara lokal
```

## Deploy ke Vercel (demonstrasi)

Vercel tidak menjalankan PHP secara bawaan. Repositori ini memakai runtime
komunitas `vercel-php` melalui `vercel.json` dan titik masuk `api/index.php`.

1. Impor repositori di Vercel. `vercel.json` sudah menonaktifkan deteksi
   kerangka Vite, jadi pengaturan Build dan Output tidak perlu diubah.
2. Tambahkan satu variabel lingkungan di **Settings → Environment Variables**:
   `APP_KEY`, berisi keluaran `php artisan key:generate --show`.
3. Deploy ulang.

Batasan yang perlu dipahami: Vercel hanya mengizinkan penulisan ke `/tmp`, jadi
basis data SQLite disusun ulang dari data simulasi setiap kali fungsi dimulai
dingin. Perubahan (registrasi perkara, peninjauan, dan sebagainya) bersifat
sementara dan tidak dibagi antar instans fungsi. Ini cukup untuk memperagakan
alur purwarupa, tetapi tidak untuk pemakaian berkelanjutan. Untuk itu gunakan
hosting PHP dengan basis data tetap, misalnya Laravel Cloud, Railway, atau VPS.

## Pengujian

```bash
php artisan test
```

95 uji, seluruhnya lulus. Diverifikasi pada PHP 8.3 dan Laravel 13. Uji inti dari
kerangka awal (48 uji) tetap tidak diubah.

## Peta berkas ke bagian artikel

| Berkas | Bagian artikel |
| --- | --- |
| `database/migrations/*_create_core_tables.php` | 4.3.1, model data, pemisahan Deteni dan Perkara |
| `database/migrations/*_create_process_tables.php` | 4.3.1, tahap sebagai peristiwa |
| `database/migrations/*_create_warning_tables.php` | 4.3.1, ambang sebagai data dan log audit hanya-tambah |
| `app/Services/IndicatorEngine.php` | 4.3.2, modul peringatan dini |
| `app/Services/StatusGate.php` | 4.3.4, gerbang status hukum |
| `app/Services/AlertCloser.php` | 4.3.2, FR10, penutupan menuntut alasan |
| `app/Services/AuditLogger.php` | 4.3.4, NFR3 |
| `database/seeders/IndicatorSeeder.php` | Table 2, sepuluh indikator |
| `database/seeders/LegalStatusSeeder.php` | 4.3.4, status yang dikecualikan |
| `database/seeders/SimulatedCaseSeeder.php` | 4.4, demonstrasi |
| `tests/Feature/IndicatorBoundaryTest.php` | 4.5, pengujian batas |
| `tests/Feature/StatusGateTest.php` | 4.5, pengujian gerbang status |
| `tests/Feature/AccountabilityTest.php` | 4.5, FR10 dan NFR3 |
| `app/Http/Controllers/BoardController.php`, `resources/views/board.blade.php` | FR7, papan perkara |
| `app/Http/Controllers/CaseController.php`, `resources/views/cases/*` | FR7, berkas perkara dan aksinya |
| `app/Services/StageTracker.php` | 4.3.1, perpindahan tahap sebagai peristiwa, dijaga gerbang status |
| `app/Services/CaseRegistry.php`, `app/Services/CaseCloser.php` | registrasi, pembaruan, dan penutupan perkara |
| `app/Services/StageDurationReport.php` | FR8, laporan durasi tahap dan sebab tertahan |
| `app/Services/RetentionPolicy.php` | NFR5, anonimisasi identitas setelah jangka retensi |
| `config/sipasti.php`, `app/Providers/AppServiceProvider.php` | NFR2, matriks peran dan Gate |
| `app/Http/Controllers/IndicatorController.php` | 4.3.1, penyuntingan ambang melalui antarmuka |
| `database/seeders/HistoricalCaseSeeder.php` | perkara tertutup untuk demonstrasi FR8 dan NFR5 |
| `tests/Feature/CaseBoardTest.php` | FR7, termasuk gerbang status yang tidak dapat dilewati dari layar |
| `tests/Feature/StageDurationReportTest.php` | FR8 |
| `tests/Feature/RoleAccessTest.php` | NFR2 |
| `tests/Feature/RetentionPolicyTest.php` | NFR5, pola tiga hari di sekitar batas retensi |
| `tests/Feature/IndicatorManagementTest.php` | 4.3.1, ambang sebagai data |

## Tiga keputusan desain yang menanggung klaim artikel

**Tanggal acuan selalu masuk sebagai parameter.** `IndicatorEngine` tidak pernah
memanggil waktu saat ini. Tanpa aturan ini, pengujian batas pada hari ke-6, ke-7,
dan ke-8 mustahil dijalankan tanpa mengubah jam sistem.

**Ambang disimpan sebagai data.** Tabel `indicators` memuat nilai ambang, satuan,
jangkar perhitungan, dasar normatif, dan tindakan yang dipicu. Perubahan pedoman
diterapkan dengan menyunting baris, bukan dengan membangun ulang sistem.

**Log audit hanya-tambah ditegakkan basis data.** Pemicu `audit_logs_no_update`
dan `audit_logs_no_delete` menolak perintah ubah dan hapus. Sifat itu menjadi
properti basis data, bukan janji di lapisan kode, sehingga pengujian NFR3 benar
benar bermakna.

## Catatan temuan selama pembangunan

Pengujian awal memperlihatkan bahwa perkara berstatus perlindungan tertunda tetap
memicu indikator I3, yang tindakannya adalah mengirim surat kepada perwakilan
negara asal. Bagi orang yang mengajukan perlindungan, pemberitahuan semacam itu
membahayakan yang bersangkutan.

Perbaikannya memperluas gerbang status hingga ke mesin indikator. Indikator I3,
I4, I5, dan I6 kini tidak terbit bagi status yang dikecualikan, sedangkan I9 dan
I10 tetap berlaku bagi semua status karena keduanya mengukur lamanya penahanan
dan menuntut peninjauan, bukan pemulangan.

Temuan ini layak dilaporkan pada subbagian 4.5, karena memperlihatkan bahwa
pengamanan berbasis status perlu melingkupi seluruh jalur pemulangan, tidak hanya
tindakan deportasi itu sendiri.

## Keputusan desain pada bagian yang dilanjutkan

**Antarmuka tidak membuka jalan pintas.** Pengendali hanya meneruskan masukan ke
lapis layanan. Pindah ke tahap 7 dan 8, melampirkan keputusan deportasi, mencatat
surat kepada perwakilan negara, dan menutup perkara karena deportasi seluruhnya
memanggil `StatusGate`. Formulir status dan peninjauan sengaja tidak memvalidasi
kelengkapan rujukan atau alasan, agar penolakan terjadi di gerbang dan tercatat
pada log audit. `CaseBoardTest` menguji setiap jalur ini melalui HTTP.

**Papan perkara tidak memuat identitas.** Kartu dan daftar peringatan hanya
menampilkan nomor perkara, tahap, lama detensi, status hukum, dan peringatan.
Nama dan kebangsaan hanya tampak di berkas perkara, yang pembukaannya dicatat.
Ini melanjutkan pagar pemrofilan pada model data ke lapisan tampilan.

**Perkara tidak dapat ditutup selama peringatannya terbuka.** Tanpa aturan ini,
menutup perkara menjadi jalan pintas untuk menghilangkan peringatan yang belum
ditinjau, dan kewajiban FR10 kehilangan maknanya.

**Perlu-tahu pada pembatasan akses.** Auditor membaca log audit dan laporan
agregat tetapi tidak membaca identitas deteni. Admin menyunting ambang tetapi tidak
membaca berkas perkara. Setiap akses yang ditolak dicatat sebagai `access.denied`.

**Retensi menganonimkan, bukan menghapus.** Yang dihapus hanya medan identitas.
Data perkara dipertahankan agar laporan durasi tahap tetap dapat dihitung, dan
log audit tidak disentuh. Satu perkara yang masih terbuka atau baru ditutup cukup
untuk menahan anonimisasi seorang deteni.

**Log audit mencatat nama medan, bukan nilainya.** Pembaruan nomor dokumen
perjalanan tercatat sebagai `medan=deteni.travel_document_no`, sehingga log audit
tidak menjadi salinan kedua data pribadi.

## Hal yang perlu diselaraskan dengan artikel

Tiga hal berikut merupakan usulan desain dalam purwarupa dan perlu dicocokkan
dengan naskah sebelum dilaporkan.

1. **Label delapan tahap** pada `config/sipasti.php` disusun dari perilaku kode
   (tahap 1 sampai 3 sebelum Rudenim, tahap 7 dan 8 jalur deportasi). Sesuaikan
   dengan rekonstruksi alur pada subbagian 4.1.
2. **Jangka retensi lima tahun** bukan turunan normatif. Selaraskan dengan jadwal
   retensi arsip instansi melalui `SIPASTI_RETENTION_YEARS`.
3. **Daftar alasan penutupan perkara** (`closure_reasons`) bersifat usulan.

Seluruh data bersifat bentukan. Purwarupa ini tidak dirancang untuk memproses
data pribadi deteni yang sebenarnya.
