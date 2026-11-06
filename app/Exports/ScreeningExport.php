<?php

namespace App\Exports;

use App\GeneralAssessment;
use App\Models\Nakes;
use App\Models\Screening;
use App\ScreeningReportQuery;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ScreeningExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private array $filters, private ?Nakes $nurse) {}

    public function query(): Builder
    {
        return (new ScreeningReportQuery)->query($this->filters, $this->nurse?->id, $this->nurse?->poliklinik_id ?? ($this->filters['poliklinik_id'] ?? null));
    }

    public function headings(): array
    {
        return array_merge([
            'Tanggal Kunjungan', 'No. Kunjungan', 'No. RM', 'Nama Pasien', 'Poliklinik', 'Petugas',
            'Status Pengkajian', 'Waktu Terkirim', 'Keluhan', 'Triase', 'Prioritas',
            'Sistole (mmHg)', 'Diastole (mmHg)', 'Nadi (/menit)', 'Suhu (C)', 'SpO2 (%)',
            'Respirasi (/menit)', 'Berat Badan (kg)', 'Tinggi Badan (cm)', 'Riwayat Alergi',
            'Riwayat Penyakit', 'Riwayat Obat', 'Skala Nyeri', 'Catatan keselamatan dan pengkajian tambahan',
        ], $this->nurse?->poliklinik?->jenis === 'gigi' || $this->nurse === null ? ['Lokasi Nyeri Gigi', 'Durasi Keluhan Gigi', 'Catatan Medis Gigi'] : [],
            $this->nurse?->poliklinik?->jenis !== 'gigi' ? array_keys((new Screening)->generalAssessmentSummary()) : [],
            ['Waktu Pengukuran TTV', 'Waktu Triase', 'Waktu Pengiriman Terakhir']);
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
        $summary = $screening->handoverSummary();
        // Keep structured general findings in separate cells: a combined narrative
        // can exceed Excel's cell limit and silently truncate assessment data.
        $general = $screening->generalAssessmentSummary();
        $clinical = array_diff_key($summary, $general, array_flip(['Pengirim pengkajian pertama', 'Waktu pengiriman pertama', 'Waktu pengiriman terakhir', 'Waktu pencatatan TTV', 'Petugas pengkajian TTV', 'Waktu pencatatan triase']));
        $notes = array_filter(array_slice($clinical, 0, count(Screening::SAFETY_FIELDS) + 8, true), fn ($value): bool => filled($value));
        if ($visit->poliklinik?->jenis === 'gigi') {
            $notes = [...$notes, ...array_filter(GeneralAssessment::handoverSummary($screening->general_assessment), fn ($value): bool => filled($value))];
        }
        $values[] = implode('; ', array_map(fn ($label, $value): string => $label.': '.$value, array_keys($notes), array_values($notes)));
        if ($visit->poliklinik?->jenis === 'gigi' || $this->nurse === null) {
            array_push($values, $screening->lokasi_nyeri_gigi, $screening->durasi_keluhan_gigi, $screening->catatan_medis_gigi);
        }
        if ($this->nurse?->poliklinik?->jenis !== 'gigi') {
            array_push($values, ...array_values($visit->poliklinik?->jenis === 'umum' ? $general : array_fill_keys(array_keys($general), null)));
        }
        array_push($values, $screening->vitals_measured_at?->format('d/m/Y H:i'), $screening->triaged_at?->format('d/m/Y H:i'), $screening->last_submitted_at?->format('d/m/Y H:i'));

        return array_map(fn ($value) => is_string($value) && preg_match('/^[=+@-]/', $value) ? chr(39).$value : $value, $values);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
