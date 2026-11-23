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
        Schema::create('pharmacy_direct_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmasi_mandiri_id')->unique()->constrained('farmasi_mandiris')->restrictOnDelete();
            $table->foreignId('credential_id')->constrained('pharmacy_staff_credentials')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('data');
            $table->timestamp('recorded_at');
        });
        foreach (['update', 'delete'] as $operation) {
            $name = 'protect_pharmacy_direct_reviews_'.$operation;
            DB::unprepared(match (DB::connection()->getDriverName()) {
                'sqlite' => "CREATE TRIGGER $name BEFORE $operation ON pharmacy_direct_reviews BEGIN SELECT RAISE(ABORT, 'Review farmasi langsung dilindungi'); END",
                'mysql' => "CREATE TRIGGER $name BEFORE $operation ON pharmacy_direct_reviews FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Review farmasi langsung dilindungi'",
                'pgsql' => "CREATE TRIGGER $name BEFORE $operation ON pharmacy_direct_reviews FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()",
                default => throw new RuntimeException('Engine review farmasi langsung belum didukung.'),
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Review farmasi langsung dipertahankan; gunakan forward fix.');
    }
};
