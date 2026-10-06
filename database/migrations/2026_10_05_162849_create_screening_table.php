<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('petugas_id')->nullable()->constrained('nakes')->nullOnDelete();
            $table->text('keluhan')->nullable();
            $table->integer('td_sistole')->nullable();
            $table->integer('td_diastole')->nullable();
            $table->integer('nadi')->nullable();
            $table->decimal('suhu', 5, 1)->nullable();
            $table->decimal('berat_badan', 5, 1)->nullable();
            $table->decimal('tinggi_badan', 5, 1)->nullable();
            $table->integer('spo2')->nullable();
            $table->integer('respirasi')->nullable();
            $table->text('riwayat_penyakit')->nullable();
            $table->text('riwayat_alergi')->nullable();
            $table->string('risiko_jatuh')->nullable();
            $table->string('risiko_nyeri')->nullable();
            $table->string('skrining_gizi')->nullable();
            $table->text('pemeriksaan_fisik')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening');
    }
};
