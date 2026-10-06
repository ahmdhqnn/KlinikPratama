<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_hasil_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_hasil_id')->constrained('lab_hasil')->cascadeOnDelete();
            $table->foreignId('indikator_id')->constrained('lab_indikator')->cascadeOnDelete();
            $table->string('nilai')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_hasil_detail');
    }
};
