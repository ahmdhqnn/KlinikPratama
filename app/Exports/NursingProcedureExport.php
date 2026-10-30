<?php

namespace App\Exports;

use App\Models\TindakanKunjungan;
use App\NursingProcedureReportQuery;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NursingProcedureExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  array{dari?: ?string, sampai?: ?string, search?: ?string, status?: ?string}  $filters
     */
    public function __construct(private array $filters, private int $clinicId) {}

    public function query(): Builder
    {
        return (new NursingProcedureReportQuery)->query($this->filters, $this->clinicId);
    }

    public function headings(): array
    {
        return [
            'Tanggal kunjungan', 'No. kunjungan', 'No. RM', 'Nama pasien', 'Poliklinik', 'Dokter',
            'Tindakan perawat', 'Kode ICD-9-CM', 'Jumlah', 'Lokasi gigi', 'Instruksi dokter',
            'Status tindakan', 'Hasil pelaksanaan', 'Pelaksana', 'Waktu pelaksanaan',
        ];
    }

    /** @param TindakanKunjungan $procedure */
    public function map(mixed $procedure): array
    {
        $visit = $procedure->kunjungan;
        $values = [
            $visit?->tanggal?->format('d/m/Y'),
            $visit?->no_kunjungan,
            $visit?->pasien?->no_rm,
            $visit?->pasien?->nama,
            $visit?->poliklinik?->nama,
            $visit?->dokter?->nama,
            $procedure->nama_tindakan ?? $procedure->nama_tindakan_manual ?? $procedure->tindakan?->nama,
            $procedure->kode_icd9cm,
            $procedure->jumlah,
            $procedure->tooth_fdi,
            $procedure->catatan,
            $procedure->execution_status === 'completed' ? 'Selesai' : 'Menunggu tindakan',
            $procedure->execution_result,
            $procedure->performedBy?->name,
            $procedure->performed_at?->format('d/m/Y H:i'),
        ];

        return array_map(fn ($value) => is_string($value) && preg_match('/^[=+@-]/', $value) ? chr(39).$value : $value, $values);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
