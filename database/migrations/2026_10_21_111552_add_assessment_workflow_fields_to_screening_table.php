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
            $table->text('keluhan_tambahan')->nullable();
            $table->string('lama_keluhan')->nullable();
            $table->text('riwayat_obat')->nullable();
            $table->string('status_alergi')->nullable();
            $table->string('status_riwayat_penyakit')->nullable();
            $table->string('sumber_informasi')->nullable();
            $table->text('alasan_pengukuran_tidak_lengkap')->nullable();
            $table->string('triase_perawat')->nullable();
            $table->text('catatan_triase')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screening', function (Blueprint $table): void {
            $table->dropColumn([
                'keluhan_tambahan', 'lama_keluhan', 'riwayat_obat', 'status_alergi',
                'status_riwayat_penyakit', 'sumber_informasi', 'alasan_pengukuran_tidak_lengkap',
                'triase_perawat', 'catatan_triase', 'started_at', 'submitted_at',
            ]);
        });
    }
};
