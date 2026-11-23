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
        Schema::create('medical_information_release_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->restrictOnDelete();
            $table->uuid('request_id')->index();
            $table->unsignedInteger('version');
            $table->string('state', 20);
            $table->text('data');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->unique(['request_id', 'version']);
        });
        foreach (['update', 'delete'] as $operation) {
            $name = 'protect_information_release_'.$operation;
            DB::unprepared(match (DB::connection()->getDriverName()) {
                'sqlite' => "CREATE TRIGGER $name BEFORE $operation ON medical_information_release_events BEGIN SELECT RAISE(ABORT, 'Register pelepasan dilindungi'); END",
                'mysql' => "CREATE TRIGGER $name BEFORE $operation ON medical_information_release_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Register pelepasan dilindungi'",
                'pgsql' => "CREATE TRIGGER $name BEFORE $operation ON medical_information_release_events FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()",
                default => throw new RuntimeException('Engine register pelepasan belum didukung.'),
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Register pelepasan dipertahankan; gunakan forward fix.');
    }
};
