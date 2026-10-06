<?php

namespace App\Exports;

use App\Models\Pasien;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PasienExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return Pasien::with('asuransi')->orderBy('no_rm')->get();
    }

    public function headings(): array
    {
        return [
            'No. RM', 'NIK', 'Nama', 'Tanggal Lahir', 'Jenis Kelamin',
            'Golongan Darah', 'Alamat', 'Telepon', 'Pekerjaan', 'Agama',
            'Asuransi', 'No. Asuransi',
        ];
    }

    public function map($pasien): array
    {
        return [
            $pasien->no_rm,
            $pasien->nik,
            $pasien->nama,
            $pasien->tanggal_lahir?->format('d/m/Y'),
            $pasien->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
            $pasien->golongan_darah,
            $pasien->alamat,
            $pasien->telepon,
            $pasien->pekerjaan,
            $pasien->agama,
            $pasien->asuransi?->nama,
            $pasien->no_asuransi,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
