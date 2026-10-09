<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_dokter', function (Blueprint $table): void {
            $table->date('berlaku_mulai')->nullable();
            $table->date('berlaku_sampai')->nullable();
            $table->dropUnique(['dokter_id', 'poliklinik_id', 'hari']);
        });
        DB::table('jadwal_dokter')->whereNull('berlaku_mulai')->update(['berlaku_mulai' => '1970-01-01']);
        Schema::table('jadwal_dokter', function (Blueprint $table): void {
            $table->unique(['dokter_id', 'poliklinik_id', 'hari', 'berlaku_mulai'], 'jadwal_dokter_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_dokter', function (Blueprint $table): void {
            $table->dropUnique('jadwal_dokter_period_unique');
            $table->unique(['dokter_id', 'poliklinik_id', 'hari']);
            $table->dropColumn(['berlaku_mulai', 'berlaku_sampai']);
        });
    }
};
