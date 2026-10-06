<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biaya_pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poliklinik_id')->nullable()->constrained('poliklinik')->nullOnDelete();
            $table->foreignId('dokter_id')->nullable()->constrained('nakes')->nullOnDelete();
            $table->string('jenis_pasien')->default('baru'); // baru, lama
            $table->decimal('tarif', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biaya_pendaftaran');
    }
};
