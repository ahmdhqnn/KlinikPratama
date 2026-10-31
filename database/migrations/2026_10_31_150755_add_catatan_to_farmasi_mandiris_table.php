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
        Schema::table('farmasi_mandiris', function (Blueprint $table): void {
            $table->text('catatan')->nullable()->after('dasar_pemberian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farmasi_mandiris', function (Blueprint $table): void {
            $table->dropColumn('catatan');
        });
    }
};
