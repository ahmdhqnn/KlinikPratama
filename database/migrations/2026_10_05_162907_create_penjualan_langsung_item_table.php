<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualan_langsung_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_langsung_id')->constrained('penjualan_langsung')->cascadeOnDelete();
            $table->foreignId('obat_id')->constrained('obat');
            $table->decimal('jumlah', 10, 2)->default(1);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan_langsung_item');
    }
};
