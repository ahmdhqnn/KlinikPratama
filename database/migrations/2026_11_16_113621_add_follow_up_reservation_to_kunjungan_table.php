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
        Schema::table('kunjungan', function (Blueprint $table) {
            $table->foreignId('kontrol_asal_id')->nullable()->unique()->constrained('kunjungan')->nullOnDelete();
            $table->boolean('kontrol_pending')->default(false);
            $table->text('kontrol_catatan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kunjungan', function (Blueprint $table) {
            $table->dropUnique(['kontrol_asal_id']);
            $table->dropConstrainedForeignId('kontrol_asal_id');
            $table->dropColumn(['kontrol_pending', 'kontrol_catatan']);
        });
    }
};
