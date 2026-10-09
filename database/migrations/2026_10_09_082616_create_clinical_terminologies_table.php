<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_terminologies', function (Blueprint $table): void {
            $table->id();
            $table->string('code_system', 100);
            $table->string('release', 100)->default('');
            $table->string('code', 100);
            $table->text('display');
            $table->string('code_type', 50)->nullable();
            $table->string('source_file')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['code_system', 'release', 'code']);
            $table->index(['code_system', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_terminologies');
    }
};
