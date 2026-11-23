<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false);
            $table->unsignedInteger('session_version')->default(0);
        });
        Schema::table('kunjungan', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
        });
        Schema::table('clinical_audit_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable()->change();
            $table->string('actor_role', 30)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->string('outcome', 30)->default('success');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Gunakan forward fix agar bukti audit dan pembatalan tidak hilang.');
    }
};
