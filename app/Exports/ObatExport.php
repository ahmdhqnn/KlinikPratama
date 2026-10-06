<?php

namespace App\Exports;

use App\Models\Obat;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ObatExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return Obat::orderBy('nama')->get();
    }

    public function headings(): array
    {
        return [
            'Kode', 'Kode KFA', 'Nama', 'Satuan Besar', 'Satuan Kecil',
            'Konversi', 'Harga Beli', 'Harga Jual', 'Stok', 'Stok Minimum',
            'Jenis', 'Indikasi', 'Kandungan',
        ];
    }

    public function map($obat): array
    {
        return [
            $obat->kode,
            $obat->kode_kfa,
            $obat->nama,
            $obat->satuan_besar,
            $obat->satuan_kecil,
            $obat->konversi_satuan,
            $obat->harga_beli,
            $obat->harga_jual,
            $obat->stok,
            $obat->stok_minimum,
            $obat->jenis,
            $obat->indikasi,
            $obat->kandungan,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
