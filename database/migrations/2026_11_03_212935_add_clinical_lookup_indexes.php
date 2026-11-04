<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const LOOKUPS = [
        'diagnosa' => 'pemeriksaan_id',
        'tindakan_kunjungan' => 'kunjungan_id',
        'resep_obat' => 'resep_id',
        'farmasi_item' => 'farmasi_id',
    ];

    public function up(): void
    {
        foreach (self::LOOKUPS as $tableName => $column) {
            if (! Schema::hasIndex($tableName, [$column])) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName, $column): void {
                    $table->index($column, $tableName.'_clinical_lookup_index');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::LOOKUPS as $tableName => $column) {
            $index = $tableName.'_clinical_lookup_index';

            if (Schema::hasIndex($tableName, $index)) {
                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }
    }
};
