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
        Schema::table('diagnosa', function (Blueprint $table): void {
            $table->string('code_system', 100)->default('icd10_who')->after('kode_icd10');
            $table->string('code_release', 100)->nullable()->after('code_system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagnosa', function (Blueprint $table): void {
            $table->dropColumn(['code_system', 'code_release']);
        });
    }
};
