<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosa', function (Blueprint $table): void {
            $table->string('kode_icd10')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('diagnosa')->whereNull('kode_icd10')->exists()) {
            throw new RuntimeException('Diagnosis manual masih tersimpan; kolom kode tidak dapat diwajibkan kembali.');
        }
        Schema::table('diagnosa', function (Blueprint $table): void {
            $table->string('kode_icd10')->nullable(false)->change();
        });
    }
};
