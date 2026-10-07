<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel peringatan dini, peninjauan, dan log audit.
 *
 * Ambang disimpan sebagai data rujukan pada tabel indicators, bukan ditulis
 * ke dalam logika program. Perubahan pedoman diterapkan dengan menyunting
 * baris tabel, bukan dengan membangun ulang sistem (subbagian 4.3.1).
 *
 * Log audit dibuat hanya-tambah melalui pemicu basis data, sehingga sifat itu
 * menjadi properti basis data, bukan janji di lapisan kode. Inilah yang membuat
 * pengujian NFR3 bermakna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            // exceeded  : Perhatian pada H-1, Peringatan pada H+1 setelah tenggat
            // reached   : Peringatan tepat saat ambang tercapai
            // advance   : Perhatian ketika sisa waktu mencapai ambang
            // milestone : Kritis pada setiap tonggak yang dilewati
            // condition : dievaluasi dari keadaan perkara, bukan dari waktu
            $table->enum('mode', ['exceeded', 'reached', 'advance', 'milestone', 'condition']);
            $table->string('threshold_value')->nullable();
            $table->string('threshold_unit')->nullable();
            $table->string('anchor')->nullable();
            $table->text('normative_basis');
            $table->text('triggered_action');
            $table->boolean('active')->default(true);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_case_id')->constrained()->cascadeOnDelete();
            $table->string('indicator_code');
            $table->date('raised_at');
            $table->enum('tier', ['perhatian', 'peringatan', 'kritis']);
            $table->enum('state', ['open', 'closed'])->default('open');
            $table->timestamps();

            $table->foreign('indicator_code')->references('code')->on('indicators');
            // Keunikan peringatan terbuka per indikator ditegakkan di lapis
            // aplikasi, bukan sebagai indeks unik. Indeks unik pada kombinasi
            // perkara, indikator, dan status akan menolak peringatan kedua yang
            // sudah ditutup, padahal satu perkara dapat menimbulkan peringatan
            // berulang pada indikator yang sama.
            $table->index(['immigration_case_id', 'indicator_code', 'state']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->date('reviewed_at');
            $table->string('reviewer_role');
            $table->string('decision');
            // Wajib. Penutupan peringatan tanpa alasan ditolak (FR10).
            $table->text('reasoning');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor');
            $table->string('action');
            $table->string('entity');
            $table->string('entity_id')->nullable();
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity', 'entity_id']);
        });

        $this->guardAuditLog();
    }

    /**
     * Menolak perintah ubah dan hapus pada tabel log audit.
     */
    private function guardAuditLog(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("
                CREATE TRIGGER audit_logs_no_update
                BEFORE UPDATE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs bersifat hanya-tambah');
                END;
            ");
            DB::statement("
                CREATE TRIGGER audit_logs_no_delete
                BEFORE DELETE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs bersifat hanya-tambah');
                END;
            ");

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("
                CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
                FOR EACH ROW SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'audit_logs bersifat hanya-tambah';
            ");
            DB::unprepared("
                CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs
                FOR EACH ROW SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'audit_logs bersifat hanya-tambah';
            ");
        }
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_update');
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('indicators');
    }
};
