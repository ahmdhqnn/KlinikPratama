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
        Schema::table('screening', function (Blueprint $table): void {
            $table->text('riwayat_penyakit_keluarga')->nullable();
            $table->unsignedTinyInteger('skala_nyeri')->nullable();
            $table->string('lokasi_nyeri_gigi')->nullable();
            $table->json('pemicu_nyeri_gigi')->nullable();
            $table->string('durasi_keluhan_gigi')->nullable();
            $table->json('risiko_medis_gigi')->nullable();
            $table->json('riwayat_infeksi_gigi')->nullable();
            $table->text('catatan_medis_gigi')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screening', function (Blueprint $table): void {
            $table->dropColumn([
                'riwayat_penyakit_keluarga', 'skala_nyeri', 'lokasi_nyeri_gigi',
                'pemicu_nyeri_gigi', 'durasi_keluhan_gigi', 'risiko_medis_gigi',
                'riwayat_infeksi_gigi', 'catatan_medis_gigi',
            ]);
        });
    }
};
