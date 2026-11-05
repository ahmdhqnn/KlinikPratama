<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['dental_red_flags_other', 'risiko_medis_gigi_lainnya', 'pemicu_nyeri_gigi_lainnya', 'riwayat_infeksi_gigi_lainnya', 'dental_medication_categories_other'] as $field) {
            if (! Schema::hasColumn('screening', $field)) {
                Schema::table('screening', function (Blueprint $table) use ($field): void {
                    $table->text($field)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('screening', function (Blueprint $table): void {
            $table->dropColumn(['dental_red_flags_other', 'risiko_medis_gigi_lainnya', 'pemicu_nyeri_gigi_lainnya', 'riwayat_infeksi_gigi_lainnya', 'dental_medication_categories_other']);
        });
    }
};
