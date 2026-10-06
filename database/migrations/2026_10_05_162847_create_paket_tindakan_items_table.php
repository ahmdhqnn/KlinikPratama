<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paket_tindakan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paket_tindakan_id')->constrained('paket_tindakan')->cascadeOnDelete();
            $table->foreignId('tindakan_id')->nullable()->constrained('tindakan')->nullOnDelete();
            $table->foreignId('laboratorium_id')->nullable()->constrained('laboratorium')->nullOnDelete();
            $table->string('jenis'); // tindakan, lab
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paket_tindakan_items');
    }
};
