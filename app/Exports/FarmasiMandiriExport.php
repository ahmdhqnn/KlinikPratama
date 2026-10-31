<?php

namespace App\Exports;

use App\Models\FarmasiMandiri;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FarmasiMandiriExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    /** @param array{dari?: string|null, sampai?: string|null, search?: string|null} $filters */
    public function __construct(private array $filters) {}

    public function query(): Builder
    {
        return FarmasiMandiri::query()->with(['pasien', 'obat', 'petugas'])
            ->when($this->filters['dari'] ?? null, fn (Builder $query, string $date) => $query->whereDate('diserahkan_pada', '>=', $date))
            ->when($this->filters['sampai'] ?? null, fn (Builder $query, string $date) => $query->whereDate('diserahkan_pada', '<=', $date))
            ->when($this->filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $matches) => $matches
                ->where('nomor', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))
                ->orWhereHas('obat', fn (Builder $medicines) => $medicines->where('nama', 'like', "%{$search}%"))))
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
