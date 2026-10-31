<?php

namespace App\Exports;

use App\Models\ResepObat;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FarmasiResepLuarExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Builder $records) {}

    public function query(): Builder
    {
        return $this->records->with(['obat', 'resep.kunjungan.pasien', 'resep.kunjungan.poliklinik', 'farmasiResepLuar.petugas'])
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return ['Tanggal kunjungan', 'No. resep', 'No. RM', 'Pasien', 'Poliklinik', 'Obat', 'Jumlah', 'Satuan', 'Aturan pakai', 'Asal resep luar', 'Petugas pengalihan'];
    }

    public function map(mixed $record): array
    {
        if (! $record instanceof ResepObat) {
            return [];
        }

        $values = [
            $record->resep?->kunjungan?->tanggal?->format('d/m/Y'), $record->resep?->no_resep,
            $record->resep?->kunjungan?->pasien?->no_rm, $record->resep?->kunjungan?->pasien?->nama,
            $record->resep?->kunjungan?->poliklinik?->nama, $record->nama_obat ?? $record->obat?->nama,
            $record->jumlah, $record->satuan, $record->aturan_pakai,
            $record->farmasiResepLuar ? 'Dialihkan farmasi' : 'Diresepkan dokter',
            $record->farmasiResepLuar?->petugas?->name,
        ];

        return array_map(fn (mixed $value): mixed => is_string($value) && preg_match('/^[\x00-\x20]*[=+@-]/', $value) ? chr(39).$value : $value, $values);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
