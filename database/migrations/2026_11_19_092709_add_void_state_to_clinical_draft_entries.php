<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['diagnosa', 'resep_obat', 'tindakan_kunjungan', 'farmasi_item'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestamp('voided_at')->nullable()->index();
                $table->unsignedBigInteger('voided_by')->nullable();
                $table->text('void_reason')->nullable();
            });
            $driver = DB::connection()->getDriverName();
            $trigger = 'protect_'.$name.'_delete';
            $sql = match ($driver) {
                'sqlite' => "CREATE TRIGGER $trigger BEFORE DELETE ON $name BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END",
                'mysql' => "CREATE TRIGGER $trigger BEFORE DELETE ON $name FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Catatan klinis dilindungi'",
                'pgsql' => "CREATE TRIGGER $trigger BEFORE DELETE ON $name FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()",
                default => throw new RuntimeException('Proteksi pembatalan belum tersedia untuk engine ini.'),
            };
            DB::unprepared($sql);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Bukti pembatalan entri klinis tidak dihapus melalui rollback.');
    }
};
