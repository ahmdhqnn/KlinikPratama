<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmasi_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmasi_id')->constrained('farmasi')->cascadeOnDelete();
            $table->foreignId('resep_obat_id')->nullable()->constrained('resep_obat')->nullOnDelete();
            $table->foreignId('obat_id')->nullable()->constrained('obat')->nullOnDelete();
            $table->decimal('jumlah_diberikan', 10, 2)->default(0);
            $table->string('aturan_pakai')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmasi_item');
    }
};
