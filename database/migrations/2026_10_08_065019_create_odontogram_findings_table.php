<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odontogram_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->restrictOnDelete();
            $table->char('tooth_fdi', 2);
            $table->char('surface', 1)->default('W');
            $table->string('finding_code', 32);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['kunjungan_id', 'tooth_fdi', 'surface']);
        });

        Schema::table('tindakan_kunjungan', function (Blueprint $table) {
            $table->char('tooth_fdi', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tindakan_kunjungan', function (Blueprint $table) {
            $table->dropColumn('tooth_fdi');
        });

        Schema::dropIfExists('odontogram_findings');
    }
};
