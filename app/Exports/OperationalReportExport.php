<?php

namespace App\Exports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OperationalReportExport extends DefaultValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithMapping, WithStrictNullComparison, WithStyles
{
    public function __construct(
        private readonly Builder $records,
        private readonly array $columns,
        private readonly Closure $rows,
    ) {}

    public function query(): Builder
    {
        return $this->records;
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function map($row): array
    {
        return ($this->rows)($row);
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        foreach ($this->columns as $index => $heading) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $width = match ($heading) {
                'Nama item', 'Pasien', 'Pemeriksaan', 'Petugas', 'Indikator', 'Keterangan' => 32,
                default => max(14, mb_strlen($heading) + 4),
            };
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true);

        return [1 => ['font' => ['bold' => true]]];
    }
}
