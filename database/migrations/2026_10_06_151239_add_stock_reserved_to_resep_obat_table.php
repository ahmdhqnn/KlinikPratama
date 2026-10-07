<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resep_obat', function (Blueprint $table) {
            $table->boolean('stok_dikurangi')->default(false)->after('is_resep_luar');
        });
    }

    public function down(): void
    {
        Schema::table('resep_obat', function (Blueprint $table) {
            $table->dropColumn('stok_dikurangi');
        });
    }
};
