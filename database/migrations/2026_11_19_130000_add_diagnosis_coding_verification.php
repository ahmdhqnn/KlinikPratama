<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosa', function (Blueprint $table): void {
            $table->string('coding_status', 30)->default('legacy')->index();
            $table->text('coding_unresolved_reason')->nullable();
            $table->foreignId('coding_verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('coding_verified_at')->nullable();
        });
        Schema::table('pemeriksaan', function (Blueprint $table): void {
            $table->string('coding_status', 30)->default('legacy')->index();
            $table->text('coding_unresolved_reason')->nullable();
            $table->timestamp('coding_review_due_at')->nullable();
        });

        // Arsip bertanda tangan tetap legacy/read-only. Draf yang sudah ada harus
        // ditinjau ulang; migration tidak mengarang verifikasi katalog atau actor.
        $draftExaminations = DB::table('pemeriksaan')->whereNull('signed_at')->pluck('id');
        if ($draftExaminations->isNotEmpty()) {
            DB::table('pemeriksaan')->whereIn('id', $draftExaminations)->update([
                'coding_status' => 'unresolved',
                'coding_unresolved_reason' => 'Draf dibuat sebelum verifikasi terminologi diaktifkan.',
            ]);
            DB::table('diagnosa')->whereIn('pemeriksaan_id', $draftExaminations)->whereNull('voided_at')->update([
                'coding_status' => 'unresolved',
                'coding_unresolved_reason' => 'Diagnosis perlu dipilih ulang dari katalog ICD-10 WHO yang aktif.',
            ]);
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            foreach (['diagnosa', 'pemeriksaan'] as $table) {
                DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_{$table}_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END");
            }
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_diagnosa_voided_update BEFORE UPDATE ON diagnosa WHEN OLD.voided_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Entri klinis yang dibatalkan dilindungi'); END");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Status verifikasi coding merupakan bagian catatan final; gunakan forward fix.');
    }
};
