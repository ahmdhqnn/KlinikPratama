<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemeriksaan', function (Blueprint $table): void {
            $table->json('body_map')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pemeriksaan', function (Blueprint $table): void {
            $table->dropColumn('body_map');
        });
    }
};
