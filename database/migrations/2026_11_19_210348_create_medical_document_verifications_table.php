<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medical_document_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_medis_id')->unique()->constrained('surat_medis')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->text('token');
            $table->char('content_hash', 64);
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
        });
        Schema::create('medical_document_verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_id')->constrained('medical_document_verifications')->restrictOnDelete();
            $table->string('action', 30);
            $table->text('reason');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
        });
        foreach (['medical_document_verifications', 'medical_document_verification_events'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                $name = 'protect_'.$table.'_'.$operation;
                DB::unprepared(match (DB::connection()->getDriverName()) {
                    'sqlite' => "CREATE TRIGGER $name BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT, 'Bukti dokumen dilindungi'); END",
                    'mysql' => "CREATE TRIGGER $name BEFORE $operation ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bukti dokumen dilindungi'",
                    'pgsql' => "CREATE TRIGGER $name BEFORE $operation ON $table FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()",
                    default => throw new RuntimeException('Engine verifikasi dokumen belum didukung.'),
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Bukti penerbitan dokumen dipertahankan; gunakan forward fix.');
    }
};
