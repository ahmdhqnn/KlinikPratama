<?php

namespace App\Console\Commands;

use App\Models\JadwalDokter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('clinic:prune-expired-schedules {--dry-run : Tampilkan jumlah tanpa menghapus}')]
#[Description('Hapus jadwal dokter yang masa berlakunya berakhir dan pengecualian tanggal yang telah lewat')]
class PruneExpiredDoctorSchedules extends Command
{
    public function handle(): int
    {
        $counts = DB::transaction(function (): array {
            $schedules = JadwalDokter::query()->whereDate('berlaku_sampai', '<', today());
            $exceptions = DB::table('jadwal_khusus_dokter')->whereDate('tanggal', '<', today());
            if ($this->option('dry-run')) {
                return [$schedules->count(), $exceptions->count()];
            }

            return [$schedules->delete(), $exceptions->delete()];
        });

        $this->info(sprintf('%s: %d jadwal berakhir, %d pengecualian lewat.', $this->option('dry-run') ? 'Pratinjau' : 'Dihapus', ...$counts));

        return self::SUCCESS;
    }
}
