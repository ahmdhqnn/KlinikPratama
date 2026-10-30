<?php

namespace App\Exports;

use App\DateRangeFilter;
use App\DoctorActivityReportQuery;
use App\Models\ResepObat;
use App\Models\TindakanKunjungan;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DoctorActivityExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private string $type,
        private int $doctorId,
        private DateRangeFilter $period,
        private string $search = '',
    ) {}

    public function query(): Builder
    {
        $reports = new DoctorActivityReportQuery;

        return $this->type === 'tindakan'
            ? $reports->treatments($this->doctorId, $this->period, $this->search)
            : $reports->medicines($this->doctorId, $this->period, $this->search);
    }

    public function headings(): array
    {
        return $this->type === 'tindakan'
            ? ['Tanggal', 'No. Kunjungan', 'No. RM', 'Nama Pasien', 'Poliklinik', 'Tindakan', 'Kode ICD-9-CM', 'Jumlah', 'Pelaksana', 'Status', 'Waktu Pelaksanaan']
            : ['Tanggal', 'No. Kunjungan', 'No. RM', 'Nama Pasien', 'Poliklinik', 'Obat', 'Jumlah', 'Satuan', 'Aturan Pakai', 'Jenis Resep', 'Status Farmasi'];
    }

    public function map(mixed $record): array
    {
        if ($record instanceof TindakanKunjungan) {
            $visit = $record->kunjungan;
            $values = [
                $visit?->tanggal?->format('d/m/Y'), $visit?->no_kunjungan, $visit?->pasien?->no_rm,
                $visit?->pasien?->nama, $visit?->poliklinik?->nama,
                $record->nama_tindakan ?: $record->nama_tindakan_manual ?: $record->tindakan?->nama,
                $record->kode_icd9cm, $record->jumlah,
                $record->execution_role === 'perawat' ? 'Perawat' : 'Dokter',
                $record->execution_status === 'completed' ? 'Selesai' : ($record->execution_role === 'perawat' ? 'Menunggu tindakan perawat' : 'Belum difinalisasi'),
                $record->performed_at?->format('d/m/Y H:i'),
            ];

            return $this->escapeFormulaValues($values);
        }

        if ($record instanceof ResepObat) {
            $visit = $record->resep?->kunjungan;
            $values = [
                $visit?->tanggal?->format('d/m/Y'), $visit?->no_kunjungan, $visit?->pasien?->no_rm,
                $visit?->pasien?->nama, $visit?->poliklinik?->nama, $record->nama_obat ?: $record->obat?->nama,
                $record->jumlah, $record->satuan, $record->aturan_pakai,
                $record->is_resep_luar ? 'Resep luar' : 'Resep klinik', $this->pharmacyStatus($record->resep?->status),
            ];

            return $this->escapeFormulaValues($values);
        }

        return [];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /** @param array<int, mixed> $values
     * @return array<int, mixed>
     */
    private function escapeFormulaValues(array $values): array
    {
        return array_map(fn (mixed $value): mixed => is_string($value) && preg_match('/^[\x00-\x20]*[=+@-]/', $value) ? chr(39).$value : $value, $values);
    }

    private function pharmacyStatus(?string $status): string
    {
        return match ($status) {
            'selesai' => 'Disiapkan farmasi',
            'diproses' => 'Sedang disiapkan',
            'batal' => 'Resep dibatalkan',
            default => 'Menunggu farmasi',
        };
    }
}
