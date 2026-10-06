<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PasienTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['Budi Santoso', '3201234567890001', '1990-05-15', 'L', 'O', 'Jl. Merdeka No. 10', '081234567890', 'Karyawan Swasta', 'Islam', 'BPJS Kesehatan', '000123456789'],
        ];
    }

    public function headings(): array
    {
        return [
            'nama', 'nik', 'tanggal_lahir', 'jenis_kelamin', 'golongan_darah',
            'alamat', 'telepon', 'pekerjaan', 'agama', 'asuransi', 'no_asuransi',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'DBEAFE']]],
        ];
    }
}
