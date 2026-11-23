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
        Schema::table('tindakan_kunjungan', function (Blueprint $table) {
            $table->string('coding_status', 30)->default('legacy');
            $table->string('coding_display')->nullable();
            $table->text('coding_review_reason')->nullable();
            $table->foreignId('coding_reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('coding_reviewed_at')->nullable();
        });
        DB::table('tindakan_kunjungan')->whereNull('voided_at')->whereNull('performed_at')
            ->where('consent_review_required', true)->whereIn('kunjungan_id', DB::table('pemeriksaan')->whereNull('signed_at')->select('kunjungan_id'))
            ->update(['coding_status' => 'unresolved']);
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_tindakan_kunjungan_delete BEFORE DELETE ON tindakan_kunjungan BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END");
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_tindakan_kunjungan_voided_update BEFORE UPDATE ON tindakan_kunjungan WHEN OLD.voided_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Entri klinis yang dibatalkan dilindungi'); END");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Review coding merupakan bukti klinis; gunakan forward fix.');
    }
};
