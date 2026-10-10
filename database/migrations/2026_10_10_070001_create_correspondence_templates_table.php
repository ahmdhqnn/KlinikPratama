<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('correspondence_templates', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('jenis')->index();
            $table->string('prefix', 40);
            $table->string('format_nomor', 120)->default('[prefix]/[urut]/[bulan]/[tahun]');
            $table->unsignedBigInteger('nomor_berikutnya')->default(1);
            $table->text('isi');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('surat_medis', function (Blueprint $table): void {
            $table->foreignId('surat_template_id')->nullable()->after('dokter_id')->constrained('correspondence_templates')->nullOnDelete();
        });

        Schema::table('rujukan_internal', function (Blueprint $table): void {
            $table->foreignId('surat_template_id')->nullable()->after('ke_poli_id')->constrained('correspondence_templates')->nullOnDelete();
            $table->string('nomor_surat')->nullable()->after('surat_template_id');
            $table->text('konten_surat')->nullable()->after('nomor_surat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rujukan_internal', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('surat_template_id');
            $table->dropColumn(['nomor_surat', 'konten_surat']);
        });

        Schema::table('surat_medis', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('surat_template_id');
        });

        Schema::dropIfExists('correspondence_templates');
    }
};
