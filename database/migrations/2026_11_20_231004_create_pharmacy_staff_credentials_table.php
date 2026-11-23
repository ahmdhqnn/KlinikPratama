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
        Schema::create('pharmacy_staff_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nakes_id')->constrained('nakes')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('qualification', 20);
            $table->text('data');
            $table->timestamp('recorded_at');
        });
        foreach (['update', 'delete'] as $operation) {
            $name = 'protect_pharmacy_credentials_'.$operation;
            DB::unprepared(match (DB::connection()->getDriverName()) {
                'sqlite' => "CREATE TRIGGER $name BEFORE $operation ON pharmacy_staff_credentials BEGIN SELECT RAISE(ABORT, 'Bukti kualifikasi dilindungi'); END",
                'mysql' => "CREATE TRIGGER $name BEFORE $operation ON pharmacy_staff_credentials FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bukti kualifikasi dilindungi'",
                'pgsql' => "CREATE TRIGGER $name BEFORE $operation ON pharmacy_staff_credentials FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()",
                default => throw new RuntimeException('Engine kualifikasi farmasi belum didukung.'),
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Bukti kualifikasi dipertahankan; gunakan forward fix.');
    }
};
