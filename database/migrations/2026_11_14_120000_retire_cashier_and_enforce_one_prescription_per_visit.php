<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicatePrescriptions = DB::table('resep')
            ->select('kunjungan_id')
            ->groupBy('kunjungan_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicatePrescriptions) {
            throw new RuntimeException('Rekonsiliasi resep ganda per kunjungan sebelum menerapkan batas unik.');
        }

        DB::table('kunjungan')->where('status', 'kasir')->update(['status' => 'selesai']);

        Schema::table('resep', function (Blueprint $table): void {
            $table->unique('kunjungan_id');
        });
    }

    public function down(): void
    {
        Schema::table('resep', function (Blueprint $table): void {
            $table->dropUnique(['kunjungan_id']);
        });
    }
};
