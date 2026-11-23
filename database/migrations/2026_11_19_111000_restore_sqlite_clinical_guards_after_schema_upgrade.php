<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }
        // SQLite ALTERs that rebuild a table can discard its triggers. Reassert the complete
        // guard set after the consent FK upgrade, including guards that remain intact.
        $immutable = ['clinical_audit_events', 'clinical_note_versions', 'screening_versions'];
        $retained = ['pasien', 'kunjungan', 'screening', 'pemeriksaan', 'resep', 'farmasi', 'farmasi_mandiris', 'surat_medis', 'informed_consent', 'diagnosa', 'resep_obat', 'tindakan_kunjungan', 'farmasi_item', 'odontogram_findings'];
        foreach (array_unique([...$retained, ...$immutable]) as $table) {
            foreach (in_array($table, $immutable, true) ? ['delete', 'update'] : ['delete'] as $operation) {
                $name = 'protect_'.$table.'_'.$operation;
                DB::unprepared("CREATE TRIGGER IF NOT EXISTS $name BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END");
            }
        }
        foreach (['diagnosa', 'resep_obat', 'tindakan_kunjungan', 'farmasi_item', 'odontogram_findings'] as $table) {
            $name = 'protect_'.$table.'_voided_update';
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS $name BEFORE UPDATE ON $table WHEN OLD.voided_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Entri klinis yang dibatalkan dilindungi'); END");
        }
        foreach (['update', 'delete'] as $operation) {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_general_consents_$operation BEFORE $operation ON general_consents BEGIN SELECT RAISE(ABORT, 'Bukti consent dilindungi'); END");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Proteksi bukti klinis tidak dinonaktifkan melalui rollback.');
    }
};
