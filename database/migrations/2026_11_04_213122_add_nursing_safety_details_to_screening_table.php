<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['alergi_keparahan', 'alergi_verifikasi', 'nyeri_karakter', 'nyeri_pereda', 'nyeri_dampak', 'riwayat_kondisi_khusus', 'observasi_mulut', 'kebiasaan_perawatan_mulut'] as $field) {
            if (! Schema::hasColumn('screening', $field)) {
                Schema::table('screening', function (Blueprint $table) use ($field): void {
                    $table->text($field)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Migration pemulihan mempertahankan kolom klinis yang sudah ada. Gunakan backup untuk pemulihan schema.');
    }
};
