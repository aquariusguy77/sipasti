# SIPASTI — kerangka purwarupa

Sistem Informasi Pemantauan Alur dan Status Detensi Imigrasi. Berkas ini memuat
inti purwarupa, yaitu bagian yang menanggung klaim artikel. Antarmuka dan CRUD
biasa belum termasuk dan dapat Anda lanjutkan sendiri.

Seluruh ambang diturunkan dari **Pedoman Direktur Jenderal Imigrasi Nomor
IMI-190.GR.03.11 Tahun 2024**, kecuali batas sepuluh tahun yang bersumber pada
Pasal 85 Undang-Undang Nomor 6 Tahun 2011.

## Cara memasang

```bash
composer create-project laravel/laravel sipasti
cd sipasti
```

Salin isi folder ini ke dalam proyek Laravel tersebut, dengan struktur yang sama.
Lalu jalankan:

```bash
php artisan migrate:fresh --seed
php artisan test --testsuite=Feature
```

SQLite sudah menjadi bawaan Laravel terbaru, sehingga tidak perlu memasang server
basis data. Pemicu log audit ditulis untuk SQLite dan MySQL.

## Status pengujian

49 uji, seluruhnya lulus. Diverifikasi pada PHP 8.3 dan Laravel 12.

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

## Yang belum termasuk

Antarmuka papan perkara (FR7), laporan durasi tahap (FR8), pembatasan akses
berdasarkan peran (NFR2), dan aturan retensi (NFR5) belum dibangun. Keempatnya
merupakan pekerjaan antarmuka dan konfigurasi yang dapat Anda lanjutkan dengan
scaffolding Laravel biasa.

Seluruh data bersifat bentukan. Purwarupa ini tidak dirancang untuk memproses
data pribadi deteni yang sebenarnya.
