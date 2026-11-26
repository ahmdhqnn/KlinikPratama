<?php

namespace App\Exports;

use App\Models\Farmasi;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FarmasiDispensingExport extends SafeSpreadsheetValueBinder implements FromQuery, WithHeadings, WithMapping, WithStrictNullComparison, WithStyles
{
    public function __construct(private Builder $records) {}

    public function query(): Builder
    {
        return $this->records
            ->with(['kunjungan.pasien', 'kunjungan.poliklinik', 'items.obat', 'dispensingPharmacist'])
            ->orderByDesc('dispensed_at')->orderByDesc('id');
    }

    public function headings(): array
    {
        return ['Tanggal penyerahan', 'No. Kunjungan', 'No. RM', 'Nama pasien', 'Poliklinik', 'Obat diserahkan', 'Jumlah item obat', 'Petugas farmasi'];
    }

    public function map(mixed $record): array
    {
        if (! $record instanceof Farmasi) {
            return [];
        }
        $values = [
            $record->dispensed_at?->format('d/m/Y H:i'), $record->kunjungan?->no_kunjungan,
            $record->kunjungan?->pasien?->no_rm, $record->kunjungan?->pasien?->nama,
            $record->kunjungan?->poliklinik?->nama,
            $record->items->map(fn ($item) => $item->obat?->nama)->filter()->unique()->implode(', '),
            $record->items->count(), $record->dispensingPharmacist?->name,
        ];

        return array_map(fn (mixed $value): mixed => is_string($value) && preg_match('/^[\x00-\x20]*[=+@-]/', $value) ? chr(39).$value : $value, $values);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
