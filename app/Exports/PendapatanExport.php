<?php

namespace App\Exports;

use App\Models\Tagihan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PendapatanExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly ?string $dari = null,
        private readonly ?string $sampai = null,
    ) {}

    public function query()
    {
        return Tagihan::with(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.dokter', 'kasir'])
            ->where('status', 'lunas')
            ->when($this->dari, fn ($q) => $q->whereDate('created_at', '>=', $this->dari))
            ->when($this->sampai, fn ($q) => $q->whereDate('created_at', '<=', $this->sampai))
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'No. Tagihan',
            'Tanggal',
            'No. Kunjungan',
            'No. RM',
            'Nama Pasien',
            'Poliklinik',
            'Dokter',
            'Subtotal',
            'Diskon',
            'Total',
            'Metode Bayar',
            'Status',
            'Kasir',
        ];
    }

    public function map($tagihan): array
    {
        return [
            $tagihan->no_tagihan,
            $tagihan->created_at->format('d/m/Y H:i'),
            $tagihan->kunjungan?->no_kunjungan ?? '-',
            $tagihan->kunjungan?->pasien?->no_rm ?? '-',
            $tagihan->kunjungan?->pasien?->nama ?? '-',
            $tagihan->kunjungan?->poliklinik?->nama ?? '-',
            $tagihan->kunjungan?->dokter?->nama ?? '-',
            $tagihan->subtotal,
            $tagihan->diskon,
            $tagihan->total,
            strtoupper($tagihan->metode_bayar ?? '-'),
            ucfirst($tagihan->status),
            $tagihan->kasir?->nama ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
