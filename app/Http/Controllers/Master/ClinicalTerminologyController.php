<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Imports\ClinicalTerminologyImport;
use App\Models\ClinicalTerminology;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ClinicalTerminologyController extends Controller
{
    private const SYSTEMS = [
        'icd10_who' => 'ICD-10 WHO (diagnosis)',
        'icd10_cm' => 'ICD-10-CM (diagnosis)',
        'icd9cm_diagnosis' => 'ICD-9-CM (diagnosis)',
        'icd9cm_procedure' => 'ICD-9-CM Volume 3 (procedure)',
    ];

    public function index(): Response
    {
        $systems = ClinicalTerminology::query()
            ->select('code_system', 'release', DB::raw('count(*) as code_count'), DB::raw('max(updated_at) as imported_at'))
            ->groupBy('code_system', 'release')
            ->orderBy('code_system')
            ->orderByDesc('release')
            ->get()
            ->map(fn (ClinicalTerminology $item): array => [
                'system' => $item->code_system,
                'label' => self::SYSTEMS[$item->code_system] ?? $item->code_system,
                'release' => $item->release ?: 'Tidak ditentukan',
                'count' => (int) $item->code_count,
                'importedAt' => $item->imported_at,
            ]);

        return Inertia::render('master/terminologi/index', [
            'systems' => $systems,
            'codeSystems' => collect(self::SYSTEMS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,zip', 'max:20480'],
            'code_system' => ['required', 'string', 'max:100'],
            'custom_system' => ['nullable', 'required_if:code_system,other', 'string', 'max:100'],
            'release' => ['nullable', 'string', 'max:100'],
        ]);
        $system = $data['code_system'] === 'other' ? trim($data['custom_system']) : $data['code_system'];
        $rows = strtolower($request->file('file')->getClientOriginalExtension()) === 'zip'
            ? $this->readCmsArchive($request, $system)
            : (Excel::toCollection(new ClinicalTerminologyImport, $request->file('file'))->first() ?? collect());
        [$headerIndex, $codeColumn, $displayColumn, $typeColumn] = $this->detectColumns($rows);

        if ($headerIndex === null) {
            throw ValidationException::withMessages(['file' => 'Kolom kode dan nama/uraian tidak ditemukan. Pastikan berkas memiliki header kode (Code) dan uraian (Title/Description/Name).']);
        }

        $timestamp = now();
        $source = mb_substr($request->file('file')->getClientOriginalName(), 0, 255);
        $release = trim($data['release'] ?? '');
        $records = [];
        $invalidRows = [];
        $seenCodes = [];

        foreach ($rows->slice($headerIndex + 1) as $index => $row) {
            $code = trim((string) ($row[$codeColumn] ?? ''));
            $display = trim((string) ($row[$displayColumn] ?? ''));
            if ($code === '' && $display === '') {
                continue;
            }
            if ($code === '' || $display === '') {
                $invalidRows[] = $index + 1;
                if (count($invalidRows) >= 10) {
                    break;
                }

                continue;
            }
            if (isset($seenCodes[$code])) {
                $invalidRows[] = $index + 1;
                if (count($invalidRows) >= 10) {
                    break;
                }

                continue;
            }
            $seenCodes[$code] = true;

            $records[] = [
                'code_system' => $system,
                'release' => $release,
                'code' => mb_substr($code, 0, 100),
                'display' => $display,
                'code_type' => $this->typeForRow($typeColumn !== null ? (string) ($row[$typeColumn] ?? '') : '', $system),
                'source_file' => $source,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($invalidRows !== []) {
            throw ValidationException::withMessages(['file' => 'Periksa baris kosong/tidak lengkap atau kode duplikat: '.implode(', ', $invalidRows).'. Tidak ada perubahan yang disimpan.']);
        }
        if ($records === []) {
            throw ValidationException::withMessages(['file' => 'Tidak ditemukan baris kode terminologi yang dapat diimpor.']);
        }

        DB::transaction(function () use ($records): void {
            foreach (array_chunk($records, 500) as $chunk) {
                ClinicalTerminology::upsert($chunk, ['code_system', 'release', 'code'], ['display', 'code_type', 'source_file', 'is_active', 'updated_at']);
            }
        });

        return back()->with('success', count($records).' kode terminologi berhasil diproses dari '.$source.'.');
    }

    /** @return array{0: ?int, 1: ?int, 2: ?int, 3: ?int} */
    private function detectColumns(Collection $rows): array
    {
        $codeAliases = ['code', 'kode', 'icd10code', 'icd9code', 'diagnosiscode', 'procedurecode', 'classificationcode'];
        $displayAliases = ['title', 'description', 'display', 'name', 'nama', 'longdescription', 'longtitle', 'fullname', 'fulltitle'];
        $typeAliases = ['type', 'codetype', 'category', 'kategori'];

        foreach ($rows->take(20) as $rowIndex => $row) {
            $headers = collect($row)->map(fn (mixed $value): string => preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $value))) ?? '')->all();
            $codeIndex = $this->findAlias($headers, $codeAliases);
            $displayIndex = $this->findAlias($headers, $displayAliases);
            if ($codeIndex !== null && $displayIndex !== null) {
                return [$rowIndex, $codeIndex, $displayIndex, $this->findAlias($headers, $typeAliases)];
            }
        }

        return [null, null, null, null];
    }

    /** @param array<int, string> $headers @param array<int, string> $aliases */
    private function findAlias(array $headers, array $aliases): ?int
    {
        foreach ($headers as $index => $header) {
            if (in_array($header, $aliases, true)) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeType(string $value): ?string
    {
        $type = mb_strtolower(trim($value));

        return match (true) {
            str_contains($type, 'proced') || str_contains($type, 'tindakan') => 'procedure',
            str_contains($type, 'diagnos') => 'diagnosis',
            default => $type !== '' ? mb_substr($type, 0, 50) : null,
        };
    }

    private function defaultType(string $system): string
    {
        return str_contains($system, 'procedure') ? 'procedure' : 'diagnosis';
    }

    private function typeForRow(string $value, string $system): string
    {
        $type = $this->normalizeType($value);

        return in_array($type, ['diagnosis', 'procedure'], true) ? $type : $this->defaultType($system);
    }

    private function readCmsArchive(Request $request, string $system): Collection
    {
        if (! in_array($system, ['icd9cm_diagnosis', 'icd9cm_procedure'], true) || ! class_exists(\ZipArchive::class)) {
            throw ValidationException::withMessages(['file' => 'Berkas ZIP hanya didukung untuk arsip ICD-9-CM resmi dan memerlukan ekstensi ZIP PHP.']);
        }

        $zip = new \ZipArchive;
        if ($zip->open($request->file('file')->getRealPath()) !== true) {
            throw ValidationException::withMessages(['file' => 'Arsip ZIP tidak dapat dibuka.']);
        }

        try {
            $volume = $system === 'icd9cm_procedure' ? 'SG' : 'DX';
            $fileIndex = null;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = strtoupper($zip->getNameIndex($index) ?: '');
                if (str_ends_with($name, '_DESC_LONG_'.$volume.'.TXT')) {
                    $fileIndex = $index;
                    break;
                }
            }

            if ($fileIndex === null) {
                throw ValidationException::withMessages(['file' => 'Arsip ICD-9-CM tidak memuat file uraian panjang untuk volume yang dipilih (DX atau SG).']);
            }

            $stat = $zip->statIndex($fileIndex);
            if (! $stat || $stat['size'] > 50 * 1024 * 1024) {
                throw ValidationException::withMessages(['file' => 'Ukuran file di dalam arsip melebihi batas 50 MB.']);
            }

            $contents = $zip->getFromIndex($fileIndex);
            if (! is_string($contents)) {
                throw ValidationException::withMessages(['file' => 'File kode di dalam arsip tidak dapat dibaca.']);
            }

            $codeWidth = $volume === 'DX' ? 6 : 5;
            $rows = collect([['Code', 'Title']]);
            foreach (preg_split('/\r\n|\n|\r/', $contents) ?: [] as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $code = trim(substr($line, 0, $codeWidth));
                $display = trim(substr($line, $codeWidth));
                if ($code === '' || $display === '') {
                    continue;
                }
                $rows->push([$this->normalizeIcd9Code($code, $volume), $display]);
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function normalizeIcd9Code(string $code, string $volume): string
    {
        if (str_contains($code, '.')) {
            return $code;
        }

        $decimalPosition = $volume === 'SG' ? 2 : (str_starts_with($code, 'E') ? 4 : (str_starts_with($code, 'V') ? 3 : 3));

        return mb_strlen($code) > $decimalPosition
            ? mb_substr($code, 0, $decimalPosition).'.'.mb_substr($code, $decimalPosition)
            : $code;
    }
}
