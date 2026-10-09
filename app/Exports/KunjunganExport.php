<?php

namespace App\Exports;

use App\Models\Kunjungan;
use Illuminate\Database\Eloquent\Builder;
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
        private readonly ?int $clinicId = null,
        private readonly ?string $status = null,
    ) {}

    public function query(): Builder
    {
        return Kunjungan::with(['pasien', 'poliklinik', 'dokter'])
            ->when($this->dari, fn ($q) => $q->whereDate('tanggal', '>=', $this->dari))
            ->when($this->sampai, fn ($q) => $q->whereDate('tanggal', '<=', $this->sampai))
            ->when($this->clinicId, fn ($q) => $q->where('poliklinik_id', $this->clinicId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy('tanggal');
    }

    public function headings(): array
    {
        return [
            'No. Kunjungan', 'Tanggal', 'No. RM', 'Nama Pasien', 'Poliklinik',
            'Dokter', 'Kategori Peserta', 'Unit Kerja', 'Cost Center', 'Status', 'Verifikasi Hak Layanan',
        ];
    }

    public function map($kunjungan): array
    {
        return array_map(fn ($value) => is_string($value) && preg_match('/^[=+@-]/', $value) ? chr(39).$value : $value, [
            $kunjungan->no_kunjungan,
            $kunjungan->tanggal->format('d/m/Y'),
            $kunjungan->pasien->no_rm,
            $kunjungan->pasien->nama,
            $kunjungan->poliklinik->nama,
            $kunjungan->dokter?->nama ?? '-',
            $kunjungan->kategori_peserta ?? 'Historis',
            $kunjungan->unit_kerja,
            $kunjungan->cost_center,
            ucfirst($kunjungan->status),
            $kunjungan->verified_at?->format('d/m/Y H:i'),
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
