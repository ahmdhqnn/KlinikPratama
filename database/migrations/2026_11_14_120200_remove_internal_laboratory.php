<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['lab_hasil_detail', 'lab_hasil', 'lab_bhp', 'lab_indikator', 'laboratorium'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException("Arsipkan dan verifikasi data {$table} sebelum penghapusan skema laboratorium.");
            }
        }

        foreach (self::TABLES as $table) {
            Schema::drop($table);
        }
    }

    public function down(): void
    {
        foreach ([
            '2026_10_05_162842_create_laboratorium_table.php',
            '2026_10_05_162845_create_lab_indikator_table.php',
            '2026_10_05_162846_create_lab_bhp_table.php',
            '2026_10_05_162856_create_lab_hasil_table.php',
            '2026_10_05_162901_create_lab_hasil_detail_table.php',
        ] as $migration) {
            (require __DIR__.'/'.$migration)->up();
        }

        Schema::table('laboratorium', function (Blueprint $table): void {
            $table->dropColumn('tarif');
        });
    }
};
