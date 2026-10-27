<?php

namespace App\Exports;

use App\DateRangeFilter;
use App\DoctorExaminationReport;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\{FromQuery, WithHeadings, WithMapping, WithStyles};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PemeriksaanExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private int $doctorId, private DateRangeFilter $period, private string $search = '') {}

    public function query(): Builder
    {
        return (new DoctorExaminationReport)->query($this->doctorId, $this->period, $this->search);
    }

    public function headings(): array
    {
        return ['Tanggal', 'No. Kunjungan', 'No. RM', 'Pasien', 'Poliklinik', 'Status Pemeriksaan', 'Waktu Finalisasi', 'Diagnosis', 'Kontrol Berikutnya'];
    }

    public function map($visit): array
    {
        $exam = $visit->pemeriksaan;

        return array_map(fn ($value) => is_string($value) && preg_match('/^[=+@-]/', $value) ? chr(39).$value : $value, [
            $visit->tanggal?->format('d/m/Y'), $visit->no_kunjungan, $visit->pasien?->no_rm, $visit->pasien?->nama,
            $visit->poliklinik?->nama, $exam->signed_at ? 'Final' : 'Draf', $exam->signed_at?->format('d/m/Y H:i'),
            $exam->diagnosa->map(fn ($diagnosis) => $diagnosis->kode_icd10.' - '.$diagnosis->nama_diagnosa)->implode('; '),
            $exam->kontrol_berikutnya?->format('d/m/Y'),
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
