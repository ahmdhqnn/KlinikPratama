<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informed_consent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('tindakan_id')->nullable()->constrained('tindakan')->nullOnDelete();
            $table->string('jenis')->default('persetujuan'); // persetujuan, penolakan
            $table->text('isi')->nullable();
            $table->string('nama_penandatangan')->nullable();
            $table->string('hubungan_pasien')->nullable();
            $table->date('tanggal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informed_consent');
    }
};
