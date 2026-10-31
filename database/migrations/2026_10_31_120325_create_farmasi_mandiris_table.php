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
        Schema::create('farmasi_mandiris', function (Blueprint $table): void {
            $table->id();
            $table->string('nomor', 40)->unique();
            $table->foreignId('pasien_id')->constrained('pasien')->restrictOnDelete();
            $table->foreignId('obat_id')->constrained('obat')->restrictOnDelete();
            $table->foreignId('petugas_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('jumlah');
            $table->string('aturan_pakai', 255);
            $table->text('dasar_pemberian');
            $table->timestamp('diserahkan_pada');
            $table->timestamps();
            $table->index(['pasien_id', 'diserahkan_pada']);
            $table->index(['obat_id', 'diserahkan_pada']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farmasi_mandiris');
    }
};
