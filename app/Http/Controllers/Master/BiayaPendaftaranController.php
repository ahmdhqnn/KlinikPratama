<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\BiayaPendaftaran;
use App\Models\Nakes;
use App\Models\Poliklinik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BiayaPendaftaranController extends Controller
{
    public function index(Request $request): Response
    {
        $biayaPendaftaran = BiayaPendaftaran::with(['poliklinik', 'dokter'])
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();

        return Inertia::render('master/biaya-pendaftaran/index', [
            'fees' => [
                'data' => $biayaPendaftaran->getCollection()->map(fn (BiayaPendaftaran $fee): array => [
                    'id' => $fee->id,
                    'clinicId' => $fee->poliklinik_id,
                    'clinic' => $fee->poliklinik?->nama,
                    'doctorId' => $fee->dokter_id,
                    'doctor' => $fee->dokter?->nama,
                    'patientType' => $fee->jenis_pasien,
                    'tariff' => (float) $fee->tarif,
                ])->values(),
                'currentPage' => $biayaPendaftaran->currentPage(),
                'lastPage' => $biayaPendaftaran->lastPage(),
                'perPage' => $biayaPendaftaran->perPage(),
                'total' => $biayaPendaftaran->total(),
                'from' => $biayaPendaftaran->firstItem(),
                'to' => $biayaPendaftaran->lastItem(),
                'previousUrl' => $biayaPendaftaran->previousPageUrl(),
                'nextUrl' => $biayaPendaftaran->nextPageUrl(),
            ],
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'doctors' => $dokterList->map(fn (Nakes $doctor): array => [
                'id' => $doctor->id,
                'name' => $doctor->nama,
            ])->values(),
            'patientTypes' => [
                ['value' => 'baru', 'label' => 'Pasien baru'],
                ['value' => 'lama', 'label' => 'Pasien lama'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'jenis_pasien' => ['required', 'in:baru,lama'],
            'tarif' => ['required', 'numeric', 'min:0'],
        ]);

        BiayaPendaftaran::create($request->only(['poliklinik_id', 'dokter_id', 'jenis_pasien', 'tarif']));

        return back()->with('success', 'Biaya pendaftaran berhasil ditambahkan.');
    }

    public function update(Request $request, BiayaPendaftaran $biayaPendaftaran): RedirectResponse
    {
        $request->validate([
            'poliklinik_id' => ['nullable', 'exists:poliklinik,id'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'jenis_pasien' => ['required', 'in:baru,lama'],
            'tarif' => ['required', 'numeric', 'min:0'],
        ]);

        $biayaPendaftaran->update($request->only(['poliklinik_id', 'dokter_id', 'jenis_pasien', 'tarif']));

        return back()->with('success', 'Biaya pendaftaran berhasil diperbarui.');
    }

    public function destroy(BiayaPendaftaran $biayaPendaftaran): RedirectResponse
    {
        $biayaPendaftaran->delete();

        return back()->with('success', 'Biaya pendaftaran berhasil dihapus.');
    }
}
