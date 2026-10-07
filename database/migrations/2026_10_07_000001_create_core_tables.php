<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIPASTI core entities (subbagian 4.3.1).
 *
 * Catatan desain: entitas Detainee sengaja dipisahkan dari ImmigrationCase.
 * Seluruh indikator membaca dari ImmigrationCase dan turunannya, tidak ada
 * satu pun yang membaca dari Detainee. Pemisahan ini yang membuat pembalikan
 * logika risiko menjadi sifat model data, bukan sekadar niat perancang.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabel rujukan status hukum. Dibaca oleh StatusGate.
        Schema::create('legal_statuses', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->boolean('allows_deportation')->default(false);
            $table->boolean('allows_repatriation')->default(false);
            $table->text('note')->nullable();
        });

        // Medan dibatasi pada apa yang sudah diwajibkan Kartu Deteni dan
        // berita acara pendetensian (NFR4). Tidak ada muatan biometrik.
        Schema::create('detainees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->enum('sex', ['L', 'P']);
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('nationality');
            $table->string('travel_document_no')->nullable();
            $table->string('travel_document_place')->nullable();
            $table->date('travel_document_date')->nullable();
            $table->string('sending_agency')->nullable();
            $table->string('provision_violated')->nullable();
            // Penanda bahwa sidik jari dan foto telah diambil sesuai pedoman.
            // Yang disimpan hanya penanda, bukan datanya.
            $table->boolean('biometric_captured')->default(false);
            $table->timestamps();
        });

        Schema::create('immigration_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->foreignId('detainee_id')->constrained()->cascadeOnDelete();

            // Penanda status hukum dan rujukan penetapannya. Perubahan penanda
            // tanpa rujukan penetapan ditolak oleh StatusGate.
            $table->string('legal_status_code');
            $table->string('status_determination_ref')->nullable();
            $table->string('status_determination_authority')->nullable();
            $table->date('status_determination_date')->nullable();

            // Jangkar perhitungan I1, I2, dan I10.
            $table->string('detention_order_no');
            $table->date('detention_order_date');
            $table->enum('detention_room_type', ['KANIM', 'DITJENIM'])->nullable();

            // Jangkar perhitungan I6 dan I7.
            $table->string('deportation_decision_no')->nullable();
            $table->date('deportation_decision_date')->nullable();
            $table->date('deportation_decision_valid_until')->nullable();

            // Jangkar perhitungan I5.
            $table->string('funding_source')->nullable();
            $table->boolean('funding_exhausted')->default(false);

            // Jangkar perhitungan I8.
            $table->date('guarantee_revoked_at')->nullable();

            $table->unsignedTinyInteger('current_stage')->default(1);
            $table->enum('state', ['open', 'closed'])->default('open');
            $table->date('closed_at')->nullable();
            $table->string('closure_reason')->nullable();
            $table->timestamps();

            $table->foreign('legal_status_code')->references('code')->on('legal_statuses');
            $table->index(['state', 'current_stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_cases');
        Schema::dropIfExists('detainees');
        Schema::dropIfExists('legal_statuses');
    }
};
