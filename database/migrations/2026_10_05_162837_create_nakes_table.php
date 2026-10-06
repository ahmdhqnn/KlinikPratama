<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nakes', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('jenis_kelamin')->nullable(); // L, P
            $table->string('kategori'); // medis, non_medis
            $table->string('jabatan'); // dokter, perawat, bidan, lab, pendaftaran, kasir, farmasi, apotek
            $table->string('no_sip')->nullable();
            $table->string('no_str')->nullable();
            $table->string('telepon')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nakes');
    }
};
