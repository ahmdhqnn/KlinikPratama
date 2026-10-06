<?php

namespace App\Imports;

use App\Models\Asuransi;
use App\Models\Pasien;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PasienImport implements ToModel, WithHeadingRow
{
    private int $counter = 0;

    public function model(array $row): ?Pasien
    {
        $this->counter++;

        $noRm = 'RM-'.str_pad(Pasien::max('id') + $this->counter, 6, '0', STR_PAD_LEFT);

        $asuransiId = null;
        if (! empty($row['asuransi'])) {
            $asuransi = Asuransi::where('nama', 'like', "%{$row['asuransi']}%")->first();
            $asuransiId = $asuransi?->id;
        }

        return new Pasien([
            'no_rm' => $noRm,
            'nama' => $row['nama'],
            'nik' => $row['nik'] ?? null,
            'tanggal_lahir' => isset($row['tanggal_lahir']) ? date('Y-m-d', strtotime($row['tanggal_lahir'])) : null,
            'jenis_kelamin' => $row['jenis_kelamin'] ?? null,
            'golongan_darah' => $row['golongan_darah'] ?? null,
            'alamat' => $row['alamat'] ?? null,
            'telepon' => $row['telepon'] ?? null,
            'pekerjaan' => $row['pekerjaan'] ?? null,
            'agama' => $row['agama'] ?? null,
            'asuransi_id' => $asuransiId,
            'no_asuransi' => $row['no_asuransi'] ?? null,
        ]);
    }
}
