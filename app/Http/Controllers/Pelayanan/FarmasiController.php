<?php

namespace App\Http\Controllers\Pelayanan;

use App\Http\Controllers\Controller;
use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\ResepObat;
use App\Models\StokMutasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FarmasiController extends Controller
{
    public function index(Request $request): View
    {
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'farmasi', 'resep.resepObat.obat'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('pasien', fn ($pq) => $pq->where('nama', 'like', "%$s%")->orWhere('no_rm', 'like', "%$s%")))
            ->when($request->tanggal, fn ($q, $t) => $q->whereDate('tanggal', $t))
            ->whereDate('tanggal', $request->tanggal ?? today())
            ->whereIn('status', ['farmasi'])
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('pelayanan.farmasi.index', compact('kunjungan'));
    }

    public function show(Kunjungan $kunjungan): View
    {
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter',
            'resep.resepObat.obat',
            'farmasi.items.obat',
        ]);

        $obatList = Obat::where('is_active', true)->where('jenis', 'obat')->orderBy('nama')->get(['id', 'nama', 'stok', 'satuan_kecil', 'harga_jual']);

        return view('pelayanan.farmasi.show', compact('kunjungan', 'obatList'));
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.resep_obat_id' => ['nullable', 'integer'],
            'items.*.obat_id' => ['required', 'exists:obat,id'],
            'items.*.jumlah_diberikan' => ['required', 'numeric', 'min:0'],
            'items.*.aturan_pakai' => ['nullable', 'string'],
            'items.*.catatan' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        foreach ($request->items as $item) {
            if ($item['jumlah_diberikan'] > 0) {
                $obat = Obat::find($item['obat_id']);
                $resepObat = ! empty($item['resep_obat_id'])
                    ? $kunjungan->resep?->resepObat()->find($item['resep_obat_id'])
                    : null;
                $isReserved = $resepObat?->stok_dikurangi === true;

                if (! $obat || (! $isReserved && $obat->stok < $item['jumlah_diberikan'])) {
                    $obatName = $obat?->nama ?? 'Obat tidak ditemukan';

                    return back()->withErrors(['items' => "Stok obat {$obatName} tidak cukup. Stok tersedia: ".($obat?->stok ?? 0).', diminta: '.$item['jumlah_diberikan']])->withInput();
                }
            }
        }

        $farmasi = Farmasi::firstOrCreate(
            ['kunjungan_id' => $kunjungan->id],
            ['resep_id' => $kunjungan->resep?->id, 'status' => 'diproses']
        );

        $farmasi->items()->delete();

        foreach ($request->items as $item) {
            if ($item['jumlah_diberikan'] > 0) {
                $farmasi->items()->create([
                    'resep_obat_id' => $item['resep_obat_id'] ?? null,
                    'obat_id' => $item['obat_id'],
                    'jumlah_diberikan' => $item['jumlah_diberikan'],
                    'aturan_pakai' => $item['aturan_pakai'] ?? null,
                    'catatan' => $item['catatan'] ?? null,
                ]);
            }
        }

        $farmasi->update([
            'status' => 'diproses',
            'catatan' => $request->catatan,
        ]);

        return back()->with('success', 'Data farmasi berhasil disimpan.');
    }

    public function selesai(Kunjungan $kunjungan): RedirectResponse
    {
        $farmasi = $kunjungan->farmasi()->with(['items.obat', 'items.resepObat'])->first();

        if (! $farmasi) {
            return back()->with('error', 'Data farmasi tidak ditemukan.');
        }

        if ($farmasi->status === 'selesai') {
            return redirect()->route('pelayanan.farmasi.index')
                ->with('success', 'Dispensing kunjungan ini sudah selesai.');
        }

        $kunjungan->loadMissing('resep.resepObat');
        DB::transaction(function () use ($farmasi, $kunjungan): void {
            foreach ($farmasi->items as $item) {
                $reservedItem = $item->resepObat
                    ?? $kunjungan->resep?->resepObat->first(
                        fn (ResepObat $resepObat): bool => $resepObat->obat_id === $item->obat_id
                            && $resepObat->stok_dikurangi
                    );

                if ($reservedItem?->stok_dikurangi) {
                    continue;
                }

                $obat = $item->obat ? Obat::whereKey($item->obat->id)->lockForUpdate()->first() : null;
                if (! $obat || $obat->stok < $item->jumlah_diberikan) {
                    throw ValidationException::withMessages([
                        'items' => 'Stok obat '.$item->obat?->nama.' tidak mencukupi saat dispensing.',
                    ]);
                }

                $stokSebelum = (float) $obat->stok;
                $stokSesudah = $stokSebelum - (float) $item->jumlah_diberikan;
                $obat->update(['stok' => $stokSesudah]);

                StokMutasi::create([
                    'obat_id' => $obat->id,
                    'jenis' => 'keluar',
                    'referensi_type' => 'Farmasi',
                    'referensi_id' => $farmasi->id,
                    'jumlah' => $item->jumlah_diberikan,
                    'harga' => $obat->harga_jual,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'keterangan' => "Dispensing kunjungan {$kunjungan->no_kunjungan}",
                ]);
            }
        });

        $farmasi->update(['status' => 'selesai']);

        if ($kunjungan->resep) {
            $kunjungan->resep->update(['status' => 'selesai']);
        }

        $kunjungan->update(['status' => 'kasir']);

        return redirect()->route('pelayanan.farmasi.index')
            ->with('success', 'Dispensing selesai. Pasien diteruskan ke kasir.');
    }
}
