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
        Schema::table('klinik_settings', function (Blueprint $table) {
            Schema::table('klinik_settings', function (Blueprint $table): void {
                $table->string('pelaksana_ttv')->default('perawat')->after('website');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('klinik_settings', function (Blueprint $table) {
            Schema::table('klinik_settings', function (Blueprint $table): void {
                $table->dropColumn('pelaksana_ttv');
            });
        });
    }
};
