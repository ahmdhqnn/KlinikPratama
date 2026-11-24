<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Native ALTER preserves the existing clinical retention triggers on this table.
            DB::statement('ALTER TABLE tindakan_kunjungan ADD COLUMN doctor_started_at DATETIME');
            DB::statement('ALTER TABLE tindakan_kunjungan ADD COLUMN doctor_start_consent_id INTEGER REFERENCES informed_consent(id) ON DELETE RESTRICT');

            return;
        }

        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            $table->timestamp('doctor_started_at')->nullable();
            $table->foreignId('doctor_start_consent_id')->nullable()->constrained('informed_consent')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE tindakan_kunjungan DROP COLUMN doctor_start_consent_id');
            DB::statement('ALTER TABLE tindakan_kunjungan DROP COLUMN doctor_started_at');

            return;
        }

        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('doctor_start_consent_id');
            $table->dropColumn('doctor_started_at');
        });
    }
};
