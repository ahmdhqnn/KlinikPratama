<?php

namespace App\Console\Commands;

use App\ClinicalBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupClinicalRecords extends Command
{
    protected $signature = 'clinic:backup {--local-only : Checkpoint lokal, bukan bukti off-site} {--verify= : Verifikasi arsip di lokasi tujuan tanpa menimpa database layanan}';

    protected $description = 'Backup terenkripsi database SQLite dan lampiran private, serta verifikasi pemulihan terisolasi';

    public function handle(ClinicalBackup $backup): int
    {
        try {
            if ($name = $this->option('verify')) {
                $backup->verify($name);
                $this->info('Verifikasi arsip dan database terisolasi lulus.');
            } else {
                $name = $backup->create((bool) $this->option('local-only'));
                $this->info('Backup terenkripsi dan verifikasi lulus: '.$name);
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            Log::critical('clinical_backup_failed', ['error_class' => $exception::class]);
            $this->error('Backup/verifikasi gagal. Periksa lokasi penyimpanan, kunci, kapasitas, dan runbook.');

            return self::FAILURE;
        }
    }
}
