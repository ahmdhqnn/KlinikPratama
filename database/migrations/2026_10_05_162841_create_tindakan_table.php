<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tindakan', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('kode_icd9')->nullable();
            $table->string('nama');
            $table->string('kategori')->default('medis'); // medis, lab
            $table->foreignId('poliklinik_id')->nullable()->constrained('poliklinik')->nullOnDelete();
            $table->decimal('tarif', 15, 2)->default(0);
            $table->decimal('tarif_dokter', 15, 2)->default(0);
            $table->decimal('tarif_asisten', 15, 2)->default(0);
            $table->decimal('tarif_klinik', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tindakan');
    }
};
