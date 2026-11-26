<?php

namespace App;

use App\Models\KlinikSetting;
use Illuminate\Support\Facades\Storage;

class ClinicDocumentProfile
{
    public function data(): array
    {
        $settings = KlinikSetting::query()->first();
        $customName = trim((string) ($settings?->nama_klinik ?? ''));
        $useDefaultName = $customName === ''
            || strcasecmp($customName, 'Klinik Pratama') === 0
            || str_contains(mb_strtolower($customName), 'ekonomi kreatif');

        return [
            'name' => $useDefaultName ? 'Klinik Pratama Kementerian Pariwisata' : $customName,
            'license' => filled($settings?->izin_operasional) ? $settings->izin_operasional : '6/B.6.1/31.09.86/e/2023',
            'facilityCode' => $settings?->kode_fasyankes,
            'address' => $settings?->alamat ?: 'Jl. Medan Merdeka Barat No. 17, Gambir, Jakarta Pusat 10110',
            'postalCode' => $settings?->kode_pos ?: '10110',
            'phone' => $settings?->telepon ?: '021 3838142',
            'email' => $settings?->email,
            'website' => $settings?->website,
            'headOfClinic' => $settings?->kepala_klinik,
            'headNip' => $settings?->nip_kepala,
            'logoUrl' => $settings?->logo ? Storage::disk('public')->url($settings->logo) : null,
        ];
    }
}
