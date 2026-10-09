<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UtilisasiExport implements FromArray, WithHeadings
{
    public function __construct(private array $report, private string $from, private string $to) {}

    public function headings(): array
    {
        return ['Kelompok', 'Periode mulai', 'Periode sampai', 'Nama / unit', 'Cost center / kategori', 'Jumlah', 'Satuan', 'Nilai pemakaian anggaran (Rp)'];
    }

    public function array(): array
    {
        $rows = [
            ['Ringkasan', $this->from, $this->to, 'Kunjungan', '', $this->report['stats']['visits'], 'kunjungan', null],
            ['Ringkasan', $this->from, $this->to, 'Pasien unik', '', $this->report['stats']['patients'], 'pasien', null],
            ['Ringkasan', $this->from, $this->to, 'Resep diserahkan', '', $this->report['stats']['prescriptions'], 'resep', null],
        ];
        foreach ($this->report['units'] as $unit) {
            $rows[] = ['Unit kerja', $this->from, $this->to, $this->safe($unit['unit']), $this->safe($unit['costCenter']), $unit['visits'], 'kunjungan', null];
        }
        foreach ($this->report['costCenters'] as $item) {
            $rows[] = ['Pemakaian cost center', $this->from, $this->to, $this->safe($item['unit']), $this->safe($item['costCenter']), null, null, $item['cost']];
        }
        foreach ($this->report['usage'] as $item) {
            $rows[] = ['Pemakaian', $this->from, $this->to, $this->safe($item['name']), $item['type'], $item['quantity'], $this->safe($item['unit'] ?? ''), $item['cost']];
        }
        foreach ($this->report['diagnoses'] as $diagnosis) {
            $rows[] = ['Diagnosis', $this->from, $this->to, $this->safe($diagnosis['name']), $this->safe($diagnosis['code']), $diagnosis['count'], 'kunjungan', null];
        }

        return $rows;
    }

    private function safe(string $value): string
    {
        return preg_match('/^[=+@-]/', $value) ? "'".$value : $value;
    }
}
