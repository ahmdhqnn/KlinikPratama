<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kepesertaan', function (Blueprint $table): void {
            $table->string('jenis_lainnya', 30)->nullable()->after('kategori');
            $table->string('cost_center', 100)->nullable()->change();
        });

        foreach (['kontrak' => 'staf', 'pensiunan' => 'pensiunan', 'keluarga' => 'keluarga', 'tamu' => 'tamu', 'khusus' => 'khusus'] as $old => $detail) {
            DB::table('kepesertaan')->where('kategori', $old)->update(['kategori' => 'lainnya', 'jenis_lainnya' => $detail]);
        }
        DB::table('kepesertaan')->where('kategori', 'pegawai_pusat')->update(['kategori' => 'asn']);
    }

    public function down(): void
    {
        Schema::table('kepesertaan', function (Blueprint $table): void {
            $table->dropColumn('jenis_lainnya');
        });
    }
};
