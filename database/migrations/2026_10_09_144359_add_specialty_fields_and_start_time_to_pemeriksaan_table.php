<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pemeriksaan', function (Blueprint $table): void {
            $table->timestamp('started_at')->nullable();
            $table->text('riwayat_penyakit_sekarang')->nullable();
            $table->text('riwayat_penyakit_dahulu')->nullable();
            $table->text('riwayat_penyakit_keluarga')->nullable();
            $table->text('riwayat_alergi')->nullable();
            $table->json('pemeriksaan_fisik_terstruktur')->nullable();
            $table->text('pemeriksaan_ekstraoral')->nullable();
            $table->decimal('oral_hygiene_index', 4, 2)->nullable();
            $table->text('diagnosis_banding')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pemeriksaan', function (Blueprint $table): void {
            $table->dropColumn([
                'started_at', 'riwayat_penyakit_sekarang', 'riwayat_penyakit_dahulu',
                'riwayat_penyakit_keluarga', 'riwayat_alergi', 'pemeriksaan_fisik_terstruktur',
                'pemeriksaan_ekstraoral', 'oral_hygiene_index', 'diagnosis_banding',
            ]);
        });
    }
};
