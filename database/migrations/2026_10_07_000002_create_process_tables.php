<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel proses: tahap, sebab tertahan, dan kontak perwakilan negara.
 *
 * Tahap dicatat sebagai peristiwa, bukan sebagai medan status yang ditimpa.
 * Dengan begitu durasi per tahap tetap dapat dihitung di kemudian hari dan
 * riwayat perkara tidak hilang karena penyuntingan berikutnya (subbagian 4.3.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_case_id')->constrained()->cascadeOnDelete();
            // Tahap 1 sampai 8 sesuai rekonstruksi alur pada subbagian 4.1.
            $table->unsignedTinyInteger('stage_code');
            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->string('responsible_unit')->nullable();
            $table->string('output_document')->nullable();
            $table->timestamps();

            $table->index(['immigration_case_id', 'stage_code']);
        });

        Schema::create('stall_causes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stage_code');
            // Daftar tertutup. Daftar terbuka membuat laporan durasi tahap
            // kehilangan daya banding antar perkara.
            $table->enum('cause_code', [
                'travel_document',
                'funding',
                'transport',
                'external_response',
            ]);
            $table->date('recorded_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('immigration_case_id');
        });

        Schema::create('mission_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_case_id')->constrained()->cascadeOnDelete();
            // Perwakilan negara dicatat sebagai aktor proses, bukan sebagai
            // atribut risiko deteni. Pembedaan ini menjaga pagar pemrofilan.
            $table->string('mission');
            $table->string('letter_no');
            $table->date('letter_date');
            $table->date('response_date')->nullable();
            $table->string('response_type')->nullable();
            $table->timestamps();

            $table->index('immigration_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_contacts');
        Schema::dropIfExists('stall_causes');
        Schema::dropIfExists('stage_events');
    }
};
