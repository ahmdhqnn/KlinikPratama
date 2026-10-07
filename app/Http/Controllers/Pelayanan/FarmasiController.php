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
use Inertia\Inertia;
use Inertia\Response;

class FarmasiController extends Controller
{
    public function index(Request $request): Response
    {
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter', 'farmasi', 'resep.resepObat.obat'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('pasien', fn ($pq) => $pq->where('nama', 'like', "%$s%")->orWhere('no_rm', 'like', "%$s%")))
            ->when($request->tanggal, fn ($q, $t) => $q->whereDate('tanggal', $t))
            ->whereDate('tanggal', $request->tanggal ?? today())
            ->whereIn('status', ['farmasi'])
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('pelayanan/farmasi/index', [
            'visits' => [
                'data' => $kunjungan->getCollection()->map(fn (Kunjungan $visit) => [
                    'id' => $visit->id,
                    'prescriptionNumber' => $visit->resep?->no_resep,
                    'patient' => $visit->pasien?->nama ?? '—',
                    'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                    'clinic' => $visit->poliklinik?->nama ?? '—',
                    'doctor' => $visit->dokter?->nama,
                    'itemCount' => $visit->resep?->resepObat->count() ?? 0,
                    'pharmacyStatus' => $visit->farmasi?->status ?? 'menunggu',
                ])->values(),
                'currentPage' => $kunjungan->currentPage(),
                'lastPage' => $kunjungan->lastPage(),
                'from' => $kunjungan->firstItem(),
                'to' => $kunjungan->lastItem(),
                'total' => $kunjungan->total(),
                'previousUrl' => $kunjungan->previousPageUrl(),
                'nextUrl' => $kunjungan->nextPageUrl(),
            ],
            'date' => $request->input('tanggal', today()->toDateString()),
            'search' => $request->string('search')->toString(),
        ]);
    }

    public function show(Kunjungan $kunjungan): Response
    {
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter',
            'resep.resepObat.obat',
            'farmasi.items.obat', 'farmasi.items.resepObat',
        ]);

        $obatList = Obat::where('is_active', true)->where('jenis', 'obat')->orderBy('nama')->get(['id', 'nama', 'stok', 'satuan_kecil', 'harga_jual']);

        return Inertia::render('pelayanan/farmasi/show', [
            'visit' => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'status' => $kunjungan->status,
                'prescriptionNumber' => $kunjungan->resep?->no_resep,
                'patient' => [
                    'id' => $kunjungan->pasien->id,
                    'name' => $kunjungan->pasien->nama,
                    'medicalRecordNumber' => $kunjungan->pasien->no_rm,
                    'allergies' => $kunjungan->pasien->riwayat_alergi,
                ],
                'doctor' => $kunjungan->dokter?->nama,
                'pharmacy' => $kunjungan->farmasi ? [
                    'status' => $kunjungan->farmasi->status,
                    'notes' => $kunjungan->farmasi->catatan,
                    'items' => $kunjungan->farmasi->items->map(fn ($item) => [
                        'prescriptionItemId' => $item->resep_obat_id,
                        'medicineId' => $item->obat_id,
                        'dispensedQuantity' => (float) $item->jumlah_diberikan,
                        'instructions' => $item->aturan_pakai,
                        'notes' => $item->catatan,
                    ])->values(),
                ] : null,
                'prescriptionItems' => $kunjungan->resep?->resepObat->map(function (ResepObat $item) use ($kunjungan): array {
                    $pharmacyItem = $kunjungan->farmasi?->items->firstWhere('resep_obat_id', $item->id);

                    return [
                        'id' => $item->id,
                        'medicineId' => $item->obat_id,
                        'name' => $item->nama_obat,
                        'type' => $item->jenis,
                        'requestedQuantity' => (float) $item->jumlah,
                        'dispensedQuantity' => $pharmacyItem?->jumlah_diberikan !== null
                            ? (float) $pharmacyItem->jumlah_diberikan
                            : ($item->is_resep_luar ? 0 : (float) $item->jumlah),
                        'unit' => $item->satuan,
                        'instructions' => $pharmacyItem?->aturan_pakai ?? $item->aturan_pakai,
                        'external' => $item->is_resep_luar,
                        'reserved' => $item->stok_dikurangi,
                        'stock' => (float) ($item->obat?->stok ?? 0),
                        'stockUnit' => $item->obat?->satuan_kecil,
                    ];
                })->values() ?? collect(),
            ],
            'today' => today()->format('d/m/Y'),
            'appName' => config('app.name'),
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.resep_obat_id' => ['nullable', 'integer'],
            'items.*.obat_id' => ['nullable', 'integer', 'exists:obat,id'],
            'items.*.jumlah_diberikan' => ['required', 'numeric', 'min:0'],
            'items.*.aturan_pakai' => ['nullable', 'string'],
            'items.*.catatan' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        $prescriptionItems = $kunjungan->resep?->resepObat ?? collect();

        foreach ($data['items'] as $index => $item) {
            $resepObat = ! empty($item['resep_obat_id'])
                ? $prescriptionItems->firstWhere('id', (int) $item['resep_obat_id'])
                : null;

            if (! empty($item['resep_obat_id']) && ! $resepObat) {
                throw ValidationException::withMessages([
                    "items.$index.resep_obat_id" => 'Item resep tidak termasuk dalam kunjungan ini.',
                ]);
            }

            if ($resepObat?->is_resep_luar && (float) $item['jumlah_diberikan'] > 0) {
                throw ValidationException::withMessages([
                    "items.$index.jumlah_diberikan" => 'Resep luar harus ditebus di apotek luar klinik.',
                ]);
            }

            if ($item['jumlah_diberikan'] > 0) {
                $obat = ! empty($item['obat_id']) ? Obat::find($item['obat_id']) : null;
                $isReserved = $resepObat?->stok_dikurangi === true;

                if (! $obat || (! $isReserved && $obat->stok < $item['jumlah_diberikan'])) {
                    $obatName = $obat?->nama ?? 'Obat tidak ditemukan';

                    return back()->withErrors(['items' => "Stok obat {$obatName} tidak cukup. Stok tersedia: ".($obat?->stok ?? 0).', diminta: '.$item['jumlah_diberikan']])->withInput();
                }
            }
        }

        DB::transaction(function () use ($data, $kunjungan): void {
            $farmasi = Farmasi::firstOrCreate(
                ['kunjungan_id' => $kunjungan->id],
                ['resep_id' => $kunjungan->resep?->id, 'status' => 'diproses']
            );

            $farmasi->items()->delete();

            foreach ($data['items'] as $item) {
                if ((float) $item['jumlah_diberikan'] > 0 && ! empty($item['obat_id'])) {
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
                'catatan' => $data['catatan'] ?? null,
            ]);
        });

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
