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
        Schema::table('screening', function (Blueprint $table): void {
            $table->string('extraoral_symmetry')->nullable();
            $table->string('extraoral_swelling')->nullable();
            $table->text('extraoral_notes')->nullable();
            $table->json('dental_red_flags')->nullable();
        });
        Schema::table('odontogram_findings', function (Blueprint $table): void {
            $table->json('details')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('odontogram_findings', function (Blueprint $table): void {
            $table->dropColumn('details');
        });
        Schema::table('screening', function (Blueprint $table): void {
            $table->dropColumn(['extraoral_symmetry', 'extraoral_swelling', 'extraoral_notes', 'dental_red_flags']);
        });
    }
};
