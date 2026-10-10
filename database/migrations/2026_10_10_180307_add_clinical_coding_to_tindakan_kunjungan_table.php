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
        Schema::table('tindakan_kunjungan', function (Blueprint $table) {
            $table->text('nama_tindakan')->nullable();
            $table->string('kode_icd9cm', 100)->nullable();
            $table->string('code_system', 100)->nullable();
            $table->string('code_release', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tindakan_kunjungan', function (Blueprint $table) {
            $table->dropColumn(['nama_tindakan', 'kode_icd9cm', 'code_system', 'code_release']);
        });
    }
};
