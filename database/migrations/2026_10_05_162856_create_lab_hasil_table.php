<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_hasil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('laboratorium_id')->constrained('laboratorium');
            $table->foreignId('petugas_id')->nullable()->constrained('nakes')->nullOnDelete();
            $table->string('status')->default('menunggu'); // menunggu, selesai
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_hasil');
    }
};
