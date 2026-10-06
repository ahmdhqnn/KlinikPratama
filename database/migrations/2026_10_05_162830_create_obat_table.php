<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obat', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('kode_kfa')->nullable();
            $table->string('nama');
            $table->string('satuan_besar')->nullable();
            $table->string('satuan_kecil')->nullable();
            $table->decimal('konversi_satuan', 10, 2)->default(1);
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->decimal('harga_jual', 15, 2)->default(0);
            $table->text('indikasi')->nullable();
            $table->text('kandungan')->nullable();
            $table->integer('stok')->default(0);
            $table->integer('stok_minimum')->default(0);
            $table->string('jenis')->default('obat'); // obat, bhp
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obat');
    }
};
