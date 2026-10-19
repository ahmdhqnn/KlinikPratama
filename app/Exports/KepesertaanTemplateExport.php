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
            ['Peserta Contoh ASN', '0000000000000001', '000000000000000001', 'asn', '', 'aktif', 'Unit Contoh', '', '', 1, 'Jakarta', '1990-01-01', 'L', 'Islam', '', 'Alamat contoh', '001', '002', 'Kelurahan Contoh', 'Kecamatan Contoh'],
            ['Peserta Contoh Magang', '0000000000000002', '', 'lainnya', 'magang', 'aktif', 'Unit Contoh', '', '', 1, 'Jakarta', '2000-01-01', 'P', 'Islam', '', 'Alamat contoh', '001', '002', 'Kelurahan Contoh', 'Kecamatan Contoh'],
            ['Keluarga Contoh ASN', '0000000000000003', '', 'lainnya', 'keluarga', 'aktif', 'Unit Contoh', '000000000000000001', 'anak', 1, 'Jakarta', '2010-01-01', 'P', 'Islam', '', 'Alamat contoh', '001', '002', 'Kelurahan Contoh', 'Kecamatan Contoh'],
        ];
    }

    public function headings(): array
    {
        return [
            'nama', 'nik', 'nip', 'kategori', 'jenis_lainnya', 'status_kepegawaian', 'unit_kerja',
            'nip_penanggung', 'hubungan_keluarga', 'hak_layanan', 'tempat_lahir', 'tanggal_lahir',
            'jenis_kelamin', 'agama', 'golongan_darah',
            'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan',
        ];
    }

    public function columnFormats(): array
    {
        return ['B' => NumberFormat::FORMAT_TEXT, 'C' => NumberFormat::FORMAT_TEXT, 'H' => NumberFormat::FORMAT_TEXT];
    }
}
