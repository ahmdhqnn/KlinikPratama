<?php

namespace App;

use App\Models\KlinikSetting;
use Illuminate\Support\Facades\Storage;

class ClinicDocumentProfile
{
    public function data(): array
    {
        $settings = KlinikSetting::query()->first();

        return [
            'name' => 'Klinik Pratama Kementerian Pariwisata',
            'address' => $settings?->alamat ?: 'Jl. Medan Merdeka Barat No. 17, Gambir, Jakarta Pusat 10110',
            'phone' => $settings?->telepon ?: '021 3838142',
            'email' => $settings?->email,
            'logoUrl' => $settings?->logo ? Storage::disk('public')->url($settings->logo) : null,
        ];
    }
}
