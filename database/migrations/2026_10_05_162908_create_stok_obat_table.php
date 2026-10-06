<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_obat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obat_id')->constrained('obat')->cascadeOnDelete();
            $table->foreignId('depo_id')->constrained('depo_obat')->cascadeOnDelete();
            $table->decimal('stok', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['obat_id', 'depo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_obat');
    }
};
