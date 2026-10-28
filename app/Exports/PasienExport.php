<?php

namespace App\Exports;

use App\Models\Pasien;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PasienExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection(): Enumerable
    {
        return Pasien::with('kepesertaan')->orderBy('no_rm')->get();
    }

    public function headings(): array
    {
        return [
            'No. RM', 'NIK', 'Nama', 'Tanggal Lahir', 'Jenis Kelamin',
            'Golongan Darah', 'Alamat', 'Telepon', 'Agama',
            'Kategori Peserta', 'Jenis Peserta Lainnya', 'NIP', 'Unit Kerja',
        ];
    }

    public function map($pasien): array
    {
        return array_map(fn ($value) => is_string($value) && preg_match('/^[=+@-]/', $value) ? chr(39).$value : $value, [
            $pasien->no_rm,
            $pasien->nik,
            $pasien->nama,
            $pasien->tanggal_lahir?->format('d/m/Y'),
            $pasien->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
            $pasien->golongan_darah,
            $pasien->alamat,
            $pasien->telepon,
            $pasien->agama,
            $pasien->kepesertaan?->categoryKey(),
            $pasien->kepesertaan?->otherTypeKey(),
            $pasien->nip ?? $pasien->kepesertaan?->nip,
            $pasien->kepesertaan?->unit_kerja ?? $pasien->unit_kerja,
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
