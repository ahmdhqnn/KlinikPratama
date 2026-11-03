<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['screening', 'pemeriksaan'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->text('riwayat_perawatan_gigi')->nullable();
                $table->text('kebiasaan_gigi')->nullable();
                if ($tableName === 'pemeriksaan') {
                    $table->text('riwayat_obat')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('pemeriksaan', function (Blueprint $table): void {
            $table->dropColumn(['riwayat_obat', 'riwayat_perawatan_gigi', 'kebiasaan_gigi']);
        });
        Schema::table('screening', function (Blueprint $table): void {
            $table->dropColumn(['riwayat_perawatan_gigi', 'kebiasaan_gigi']);
        });
    }
};
