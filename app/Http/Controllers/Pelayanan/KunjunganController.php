<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Asuransi;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KunjunganController extends Controller
{
    public function index(Request $request): Response
    {
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter', 'tagihan'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('pasien', fn ($pq) => $pq->where('nama', 'like', "%$s%")->orWhere('no_rm', 'like', "%$s%")))
            ->when($request->tanggal, fn ($q, $t) => $q->whereDate('tanggal', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->poliklinik_id, fn ($q, $p) => $q->where('poliklinik_id', $p))
            ->whereDate('tanggal', $request->tanggal ?? today())
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();

        return Inertia::render('pelayanan/kunjungan/index', [
            'visits' => [
                'data' => $kunjungan->getCollection()->map(fn (Kunjungan $visit) => [
                    'id' => $visit->id,
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien?->nama ?? '—',
                    'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                    'gender' => $visit->pasien?->jenis_kelamin,
                    'clinic' => $visit->poliklinik?->nama ?? '—',
                    'doctor' => $visit->dokter?->nama,
                    'paymentType' => $visit->jenis_bayar,
                    'status' => $visit->status,
                    'billId' => $visit->tagihan?->id,
                ])->values(),
                'currentPage' => $kunjungan->currentPage(),
                'lastPage' => $kunjungan->lastPage(),
                'from' => $kunjungan->firstItem(),
                'to' => $kunjungan->lastItem(),
                'total' => $kunjungan->total(),
                'previousUrl' => $kunjungan->previousPageUrl(),
                'nextUrl' => $kunjungan->nextPageUrl(),
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'date' => $request->input('tanggal', today()->toDateString()),
                'status' => $request->string('status')->toString(),
                'clinicId' => $request->string('poliklinik_id')->toString(),
            ],
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic) => ['id' => $clinic->id, 'name' => $clinic->nama]),
        ]);
    }

    public function antrian(): Response
    {
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter'])
            ->whereDate('tanggal', today())
            ->whereNotIn('status', ['selesai', 'batal'])
            ->orderBy('created_at')
            ->get();

        $visits = $kunjungan->map(fn (Kunjungan $visit) => [
            'id' => $visit->id,
            'number' => $visit->no_kunjungan,
            'patient' => $visit->pasien?->nama ?? '—',
            'clinic' => $visit->poliklinik?->nama ?? '—',
            'doctor' => $visit->dokter?->nama,
            'status' => $visit->status,
        ]);

        return Inertia::render('pelayanan/kunjungan/antrian', [
            'visits' => $visits,
            'today' => today()->locale('id')->isoFormat('dddd, D MMMM Y'),
        ]);
    }

    public function create(Request $request): Response
    {
        $pasienId = $request->pasien_id;
        $pasien = $pasienId ? Pasien::find($pasienId) : null;

        $pasienList = Pasien::orderBy('nama')->get(['id', 'no_rm', 'nama', 'tanggal_lahir', 'jenis_kelamin']);
        $poliklinikList = Poliklinik::registrable()->orderBy('nama')->get();
        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();
        $asuransiList = Asuransi::where('is_active', true)->orderBy('nama')->get();

        return Inertia::render('pelayanan/kunjungan/form', [
            'patient' => $pasien ? ['id' => $pasien->id, 'name' => $pasien->nama, 'medicalRecordNumber' => $pasien->no_rm] : null,
            'patients' => $pasienList->map(fn (Pasien $patient) => ['id' => $patient->id, 'name' => $patient->nama, 'medicalRecordNumber' => $patient->no_rm]),
            ...$this->formOptions($poliklinikList, $dokterList, $asuransiList),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pasien_id' => ['required', 'exists:pasien,id'],
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'asuransi_id' => ['nullable', 'exists:asuransi,id'],
            'tanggal' => ['required', 'date'],
            'jenis_pasien' => ['required', 'in:baru,lama'],
            'jenis_bayar' => ['required', 'in:umum,bpjs,asuransi'],
            'catatan' => ['nullable', 'string'],
        ]);

        $data['no_kunjungan'] = Kunjungan::generateNomor();
        $data['status'] = 'menunggu';

        $kunjungan = Kunjungan::create($data);

        return redirect()->route('pelayanan.kunjungan.show', $kunjungan)
            ->with('success', "Kunjungan berhasil didaftarkan: {$kunjungan->no_kunjungan}");
    }

    public function show(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): Response
    {
        $auditRecorder->record($request, 'visit.view', $kunjungan->pasien_id, $kunjungan->id);
        $kunjungan->load([
            'pasien.asuransi', 'poliklinik', 'dokter', 'screening',
            'pemeriksaan.diagnosa', 'resep.resepObat.obat',
            'tindakanKunjungan.tindakan', 'farmasi', 'tagihan',
            'suratMedis', 'labHasil.laboratorium', 'rujukanInternal',
        ]);

        return Inertia::render('pelayanan/kunjungan/show', [
            'permissions' => [
                'editVisit' => auth()->user()->role === 'admin' && $kunjungan->status === 'menunggu',
                'viewRme' => auth()->user()->role === 'perawat',
                'processScreening' => auth()->user()->role === 'perawat',
                'processCashier' => auth()->user()->role === 'admin',
            ],
            'visit' => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'date' => $kunjungan->tanggal?->locale('id')->isoFormat('dddd, D MMMM Y'),
                'status' => $kunjungan->status,
                'clinic' => $kunjungan->poliklinik?->nama ?? '—',
                'doctor' => $kunjungan->dokter?->nama,
                'paymentType' => $kunjungan->jenis_bayar,
                'patientType' => $kunjungan->jenis_pasien,
                'notes' => $kunjungan->catatan,
                'patient' => [
                    'id' => $kunjungan->pasien?->id,
                    'name' => $kunjungan->pasien?->nama ?? '—',
                    'medicalRecordNumber' => $kunjungan->pasien?->no_rm ?? '—',
                    'gender' => $kunjungan->pasien?->jenis_kelamin,
                    'age' => $kunjungan->pasien?->umur,
                    'bloodType' => $kunjungan->pasien?->golongan_darah,
                    'allergies' => auth()->user()->role === 'perawat' ? $kunjungan->pasien?->riwayat_alergi : null,
                ],
                'progress' => [
                    ['label' => 'Pendaftaran', 'done' => true],
                    ['label' => 'Skrining tanda vital', 'done' => (bool) $kunjungan->screening],
                    ['label' => 'Pemeriksaan dokter', 'done' => $kunjungan->pemeriksaan?->status === 'selesai'],
                    ['label' => 'Farmasi', 'done' => $kunjungan->farmasi?->status === 'selesai'],
                    ['label' => 'Pembayaran kasir', 'done' => $kunjungan->status === 'selesai'],
                ],
                'billId' => $kunjungan->tagihan?->id,
            ],
        ]);
    }

    public function edit(Kunjungan $kunjungan): Response
    {
        abort_unless($kunjungan->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');

        $poliklinikList = Poliklinik::registrable()->orderBy('nama')->get();
        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();
        $asuransiList = Asuransi::where('is_active', true)->orderBy('nama')->get();

        return Inertia::render('pelayanan/kunjungan/form', [
            'visit' => [
                'id' => $kunjungan->id,
                'patient' => ['id' => $kunjungan->pasien_id, 'name' => $kunjungan->pasien?->nama ?? 'Pasien'],
                'clinicId' => $kunjungan->poliklinik_id,
                'doctorId' => $kunjungan->dokter_id,
                'date' => $kunjungan->tanggal?->toDateString(),
                'patientType' => $kunjungan->jenis_pasien,
                'paymentType' => $kunjungan->jenis_bayar,
                'insuranceId' => $kunjungan->asuransi_id,
                'notes' => $kunjungan->catatan,
            ],
            ...$this->formOptions($poliklinikList, $dokterList, $asuransiList),
            'today' => today()->toDateString(),
        ]);
    }

    public function update(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        abort_unless($kunjungan->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');

        $data = $request->validate([
            'poliklinik_id' => ['required', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'asuransi_id' => ['nullable', 'exists:asuransi,id'],
            'tanggal' => ['required', 'date'],
            'jenis_pasien' => ['required', 'in:baru,lama'],
            'jenis_bayar' => ['required', 'in:umum,bpjs,asuransi'],
            'catatan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($kunjungan, $data): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat diubah dari pendaftaran.');
            $lockedVisit->update($data);
        });

        return redirect()->route('pelayanan.kunjungan.show', $kunjungan)->with('success', 'Kunjungan berhasil diperbarui.');
    }

    public function destroy(Kunjungan $kunjungan): RedirectResponse
    {
        $result = DB::transaction(function () use ($kunjungan): string {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat dihapus.');

            if ($lockedVisit->tagihan()->exists()) {
                return 'tagihan';
            }

            if ($lockedVisit->screening()->exists() || $lockedVisit->resep()->exists() || $lockedVisit->pemeriksaan()->exists()) {
                return 'pelayanan';
            }

            $lockedVisit->delete();

            return 'deleted';
        });

        if ($result === 'tagihan') {
            return back()->with('error', 'Tidak dapat menghapus kunjungan yang sudah memiliki tagihan.');
        }

        if ($result === 'pelayanan') {
            return back()->with('error', 'Tidak dapat menghapus kunjungan yang sudah ada pelayanan medis.');
        }

        return redirect()->route('pelayanan.kunjungan.index')->with('success', 'Kunjungan berhasil dihapus.');
    }

    public function batal(Kunjungan $kunjungan): RedirectResponse
    {
        DB::transaction(function () use ($kunjungan): void {
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVisit->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat dibatalkan.');
            $lockedVisit->update(['status' => 'batal']);
        });

        return back()->with('success', 'Kunjungan berhasil dibatalkan.');
    }

    /**
     * @param  Collection<int, Poliklinik>  $poliklinikList
     * @param  Collection<int, Nakes>  $dokterList
     * @param  Collection<int, Asuransi>  $asuransiList
     * @return array{clinics: \Illuminate\Support\Collection<int, array{id: int, name: string}>, doctors: \Illuminate\Support\Collection<int, array{id: int, name: string}>, insuranceProviders: \Illuminate\Support\Collection<int, array{id: int, name: string, type: string}>}
     */
    private function formOptions(Collection $poliklinikList, Collection $dokterList, Collection $asuransiList): array
    {
        return [
            'clinics' => $poliklinikList->map(fn (Poliklinik $clinic) => ['id' => $clinic->id, 'name' => $clinic->nama]),
            'doctors' => $dokterList->map(fn (Nakes $doctor) => ['id' => $doctor->id, 'name' => $doctor->nama]),
            'insuranceProviders' => $asuransiList->map(fn (Asuransi $insurance) => ['id' => $insurance->id, 'name' => $insurance->nama, 'type' => $insurance->jenis]),
        ];
    }
}
