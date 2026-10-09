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
        Schema::table('kepesertaan', function (Blueprint $table): void {
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('jenis_kelamin', 1)->nullable();
            $table->string('agama', 50)->nullable();
            $table->string('golongan_darah', 2)->nullable();
            $table->text('alamat')->nullable();
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('kelurahan', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kepesertaan', function (Blueprint $table): void {
            $table->dropColumn([
                'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'golongan_darah',
                'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan',
            ]);
        });
    }
};
