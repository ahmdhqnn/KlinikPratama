<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Add the reference in place so existing visit constraints and triggers survive.
            DB::statement('ALTER TABLE kunjungan ADD COLUMN registered_by INTEGER REFERENCES users(id) ON DELETE SET NULL');
        } else {
            Schema::table('kunjungan', function (Blueprint $table) {
                $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        Schema::table('kunjungan', function (Blueprint $table) {
            $table->index('registered_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('kunjungan', function (Blueprint $table) {
                $table->dropIndex(['registered_by']);
            });
            DB::statement('ALTER TABLE kunjungan DROP COLUMN registered_by');

            return;
        }

        Schema::table('kunjungan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_by');
        });
    }
};
