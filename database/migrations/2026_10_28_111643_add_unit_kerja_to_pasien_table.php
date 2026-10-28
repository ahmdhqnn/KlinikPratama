<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pasien', function (Blueprint $table): void {
            $table->string('unit_kerja', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pasien', function (Blueprint $table): void {
            $table->dropColumn('unit_kerja');
        });
    }
};
