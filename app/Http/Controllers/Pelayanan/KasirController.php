<?php

namespace App\Http\Controllers\Pelayanan;

use App\Http\Controllers\Controller;
use App\Models\BiayaAdmin;
use App\Models\BiayaPendaftaran;
use App\Models\Kunjungan;
use App\Models\Tagihan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KasirController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date'],
        ]);
        $date = $filters['tanggal'] ?? today()->toDateString();

        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'tagihan'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->whereHas('pasien', fn ($patientQuery) => $patientQuery
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('no_rm', 'like', "%{$search}%")))
            ->whereDate('tanggal', $date)
            ->whereIn('status', ['kasir', 'selesai'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('pelayanan/kasir/index', [
            'visits' => [
                'data' => $kunjungan->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id,
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien?->nama ?? '—',
                    'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                    'clinic' => $visit->poliklinik?->nama ?? '—',
                    'payer' => $visit->jenis_bayar,
                    'billingStatus' => $visit->tagihan?->status,
                    'receiptUrl' => $visit->tagihan?->status === 'lunas' ? route('pelayanan.kasir.kuitansi', $visit->tagihan) : null,
                ])->values(),
                'currentPage' => $kunjungan->currentPage(),
                'lastPage' => $kunjungan->lastPage(),
                'perPage' => $kunjungan->perPage(),
                'total' => $kunjungan->total(),
                'from' => $kunjungan->firstItem(),
                'to' => $kunjungan->lastItem(),
                'previousUrl' => $kunjungan->previousPageUrl(),
                'nextUrl' => $kunjungan->nextPageUrl(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'date' => $date,
            ],
        ]);
    }

    public function show(Kunjungan $kunjungan): Response
    {
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter', 'asuransi',
            'resep.resepObat.obat',
            'tindakanKunjungan.tindakan',
            'farmasi.items.obat',
            'labHasil.laboratorium',
            'tagihan.items',
        ]);

        $biayaAdminList = BiayaAdmin::where('is_active', true)->orderBy('nama')->get();

        // Calculate components if tagihan doesn't exist yet
        $komponenTagihan = $this->hitungKomponen($kunjungan);

        return Inertia::render('pelayanan/kasir/show', [
            'visit' => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'patient' => [
                    'name' => $kunjungan->pasien?->nama ?? '—',
                    'medicalRecordNumber' => $kunjungan->pasien?->no_rm ?? '—',
                    'allergies' => $kunjungan->pasien?->riwayat_alergi,
                ],
                'clinic' => $kunjungan->poliklinik?->nama ?? '—',
                'doctor' => $kunjungan->dokter?->nama,
                'payer' => $kunjungan->jenis_bayar,
                'status' => $kunjungan->status,
            ],
            'billing' => $kunjungan->tagihan ? [
                'id' => $kunjungan->tagihan->id,
                'status' => $kunjungan->tagihan->status,
                'receiptUrl' => route('pelayanan.kasir.kuitansi', $kunjungan->tagihan),
            ] : null,
            'components' => array_map(fn (array $item): array => [
                'type' => $item['jenis'],
                'referenceId' => $item['referensi_id'] ?? null,
                'name' => $item['nama'],
                'quantity' => (int) $item['jumlah'],
                'tariff' => (float) $item['tarif'],
            ], $komponenTagihan),
            'subtotal' => array_sum(array_map(fn (array $item): float => (float) $item['tarif'] * (int) $item['jumlah'], $komponenTagihan)),
            'paymentMethods' => [
                ['value' => 'tunai', 'label' => 'Tunai / Cash'],
                ['value' => 'transfer', 'label' => 'Transfer Bank'],
                ['value' => 'qris', 'label' => 'QRIS'],
                ['value' => 'bpjs', 'label' => 'BPJS (Klaim)'],
                ['value' => 'asuransi', 'label' => 'Asuransi Swasta'],
            ],
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $data = $request->validate([
            'metode_bayar' => ['required', 'in:tunai,transfer,bpjs,asuransi,qris'],
            'bayar' => ['required', 'numeric', 'min:0'],
            'diskon' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama' => ['required', 'string'],
            'items.*.jenis' => ['required', 'string'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
            'items.*.tarif' => ['required', 'numeric', 'min:0'],
        ]);

        // Calculate totals
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $subtotal += $item['tarif'] * $item['jumlah'];
        }

        $diskon = $data['diskon'] ?? 0;
        if ($diskon > $subtotal) {
            throw ValidationException::withMessages([
                'diskon' => 'Diskon tidak boleh melebihi subtotal tagihan.',
            ]);
        }

        $total = $subtotal - $diskon;
        $kembalian = max(0, $data['bayar'] - $total);

        $noTagihan = 'TGH-'.now()->format('Ymd').'-'.str_pad($kunjungan->id, 4, '0', STR_PAD_LEFT);

        $tagihan = DB::transaction(function () use ($data, $diskon, $kunjungan, $kembalian, $noTagihan, $subtotal, $total): Tagihan {
            $lockedVisit = Kunjungan::query()->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            if ($lockedVisit->status !== 'kasir' || $lockedVisit->tagihan()->where('status', 'lunas')->exists()) {
                throw ValidationException::withMessages([
                    'kunjungan' => 'Tagihan kunjungan sudah lunas atau tidak lagi tersedia untuk pembayaran.',
                ]);
            }

            $tagihan = Tagihan::create([
                'no_tagihan' => $noTagihan,
                'kunjungan_id' => $lockedVisit->id,
                'kasir_id' => auth()->user()->nakes?->id,
                'subtotal' => $subtotal,
                'diskon' => $diskon,
                'total' => $total,
                'bayar' => $data['bayar'],
                'kembalian' => $kembalian,
                'metode_bayar' => $data['metode_bayar'],
                'status' => 'lunas',
            ]);

            foreach ($data['items'] as $item) {
                $tagihan->items()->create([
                    'jenis' => $item['jenis'],
                    'referensi_id' => $item['referensi_id'] ?? null,
                    'nama' => $item['nama'],
                    'jumlah' => $item['jumlah'],
                    'tarif' => $item['tarif'],
                    'total' => $item['tarif'] * $item['jumlah'],
                ]);
            }

            $lockedVisit->update(['status' => 'selesai']);

            return $tagihan;
        });

        return redirect()->route('pelayanan.kasir.kuitansi', $tagihan)
            ->with('success', 'Pembayaran berhasil. Total kembalian: Rp '.number_format($kembalian, 0, ',', '.'));
    }

    public function kuitansi(Tagihan $tagihan): Response
    {
        $tagihan->load(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.dokter', 'items', 'kasir']);

        return Inertia::render('pelayanan/kasir/kuitansi', [
            'receipt' => [
                'number' => $tagihan->no_tagihan,
                'dateTime' => $tagihan->created_at?->isoFormat('D MMMM Y, HH:mm'),
                'status' => $tagihan->status,
                'patient' => [
                    'name' => $tagihan->kunjungan?->pasien?->nama ?? '—',
                    'medicalRecordNumber' => $tagihan->kunjungan?->pasien?->no_rm ?? '—',
                    'gender' => $tagihan->kunjungan?->pasien?->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
                    'age' => $tagihan->kunjungan?->pasien?->umur,
                ],
                'clinic' => $tagihan->kunjungan?->poliklinik?->nama ?? '—',
                'doctor' => $tagihan->kunjungan?->dokter?->nama,
                'paymentMethod' => $tagihan->metode_bayar,
                'items' => $tagihan->items->map(fn ($item): array => [
                    'name' => $item->nama,
                    'quantity' => $item->jumlah,
                    'tariff' => (float) $item->tarif,
                    'total' => (float) $item->total,
                ])->values(),
                'subtotal' => (float) $tagihan->subtotal,
                'discount' => (float) $tagihan->diskon,
                'total' => (float) $tagihan->total,
                'paid' => (float) $tagihan->bayar,
                'change' => (float) $tagihan->kembalian,
                'cashier' => auth()->user()->name,
            ],
            'clinicName' => config('app.name'),
        ]);
    }

    private function hitungKomponen(Kunjungan $kunjungan): array
    {
        $items = [];

        // Biaya pendaftaran
        $biayaDaftar = BiayaPendaftaran::where('poliklinik_id', $kunjungan->poliklinik_id)
            ->where('jenis_pasien', $kunjungan->jenis_pasien)
            ->first();

        if ($biayaDaftar) {
            $items[] = [
                'jenis' => 'pendaftaran',
                'referensi_id' => $biayaDaftar->id,
                'nama' => 'Biaya Pendaftaran ('.ucfirst($kunjungan->jenis_pasien).')',
                'jumlah' => 1,
                'tarif' => $biayaDaftar->tarif,
            ];
        } else {
            // Default biaya jika tidak ada setting
            $items[] = [
                'jenis' => 'pendaftaran',
                'referensi_id' => 0,
                'nama' => 'Biaya Pendaftaran ('.ucfirst($kunjungan->jenis_pasien).')',
                'jumlah' => 1,
                'tarif' => 25000, // default
            ];
        }

        // Tindakan
        foreach ($kunjungan->tindakanKunjungan as $tk) {
            if ($tk->tindakan) {
                $items[] = [
                    'jenis' => 'tindakan',
                    'referensi_id' => $tk->id,
                    'nama' => $tk->tindakan->nama,
                    'jumlah' => $tk->jumlah,
                    'tarif' => $tk->tindakan->tarif,
                ];
            }
        }

        // Obat dari farmasi
        if ($kunjungan->farmasi) {
            foreach ($kunjungan->farmasi->items as $fi) {
                if ($fi->obat) {
                    $items[] = [
                        'jenis' => 'obat',
                        'referensi_id' => $fi->id,
                        'nama' => $fi->obat->nama,
                        'jumlah' => $fi->jumlah_diberikan,
                        'tarif' => $fi->obat->harga_jual,
                    ];
                }
            }
        }

        // Laboratorium
        foreach ($kunjungan->labHasil as $labHasil) {
            if ($labHasil->laboratorium) {
                $items[] = [
                    'jenis' => 'lab',
                    'referensi_id' => $labHasil->id,
                    'nama' => 'Lab: '.$labHasil->laboratorium->nama,
                    'jumlah' => 1,
                    'tarif' => $labHasil->laboratorium->tarif,
                ];
            }
        }

        return $items;
    }
}
