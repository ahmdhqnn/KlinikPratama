<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_signature_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('procedure_id')->constrained('tindakan_kunjungan')->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('witness_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('consent_id')->nullable()->unique()->constrained('informed_consent')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->text('credentials')->nullable();
            $table->longText('snapshot');
            $table->longText('signature')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Riwayat sesi persetujuan dipertahankan; gunakan forward fix.');
    }
};
