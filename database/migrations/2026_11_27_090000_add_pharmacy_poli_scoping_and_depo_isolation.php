<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmasi_mandiris', function (Blueprint $table): void {
            if (! Schema::hasColumn('farmasi_mandiris', 'poliklinik_id')) {
                $table->foreignId('poliklinik_id')
                    ->nullable()
                    ->after('pasien_id')
                    ->constrained('poliklinik')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('farmasi_mandiris', 'depo_id')) {
                $table->foreignId('depo_id')
                    ->nullable()
                    ->after('poliklinik_id')
                    ->constrained('depo_obat')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasIndex('farmasi_mandiris', 'farmasi_mandiris_poli_date_idx')) {
            Schema::table('farmasi_mandiris', function (Blueprint $table): void {
                $table->index(['poliklinik_id', 'diserahkan_pada'], 'farmasi_mandiris_poli_date_idx');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_farmasi_mandiris_delete BEFORE DELETE ON farmasi_mandiris BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END");
        }

        Schema::table('stok_obat', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_obat', 'stok_minimum_poli')) {
                $table->unsignedInteger('stok_minimum_poli')->default(0)->after('stok');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('stok_obat', 'stok_minimum_poli')) {
            Schema::table('stok_obat', function (Blueprint $table): void {
                $table->dropColumn('stok_minimum_poli');
            });
        }

        if (Schema::hasIndex('farmasi_mandiris', 'farmasi_mandiris_poli_date_idx')) {
            Schema::table('farmasi_mandiris', function (Blueprint $table): void {
                $table->dropIndex('farmasi_mandiris_poli_date_idx');
            });
        }

        Schema::table('farmasi_mandiris', function (Blueprint $table): void {
            if (Schema::hasColumn('farmasi_mandiris', 'depo_id')) {
                $table->dropConstrainedForeignId('depo_id');
            }
            if (Schema::hasColumn('farmasi_mandiris', 'poliklinik_id')) {
                $table->dropConstrainedForeignId('poliklinik_id');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_farmasi_mandiris_delete BEFORE DELETE ON farmasi_mandiris BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END");
        }
    }
};
