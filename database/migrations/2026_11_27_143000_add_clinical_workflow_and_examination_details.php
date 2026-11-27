<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('klinik_settings') && ! Schema::hasColumn('klinik_settings', 'clinical_workflow')) {
            Schema::table('klinik_settings', function (Blueprint $table): void {
                $table->json('clinical_workflow')->nullable()->after('pelaksana_ttv');
            });
        }

        if (Schema::hasTable('pemeriksaan') && ! Schema::hasColumn('pemeriksaan', 'clinical_assessment')) {
            Schema::table('pemeriksaan', function (Blueprint $table): void {
                $table->json('clinical_assessment')->nullable()->after('review_of_systems');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pemeriksaan') && Schema::hasColumn('pemeriksaan', 'clinical_assessment')) {
            Schema::table('pemeriksaan', function (Blueprint $table): void {
                $table->dropColumn('clinical_assessment');
            });
        }

        if (Schema::hasTable('klinik_settings') && Schema::hasColumn('klinik_settings', 'clinical_workflow')) {
            Schema::table('klinik_settings', function (Blueprint $table): void {
                $table->dropColumn('clinical_workflow');
            });
        }
    }
};
