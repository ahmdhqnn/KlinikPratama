<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new RuntimeException('Proteksi RME perlu disiapkan untuk engine database ini.');
        }
        if ($driver === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION reject_clinical_mutation() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'Catatan klinis dilindungi'; END; \$\$");
        }
        $immutable = ['clinical_audit_events', 'clinical_note_versions', 'screening_versions'];
        $retained = ['pasien', 'kunjungan', 'screening', 'pemeriksaan', 'resep', 'farmasi', 'farmasi_mandiris', 'surat_medis', 'informed_consent'];
        foreach (array_unique([...$retained, ...$immutable]) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (in_array($table, $immutable, true) ? ['delete', 'update'] : ['delete'] as $operation) {
                $name = 'protect_'.$table.'_'.$operation;
                $sql = match ($driver) {
                    'sqlite' => "CREATE TRIGGER $name BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END",
                    'mysql' => "CREATE TRIGGER $name BEFORE $operation ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Catatan klinis dilindungi'",
                    'pgsql' => "CREATE TRIGGER $name BEFORE $operation ON $table FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()",
                };
                DB::unprepared($sql);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Proteksi retensi tidak dilepas melalui rollback aplikasi; gunakan perubahan terkontrol oleh pengelola database.');
    }
};
