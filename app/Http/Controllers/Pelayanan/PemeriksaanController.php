<?php

namespace App\Http\Controllers\Pelayanan;

use App\Http\Controllers\Controller;
use App\Models\Diagnosa;
use App\Models\Farmasi;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\ResepObat;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PemeriksaanController extends Controller
{
    public function index(Request $request): View
    {
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter', 'pemeriksaan'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('pasien', fn ($pq) => $pq->where('nama', 'like', "%$s%")->orWhere('no_rm', 'like', "%$s%")))
            ->when($request->tanggal, fn ($q, $t) => $q->whereDate('tanggal', $t))
            ->whereDate('tanggal', $request->tanggal ?? today())
            ->whereIn('status', ['pemeriksaan', 'farmasi', 'kasir', 'selesai'])
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('pelayanan.pemeriksaan.index', compact('kunjungan'));
    }

    public function show(Kunjungan $kunjungan): View
    {
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter', 'screening',
            'pemeriksaan.diagnosa', 'resep.resepObat.obat',
            'tindakanKunjungan.tindakan', 'suratMedis',
            'labHasil.laboratorium', 'rujukanInternal.dariPoli', 'rujukanInternal.kePoli',
            'informedConsent',
        ]);

        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();
        $tindakanList = Tindakan::where('is_active', true)->where('poliklinik_id', $kunjungan->poliklinik_id)->orderBy('nama')->get();
        $obatList = Obat::where('is_active', true)->where('jenis', 'obat')->orderBy('nama')->get();
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();

        return view('pelayanan.pemeriksaan.show', compact(
            'kunjungan', 'dokterList', 'tindakanList', 'obatList', 'poliklinikList'
        ));
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $data = $request->validate([
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'anamnesis' => ['nullable', 'string'],
            'pemeriksaan_fisik' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'kontrol_berikutnya' => ['nullable', 'date'],
        ]);

        $data['kunjungan_id'] = $kunjungan->id;
        $data['status'] = 'draft';

        Pemeriksaan::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

        return back()->with('success', 'Data pemeriksaan berhasil disimpan.');
    }

    public function update(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $data = $request->validate([
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'anamnesis' => ['nullable', 'string'],
            'pemeriksaan_fisik' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'kontrol_berikutnya' => ['nullable', 'date'],
        ]);

        $kunjungan->pemeriksaan()->updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

        return back()->with('success', 'Data pemeriksaan berhasil diperbarui.');
    }

    public function storeDiagnosa(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $request->validate([
            'kode_icd10' => ['required', 'string'],
            'nama_diagnosa' => ['required', 'string'],
            'jenis' => ['required', 'in:utama,tambahan'],
        ]);

        $pemeriksaan = $kunjungan->pemeriksaan()->firstOrCreate(['kunjungan_id' => $kunjungan->id], ['status' => 'draft']);

        $pemeriksaan->diagnosa()->create([
            'kode_icd10' => $request->kode_icd10,
            'nama_diagnosa' => $request->nama_diagnosa,
            'jenis' => $request->jenis,
        ]);

        return back()->with('success', 'Diagnosa berhasil ditambahkan.');
    }

    public function destroyDiagnosa(Diagnosa $diagnosa): RedirectResponse
    {
        $diagnosa->delete();

        return back()->with('success', 'Diagnosa berhasil dihapus.');
    }

    public function storeResep(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $request->validate([
            'obat_id' => ['nullable', 'exists:obat,id'],
            'nama_obat' => ['nullable', 'string'],
            'jumlah' => ['required', 'numeric', 'min:0.01'],
            'satuan' => ['nullable', 'string'],
            'aturan_pakai' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'jenis' => ['required', 'in:jadi,racikan'],
            'is_resep_luar' => ['boolean'],
        ]);

        // Get or create resep for this kunjungan
        $resep = $kunjungan->resep()->firstOrCreate(
            ['kunjungan_id' => $kunjungan->id],
            [
                'no_resep' => 'RSP-'.now()->format('Ymd').'-'.str_pad($kunjungan->id, 4, '0', STR_PAD_LEFT),
                'dokter_id' => $request->dokter_id ?? $kunjungan->dokter_id,
                'status' => 'menunggu',
            ]
        );

        $obat = $request->obat_id ? Obat::find($request->obat_id) : null;

        $resep->resepObat()->create([
            'obat_id' => $request->obat_id,
            'nama_obat' => $request->nama_obat ?? $obat?->nama,
            'jumlah' => $request->jumlah,
            'satuan' => $request->satuan ?? $obat?->satuan_kecil,
            'aturan_pakai' => $request->aturan_pakai,
            'catatan' => $request->catatan,
            'jenis' => $request->jenis,
            'is_resep_luar' => $request->boolean('is_resep_luar'),
        ]);

        return back()->with('success', 'Obat berhasil ditambahkan ke resep.');
    }

    public function destroyResep(ResepObat $resepObat): RedirectResponse
    {
        $resepObat->delete();

        return back()->with('success', 'Obat berhasil dihapus dari resep.');
    }

    public function storeTindakan(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $request->validate([
            'tindakan_id' => ['required', 'exists:tindakan,id'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
        ]);

        $tindakan = Tindakan::findOrFail($request->tindakan_id);

        $kunjungan->tindakanKunjungan()->create([
            'tindakan_id' => $tindakan->id,
            'dokter_id' => $request->dokter_id ?? $kunjungan->dokter_id,
            'jumlah' => $request->jumlah,
            'tarif' => $tindakan->tarif * $request->jumlah,
            'tarif_dokter' => $tindakan->tarif_dokter * $request->jumlah,
        ]);

        return back()->with('success', 'Tindakan berhasil ditambahkan.');
    }

    public function destroyTindakan(TindakanKunjungan $tindakanKunjungan): RedirectResponse
    {
        $tindakanKunjungan->delete();

        return back()->with('success', 'Tindakan berhasil dihapus.');
    }

    public function storeSurat(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $request->validate([
            'jenis' => ['required', 'in:sakit,sehat,rujukan,lainnya'],
            'konten' => ['nullable', 'string'],
            'nomor_surat' => ['nullable', 'string'],
            'tanggal' => ['required', 'date'],
        ]);

        $kunjungan->suratMedis()->create([
            'dokter_id' => $kunjungan->dokter_id,
            'jenis' => $request->jenis,
            'konten' => $request->konten,
            'nomor_surat' => $request->nomor_surat,
            'tanggal' => $request->tanggal,
        ]);

        return back()->with('success', 'Surat medis berhasil dibuat.');
    }

    public function storeRujukan(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $request->validate([
            'ke_poli_id' => ['required', 'exists:poliklinik,id'],
            'catatan' => ['nullable', 'string'],
        ]);

        $kunjungan->rujukanInternal()->create([
            'dari_poli_id' => $kunjungan->poliklinik_id,
            'ke_poli_id' => $request->ke_poli_id,
            'catatan' => $request->catatan,
            'status' => 'menunggu',
        ]);

        return back()->with('success', 'Rujukan internal berhasil dibuat.');
    }

    public function selesai(Kunjungan $kunjungan): RedirectResponse
    {
        if ($kunjungan->pemeriksaan) {
            $kunjungan->pemeriksaan->update(['status' => 'selesai']);
        }

        // Create farmasi if resep exists
        if ($kunjungan->resep) {
            Farmasi::firstOrCreate(
                ['kunjungan_id' => $kunjungan->id],
                ['resep_id' => $kunjungan->resep->id, 'status' => 'menunggu']
            );
            $kunjungan->update(['status' => 'farmasi']);
        } else {
            $kunjungan->update(['status' => 'kasir']);
        }

        return redirect()->route('pelayanan.pemeriksaan.index')
            ->with('success', 'Pemeriksaan selesai. Pasien diteruskan ke tahap berikutnya.');
    }

    public function searchIcd10(Request $request): JsonResponse
    {
        $query = $request->q;

        $results = Icd10::where('kode', 'like', "%$query%")
            ->orWhere('nama', 'like', "%$query%")
            ->limit(10)
            ->get(['kode', 'nama']);

        return response()->json($results);
    }
}
