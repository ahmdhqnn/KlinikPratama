<?php

namespace App\Exports;

use App\VisitQueryService;
use App\DateRangeFilter;
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
        return (new VisitQueryService)->filtered(
            ['poliklinik_id' => $this->clinicId, 'status' => $this->status],
            period: new DateRangeFilter($this->dari, $this->sampai),
        )->with(['pasien', 'poliklinik', 'dokter'])->latest('tanggal')->latest('id');
    }

    public function headings(): array
    {
        return [
            'No. Kunjungan', 'Tanggal', 'No. RM', 'Nama Pasien', 'Poliklinik',
            'Dokter', 'Kategori Peserta', 'Unit Kerja', 'Status', 'Verifikasi Hak Layanan',
        ];
    }

    public function map($kunjungan): array
    {
        return array_map(fn ($value) => is_string($value) && preg_match('/^[=+@-]/', $value) ? chr(39).$value : $value, [
            $kunjungan->no_kunjungan,
            $kunjungan->tanggal->format('d/m/Y'),
            $kunjungan->pasien?->no_rm ?? '-',
            $kunjungan->pasien?->nama ?? '-',
            $kunjungan->poliklinik?->nama ?? '-',
            $kunjungan->dokter?->nama ?? '-',
            $kunjungan->kategori_peserta ?? 'Historis',
            $kunjungan->unit_kerja,
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
