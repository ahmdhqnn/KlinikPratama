<?php

namespace App\Exports;

use App\Models\Nakes;
use App\ScreeningReportQuery;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ScreeningExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private array $filters, private Nakes $nurse) {}

    public function query(): Builder
    {
        return (new ScreeningReportQuery)->query($this->filters, $this->nurse->id, $this->nurse->poliklinik_id);
    }

    public function headings(): array
    {
        return array_merge([
            'Tanggal Kunjungan', 'No. Kunjungan', 'No. RM', 'Nama Pasien', 'Poliklinik', 'Petugas',
            'Status Pengkajian', 'Waktu Terkirim', 'Keluhan', 'Triase', 'Prioritas',
            'Sistole (mmHg)', 'Diastole (mmHg)', 'Nadi (/menit)', 'Suhu (C)', 'SpO2 (%)',
            'Respirasi (/menit)', 'Berat Badan (kg)', 'Tinggi Badan (cm)', 'Riwayat Alergi',
            'Riwayat Penyakit', 'Riwayat Obat', 'Skala Nyeri',
        ], $this->nurse->poliklinik->jenis === 'gigi' ? ['Lokasi Nyeri Gigi', 'Durasi Keluhan Gigi', 'Catatan Medis Gigi'] : []);
    }

    public function map($screening): array
    {
        $visit = $screening->kunjungan;
        $draft = $screening->submitted_at === null && ($screening->started_at !== null || in_array($visit->status, ['menunggu', 'screening'], true));
        $values = [
            $visit->tanggal?->format('d/m/Y'), $visit->no_kunjungan, $visit->pasien?->no_rm,
            $visit->pasien?->nama, $visit->poliklinik?->nama, $screening->petugas?->nama,
            $draft ? 'Draf pengkajian' : 'Pengkajian terkirim', $screening->submitted_at?->format('d/m/Y H:i'),
            $screening->keluhan, $screening->kesimpulan_triase, $screening->prioritas_layanan,
            $screening->td_sistole, $screening->td_diastole, $screening->nadi, $screening->suhu,
            $screening->spo2, $screening->respirasi, $screening->berat_badan, $screening->tinggi_badan,
            $screening->riwayat_alergi, $screening->riwayat_penyakit, $screening->riwayat_obat, $screening->skala_nyeri,
        ];
        if ($this->nurse->poliklinik->jenis === 'gigi') {
            array_push($values, $screening->lokasi_nyeri_gigi, $screening->durasi_keluhan_gigi, $screening->catatan_medis_gigi);
        }

        return array_map(fn ($value) => is_string($value) && preg_match('/^[=+@-]/', $value) ? chr(39).$value : $value, $values);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
