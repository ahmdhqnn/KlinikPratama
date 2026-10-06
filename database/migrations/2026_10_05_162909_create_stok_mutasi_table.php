<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_mutasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obat_id')->constrained('obat');
            $table->foreignId('depo_id')->nullable()->constrained('depo_obat')->nullOnDelete();
            $table->string('jenis'); // masuk, keluar, retur, penyesuaian
            $table->string('referensi_type')->nullable(); // PurchaseOrder, Resep, PenjualanLangsung
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->decimal('jumlah', 10, 2)->default(0);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('stok_sebelum', 10, 2)->default(0);
            $table->decimal('stok_sesudah', 10, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_mutasi');
    }
};
