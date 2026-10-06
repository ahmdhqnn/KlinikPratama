<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_dokter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokter_id')->constrained('nakes')->cascadeOnDelete();
            $table->foreignId('poliklinik_id')->constrained('poliklinik')->cascadeOnDelete();
            $table->string('hari');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['dokter_id', 'poliklinik_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_dokter');
    }
};
