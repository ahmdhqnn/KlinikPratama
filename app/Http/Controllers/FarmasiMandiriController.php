<?php

namespace App\Http\Controllers;

use App\AdminPharmacyAssistance;
use App\ClinicalAuditRecorder;
use App\Exports\FarmasiMandiriExport;
use App\FarmasiClinicResolver;
use App\Models\Farmasi;
use App\Models\FarmasiMandiri;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\PharmacyDirectReview;
use App\Models\ResepObat;
use App\PatientAllergyHistory;
use App\PersediaanRecorder;
use App\PharmacyStaffAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class FarmasiMandiriController extends Controller
{
    public function index(Request $request, FarmasiClinicResolver $pharmacyClinics): Response
    {
        $clinicId = $pharmacyClinics->scopedClinicIdOrFail($request->user());
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ]);
        $scopedBase = FarmasiMandiri::query()->forClinic($clinicId);
        $records = $this->reportQuery($filters)->forClinic($clinicId)->with(['pasien', 'obat', 'petugas'])
            ->latest('diserahkan_pada')->paginate(10)->withQueryString();
        $todayRecords = (clone $scopedBase)->whereDate('diserahkan_pada', today())
            ->with(['pasien', 'obat', 'petugas'])->latest('diserahkan_pada')
            ->paginate(10, ['*'], 'today_page')->withQueryString();
        $mapRecord = fn (FarmasiMandiri $record): array => [
            'number' => $record->nomor,
            'date' => $record->diserahkan_pada->format('d/m/Y H:i'),
            'patient' => $record->pasien->nama,
            'medicalRecordNumber' => $record->pasien->no_rm,
            'medicine' => $record->obat->nama,
            'quantity' => $record->jumlah,
            'unit' => $record->obat->satuan_kecil,
            'pharmacist' => $record->petugas->name,
            'printUrl' => route('farmasi.pemberian-obat.cetak', $record),
        ];
        $page = fn ($paginator): array => [
            'data' => $paginator->getCollection()->map($mapRecord)->values(),
            'currentPage' => $paginator->currentPage(), 'lastPage' => $paginator->lastPage(),
            'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total(),
            'previousUrl' => $paginator->previousPageUrl(), 'nextUrl' => $paginator->nextPageUrl(),
        ];

        return Inertia::render('farmasi/pemberian-obat/index', [
            'stats' => [
                'today' => (clone $scopedBase)->whereDate('diserahkan_pada', today())->count(),
                'patients' => (clone $scopedBase)->whereDate('diserahkan_pada', today())->distinct('pasien_id')->count('pasien_id'),
                'all' => (clone $scopedBase)->count(),
            ],
            'search' => $filters['search'] ?? '',
            'filters' => [
                'search' => $filters['search'] ?? '',
                'dari' => $filters['dari'] ?? '',
                'sampai' => $filters['sampai'] ?? '',
            ],
            'todayRecords' => $page($todayRecords),
            'records' => $page($records),
        ]);
    }

    public function create(Request $request, FarmasiClinicResolver $pharmacyClinics): Response
    {
        $clinicId = $pharmacyClinics->scopedClinicIdOrFail($request->user());
        $depoId = $clinicId !== null ? $pharmacyClinics->depoId($request->user()) : null;
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $filters['search'] ?? '';
        $patients = Pasien::query()
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")))
            ->when($search, fn (Builder $query) => $query->orderBy('nama'), fn (Builder $query) => $query->latest('created_at')->latest('id'))
            ->limit(10)->get(['id', 'nama', 'no_rm', 'riwayat_alergi']);
        $approvedMedicines = Obat::query()->where('is_active', true)->where('jenis', 'obat')->where('boleh_diberikan_langsung', true);
        $medicines = (clone $approvedMedicines)
            ->withSum(['batches as usable_stock' => fn (Builder $query) => $query->usable()->forDepo($depoId)], 'stok')
            ->orderBy('nama')->get();

        return Inertia::render('farmasi/pemberian-obat/form', [
            'adminAssistance' => $request->user()->isAdmin(),
            'requestKey' => (string) Str::uuid(),
            'search' => $search,
            'patients' => $patients->map(fn (Pasien $patient): array => ['id' => $patient->id, 'name' => $patient->nama, 'medicalRecordNumber' => $patient->no_rm, 'allergies' => app(PatientAllergyHistory::class)->forPatient($patient)['riwayat_alergi']]),
            'medicines' => $medicines->map(fn (Obat $medicine): array => ['id' => $medicine->id, 'name' => $medicine->nama, 'unit' => $medicine->satuan_kecil, 'stock' => (float) $medicine->usable_stock]),
        ]);
    }

    public function store(Request $request, PersediaanRecorder $inventory, ClinicalAuditRecorder $audit, FarmasiClinicResolver $pharmacyClinics): RedirectResponse
    {
        $assistance = app(AdminPharmacyAssistance::class)->context($request);
        $clinicId = $pharmacyClinics->scopedClinicIdOrFail($request->user());
        $depoId = $clinicId !== null ? $pharmacyClinics->depoId($request->user()) : null;
        $data = $request->validate([
            'request_key' => [$request->user()->isAdmin() ? 'required' : 'nullable', 'uuid'],
            'pasien_id' => ['required', 'integer', 'exists:pasien,id'], 'obat_id' => ['required', 'integer', 'exists:obat,id'],
            'jumlah' => ['required', 'integer', 'min:1'], 'aturan_pakai' => ['required', 'string', 'max:255'],
            'dasar_pemberian' => ['required', 'string', 'max:2000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'administrative_review' => ['required', 'accepted'], 'pharmaceutical_review' => ['required', 'accepted'], 'clinical_review' => ['required', 'accepted'],
            'review_notes' => ['required', 'string', 'min:10', 'max:3000'],
            'recipient_name' => ['required', 'string', 'max:200'], 'recipient_relationship' => ['required', 'string', 'max:200'],
            'recipient_verified' => ['required', 'accepted'], 'counseling_completed' => ['required', 'accepted'],
            'counseling_notes' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $record = DB::transaction(function () use ($data, $request, $inventory, $audit, $assistance, $clinicId, $depoId): FarmasiMandiri {
            $patient = Pasien::query()->whereKey($data['pasien_id'])->lockForUpdate()->firstOrFail();
            $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            if (! empty($data['request_key'])) {
                $existing = FarmasiMandiri::where('request_key', $data['request_key'])->lockForUpdate()->first();
                if ($existing) {
                    abort_unless($existing->petugas_id === $request->user()->id && hash_equals($existing->request_fingerprint ?? '', $fingerprint), 409, 'Identitas transaksi sudah digunakan untuk data lain.');

                    return $existing;
                }
            }
            $credential = app(PharmacyStaffAccess::class)->current($request->user());
            if ($credential?->qualification !== 'apoteker') {
                throw ValidationException::withMessages(['clinical_review' => 'Pemberian langsung memerlukan review dan penyerahan oleh apoteker terverifikasi.']);
            }
            $medicine = Obat::query()->whereKey($data['obat_id'])->where('is_active', true)->where('jenis', 'obat')
                ->where('boleh_diberikan_langsung', true)->first();
            if (! $medicine) {
                throw ValidationException::withMessages(['obat_id' => 'Obat belum disetujui untuk diberikan tanpa pemeriksaan.']);
            }
            $record = FarmasiMandiri::create([
                ...collect($data)->only(['request_key', 'pasien_id', 'obat_id', 'jumlah', 'aturan_pakai', 'dasar_pemberian', 'catatan'])->all(),
                'poliklinik_id' => $clinicId,
                'depo_id' => $depoId,
                'request_fingerprint' => $fingerprint, 'nomor' => 'FMD-'.today()->format('Ymd').'-'.Str::ulid(),
                'petugas_id' => $request->user()->id, 'diserahkan_pada' => now(),
            ]);
            PharmacyDirectReview::create(['farmasi_mandiri_id' => $record->id, 'credential_id' => $credential->id,
                'actor_id' => $request->user()->id, 'recorded_at' => now(), 'data' => [
                    'review' => collect($data)->only(['administrative_review', 'pharmaceutical_review', 'clinical_review', 'review_notes',
                        'recipient_name', 'recipient_relationship', 'recipient_verified', 'counseling_completed', 'counseling_notes'])->all(),
                    'patient_id' => $patient->id, 'allergy' => app(PatientAllergyHistory::class)->forPatient($patient),
                    'medicine_id' => $medicine->id, 'medicine_name' => $medicine->nama, 'quantity' => $data['jumlah'],
                    'instructions' => $data['aturan_pakai'], 'basis' => $data['dasar_pemberian'],
                ]]);
            $inventory->issueWithoutVisit($medicine->id, (int) $data['jumlah'], $request->user(), $patient, 'FarmasiMandiri', $record->id, $depoId);
            $audit->record($request, 'pharmacy.direct_dispensed', $patient->id, metadata: ['record_id' => $record->id, 'medicine_id' => $medicine->id, ...$assistance]);

            return $record;
        });

        return redirect()->route('farmasi.pemberian-obat.cetak', $record)->with('success', 'Pemberian obat berhasil dicatat dan stok telah diperbarui.');
    }

    public function print(Request $request, FarmasiMandiri $farmasiMandiri, ClinicalAuditRecorder $audit, FarmasiClinicResolver $pharmacyClinics): Response
    {
        $clinicId = $pharmacyClinics->scopedClinicIdOrFail($request->user());
        if ($clinicId !== null && $farmasiMandiri->poliklinik_id !== null && (int) $farmasiMandiri->poliklinik_id !== $clinicId) {
            abort(404);
        }
        $farmasiMandiri->load(['pasien', 'obat', 'petugas']);
        $audit->record($request, 'pharmacy_direct.print', $farmasiMandiri->pasien_id, metadata: ['dispensing_id' => $farmasiMandiri->id]);

        return Inertia::render('farmasi/pemberian-obat/cetak', ['record' => [
            'number' => $farmasiMandiri->nomor, 'date' => $farmasiMandiri->diserahkan_pada->format('d/m/Y H:i'),
            'patient' => $farmasiMandiri->pasien->nama, 'medicalRecordNumber' => $farmasiMandiri->pasien->no_rm,
            'medicine' => $farmasiMandiri->obat->nama, 'quantity' => $farmasiMandiri->jumlah, 'unit' => $farmasiMandiri->obat->satuan_kecil,
            'instruction' => $farmasiMandiri->aturan_pakai, 'basis' => $farmasiMandiri->dasar_pemberian, 'notes' => $farmasiMandiri->catatan, 'pharmacist' => $farmasiMandiri->petugas->name,
        ], 'appName' => config('app.name'), 'backUrl' => $request->query('asal') === 'profil' && ! $farmasiMandiri->pasien->trashed()
            ? route('pelayanan.pasien.show', [$farmasiMandiri->pasien_id, 'bagian' => 'obat-langsung'])
            : route('farmasi.pemberian-obat.index')]);
    }

    public function patients(Request $request, FarmasiClinicResolver $pharmacyClinics): Response
    {
        $assignedClinicId = $pharmacyClinics->scopedClinicIdOrFail($request->user());
        $assignedPoliType = $assignedClinicId !== null ? $pharmacyClinics->clinicType($request->user()) : null;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'poli' => ['nullable', 'in:umum,gigi'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ]);
        $poli = $assignedPoliType ?? ($filters['poli'] ?? null);
        $completed = Farmasi::query()->where('status', 'selesai')
            ->whereHas('kunjungan', fn (Builder $query) => $query->forClinicType($poli));
        $visits = Kunjungan::query()->forClinicType($poli)->whereHas('farmasi', fn (Builder $query) => $query->where('status', 'selesai'))
            ->with(['pasien', 'poliklinik', 'farmasi'])
            ->when($filters['dari'] ?? null, fn (Builder $query, string $date) => $query->whereDate('tanggal', '>=', $date))
            ->when($filters['sampai'] ?? null, fn (Builder $query, string $date) => $query->whereDate('tanggal', '<=', $date))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('pasien', fn (Builder $patients) => $patients
                ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")))
            ->latest('tanggal')->latest('id')->paginate(10)->withQueryString();
        $directQuery = FarmasiMandiri::query()
            ->when($assignedClinicId !== null, fn (Builder $query) => $query->forClinic($assignedClinicId))
            ->when($assignedClinicId === null && $poli !== null, fn (Builder $query) => $query->whereHas('poliklinik', fn (Builder $clinic) => $clinic->where('jenis', $poli)));
        $directRecords = (clone $directQuery)->with(['pasien', 'obat'])
            ->when($filters['dari'] ?? null, fn (Builder $query, string $date) => $query->whereDate('diserahkan_pada', '>=', $date))
            ->when($filters['sampai'] ?? null, fn (Builder $query, string $date) => $query->whereDate('diserahkan_pada', '<=', $date))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('pasien', fn (Builder $patients) => $patients
                ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")))
            ->latest('diserahkan_pada')->limit(10)->get();

        return Inertia::render('farmasi/pasien', [
            'search' => $filters['search'] ?? '',
            'filters' => [
                'search' => $filters['search'] ?? '',
                'dari' => $filters['dari'] ?? '',
                'sampai' => $filters['sampai'] ?? '',
            ],
            'clinicType' => $poli,
            'stats' => [
                'prescriptionsToday' => (clone $completed)->whereDate('dispensed_at', today())->count(),
                'directToday' => (clone $directQuery)->whereDate('diserahkan_pada', today())->count(),
                'allPrescriptions' => $completed->count(),
            ],
            'directRecords' => $directRecords->map(fn (FarmasiMandiri $record): array => [
                'number' => $record->nomor, 'date' => $record->diserahkan_pada->format('d/m/Y H:i'),
                'patient' => $record->pasien->nama, 'medicalRecordNumber' => $record->pasien->no_rm,
                'profileUrl' => $record->pasien->trashed() ? null : route('pelayanan.pasien.show', [$record->pasien, 'bagian' => 'obat-langsung']),
                'medicine' => $record->obat->nama, 'quantity' => $record->jumlah, 'unit' => $record->obat->satuan_kecil,
            ])->values(),
            'visits' => [
                'data' => $visits->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id, 'date' => $visit->tanggal?->format('d/m/Y'), 'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien?->nama ?? 'Belum diisi', 'medicalRecordNumber' => $visit->pasien?->no_rm ?? 'Belum diisi',
                    'profileUrl' => $visit->pasien ? route('pelayanan.pasien.show', [$visit->pasien, 'bagian' => 'obat']) : null,
                    'clinic' => $visit->poliklinik?->nama ?? 'Belum diisi', 'dispensedAt' => $visit->farmasi?->dispensed_at?->format('d/m/Y H:i'),
                    'detailUrl' => route('farmasi.pasien.kunjungan', $visit),
                ])->values(),
                'currentPage' => $visits->currentPage(), 'lastPage' => $visits->lastPage(), 'from' => $visits->firstItem(), 'to' => $visits->lastItem(),
                'total' => $visits->total(), 'previousUrl' => $visits->previousPageUrl(), 'nextUrl' => $visits->nextPageUrl(),
            ],
        ]);
    }

    public function patientVisit(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $audit, FarmasiClinicResolver $pharmacyClinics): Response
    {
        $pharmacyClinics->authorizeVisit($request->user(), $kunjungan);
        $kunjungan->load([
            'pasien', 'poliklinik', 'dokter', 'resep.resepObat.farmasiResepLuar',
            'farmasi.items', 'farmasi.dispensingPharmacist',
        ]);
        abort_unless($kunjungan->farmasi?->status === 'selesai', 404);
        $audit->record($request, 'pharmacy_dispensing.view', $kunjungan->pasien_id, $kunjungan->id);

        return Inertia::render('farmasi/pasien-kunjungan', [
            'backUrl' => match ($request->query('asal')) {
                'laporan' => route('farmasi.laporan'),
                'penyerahan' => route('pelayanan.farmasi.index'),
                'profil' => route('pelayanan.pasien.show', [$kunjungan->pasien_id, 'bagian' => 'obat']),
                default => route('farmasi.pasien'),
            },
            'backLabel' => match ($request->query('asal')) {
                'laporan' => 'Kembali ke laporan obat',
                'penyerahan' => 'Kembali ke penyerahan obat',
                'profil' => 'Kembali ke profil pasien',
                default => 'Kembali ke pasien',
            },
            'visit' => [
                'number' => $kunjungan->no_kunjungan,
                'date' => $kunjungan->tanggal?->format('d/m/Y'),
                'patient' => $kunjungan->pasien?->nama,
                'medicalRecordNumber' => $kunjungan->pasien?->no_rm,
                'clinic' => $kunjungan->poliklinik?->nama,
                'doctor' => $kunjungan->dokter?->nama,
                'prescriptionNumber' => $kunjungan->resep?->no_resep,
                'completedAt' => $kunjungan->farmasi->dispensed_at?->format('d/m/Y H:i'),
                'pharmacist' => $kunjungan->farmasi->dispensingPharmacist?->name,
                'notes' => $kunjungan->farmasi->catatan,
                'externalPrintUrl' => route('farmasi.resep-luar.cetak', $kunjungan).'?asal=pasien',
                'items' => $kunjungan->resep?->resepObat->map(function (ResepObat $item) use ($kunjungan): array {
                    $dispensed = $kunjungan->farmasi->items->firstWhere('resep_obat_id', $item->id);

                    return [
                        'name' => $item->nama_obat,
                        'requested' => (float) $item->jumlah,
                        'dispensed' => (float) ($dispensed?->jumlah_diberikan ?? 0),
                        'unit' => $item->satuan,
                        'instructions' => $dispensed?->aturan_pakai ?? $item->aturan_pakai,
                        'external' => $item->is_resep_luar || $item->farmasiResepLuar !== null,
                    ];
                })->values() ?? [],
            ],
        ]);
    }

    public function report(Request $request, FarmasiClinicResolver $pharmacyClinics): Response
    {
        $clinicId = $pharmacyClinics->scopedClinicIdOrFail($request->user());
        $filters = $this->reportFilters($request);
        $accountId = $request->user()->isAdmin() ? null : $request->user()->id;
        $stats = $this->reportQuery($filters)->forClinic($clinicId)->when($accountId, fn (Builder $query, int $id) => $query->where('petugas_id', $id));
        $records = (clone $stats)->with(['pasien', 'obat', 'petugas'])->latest('diserahkan_pada')->latest('id')->paginate(10)->withQueryString();

        return Inertia::render('farmasi/laporan', [
            'filters' => ['dari' => $filters['dari'] ?? '', 'sampai' => $filters['sampai'] ?? '', 'search' => $filters['search'] ?? ''],
            'stats' => ['total' => (clone $stats)->count(), 'patients' => (clone $stats)->distinct('pasien_id')->count('pasien_id'), 'items' => (clone $stats)->distinct('obat_id')->count('obat_id')],
            'rows' => [
                'data' => $records->getCollection()->map(fn (FarmasiMandiri $record): array => [
                    'date' => $record->diserahkan_pada->format('d/m/Y H:i'), 'number' => $record->nomor,
                    'patient' => $record->pasien->nama, 'medicalRecordNumber' => $record->pasien->no_rm,
                    'medicine' => $record->obat->nama, 'quantity' => $record->jumlah, 'unit' => $record->obat->satuan_kecil,
                    'instruction' => $record->aturan_pakai, 'pharmacist' => $record->petugas->name,
                    'printUrl' => route('farmasi.pemberian-obat.cetak', $record),
                ])->values(),
                'currentPage' => $records->currentPage(), 'lastPage' => $records->lastPage(), 'from' => $records->firstItem(), 'to' => $records->lastItem(),
                'total' => $records->total(), 'previousUrl' => $records->previousPageUrl(), 'nextUrl' => $records->nextPageUrl(),
            ],
            'exportUrl' => route('farmasi.laporan.pemberian-langsung.export', $filters),
            'personal' => $accountId !== null,
        ]);
    }

    public function export(Request $request, FarmasiClinicResolver $pharmacyClinics)
    {
        $clinicId = $pharmacyClinics->scopedClinicIdOrFail($request->user());
        $filters = $this->reportFilters($request);
        $records = $this->reportQuery($filters)->forClinic($clinicId)
            ->when(! $request->user()->isAdmin(), fn (Builder $query) => $query->where('petugas_id', $request->user()->id));

        return Excel::download(new FarmasiMandiriExport($records), 'laporan-pemberian-obat-'.today()->format('Y-m-d').'.xlsx');
    }

    private function reportFilters(Request $request): array
    {
        $filters = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        if (! filled($filters['dari'] ?? null) && ! filled($filters['sampai'] ?? null)) {
            $filters['dari'] = today()->startOfMonth()->toDateString();
            $filters['sampai'] = today()->toDateString();
        }

        return $filters;
    }

    private function reportQuery(array $filters): Builder
    {
        return FarmasiMandiri::query()
            ->when($filters['dari'] ?? null, fn (Builder $query, string $date) => $query->whereDate('diserahkan_pada', '>=', $date))
            ->when($filters['sampai'] ?? null, fn (Builder $query, string $date) => $query->whereDate('diserahkan_pada', '<=', $date))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $matches) => $matches
                ->where('nomor', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))
                ->orWhereHas('obat', fn (Builder $medicines) => $medicines->where('nama', 'like', "%{$search}%"))));
    }
}
