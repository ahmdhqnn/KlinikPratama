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
use App\Models\StokMutasi;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            ->when(auth()->user()->role === 'dokter', fn ($q) => $q->where('dokter_id', $this->currentDoctorId()))
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('pelayanan.pemeriksaan.index', compact('kunjungan'));
    }

    public function show(Kunjungan $kunjungan): View
    {
        $this->authorizeVisit($kunjungan);
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter', 'screening',
            'pemeriksaan.diagnosa', 'resep.resepObat.obat',
            'tindakanKunjungan.tindakan', 'suratMedis',
            'labHasil.laboratorium', 'rujukanInternal.dariPoli', 'rujukanInternal.kePoli',
            'informedConsent',
        ]);

        $dokterList = auth()->user()->role === 'dokter'
            ? Nakes::whereKey($this->currentDoctorId())->get()
            : Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();
        $tindakanList = Tindakan::where('is_active', true)->where('poliklinik_id', $kunjungan->poliklinik_id)->orderBy('nama')->get();
        $obatList = Obat::where('is_active', true)->where('jenis', 'obat')->orderBy('nama')->get();
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();

        return view('pelayanan.pemeriksaan.show', compact(
            'kunjungan', 'dokterList', 'tindakanList', 'obatList', 'poliklinikList'
        ));
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
        $data = $request->validate([
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'anamnesis' => ['nullable', 'string'],
            'pemeriksaan_fisik' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'edukasi' => ['nullable', 'string'],
            'kontrol_berikutnya' => ['nullable', 'date'],
        ]);

        $data['kunjungan_id'] = $kunjungan->id;
        $data['dokter_id'] = auth()->user()->role === 'dokter'
            ? $this->currentDoctorId()
            : ($data['dokter_id'] ?? $kunjungan->dokter_id);
        $data['status'] = 'draft';

        Pemeriksaan::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

        return back()->with('success', 'Data pemeriksaan berhasil disimpan.');
    }

    public function update(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
        $data = $request->validate([
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'anamnesis' => ['nullable', 'string'],
            'pemeriksaan_fisik' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'edukasi' => ['nullable', 'string'],
            'kontrol_berikutnya' => ['nullable', 'date'],
        ]);

        $data['dokter_id'] = auth()->user()->role === 'dokter'
            ? $this->currentDoctorId()
            : ($data['dokter_id'] ?? $kunjungan->dokter_id);
        $kunjungan->pemeriksaan()->updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

        return back()->with('success', 'Data pemeriksaan berhasil diperbarui.');
    }

    public function storeDiagnosa(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
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
        $this->authorizeVisit($diagnosa->load('pemeriksaan.kunjungan')->pemeriksaan->kunjungan);
        $diagnosa->delete();

        return back()->with('success', 'Diagnosa berhasil dihapus.');
    }

    public function storeResep(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
        $data = $request->validate([
            'obat_id' => ['nullable', 'required_unless:is_resep_luar,1', 'exists:obat,id'],
            'nama_obat' => ['nullable', 'required_if:is_resep_luar,1', 'string', 'max:255'],
            'jumlah' => ['required', 'numeric', 'min:0.01'],
            'satuan' => ['nullable', 'string'],
            'aturan_pakai' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'jenis' => ['required', 'in:jadi,racikan'],
            'is_resep_luar' => ['sometimes', 'boolean'],
        ]);
        $data['is_resep_luar'] = $request->boolean('is_resep_luar');
        if ($data['is_resep_luar']) {
            $data['obat_id'] = null;
        }

        DB::transaction(function () use ($data, $kunjungan): void {
            $resep = $kunjungan->resep()->firstOrCreate(
                ['kunjungan_id' => $kunjungan->id],
                [
                    'no_resep' => 'RSP-'.now()->format('Ymd').'-'.str_pad($kunjungan->id, 4, '0', STR_PAD_LEFT),
                    'dokter_id' => auth()->user()->role === 'dokter' ? $this->currentDoctorId() : $kunjungan->dokter_id,
                    'status' => 'menunggu',
                ]
            );

            $obat = ! empty($data['obat_id']) ? Obat::whereKey($data['obat_id'])->lockForUpdate()->firstOrFail() : null;
            $isResepLuar = (bool) ($data['is_resep_luar'] ?? false);
            $stokDikurangi = false;

            if (! $isResepLuar) {
                if (! $obat || (float) $obat->stok < (float) $data['jumlah']) {
                    throw ValidationException::withMessages([
                        'jumlah' => 'Stok obat tidak mencukupi. Stok tersedia: '.($obat?->stok ?? 0).'.',
                    ]);
                }

                $stokSebelum = (float) $obat->stok;
                $obat->update(['stok' => $stokSebelum - (float) $data['jumlah']]);
                $stokDikurangi = true;
            }

            $item = $resep->resepObat()->create([
                'obat_id' => $data['obat_id'] ?? null,
                'nama_obat' => $data['nama_obat'] ?? $obat?->nama,
                'jumlah' => $data['jumlah'],
                'satuan' => $data['satuan'] ?? $obat?->satuan_kecil,
                'aturan_pakai' => $data['aturan_pakai'] ?? null,
                'catatan' => $data['catatan'] ?? null,
                'jenis' => $data['jenis'],
                'is_resep_luar' => $isResepLuar,
                'stok_dikurangi' => $stokDikurangi,
            ]);

            if ($stokDikurangi) {
                StokMutasi::create([
                    'obat_id' => $obat->id,
                    'jenis' => 'keluar',
                    'referensi_type' => 'ResepObat',
                    'referensi_id' => $item->id,
                    'jumlah' => $data['jumlah'],
                    'harga' => $obat->harga_jual,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $obat->stok,
                    'keterangan' => "Reservasi resep {$resep->no_resep}",
                ]);
            }
        });

        return back()->with('success', 'Obat berhasil ditambahkan ke resep.');
    }

    public function destroyResep(ResepObat $resepObat): RedirectResponse
    {
        $resepObat->load('resep.kunjungan', 'obat');
        $this->authorizeVisit($resepObat->resep->kunjungan);
        abort_unless($resepObat->resep->status === 'menunggu', 422, 'Resep yang sudah diproses tidak dapat diubah.');

        DB::transaction(function () use ($resepObat): void {
            if ($resepObat->stok_dikurangi && $resepObat->obat) {
                $obat = Obat::whereKey($resepObat->obat_id)->lockForUpdate()->firstOrFail();
                $stokSebelum = (float) $obat->stok;
                $obat->update(['stok' => $stokSebelum + (float) $resepObat->jumlah]);
                StokMutasi::create([
                    'obat_id' => $obat->id,
                    'jenis' => 'masuk',
                    'referensi_type' => 'ResepObat',
                    'referensi_id' => $resepObat->id,
                    'jumlah' => $resepObat->jumlah,
                    'harga' => $obat->harga_jual,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $obat->stok,
                    'keterangan' => 'Pengembalian stok karena item resep dihapus',
                ]);
            }

            $resepObat->delete();
        });

        return back()->with('success', 'Obat berhasil dihapus dari resep.');
    }

    public function storeTindakan(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
        $request->validate([
            'tindakan_id' => ['required', 'exists:tindakan,id'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
        ]);

        $tindakan = Tindakan::findOrFail($request->tindakan_id);
        abort_unless($tindakan->poliklinik_id === $kunjungan->poliklinik_id, 422, 'Tindakan tidak tersedia di poliklinik kunjungan ini.');

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
        $this->authorizeVisit($tindakanKunjungan->load('kunjungan')->kunjungan);
        $tindakanKunjungan->delete();

        return back()->with('success', 'Tindakan berhasil dihapus.');
    }

    public function storeSurat(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeVisit($kunjungan);
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
        $this->authorizeVisit($kunjungan);
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
        $this->authorizeVisit($kunjungan);
        abort_unless($kunjungan->pemeriksaan, 422, 'Simpan hasil pemeriksaan sebelum menyelesaikan kunjungan.');
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

    private function currentDoctorId(): int
    {
        $doctorId = auth()->user()->nakes?->id;
        abort_unless($doctorId, 403, 'Akun dokter belum terhubung dengan data tenaga kesehatan.');

        return $doctorId;
    }

    private function authorizeVisit(Kunjungan $kunjungan): void
    {
        if (auth()->user()->role === 'dokter') {
            abort_unless($kunjungan->dokter_id === $this->currentDoctorId(), 403, 'Kunjungan bukan tanggung jawab dokter ini.');
        }
    }
}
