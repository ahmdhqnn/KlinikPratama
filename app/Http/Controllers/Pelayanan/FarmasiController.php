<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\ResepObat;
use App\Models\StokMutasi;
use App\PersediaanRecorder;
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
            ->where(fn ($query) => $query->where('status', 'farmasi')->orWhereHas('farmasi', fn ($query) => $query->where('status', 'selesai')))
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

    public function show(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $audit): Response
    {
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter',
            'resep.resepObat.obat.batches',
            'farmasi.items.obat', 'farmasi.items.resepObat',
        ]);

        $audit->record($request, 'pharmacy.view', $kunjungan->pasien_id, $kunjungan->id);
        $movements = StokMutasi::with(['batch', 'actor', 'obat'])->where('referensi_type', 'Farmasi')->where('referensi_id', $kunjungan->farmasi?->id ?? 0)->get();

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
                'allocations' => $movements->map(fn (StokMutasi $movement): array => [
                    'medicine' => $movement->obat?->nama, 'batch' => $movement->batch?->nomor_batch,
                    'expiry' => $movement->batch?->expired_at?->format('d/m/Y'), 'quantity' => (float) $movement->jumlah,
                    'actor' => $movement->actor?->name, 'at' => $movement->created_at?->format('d/m/Y H:i'),
                ]),
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
                        'stock' => (float) ($item->obat?->batches->filter(fn ($batch): bool => $batch->status === 'tersedia' && $batch->expired_at?->gt(today()))->sum('stok') ?? 0),
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
        abort_unless($kunjungan->status === 'farmasi', 422, 'Kunjungan tidak berada pada tahap farmasi.');

        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.resep_obat_id' => ['nullable', 'integer', 'distinct'],
            'items.*.obat_id' => ['nullable', 'integer', 'exists:obat,id'],
            'items.*.jumlah_diberikan' => ['required', 'integer', 'min:0'],
            'items.*.aturan_pakai' => ['nullable', 'string'],
            'items.*.catatan' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        $prescriptionItems = $kunjungan->resep?->resepObat ?? collect();
        $submittedPrescriptionItemIds = [];

        foreach ($data['items'] as $index => $item) {
            $resepObat = ! empty($item['resep_obat_id'])
                ? $prescriptionItems->firstWhere('id', (int) $item['resep_obat_id'])
                : null;

            if (! $resepObat && empty($item['resep_obat_id']) && ! empty($item['obat_id'])) {
                $matchingItems = $prescriptionItems->where('obat_id', (int) $item['obat_id']);
                if ($matchingItems->count() > 1) {
                    throw ValidationException::withMessages([
                        "items.$index.resep_obat_id" => 'Pilih item resep untuk obat ini secara langsung.',
                    ]);
                }

                $resepObat = $matchingItems->first();
                if ($resepObat) {
                    $data['items'][$index]['resep_obat_id'] = $resepObat->id;
                }
            }

            if (! empty($item['resep_obat_id']) && ! $resepObat) {
                throw ValidationException::withMessages([
                    "items.$index.resep_obat_id" => 'Item resep tidak termasuk dalam kunjungan ini.',
                ]);
            }

            if (! $resepObat && (float) $item['jumlah_diberikan'] > 0) {
                throw ValidationException::withMessages([
                    "items.$index.resep_obat_id" => 'Obat yang diberikan harus berasal dari resep kunjungan ini.',
                ]);
            }

            if ($resepObat && in_array($resepObat->id, $submittedPrescriptionItemIds, true)) {
                throw ValidationException::withMessages([
                    "items.$index.resep_obat_id" => 'Item resep tidak boleh dicatat dua kali.',
                ]);
            }

            if ($resepObat) {
                $submittedPrescriptionItemIds[] = $resepObat->id;
            }

            if ($resepObat?->is_resep_luar && (float) $item['jumlah_diberikan'] > 0) {
                throw ValidationException::withMessages([
                    "items.$index.jumlah_diberikan" => 'Resep luar harus ditebus di apotek luar klinik.',
                ]);
            }

            if ($resepObat && (float) $item['jumlah_diberikan'] > (float) $resepObat->jumlah) {
                throw ValidationException::withMessages([
                    "items.$index.jumlah_diberikan" => 'Jumlah diberikan tidak boleh melebihi jumlah resep.',
                ]);
            }

            if ($resepObat && (float) $item['jumlah_diberikan'] > 0 && $resepObat->obat_id !== (int) ($item['obat_id'] ?? 0)) {
                throw ValidationException::withMessages([
                    "items.$index.obat_id" => 'Obat yang diberikan harus sesuai dengan item resep.',
                ]);
            }

            if ($item['jumlah_diberikan'] > 0) {
                $obat = ! empty($item['obat_id']) ? Obat::find($item['obat_id']) : null;
                $usable = $obat ? (float) $obat->batches()->usable()->sum('stok') : 0;

                if (! $obat || ! $obat->is_active || $usable < $item['jumlah_diberikan']) {
                    $obatName = $obat?->nama ?? 'Obat tidak ditemukan';

                    return back()->withErrors(['items' => "Stok obat {$obatName} tidak cukup. Stok tersedia: ".$usable.', diminta: '.$item['jumlah_diberikan']])->withInput();
                }
            }
        }

        DB::transaction(function () use ($data, $kunjungan, $request): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'farmasi', 422, 'Kunjungan tidak berada pada tahap farmasi.');

            $farmasi = Farmasi::where('kunjungan_id', $kunjungan->id)->lockForUpdate()->first();
            abort_if($farmasi?->status === 'selesai', 422, 'Dispensing sudah selesai dan tidak dapat diubah.');
            $farmasi ??= Farmasi::create([
                'kunjungan_id' => $kunjungan->id,
                'resep_id' => $kunjungan->resep?->id,
                'status' => 'diproses',
            ]);

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

            app(ClinicalAuditRecorder::class)->record($request, 'pharmacy.draft_saved', $kunjungan->pasien_id, $kunjungan->id);
            $farmasi->update([
                'status' => 'diproses',
                'catatan' => $data['catatan'] ?? null,
            ]);
        });

        return back()->with('success', 'Data farmasi berhasil disimpan.');
    }

    public function selesai(Request $request, Kunjungan $kunjungan, PersediaanRecorder $inventory, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $result = DB::transaction(function () use ($kunjungan, $request, $inventory, $audit): string {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $farmasi = Farmasi::where('kunjungan_id', $kunjungan->id)->lockForUpdate()->first();

            if (! $farmasi) {
                return 'missing';
            }

            if ($farmasi->status === 'selesai') {
                return 'already';
            }

            abort_unless($lockedVisit->status === 'farmasi', 422, 'Kunjungan tidak berada pada tahap farmasi.');

            $farmasi->load(['items.obat', 'items.resepObat']);
            $lockedVisit->loadMissing('resep.resepObat');

            foreach ($farmasi->items as $item) {
                if (floor((float) $item->jumlah_diberikan) !== (float) $item->jumlah_diberikan) {
                    throw ValidationException::withMessages([
                        'items' => 'Jumlah obat dari stok klinik harus berupa bilangan bulat.',
                    ]);
                }
            }

            foreach ($farmasi->items as $item) {
                if ($item->resep_obat_id || ! $item->obat_id) {
                    continue;
                }

                $matchingItems = $lockedVisit->resep?->resepObat->where('obat_id', $item->obat_id) ?? collect();
                if ($matchingItems->count() > 1) {
                    throw ValidationException::withMessages([
                        'items' => 'Item resep tidak jelas untuk obat '.$item->obat?->nama.'. Pilih item resep secara langsung.',
                    ]);
                }

                if ($matchingItems->count() === 1) {
                    $matchingItem = $matchingItems->first();
                    $item->update(['resep_obat_id' => $matchingItem->id]);
                    $item->setRelation('resepObat', $matchingItem);
                }
            }

            foreach ($lockedVisit->resep?->resepObat ?? collect() as $prescriptionItem) {
                $dispensed = (float) $farmasi->items->where('resep_obat_id', $prescriptionItem->id)->sum('jumlah_diberikan');
                if (! $prescriptionItem->is_resep_luar && $dispensed < (float) $prescriptionItem->jumlah && blank($farmasi->catatan)) {
                    throw ValidationException::withMessages(['catatan' => 'Catat alasan dan tindak lanjut untuk resep yang belum diserahkan seluruhnya.']);
                }
            }

            $processedPrescriptionItemIds = [];
            foreach ($farmasi->items as $item) {
                $prescriptionItem = $item->resepObat;
                if (! $prescriptionItem
                    || (int) $prescriptionItem->resep_id !== (int) $lockedVisit->resep?->id
                    || $prescriptionItem->is_resep_luar
                    || (int) $prescriptionItem->obat_id !== (int) $item->obat_id
                    || (float) $item->jumlah_diberikan > (float) $prescriptionItem->jumlah
                    || in_array($prescriptionItem->id, $processedPrescriptionItemIds, true)) {
                    throw ValidationException::withMessages([
                        'items' => 'Data dispensing tidak sesuai dengan resep kunjungan. Periksa kembali item obat.',
                    ]);
                }

                $processedPrescriptionItemIds[] = $prescriptionItem->id;
            }

            if ($lockedVisit->resep?->resepObat->contains('stok_dikurangi', true)) {
                throw ValidationException::withMessages(['items' => 'Reservasi lama harus direkonsiliasi sebelum penyerahan obat.']);
            }
            foreach ($farmasi->items->sortBy('obat_id') as $item) {
                $inventory->issue((int) $item->obat_id, (float) $item->jumlah_diberikan, $request->user(), $lockedVisit, 'Farmasi', $farmasi->id, $item->id);
            }

            $audit->record($request, 'pharmacy.dispensed', $lockedVisit->pasien_id, $lockedVisit->id);
            $farmasi->update(['status' => 'selesai', 'dispensed_by' => $request->user()->id, 'dispensed_at' => now()]);
            $lockedVisit->resep?->update(['status' => 'selesai']);
            $lockedVisit->update(['status' => 'selesai']);

            return 'complete';
        });

        if ($result === 'missing') {
            return back()->with('error', 'Data farmasi tidak ditemukan.');
        }

        if ($result === 'already') {
            return redirect()->route('pelayanan.farmasi.index')
                ->with('success', 'Dispensing kunjungan ini sudah selesai.');
        }

        return redirect()->route('pelayanan.farmasi.index')
            ->with('success', 'Obat telah diserahkan. Kunjungan selesai tanpa pembayaran pasien.');
    }
}
