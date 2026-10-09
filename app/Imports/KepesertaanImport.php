<?php

namespace App\Imports;

use App\KepesertaanRegistry;
use App\Models\Kepesertaan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use ZipArchive;

class KepesertaanImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    public int $count = 0;

    /**
     * @var array<string, string>
     */
    private array $numericIdentifiers;

    public function __construct(private User $actor, private string $reference, string $filePath)
    {
        $this->numericIdentifiers = $this->readNumericIdentifiers($filePath);
    }

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows): void
    {
        if ($rows->count() > 5000) {
            throw ValidationException::withMessages(['file' => 'Maksimal 5.000 peserta per impor.']);
        }
        $seenNik = [];
        $seenNip = [];
        $pending = [];
        foreach ($rows as $offset => $row) {
            $data = $row->toArray();
            if (! collect($data)->contains(fn ($value) => $value !== null && $value !== '')) {
                continue;
            }
            foreach (['nama', 'nik', 'nip', 'kategori', 'status_kepegawaian', 'unit_kerja', 'cost_center', 'nip_penanggung', 'hubungan_keluarga', 'hak_layanan', 'berlaku_mulai', 'berlaku_sampai', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'golongan_darah', 'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan'] as $header) {
                if (! array_key_exists($header, $data)) {
                    $this->fail($offset, "Kolom {$header} tidak ditemukan. Gunakan template tanpa mengubah nama kolom.");
                }
            }
            foreach (['nik', 'nip', 'nip_penanggung'] as $key) {
                $data[$key] = ($data[$key] ?? '') === '' ? null : $data[$key];
                if ($data[$key] !== null && ! is_string($data[$key])) {
                    $column = ['nik' => 'B', 'nip' => 'C', 'nip_penanggung' => 'H'][$key];
                    $coordinate = $column.($offset + 2);
                    $data[$key] = $this->numericIdentifiers[$coordinate] ?? null;
                    if ($data[$key] === null) {
                        $this->fail($offset, 'Sel '.$coordinate.' berisi angka desimal yang tidak dapat dibaca persis sebagai identitas. Atur kolom NIP/NIK sebagai Teks, isi ulang dari sumber, lalu impor kembali.');
                    }
                }
                if ($data[$key] !== null) {
                    $data[$key] = trim($data[$key]);
                }
            }
            $duplicateRow = ($data['nik'] !== null ? ($seenNik[$data['nik']] ?? null) : null)
                ?? ($data['nip'] !== null ? ($seenNip[$data['nip']] ?? null) : null);
            if ($duplicateRow !== null) {
                $this->fail($offset, "NIP atau NIK duplikat dengan baris {$duplicateRow}. Periksa kembali sumber identitas.");
            }
            if ($data['nik']) {
                $seenNik[$data['nik']] = $offset + 2;
            }
            if ($data['nip']) {
                $seenNip[$data['nip']] = $offset + 2;
            }
            $data['berlaku_sampai'] = $data['berlaku_sampai'] ?: null;
            $data['hubungan_keluarga'] = ($data['hubungan_keluarga'] ?? '') ?: null;
            $data['referensi_bukti'] = $this->reference;
            $pending[] = ['offset' => $offset, 'data' => $data];
        }
        if ($pending === []) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak berisi data peserta. Gunakan template yang tersedia.']);
        }

        usort($pending, fn (array $a, array $b): int => ($a['data']['kategori'] === 'keluarga' ? 1 : 0) <=> ($b['data']['kategori'] === 'keluarga' ? 1 : 0));
        foreach ($pending as $line) {
            $data = $line['data'];
            $byNik = $data['nik'] ? Kepesertaan::where('nik', $data['nik'])->lockForUpdate()->first() : null;
            $byNip = $data['nip'] ? Kepesertaan::where('nip', $data['nip'])->lockForUpdate()->first() : null;
            if ($byNik && $byNip && $byNik->id !== $byNip->id) {
                $this->fail($line['offset'], 'NIP dan NIK menunjuk dua peserta berbeda. Periksa identitas sumber.');
            }
            $data['pegawai_penanggung_id'] = $data['nip_penanggung']
                ? Kepesertaan::where('nip', $data['nip_penanggung'])->value('id') : null;
            try {
                app(KepesertaanRegistry::class)->save($data, $this->actor, $byNik ?? $byNip);
            } catch (ValidationException $exception) {
                $this->fail($line['offset'], collect($exception->errors())->flatten()->implode(' '));
            }
            $this->count++;
        }
    }

    private function fail(int $offset, string $message): never
    {
        throw ValidationException::withMessages(['file' => 'Baris '.($offset + 2).': '.$message.' Seluruh impor dibatalkan.']);
    }

    /**
     * @return array<string, string>
     */
    private function readNumericIdentifiers(string $filePath): array
    {
        $archive = new ZipArchive;
        if ($archive->open($filePath) !== true) {
            return [];
        }

        try {
            $workbookContents = $archive->getFromName('xl/workbook.xml');
            $relationshipsContents = $archive->getFromName('xl/_rels/workbook.xml.rels');
            if (! is_string($workbookContents) || ! is_string($relationshipsContents)) {
                return [];
            }

            $workbook = simplexml_load_string($workbookContents, options: LIBXML_NONET | LIBXML_COMPACT);
            $relationships = simplexml_load_string($relationshipsContents, options: LIBXML_NONET | LIBXML_COMPACT);
            if (! $workbook || ! $relationships) {
                return [];
            }

            $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $firstSheet = $workbook->xpath('/x:workbook/x:sheets/x:sheet')[0] ?? null;
            $relationshipId = $firstSheet ? (string) $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';
            if ($relationshipId === '') {
                return [];
            }

            $relationships->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');
            $sheetPath = null;
            foreach ($relationships->xpath('/p:Relationships/p:Relationship') ?: [] as $relationship) {
                if ((string) $relationship['Id'] === $relationshipId) {
                    $sheetPath = $this->normalizeZipPath((string) $relationship['Target']);
                    break;
                }
            }
            if ($sheetPath === null) {
                return [];
            }

            $sheetContents = $archive->getFromName($sheetPath);
            $sheet = is_string($sheetContents) ? simplexml_load_string($sheetContents, options: LIBXML_NONET | LIBXML_COMPACT) : false;
            if (! $sheet) {
                return [];
            }

            $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $identifiers = [];
            foreach ($sheet->xpath('/x:worksheet/x:sheetData/x:row/x:c') ?: [] as $cell) {
                $coordinate = (string) $cell['r'];
                if (! preg_match('/^(B|C|H)\d+$/', $coordinate) || ! in_array((string) $cell['t'], ['', 'n'], true)) {
                    continue;
                }

                $value = $cell->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->v ?? null;
                $normalized = $value ? $this->normalizeNumericIdentifier((string) $value) : null;
                if ($normalized !== null) {
                    $identifiers[$coordinate] = $normalized;
                }
            }

            return $identifiers;
        } finally {
            $archive->close();
        }
    }

    private function normalizeZipPath(string $target): ?string
    {
        $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function normalizeNumericIdentifier(string $value): ?string
    {
        if (! preg_match('/^(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/', $value, $matches)) {
            return null;
        }

        $fraction = $matches[2] ?? '';
        $digits = $matches[1].$fraction;
        $decimalPosition = strlen($matches[1]) + (int) ($matches[3] ?? 0);
        if ($decimalPosition < strlen($digits) && trim(substr($digits, max(0, $decimalPosition)), '0') !== '') {
            return null;
        }

        if ($decimalPosition <= 0) {
            return null;
        }
        if ($decimalPosition > strlen($digits)) {
            $digits .= str_repeat('0', $decimalPosition - strlen($digits));
        } else {
            $digits = substr($digits, 0, $decimalPosition);
        }

        $digits = ltrim($digits, '0');

        return $digits !== '' ? $digits : '0';
    }
}
