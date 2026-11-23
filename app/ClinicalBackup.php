<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ClinicalBackup
{
    private function password(): string
    {
        $path = config('clinical_backup.key_file');
        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('Berkas kunci backup terpisah belum dikonfigurasi.');
        }
        $password = trim(file_get_contents($path));
        if (strlen($password) < 32) {
            throw new RuntimeException('Kunci backup harus memiliki sedikitnya 32 karakter acak.');
        }

        return $password;
    }

    public function create(bool $localOnly = false): string
    {
        if (! $localOnly && ! config('clinical_backup.offsite_confirmed')) {
            throw new RuntimeException('Lokasi off-site belum diverifikasi; gunakan --local-only hanya untuk checkpoint lokal.');
        }
        $password = $this->password();
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Backup engine ini belum dikonfigurasi; gunakan backup native yang konsisten dan uji restore sebelum rilis.');
        }
        $work = storage_path('app/backup-work/'.Str::uuid());
        File::ensureDirectoryExists($work, 0700);
        $zip = new ZipArchive;
        $zipOpen = false;
        try {
            DB::connection()->getPdo()->exec('VACUUM INTO '.DB::connection()->getPdo()->quote($work.'/database.sqlite'));
            if ($zip->open($work.'/backup.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Arsip backup tidak dapat dibuat.');
            }
            $zipOpen = true;
            $files = ['database.sqlite' => $work.'/database.sqlite'];
            foreach (['clinical-attachments', 'signatures', 'consents', 'documents'] as $folder) {
                foreach (Storage::disk('local')->allFiles($folder) as $path) {
                    $files['private/'.$path] = Storage::disk('local')->path($path);
                }
            }
            $manifest = ['format' => 1, 'created_at' => now()->toIso8601String(), 'local_only' => $localOnly, 'files' => []];
            foreach ($files as $name => $path) {
                $manifest['files'][$name] = hash_file('sha256', $path);
                if (! $zip->addFile($path, $name) || ! $zip->setEncryptionName($name, ZipArchive::EM_AES_256, $password)) {
                    throw new RuntimeException('Enkripsi berkas backup gagal.');
                }
            }
            if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR))
                || ! $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $password)) {
                throw new RuntimeException('Enkripsi manifest backup gagal.');
            }
            if (! $zip->close()) {
                throw new RuntimeException('Penutupan arsip backup gagal.');
            }
            $zipOpen = false;
            $name = 'clinical-'.now()->format('Ymd-His').'-'.Str::uuid().'.zip';
            $stream = fopen($work.'/backup.zip', 'rb');
            try {
                if (! Storage::disk(config('clinical_backup.disk'))->put($name, $stream)) {
                    throw new RuntimeException('Penulisan backup ke lokasi tujuan gagal.');
                }
            } finally {
                fclose($stream);
            }
            // Read back from the destination: a successful upload alone is not verification.
            $this->verify($name);
            if (! Storage::disk(config('clinical_backup.disk'))->put('last-success.json', json_encode(['at' => now()->toIso8601String(), 'archive' => $name, 'local_only' => $localOnly], JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Status pemantauan backup belum dapat disimpan.');
            }

            return $name;
        } finally {
            try {
                if ($zipOpen) {
                    $zip->close();
                }
            } finally {
                // Only this generated private workspace is removed, never an arbitrary archive path.
                $this->removeWorkspace($work);
            }
        }
    }

    public function verify(string $name): void
    {
        if (! preg_match('/^clinical-[A-Za-z0-9-]+\.zip$/D', $name)) {
            throw new RuntimeException('Nama arsip tidak valid.');
        }
        $password = $this->password();
        $work = storage_path('app/backup-work/'.Str::uuid());
        File::ensureDirectoryExists($work, 0700);
        $zip = new ZipArchive;
        $zipOpen = false;
        $restored = null;
        try {
            $source = Storage::disk(config('clinical_backup.disk'))->readStream($name);
            if (! is_resource($source)) {
                throw new RuntimeException('Arsip backup tidak tersedia.');
            }
            $target = fopen($work.'/backup.zip', 'wb');
            stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);
            if ($zip->open($work.'/backup.zip') !== true) {
                throw new RuntimeException('Arsip backup tidak dapat dibuka.');
            }
            $zipOpen = true;
            if (! $zip->setPassword($password)) {
                throw new RuntimeException('Kunci arsip backup tidak dapat digunakan.');
            }
            $raw = $zip->getFromName('manifest.json');
            if ($raw === false) {
                throw new RuntimeException('Manifest tidak dapat didekripsi.');
            }
            $manifest = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? null) !== 1 || empty($manifest['files']['database.sqlite'])) {
                throw new RuntimeException('Manifest backup tidak lengkap.');
            }
            foreach ($manifest['files'] as $path => $expected) {
                if ($path !== 'database.sqlite' && (! str_starts_with($path, 'private/') || str_contains($path, '..') || str_contains($path, '\\'))) {
                    throw new RuntimeException('Path arsip tidak diizinkan.');
                }
                $entry = $zip->getStream($path);
                if (! is_resource($entry)) {
                    throw new RuntimeException('Berkas dalam arsip tidak dapat dibaca.');
                }
                $digest = hash_init('sha256');
                hash_update_stream($digest, $entry);
                fclose($entry);
                if (! hash_equals($expected, hash_final($digest))) {
                    throw new RuntimeException('Checksum backup tidak sesuai.');
                }
            }
            $entry = $zip->getStream('database.sqlite');
            $dbFile = fopen($work.'/restored.sqlite', 'wb');
            stream_copy_to_stream($entry, $dbFile);
            fclose($entry);
            fclose($dbFile);
            $restored = new \PDO('sqlite:'.$work.'/restored.sqlite');
            if ($restored->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $restored->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
                throw new RuntimeException('Verifikasi database hasil pemulihan gagal.');
            }
            $this->verifyReferencedFiles($restored, $manifest['files']);
            $restored = null;
            $zip->close();
            $zipOpen = false;
        } finally {
            $restored = null;
            try {
                if ($zipOpen) {
                    $zip->close();
                }
            } finally {
                $this->removeWorkspace($work);
            }
        }
    }

    private function verifyReferencedFiles(\PDO $database, array $files): void
    {
        $missing = 0;
        foreach ([['kunjungan', 'lampiran'], ['users', 'signature_path'], ['general_consents', 'evidence_path'], ['informed_consent', 'evidence_path']] as [$table, $column]) {
            $columns = $database->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_COLUMN, 1);
            if (! in_array($column, $columns, true)) {
                continue;
            }
            foreach ($database->query("SELECT $column FROM $table WHERE $column IS NOT NULL AND $column != ''", \PDO::FETCH_COLUMN, 0) as $value) {
                $paths = [$value];
                if ($column === 'lampiran') {
                    $attachments = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
                    if (! is_array($attachments) || ! array_is_list($attachments)) {
                        throw new RuntimeException('Metadata lampiran pada backup perlu direkonsiliasi.');
                    }
                    $paths = [];
                    foreach ($attachments as $attachment) {
                        if (! is_array($attachment) || ! is_string($attachment['path'] ?? null)) {
                            throw new RuntimeException('Referensi lampiran pada backup belum lengkap.');
                        }
                        $paths[] = $attachment['path'];
                    }
                }
                foreach ($paths as $path) {
                    if (! is_string($path) || ! array_key_exists('private/'.$path, $files)) {
                        $missing++;
                    }
                }
            }
        }
        if ($missing > 0) {
            throw new RuntimeException('Backup belum lengkap: '.$missing.' referensi berkas klinis tidak tercakup arsip.');
        }
    }

    private function removeWorkspace(string $work): void
    {
        $root = realpath(storage_path('app/backup-work'));
        $resolved = realpath($work);
        if ($root === false || $resolved === false || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) || is_link($work)) {
            throw new RuntimeException('Lokasi kerja backup tidak dapat diverifikasi untuk dibersihkan.');
        }
        if (! File::deleteDirectory($resolved)) {
            throw new RuntimeException('Workspace backup privat belum dapat dibersihkan. Periksa penyimpanan server.');
        }
    }
}
