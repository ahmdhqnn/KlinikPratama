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
        Schema::table('clinical_audit_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('membership_id')->nullable()->index();
            $table->json('metadata')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinical_audit_events', function (Blueprint $table): void {
            $table->dropColumn(['membership_id', 'metadata']);
        });
    }
};
