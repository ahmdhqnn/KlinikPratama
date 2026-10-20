<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pasien', function (Blueprint $table): void {
            $table->string('nip', 30)->nullable()->unique();
        });

        DB::table('pasien')->whereNotNull('kepesertaan_id')->orderBy('id')->chunkById(200, function ($patients): void {
            $members = DB::table('kepesertaan')->whereIn('id', $patients->pluck('kepesertaan_id'))->pluck('nip', 'id');
            foreach ($patients as $patient) {
                DB::table('pasien')->where('id', $patient->id)->update(['nip' => $members[$patient->kepesertaan_id] ?? null]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pasien', function (Blueprint $table): void {
            $table->dropUnique(['nip']);
            $table->dropColumn('nip');
        });
    }
};
