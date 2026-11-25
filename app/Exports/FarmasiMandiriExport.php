<?php

namespace App\Exports;

use App\Models\FarmasiMandiri;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FarmasiMandiriExport extends SafeSpreadsheetValueBinder implements FromQuery, WithHeadings, WithMapping, WithStrictNullComparison, WithStyles
{
    public function __construct(private Builder $records) {}

    public function query(): Builder
    {
        return $this->records->with(['pasien', 'obat', 'petugas'])
            ->orderByDesc('diserahkan_pada')->orderByDesc('id');
    }

    public function headings(): array
    {
        return ['Tanggal penyerahan', 'Nomor transaksi', 'No. RM', 'Nama pasien', 'Obat', 'Jumlah', 'Satuan', 'Aturan pakai', 'Dasar pemberian', 'Petugas'];
    }

    public function map(mixed $record): array
    {
        if (! $record instanceof FarmasiMandiri) {
            return [];
        }
        $values = [
            $record->diserahkan_pada->format('d/m/Y H:i'), $record->nomor, $record->pasien->no_rm, $record->pasien->nama,
            $record->obat->nama, $record->jumlah, $record->obat->satuan_kecil, $record->aturan_pakai, $record->dasar_pemberian, $record->petugas->name,
        ];

        return array_map(fn (mixed $value): mixed => is_string($value) && preg_match('/^[\x00-\x20]*[=+@-]/', $value) ? chr(39).$value : $value, $values);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
