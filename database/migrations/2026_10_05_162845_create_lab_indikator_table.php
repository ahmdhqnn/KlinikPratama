<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_indikator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratorium_id')->constrained('laboratorium')->cascadeOnDelete();
            $table->string('nama');
            $table->string('satuan')->nullable();
            $table->string('nilai_rujukan_min')->nullable();
            $table->string('nilai_rujukan_max')->nullable();
            $table->string('format_input')->default('text'); // text, number, select
            $table->text('pilihan')->nullable(); // JSON untuk format select
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_indikator');
    }
};
