<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom pendukung pembatasan akses (NFR2) dan aturan retensi (NFR5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Peran menentukan kemampuan melalui matriks pada config/sipasti.php.
            $table->string('role')->default('petugas')->after('email');
            $table->boolean('active')->default(true)->after('role');
        });

        Schema::table('detainees', function (Blueprint $table) {
            // Diisi saat identitas dianonimkan oleh aturan retensi.
            $table->date('anonymised_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('detainees', function (Blueprint $table) {
            $table->dropColumn('anonymised_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'active']);
        });
    }
};
