<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            $table->boolean('consent_review_required')->default(false);
        });
        Schema::table('informed_consent', function (Blueprint $table): void {
            $table->foreignId('tindakan_kunjungan_id')->nullable()->constrained('tindakan_kunjungan')->restrictOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('informed_consent')->restrictOnDelete();
            $table->uuid('request_id')->nullable()->unique();
            $table->char('request_hash', 64)->nullable();
            $table->string('decision', 30)->nullable();
            $table->string('text_version', 60)->nullable();
            $table->json('procedure_snapshot')->nullable();
            $table->char('plan_hash', 64)->nullable();
            $table->json('explanation')->nullable();
            $table->text('representative_verification')->nullable();
            $table->string('witness_name', 150)->nullable();
            $table->text('reason')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name', 200)->nullable();
            $table->char('evidence_sha256', 64)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->index(['tindakan_kunjungan_id', 'id'], 'informed_consent_procedure_history_index');
        });
        $driver = DB::connection()->getDriverName();
        // Reassert SQLite guards after FK rebuild. Existing signed evidence stays immutable.
        foreach (['delete', 'update'] as $operation) {
            $name = 'protect_informed_consent_'.$operation;
            if ($driver === 'sqlite') {
                DB::unprepared("CREATE TRIGGER IF NOT EXISTS $name BEFORE $operation ON informed_consent BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END");
            } elseif ($operation === 'update') {
                DB::unprepared(match ($driver) {
                    'mysql' => "CREATE TRIGGER $name BEFORE UPDATE ON informed_consent FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Catatan klinis dilindungi'",
                    'pgsql' => "CREATE TRIGGER $name BEFORE UPDATE ON informed_consent FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()",
                    default => throw new RuntimeException('Proteksi consent belum tersedia untuk engine ini.'),
                });
            }
        }
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_tindakan_kunjungan_delete BEFORE DELETE ON tindakan_kunjungan BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END");
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_tindakan_kunjungan_voided_update BEFORE UPDATE ON tindakan_kunjungan WHEN OLD.voided_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Entri klinis yang dibatalkan dilindungi'); END");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Bukti informed consent dipertahankan; gunakan forward fix.');
    }
};
