<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rujukan_internal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('dari_poli_id')->constrained('poliklinik');
            $table->foreignId('ke_poli_id')->constrained('poliklinik');
            $table->text('catatan')->nullable();
            $table->string('status')->default('menunggu'); // menunggu, diterima, selesai
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rujukan_internal');
    }
};
