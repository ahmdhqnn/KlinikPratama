<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            $table->foreignId('tindakan_id')->nullable()->change();
            $table->string('nama_tindakan_manual')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('tindakan_kunjungan')->whereNotNull('nama_tindakan_manual')->exists()) {
            throw new RuntimeException('Hapus atau pindahkan catatan tindakan manual sebelum membatalkan migrasi.');
        }

        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            $table->dropColumn('nama_tindakan_manual');
            $table->foreignId('tindakan_id')->nullable(false)->change();
        });
    }
};
