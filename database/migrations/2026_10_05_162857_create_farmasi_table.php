<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('resep_id')->nullable()->constrained('resep')->nullOnDelete();
            $table->foreignId('petugas_id')->nullable()->constrained('nakes')->nullOnDelete();
            $table->string('status')->default('menunggu'); // menunggu, diproses, selesai
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmasi');
    }
};
