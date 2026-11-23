<?php

namespace Tests\Feature;

use App\ClinicalBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ClinicalBackupTest extends TestCase
{
    #[TestWith([null])]
    #[TestWith(['clinical-attachments/synthetic/report.pdf'])]
    #[TestWith(['signatures/synthetic/signature.png'])]
    #[TestWith(['consents/synthetic/general.pdf'])]
    public function test_backup_verification_checks_all_clinical_file_references_before_reporting_success(?string $missingPath): void
    {
        Storage::fake('local');
        Storage::fake('clinical_backups');
        $database = storage_path('framework/testing/backup-references-'.Str::uuid().'.sqlite');
        $key = storage_path('framework/testing/backup-key-'.Str::uuid());
        file_put_contents($key, bin2hex(random_bytes(32)));
        $pdo = new \PDO('sqlite:'.$database);
        $pdo->exec('CREATE TABLE kunjungan (id INTEGER PRIMARY KEY, lampiran TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, signature_path TEXT)');
        $pdo->exec('CREATE TABLE general_consents (id INTEGER PRIMARY KEY, evidence_path TEXT)');
        $pdo->prepare('INSERT INTO kunjungan (lampiran) VALUES (?)')->execute([json_encode([['path' => 'clinical-attachments/synthetic/report.pdf', 'voided_at' => '2026-11-19']])]);
        $pdo->exec("INSERT INTO users (signature_path) VALUES ('signatures/synthetic/signature.png')");
        $pdo->exec("INSERT INTO general_consents (evidence_path) VALUES ('consents/synthetic/general.pdf')");
        $pdo = null;
        config(['database.default' => 'backup_test', 'database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $database], 'clinical_backup.key_file' => $key]);
        foreach (['clinical-attachments/synthetic/report.pdf', 'signatures/synthetic/signature.png', 'consents/synthetic/general.pdf'] as $path) {
            if ($path !== $missingPath) {
                Storage::disk('local')->put($path, 'synthetic evidence');
            }
        }
        try {
            if ($missingPath) {
                try {
                    app(ClinicalBackup::class)->create(localOnly: true);
                    $this->fail('Backup dengan referensi berkas hilang tidak boleh dinyatakan lulus.');
                } catch (\RuntimeException $error) {
                    $this->assertStringContainsString('referensi berkas klinis', $error->getMessage());
                }
                Storage::disk('clinical_backups')->assertMissing('last-success.json');
            } else {
                $name = app(ClinicalBackup::class)->create(localOnly: true);
                app(ClinicalBackup::class)->verify($name);
                Storage::disk('clinical_backups')->assertExists('last-success.json');
                $this->assertTrue(json_decode(Storage::disk('clinical_backups')->get('last-success.json'), true)['local_only']);
            }
        } finally {
            DB::purge('backup_test');
            @unlink($database);
            @unlink($key);
        }
    }

    public function test_encrypted_backup_is_read_back_and_database_and_file_checksums_are_verified(): void
    {
        Storage::fake('local');
        Storage::fake('clinical_backups');
        $database = storage_path('framework/testing/backup-source-'.Str::uuid().'.sqlite');
        $key = storage_path('framework/testing/backup-key-'.Str::uuid());
        file_put_contents($key, bin2hex(random_bytes(32)));
        $pdo = new \PDO('sqlite:'.$database);
        $pdo->exec('CREATE TABLE synthetic_records (id INTEGER PRIMARY KEY, note TEXT)');
        $pdo->exec("INSERT INTO synthetic_records (note) VALUES ('synthetic only')");
        $pdo = null;
        config(['database.default' => 'backup_test', 'database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $database], 'clinical_backup.key_file' => $key]);
        Storage::disk('local')->put('clinical-attachments/synthetic/report.pdf', 'synthetic attachment');
        try {
            $service = app(ClinicalBackup::class);
            $name = $service->create(localOnly: true);
            Storage::disk('clinical_backups')->assertExists($name);
            $service->verify($name);
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open(Storage::disk('clinical_backups')->path($name)));
            $this->assertFalse($zip->getFromName('database.sqlite'));
            $zip->close();
            $status = json_decode(Storage::disk('clinical_backups')->get('last-success.json'), true);
            $this->assertTrue($status['local_only']);
            file_put_contents($key, bin2hex(random_bytes(32)));
            $this->expectException(\RuntimeException::class);
            $service->verify($name);
        } finally {
            DB::purge('backup_test');
            @unlink($database);
            @unlink($key);
        }
    }

    public function test_scheduled_backup_refuses_to_claim_unverified_local_storage_is_offsite(): void
    {
        config(['clinical_backup.offsite_confirmed' => false]);
        $this->expectException(\RuntimeException::class);
        app(ClinicalBackup::class)->create();
    }
}
