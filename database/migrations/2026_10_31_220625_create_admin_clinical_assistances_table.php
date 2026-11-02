<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_clinical_assistances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->restrictOnDelete();
            $table->foreignId('dokter_id')->constrained('nakes')->restrictOnDelete();
            $table->foreignId('poliklinik_id')->constrained('poliklinik')->restrictOnDelete();
            $table->date('service_date');
            $table->text('reason');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['actor_id', 'kunjungan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_clinical_assistances');
    }
};
