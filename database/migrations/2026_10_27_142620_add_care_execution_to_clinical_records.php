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
        Schema::table('pemeriksaan', function (Blueprint $table): void {
            $table->text('non_farmakologis')->nullable();
        });
        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            $table->string('execution_role')->default('dokter');
            $table->string('execution_status')->default('recorded');
            $table->foreignId('performed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('performed_at')->nullable();
            $table->text('execution_result')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('performed_by');
            $table->dropColumn(['execution_role', 'execution_status', 'performed_at', 'execution_result']);
        });
        Schema::table('pemeriksaan', fn (Blueprint $table) => $table->dropColumn('non_farmakologis'));
    }
};
