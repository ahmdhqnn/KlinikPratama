<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_consent_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32);
            $table->string('release', 60);
            $table->string('clinic_type', 10);
            $table->string('version', 40);
            $table->string('mode', 15);
            $table->text('trigger_condition')->nullable();
            $table->string('sop_reference', 255)->nullable();
            $table->json('explanation')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['code', 'release', 'clinic_type', 'version'], 'procedure_consent_template_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_consent_templates');
    }
};
