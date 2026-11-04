<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_database_has_no_broken_foreign_keys(): void
    {
        $this->artisan('clinic:audit-database')
            ->expectsOutputToContain('Tidak ditemukan pelanggaran foreign key.')
            ->assertSuccessful();
    }

    public function test_audit_reports_orphans_without_deleting_them(): void
    {
        Schema::create('audit_parents', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('audit_children', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('audit_parents');
        });
        DB::statement('PRAGMA defer_foreign_keys = ON');

        try {
            DB::table('audit_children')->insert([['parent_id' => 999], ['parent_id' => null]]);

            $this->artisan('clinic:audit-database')
                ->expectsOutputToContain('Ditemukan 1 pelanggaran foreign key.')
                ->assertFailed();

            $this->assertDatabaseCount('audit_children', 2);
        } finally {
            Schema::dropIfExists('audit_children');
            Schema::dropIfExists('audit_parents');
            DB::statement('PRAGMA defer_foreign_keys = OFF');
        }
    }

    public function test_membership_audit_context_can_be_rolled_back_and_reapplied(): void
    {
        $migration = require database_path('migrations/2026_10_08_160020_add_membership_audit_context.php');

        $migration->down();

        $this->assertFalse(Schema::hasColumn('clinical_audit_events', 'membership_id'));
        $this->assertFalse(Schema::hasColumn('clinical_audit_events', 'metadata'));

        $migration->up();

        $this->assertTrue(Schema::hasIndex('clinical_audit_events', ['membership_id']));
        $this->assertTrue(Schema::hasColumn('clinical_audit_events', 'metadata'));
    }

    public function test_clinical_relationship_lookups_have_indexes(): void
    {
        foreach (['diagnosa' => 'pemeriksaan_id', 'tindakan_kunjungan' => 'kunjungan_id', 'resep_obat' => 'resep_id', 'farmasi_item' => 'farmasi_id'] as $table => $column) {
            $this->assertTrue(Schema::hasIndex($table, [$column]), $table.'.'.$column);
        }
    }
}
