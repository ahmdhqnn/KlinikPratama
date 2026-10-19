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
        Schema::table('kepesertaan', function (Blueprint $table): void {
            $table->dropColumn(['berlaku_mulai', 'berlaku_sampai']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kepesertaan', function (Blueprint $table): void {
            $table->date('berlaku_mulai')->default('1970-01-01');
            $table->date('berlaku_sampai')->nullable();
        });
    }
};
