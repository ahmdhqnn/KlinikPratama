<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kepesertaan', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('nik', 16)->nullable()->unique();
            $table->string('nip', 30)->nullable()->unique();
            $table->string('kategori', 30);
            $table->string('status_kepegawaian', 30);
            $table->string('unit_kerja', 200);
            $table->string('cost_center', 100);
            $table->foreignId('pegawai_penanggung_id')->nullable()->constrained('kepesertaan')->restrictOnDelete();
            $table->string('hubungan_keluarga', 30)->nullable();
            $table->boolean('hak_layanan')->default(false);
            $table->date('berlaku_mulai');
            $table->date('berlaku_sampai')->nullable();
            $table->string('referensi_bukti', 255);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['kategori', 'status_kepegawaian']);
        });
        Schema::table('pasien', function (Blueprint $table): void {
            $table->foreignId('kepesertaan_id')->nullable()->unique()->constrained('kepesertaan')->restrictOnDelete();
        });
        Schema::table('kunjungan', function (Blueprint $table): void {
            $table->json('hak_layanan_snapshot')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('cost_center', 100)->nullable()->index();
            $table->string('unit_kerja', 200)->nullable();
            $table->string('kategori_peserta', 30)->nullable();
        });
        DB::table('kunjungan')->where('status', 'kasir')->update(['status' => 'selesai', 'updated_at' => now()]);
        DB::table('users')->where('role', 'kasir')->update(['role' => 'pendaftaran', 'updated_at' => now()]);
        DB::table('nakes')->where('jabatan', 'kasir')->update(['jabatan' => 'pendaftaran', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('kunjungan', function (Blueprint $table): void {
            $table->dropIndex(['cost_center']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['hak_layanan_snapshot', 'verified_at', 'cost_center', 'unit_kerja', 'kategori_peserta']);
        });
        Schema::table('pasien', function (Blueprint $table): void {
            $table->dropUnique(['kepesertaan_id']);
            $table->dropConstrainedForeignId('kepesertaan_id');
        });
        Schema::dropIfExists('kepesertaan');
    }
};
