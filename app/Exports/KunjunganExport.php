<?php

namespace App\Exports;

use App\Models\Kunjungan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KunjunganExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly ?string $dari = null,
        private readonly ?string $sampai = null,
    ) {}

    public function query()
    {
        return Kunjungan::with(['pasien', 'poliklinik', 'dokter', 'tagihan'])
            ->when($this->dari, fn ($q) => $q->where('tanggal', '>=', $this->dari))
            ->when($this->sampai, fn ($q) => $q->where('tanggal', '<=', $this->sampai))
            ->orderBy('tanggal');
    }

    public function headings(): array
    {
        return [
            'No. Kunjungan', 'Tanggal', 'No. RM', 'Nama Pasien', 'Poliklinik',
            'Dokter', 'Jenis Bayar', 'Status', 'Total Tagihan',
        ];
    }

    public function map($kunjungan): array
    {
        return [
            $kunjungan->no_kunjungan,
            $kunjungan->tanggal->format('d/m/Y'),
            $kunjungan->pasien->no_rm,
            $kunjungan->pasien->nama,
            $kunjungan->poliklinik->nama,
            $kunjungan->dokter?->nama ?? '-',
            strtoupper($kunjungan->jenis_bayar),
            ucfirst($kunjungan->status),
            $kunjungan->tagihan?->total ?? 0,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
