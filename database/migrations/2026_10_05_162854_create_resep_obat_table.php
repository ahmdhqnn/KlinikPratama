<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resep_obat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resep_id')->constrained('resep')->cascadeOnDelete();
            $table->foreignId('obat_id')->nullable()->constrained('obat')->nullOnDelete();
            $table->string('nama_obat')->nullable(); // untuk resep luar
            $table->decimal('jumlah', 10, 2)->default(1);
            $table->string('satuan')->nullable();
            $table->string('aturan_pakai')->nullable();
            $table->text('catatan')->nullable();
            $table->string('jenis')->default('jadi'); // jadi, racikan
            $table->boolean('is_resep_luar')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resep_obat');
    }
};
