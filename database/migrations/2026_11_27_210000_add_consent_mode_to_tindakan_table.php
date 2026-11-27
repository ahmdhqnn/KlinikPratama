<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tindakan', function (Blueprint $table): void {
            $table->string('consent_mode', 24)->default('general_consent')->after('can_delegate_to_nurse');
        });
    }

    public function down(): void
    {
        Schema::table('tindakan', function (Blueprint $table): void {
            $table->dropColumn('consent_mode');
        });
    }
};
