<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_consent_templates', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Status arsip SOP dipertahankan; gunakan forward fix.');
    }
};
