<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening', function (Blueprint $table): void {
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('screening', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recorded_by_user_id');
        });
    }
};
