<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening', function (Blueprint $table): void {
            $table->boolean('konfirmasi_riwayat')->default(false)->after('status_riwayat_penyakit');
        });
    }

    public function down(): void
    {
        Schema::table('screening', function (Blueprint $table): void {
            $table->dropColumn('konfirmasi_riwayat');
        });
    }
};
