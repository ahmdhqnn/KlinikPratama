<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pasien_id')->constrained('pasien')->restrictOnDelete();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->restrictOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('general_consents')->restrictOnDelete();
            $table->uuid('request_id')->unique();
            $table->char('request_hash', 64);
            $table->string('decision', 20);
            $table->string('text_version', 60);
            $table->text('text_snapshot');
            $table->string('signer_name', 150);
            $table->string('signer_relationship', 40);
            $table->text('representative_verification')->nullable();
            $table->text('reason')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name', 200)->nullable();
            $table->string('evidence_mime', 60)->nullable();
            $table->char('evidence_sha256', 64)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->index(['pasien_id', 'id']);
        });
        Schema::table('kunjungan', function (Blueprint $table): void {
            // Existing visits stay explicitly legacy; no consent is inferred or manufactured.
            $table->boolean('general_consent_required')->default(false);
            $table->foreignId('general_consent_id')->nullable()->constrained('general_consents')->restrictOnDelete();
        });
        foreach (['update', 'delete'] as $operation) {
            $trigger = 'protect_general_consents_'.$operation;
            DB::unprepared(match (DB::connection()->getDriverName()) {
                'sqlite' => "CREATE TRIGGER $trigger BEFORE $operation ON general_consents BEGIN SELECT RAISE(ABORT, 'Bukti consent dilindungi'); END",
                'mysql' => "CREATE TRIGGER $trigger BEFORE $operation ON general_consents FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bukti consent dilindungi'",
                'pgsql' => "CREATE TRIGGER $trigger BEFORE $operation ON general_consents FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()",
                default => throw new RuntimeException('Proteksi consent belum tersedia untuk engine ini.'),
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Bukti persetujuan dipertahankan; gunakan forward fix.');
    }
};
