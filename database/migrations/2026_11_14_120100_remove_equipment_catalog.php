<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('alkes')->exists()) {
            throw new RuntimeException('Arsipkan data alat kesehatan dan verifikasi arsip sebelum menghapus tabel alkes.');
        }

        Schema::drop('alkes');
    }

    public function down(): void
    {
        Schema::create('alkes', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('satuan')->nullable();
            $table->integer('stok')->default(0);
            $table->integer('stok_minimum')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
