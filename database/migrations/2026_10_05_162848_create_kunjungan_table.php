<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungan', function (Blueprint $table) {
            $table->id();
            $table->string('no_kunjungan')->unique();
            $table->foreignId('pasien_id')->constrained('pasien')->cascadeOnDelete();
            $table->foreignId('poliklinik_id')->constrained('poliklinik');
            $table->foreignId('dokter_id')->nullable()->constrained('nakes')->nullOnDelete();
            $table->foreignId('asuransi_id')->nullable()->constrained('asuransi')->nullOnDelete();
            $table->date('tanggal');
            $table->string('status')->default('menunggu'); // menunggu, screening, pemeriksaan, farmasi, kasir, selesai, batal
            $table->string('jenis_pasien')->default('baru'); // baru, lama
            $table->string('jenis_bayar')->default('umum'); // umum, bpjs, asuransi
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungan');
    }
};
