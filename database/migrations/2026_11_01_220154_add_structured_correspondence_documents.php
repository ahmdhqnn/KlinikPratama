<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['surat_medis', 'rujukan_internal'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->json('document_data')->nullable();
                $table->index('nomor_surat');
            });
        }
        Schema::create('correspondence_numbers', function (Blueprint $table): void {
            $table->string('number')->primary();
        });
        foreach (['surat_medis', 'rujukan_internal'] as $source) {
            DB::table($source)->whereNotNull('nomor_surat')->chunkById(500, function (Collection $documents): void {
                $numbers = $documents->pluck('nomor_surat')->filter(fn (string $number): bool => trim($number) !== '')->unique();
                if ($numbers->isNotEmpty()) {
                    DB::table('correspondence_numbers')->insertOrIgnore($numbers->map(fn (string $number): array => ['number' => $number])->values()->all());
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correspondence_numbers');
        foreach (['surat_medis', 'rujukan_internal'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('document_data');
                $table->dropIndex(['nomor_surat']);
            });
        }
    }
};
