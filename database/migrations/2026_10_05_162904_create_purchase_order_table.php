<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order', function (Blueprint $table) {
            $table->id();
            $table->string('no_po')->unique();
            $table->foreignId('depo_id')->constrained('depo_obat');
            $table->string('supplier')->nullable();
            $table->date('tanggal');
            $table->date('tanggal_kirim')->nullable();
            $table->string('status')->default('draft'); // draft, dikirim, diterima, sebagian, batal
            $table->text('catatan')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order');
    }
};
