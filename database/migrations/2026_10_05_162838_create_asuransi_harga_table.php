<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asuransi_harga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asuransi_id')->constrained('asuransi')->cascadeOnDelete();
            $table->foreignId('obat_id')->constrained('obat')->cascadeOnDelete();
            $table->decimal('harga_khusus', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['asuransi_id', 'obat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asuransi_harga');
    }
};
