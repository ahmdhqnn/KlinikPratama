<?php

namespace App\Exports;

use App\Models\ProcedureConsentTemplate;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProcedureConsentTemplateWorkbook extends SafeSpreadsheetValueBinder implements FromGenerator, WithColumnFormatting, WithHeadings, WithStyles
{
    public const COLUMNS = ['code', 'release', 'clinic_type', 'version', 'mode', 'trigger_condition', 'sop_reference'];

    public function __construct(private readonly ?Builder $records = null) {}

    public function headings(): array
    {
        return [...self::COLUMNS, ...ProcedureConsentTemplate::FIELDS];
    }

    public function generator(): Generator
    {
        foreach ($this->records?->cursor() ?? [] as $template) {
            yield [
                ...array_map(fn (string $column) => $template->{$column}, self::COLUMNS),
                ...array_map(fn (string $field) => $template->explanation[$field] ?? null, ProcedureConsentTemplate::FIELDS),
            ];
        }
    }

    public function columnFormats(): array
    {
        return array_fill_keys(range('A', 'N'), NumberFormat::FORMAT_TEXT);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true);

        return [1 => ['font' => ['bold' => true]]];
    }
}
