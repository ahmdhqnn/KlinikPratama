<?php

namespace App\Imports;

use App\Models\Obat;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ObatImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row): ?Obat
    {
        return new Obat([
            'kode' => $row['kode'],
            'kode_kfa' => $row['kode_kfa'] ?? null,
            'nama' => $row['nama'],
            'satuan_besar' => $row['satuan_besar'] ?? null,
            'satuan_kecil' => $row['satuan_kecil'] ?? null,
            'konversi_satuan' => $row['konversi_satuan'] ?? 1,
            'harga_beli' => $row['harga_beli'] ?? 0,
            'harga_jual' => $row['harga_jual'] ?? 0,
            'indikasi' => $row['indikasi'] ?? null,
            'kandungan' => $row['kandungan'] ?? null,
            'stok' => $row['stok'] ?? 0,
            'stok_minimum' => $row['stok_minimum'] ?? 0,
            'jenis' => $row['jenis'] ?? 'obat',
            'is_active' => true,
        ]);
    }

    public function rules(): array
    {
        return [
            'kode' => ['required'],
            'nama' => ['required'],
        ];
    }
}
