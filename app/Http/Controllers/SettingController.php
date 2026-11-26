<?php

namespace App\Http\Controllers;

use App\Models\KlinikSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        $setting = KlinikSetting::first() ?? new KlinikSetting;

        return Inertia::render('setting/index', [
            'setting' => [
                'namaKlinik' => $setting->nama_klinik ?? '',
                'kodeFasyankes' => $setting->kode_fasyankes ?? '',
                'izinOperasional' => $setting->izin_operasional ?? '',
                'satusehatOrgId' => $setting->satusehat_org_id ?? '',
                'alamat' => $setting->alamat ?? '',
                'kodeProvinsi' => $setting->kode_provinsi ?? '',
                'kodeKabupaten' => $setting->kode_kabupaten ?? '',
                'kodeKecamatan' => $setting->kode_kecamatan ?? '',
                'kodeKelurahan' => $setting->kode_kelurahan ?? '',
                'kodePos' => $setting->kode_pos ?? '',
                'telepon' => $setting->telepon ?? '',
                'email' => $setting->email ?? '',
                'kepalaKlinik' => $setting->kepala_klinik ?? '',
                'nipKepala' => $setting->nip_kepala ?? '',
                'tagline' => $setting->tagline ?? '',
                'website' => $setting->website ?? '',
                'pelaksanaTtv' => $setting->pelaksana_ttv ?? 'perawat',
                'logoUrl' => $setting->logo ? Storage::disk('public')->url($setting->logo) : null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_klinik' => ['required', 'string', 'max:200'],
            'kode_fasyankes' => ['nullable', 'string', 'max:32'],
            'izin_operasional' => ['nullable', 'string', 'max:100'],
            'satusehat_org_id' => ['nullable', 'string', 'max:64'],
            'alamat' => ['nullable', 'string'],
            'kode_provinsi' => ['nullable', 'digits:2'],
            'kode_kabupaten' => ['nullable', 'digits:4'],
            'kode_kecamatan' => ['nullable', 'digits:6'],
            'kode_kelurahan' => ['nullable', 'digits:10'],
            'kode_pos' => ['nullable', 'digits:5'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'kepala_klinik' => ['nullable', 'string', 'max:200'],
            'nip_kepala' => ['nullable', 'string', 'max:50'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'website' => ['nullable', 'string', 'max:100'],
            'pelaksana_ttv' => ['required', 'in:perawat,pendaftaran'],
        ]);

        $setting = KlinikSetting::first();
        if ($setting) {
            $setting->update($data);
        } else {
            KlinikSetting::create($data);
        }

        return back()->with('success', 'Pengaturan klinik berhasil disimpan.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $setting = KlinikSetting::first();
        if ($setting && $setting->logo) {
            Storage::disk('public')->delete($setting->logo);
        }

        $path = $request->file('logo')->store('clinic', 'public');

        if ($setting) {
            $setting->update(['logo' => $path]);
        } else {
            KlinikSetting::create(['nama_klinik' => 'Klinik Pratama Kementerian Pariwisata', 'logo' => $path]);
        }

        return back()->with('success', 'Logo klinik berhasil diperbarui.');
    }
}
