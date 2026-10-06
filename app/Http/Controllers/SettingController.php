<?php

namespace App\Http\Controllers;

use App\Models\KlinikSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $setting = KlinikSetting::first() ?? new KlinikSetting;

        return view('setting.index', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_klinik' => ['required', 'string', 'max:200'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'kepala_klinik' => ['nullable', 'string', 'max:200'],
            'nip_kepala' => ['nullable', 'string', 'max:50'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'website' => ['nullable', 'string', 'max:100'],
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
            'logo' => ['required', 'image', 'mimes:png', 'max:2048'], // PNG only as requested in KlikMedis tutorial
        ]);

        $setting = KlinikSetting::first();
        if ($setting && $setting->logo) {
            Storage::disk('public')->delete($setting->logo);
        }

        $path = $request->file('logo')->store('clinic', 'public');

        if ($setting) {
            $setting->update(['logo' => $path]);
        } else {
            KlinikSetting::create(['nama_klinik' => 'Klinik Pratama', 'logo' => $path]);
        }

        return back()->with('success', 'Logo klinik berhasil diperbarui (format PNG).');
    }
}
