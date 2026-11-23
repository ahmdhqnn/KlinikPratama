<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_consents', function (Blueprint $table): void {
            $table->uuid('signature_token')->nullable()->unique();
            $table->char('signature_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Bukti konfirmasi dipertahankan; gunakan forward fix.');
    }
};
