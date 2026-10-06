<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ObatTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['OBT001', '', 'Contoh Obat 500mg', 'Strip', 'Tablet', 10, 5000, 8000, 100, 20, 'obat', 'Untuk demam', 'Paracetamol'],
        ];
    }

    public function headings(): array
    {
        return [
            'kode', 'kode_kfa', 'nama', 'satuan_besar', 'satuan_kecil',
            'konversi_satuan', 'harga_beli', 'harga_jual', 'stok', 'stok_minimum',
            'jenis', 'indikasi', 'kandungan',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'DBEAFE']]],
        ];
    }
}
