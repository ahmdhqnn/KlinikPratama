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
            $table->string('status_riwayat_obat')->nullable();
            $table->json('mukosa_mulut')->nullable();
            $table->text('kelainan_mukosa')->nullable();
            $table->json('kebiasaan_gigi_opsi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('screening', function (Blueprint $table): void {
            $table->dropColumn(['status_riwayat_obat', 'mukosa_mulut', 'kelainan_mukosa', 'kebiasaan_gigi_opsi']);
        });
    }
};
