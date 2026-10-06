<?php

namespace App\Http\Controllers\Pelayanan;

use App\Http\Controllers\Controller;
use App\Models\BiayaAdmin;
use App\Models\BiayaPendaftaran;
use App\Models\Kunjungan;
use App\Models\Tagihan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KasirController extends Controller
{
    public function index(Request $request): View
    {
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'tagihan'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('pasien', fn ($pq) => $pq->where('nama', 'like', "%$s%")->orWhere('no_rm', 'like', "%$s%")))
            ->when($request->tanggal, fn ($q, $t) => $q->whereDate('tanggal', $t))
            ->whereDate('tanggal', $request->tanggal ?? today())
            ->whereIn('status', ['kasir', 'selesai'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('pelayanan.kasir.index', compact('kunjungan'));
    }

    public function show(Kunjungan $kunjungan): View
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

        return view('pelayanan.kasir.show', compact('kunjungan', 'biayaAdminList', 'komponenTagihan'));
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $request->validate([
            'metode_bayar' => ['required', 'in:tunai,transfer,bpjs,asuransi,qris'],
            'bayar' => ['required', 'numeric', 'min:0'],
            'diskon' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array'],
            'items.*.nama' => ['required', 'string'],
            'items.*.jenis' => ['required', 'string'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
            'items.*.tarif' => ['required', 'numeric', 'min:0'],
        ]);

        // Calculate totals
        $subtotal = 0;
        foreach ($request->items as $item) {
            $subtotal += $item['tarif'] * $item['jumlah'];
        }

        $diskon = $request->diskon ?? 0;
        $total = $subtotal - $diskon;
        $kembalian = max(0, $request->bayar - $total);

        $noTagihan = 'TGH-'.now()->format('Ymd').'-'.str_pad($kunjungan->id, 4, '0', STR_PAD_LEFT);

        $tagihan = Tagihan::create([
            'no_tagihan' => $noTagihan,
            'kunjungan_id' => $kunjungan->id,
            'kasir_id' => auth()->user()->nakes?->id,
            'subtotal' => $subtotal,
            'diskon' => $diskon,
            'total' => $total,
            'bayar' => $request->bayar,
            'kembalian' => $kembalian,
            'metode_bayar' => $request->metode_bayar,
            'status' => 'lunas',
        ]);

        foreach ($request->items as $item) {
            $tagihan->items()->create([
                'jenis' => $item['jenis'],
                'referensi_id' => $item['referensi_id'] ?? null,
                'nama' => $item['nama'],
                'jumlah' => $item['jumlah'],
                'tarif' => $item['tarif'],
                'total' => $item['tarif'] * $item['jumlah'],
            ]);
        }

        $kunjungan->update(['status' => 'selesai']);

        return redirect()->route('pelayanan.kasir.kuitansi', $tagihan)
            ->with('success', 'Pembayaran berhasil. Total kembalian: Rp '.number_format($kembalian, 0, ',', '.'));
    }

    public function kuitansi(Tagihan $tagihan): View
    {
        $tagihan->load(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.dokter', 'items', 'kasir']);

        return view('pelayanan.kasir.kuitansi', compact('tagihan'));
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
