<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odontogram_findings', function (Blueprint $table): void {
            $table->timestamp('voided_at')->nullable()->index();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->text('void_reason')->nullable();
            // NULL permits historical revisions, while 1 reserves a single active surface.
            $table->unsignedTinyInteger('active_slot')->nullable()->default(1);
            $table->dropUnique(['kunjungan_id', 'tooth_fdi', 'surface']);
            $table->unique(['kunjungan_id', 'tooth_fdi', 'surface', 'active_slot'], 'odontogram_active_surface_unique');
        });
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION reject_voided_clinical_update() RETURNS trigger AS $$ BEGIN IF OLD.voided_at IS NOT NULL THEN RAISE EXCEPTION 'Entri klinis yang dibatalkan dilindungi'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
        }
        foreach (['diagnosa', 'resep_obat', 'tindakan_kunjungan', 'farmasi_item', 'odontogram_findings'] as $name) {
            $trigger = 'protect_'.$name.'_voided_update';
            DB::unprepared(match ($driver) {
                'sqlite' => "CREATE TRIGGER $trigger BEFORE UPDATE ON $name WHEN OLD.voided_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Entri klinis yang dibatalkan dilindungi'); END",
                'mysql' => "CREATE TRIGGER $trigger BEFORE UPDATE ON $name FOR EACH ROW BEGIN IF OLD.voided_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Entri klinis yang dibatalkan dilindungi'; END IF; END",
                'pgsql' => "CREATE TRIGGER $trigger BEFORE UPDATE ON $name FOR EACH ROW EXECUTE FUNCTION reject_voided_clinical_update()",
                default => throw new RuntimeException('Proteksi arsip belum tersedia untuk engine ini.'),
            });
        }
        DB::unprepared(match ($driver) {
            'sqlite' => "CREATE TRIGGER protect_odontogram_findings_delete BEFORE DELETE ON odontogram_findings BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END",
            'mysql' => "CREATE TRIGGER protect_odontogram_findings_delete BEFORE DELETE ON odontogram_findings FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Catatan klinis dilindungi'",
            'pgsql' => 'CREATE TRIGGER protect_odontogram_findings_delete BEFORE DELETE ON odontogram_findings FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()',
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Riwayat odontogram tidak dihapus melalui rollback; gunakan forward fix.');
    }
};
