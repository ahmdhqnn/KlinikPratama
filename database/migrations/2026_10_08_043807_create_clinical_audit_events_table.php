<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('kunjungan_id')->nullable();
            $table->string('action', 64);
            $table->string('route_name', 128)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('occurred_at', 6);

            $table->index(['patient_id', 'occurred_at']);
            $table->index(['actor_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_audit_events');
    }
};
