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
        Schema::table('pasien', function (Blueprint $table) {
            $table->string('tempat_lahir')->nullable()->after('nama');
            $table->string('nama_ibu')->nullable()->after('nama_wali');
            $table->string('rt', 5)->nullable()->after('alamat');
            $table->string('rw', 5)->nullable()->after('rt');
            $table->string('kelurahan')->nullable()->after('rw');
            $table->string('kecamatan')->nullable()->after('kelurahan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pasien', function (Blueprint $table) {
            $table->dropColumn(['tempat_lahir', 'nama_ibu', 'rt', 'rw', 'kelurahan', 'kecamatan']);
        });
    }
};
