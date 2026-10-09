<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemeriksaan', function (Blueprint $table) {
            $table->foreignId('signed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('signed_at', 6)->nullable();
            $table->char('latest_note_hash', 64)->nullable();
        });

        Schema::create('clinical_note_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemeriksaan_id')->constrained('pemeriksaan')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('kind', 16);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('payload');
            $table->char('previous_hash', 64)->nullable();
            $table->char('content_hash', 64);
            $table->timestamp('recorded_at', 6);
            $table->timestamps();

            $table->unique(['pemeriksaan_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_note_versions');

        Schema::table('pemeriksaan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signed_by_user_id');
            $table->dropColumn('signed_at');
            $table->dropColumn('latest_note_hash');
        });
    }
};
