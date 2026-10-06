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
        Schema::table('screening', function (Blueprint $table) {
            Schema::table('screening', function (Blueprint $table): void {
                $table->decimal('lingkar_perut', 5, 1)->nullable()->after('tinggi_badan');
                $table->string('fungsi_penciuman')->nullable()->after('respirasi');
                $table->string('tingkat_kesadaran')->nullable()->after('fungsi_penciuman');
                $table->string('alergi_jenis')->nullable()->after('riwayat_alergi');
                $table->text('alergi_reaksi')->nullable()->after('alergi_jenis');
                $table->string('penyakit_nama')->nullable()->after('riwayat_penyakit');
                $table->text('penyakit_keterangan')->nullable()->after('penyakit_nama');
                $table->string('nyeri_dada')->nullable()->after('pemeriksaan_fisik');
                $table->string('kondisi_psikiatri')->nullable()->after('nyeri_dada');
                $table->string('nadi_teraba')->nullable()->after('kondisi_psikiatri');
                $table->string('kejang')->nullable()->after('nadi_teraba');
                $table->string('pola_pernapasan')->nullable()->after('kejang');
                $table->string('kesadaran')->nullable()->after('pola_pernapasan');
                $table->string('risiko_jatuh_visual')->nullable()->after('kesadaran');
                $table->string('kesimpulan_triase')->nullable()->after('risiko_jatuh_visual');
                $table->string('prioritas_layanan')->nullable()->after('kesimpulan_triase');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screening', function (Blueprint $table) {
            Schema::table('screening', function (Blueprint $table): void {
                $table->dropColumn([
                    'lingkar_perut', 'fungsi_penciuman', 'tingkat_kesadaran',
                    'alergi_jenis', 'alergi_reaksi', 'penyakit_nama', 'penyakit_keterangan',
                    'nyeri_dada', 'kondisi_psikiatri', 'nadi_teraba', 'kejang',
                    'pola_pernapasan', 'kesadaran', 'risiko_jatuh_visual',
                    'kesimpulan_triase', 'prioritas_layanan',
                ]);
            });
        });
    }
};
