<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class KepesertaanTemplateExport implements FromArray, WithColumnFormatting, WithHeadings
{
    public function array(): array
    {
        return [
            ['Peserta Contoh ASN', '0000000000000001', '000000000000000001', 'asn', '', 'aktif', 'Bagian Umum', 1, 'Jakarta', '1990-01-01', 'L', 'Islam', '', 'Jl. Contoh No. 1, Jakarta'],
            ['Peserta Contoh Magang', '0000000000000002', '', 'lainnya', 'magang', 'aktif', 'Unit Magang', 1, 'Jakarta', '2000-01-01', 'P', 'Islam', '', 'Jl. Contoh No. 2, Jakarta'],
            ['Peserta Contoh Staf', '0000000000000003', '', 'lainnya', 'staf', 'aktif', 'Bagian Umum', 1, 'Jakarta', '1995-01-01', 'P', 'Islam', '', 'Jl. Contoh No. 3, Jakarta'],
            ['Peserta Contoh Lainnya', '0000000000000004', '', 'lainnya', 'Tenaga pendukung', 'aktif', 'Unit Pelayanan', 1, 'Jakarta', '1994-01-01', 'L', 'Islam', '', 'Jl. Contoh No. 4, Jakarta'],
        ];
    }

    public function headings(): array
    {
        return [
            'nama', 'nik', 'nip', 'kategori', 'jenis_lainnya', 'status_kepegawaian', 'unit_kerja',
            'hak_layanan', 'tempat_lahir', 'tanggal_lahir',
            'jenis_kelamin', 'agama', 'golongan_darah',
            'alamat',
        ];
    }

    public function columnFormats(): array
    {
        return ['B' => NumberFormat::FORMAT_TEXT, 'C' => NumberFormat::FORMAT_TEXT];
    }
}
