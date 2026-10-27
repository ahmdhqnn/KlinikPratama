<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DiagnosisExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Builder $diagnoses) {}

    public function query(): Builder
    {
        return (clone $this->diagnoses)->orderByDesc('total')->orderBy('kode')->orderBy('nama');
    }

    public function headings(): array
    {
        return ['Kode ICD-10', 'Diagnosis', 'Jumlah kasus'];
    }

    public function map($diagnosis): array
    {
        return array_map(fn ($value) => is_string($value) && preg_match('/^[=+@-]/', $value) ? chr(39).$value : $value,
            [$diagnosis->kode, $diagnosis->nama, (int) $diagnosis->total]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
