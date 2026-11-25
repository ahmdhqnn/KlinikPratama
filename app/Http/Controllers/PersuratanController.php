<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\ClinicDocumentProfile;
use App\CorrespondenceService;
use App\DoctorAccessService;
use App\InternalReferralDirectory;
use App\MedicalDocumentVerificationService;
use App\MedicalLetterData;
use App\Models\CorrespondenceTemplate;
use App\Models\Kunjungan;
use App\Models\MedicalDocumentVerification;
use App\Models\Nakes;
use App\Models\RujukanInternal;
use App\Models\SuratMedis;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PersuratanController extends Controller
{
    public function __construct(private DoctorAccessService $doctorAccess, private CorrespondenceService $correspondence) {}

    public function index(Request $request, InternalReferralDirectory $referralDirectory): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kunjungan_id' => ['nullable', 'integer', 'exists:kunjungan,id'],
            'poli' => ['nullable', 'in:umum,gigi'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
            'jenis' => ['nullable', Rule::in([...MedicalLetterData::TYPES, 'rujukan_internal'])],
        ]);
        $search = $filters['search'] ?? '';
        $user = $request->user();
        $isAdmin = $user->role === 'admin';
        $clinic = $isAdmin ? null : ($filters['poli'] ?? null);
        $visitId = isset($filters['kunjungan_id']) ? (int) $filters['kunjungan_id'] : null;
        $doctorId = $isAdmin ? null : $this->doctorId($user);
        $eligibleVisits = Kunjungan::query()->when($isAdmin, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(! $isAdmin, fn (Builder $query) => $query->where('dokter_id', $doctorId))
            ->forClinicType($clinic)
            ->when($visitId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->whereIn('status', Kunjungan::POST_EXAMINATION_STATUSES)
            ->whereHas('pemeriksaan', fn (Builder $query) => $query->whereNotNull('signed_at'))
            ->with(['pasien', 'poliklinik'])
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('no_kunjungan', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->latest('tanggal')->latest('id')->paginate(10, ['*'], 'visits_page')->withQueryString();

        $letters = SuratMedis::query()->when($isAdmin, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters['kunjungan_id'] ?? null, fn (Builder $query, int|string $visitId) => $query->where('kunjungan_id', $visitId))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('nomor_surat', 'like', "%{$search}%")
                ->orWhereHas('kunjungan', fn (Builder $visit) => $visit->where('no_kunjungan', 'like', "%{$search}%"))
                ->orWhereHas('kunjungan.pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->with(['kunjungan.pasien', 'kunjungan.poliklinik', 'dokter'])
            ->when(! $isAdmin, fn (Builder $query) => $query->whereHas('kunjungan', fn (Builder $visit) => $visit->where('dokter_id', $doctorId)->forClinicType($clinic)))
            ->latest('id')->paginate(10, ['*'], 'letters_page')->withQueryString();
        $templates = CorrespondenceTemplate::query()
            ->when($isAdmin ? ($filters['status'] ?? null) : null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'aktif'))
            ->when($isAdmin ? ($filters['jenis'] ?? null) : null, fn (Builder $query, string $type) => $query->where('jenis', $type))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%")))
            ->withCount(['suratMedis', 'rujukanInternal'])
            ->when(! $isAdmin, fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('nama')->paginate(10, ['*'], 'templates_page')->withQueryString();

        return Inertia::render('persuratan/index', [
            'isAdmin' => $isAdmin,
            'filters' => ['search' => $search, 'visitId' => $visitId, 'clinic' => $clinic ?? '', 'type' => $isAdmin ? ($filters['jenis'] ?? '') : '', 'status' => $isAdmin ? ($filters['status'] ?? '') : ''],
            'eligibleVisits' => ! $isAdmin && $eligibleVisits ? [
                'data' => $eligibleVisits->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id,
                    'date' => $visit->tanggal->format('d/m/Y'),
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien->nama,
                    'medicalRecordNumber' => $visit->pasien->no_rm,
                    'clinic' => $visit->poliklinik->nama,
                    'letterUrl' => $isAdmin ? null : route('persuratan.surat.create', $visit),
                ])->values(),
                'currentPage' => $eligibleVisits->currentPage(), 'lastPage' => $eligibleVisits->lastPage(),
                'from' => $eligibleVisits->firstItem(), 'to' => $eligibleVisits->lastItem(), 'total' => $eligibleVisits->total(),
                'previousUrl' => $eligibleVisits->previousPageUrl(), 'nextUrl' => $eligibleVisits->nextPageUrl(),
            ] : null,
            'templates' => [
                'data' => $templates->getCollection()->map(fn (CorrespondenceTemplate $template): array => [
                    'id' => $template->id,
                    'code' => $template->kode,
                    'name' => $template->nama,
                    'type' => $template->jenis,
                    'prefix' => $template->prefix,
                    'numberFormat' => $template->format_nomor,
                    'nextNumber' => $template->nomor_berikutnya,
                    'content' => $template->isi,
                    'active' => $template->is_active,
                    'inUse' => $template->surat_medis_count + $template->rujukan_internal_count > 0,
                ])->values(),
                'currentPage' => $templates->currentPage(), 'lastPage' => $templates->lastPage(),
                'from' => $templates->firstItem(), 'to' => $templates->lastItem(), 'total' => $templates->total(),
                'previousUrl' => $templates->previousPageUrl(), 'nextUrl' => $templates->nextPageUrl(),
            ],
            'letters' => [
                'data' => $letters->getCollection()->map(fn (SuratMedis $letter): array => [
                    'id' => $letter->id,
                    'type' => $letter->jenis,
                    'number' => $letter->nomor_surat,
                    'date' => $letter->tanggal?->format('d/m/Y'),
                    'patient' => $letter->kunjungan?->pasien?->nama ?? 'Belum diisi',
                    'visitNumber' => $letter->kunjungan?->no_kunjungan ?? 'Belum diisi',
                    'clinic' => $letter->kunjungan?->poliklinik?->nama ?? 'Belum diisi',
                    'doctor' => $letter->dokter?->nama ?? 'Belum diisi',
                    'printUrl' => route('persuratan.surat.cetak', $letter),
                ])->values(),
                'currentPage' => $letters->currentPage(), 'lastPage' => $letters->lastPage(),
                'from' => $letters->firstItem(), 'to' => $letters->lastItem(), 'total' => $letters->total(),
                'previousUrl' => $letters->previousPageUrl(), 'nextUrl' => $letters->nextPageUrl(),
            ],
            'referrals' => $isAdmin ? ['data' => [], 'currentPage' => 1, 'lastPage' => 1, 'from' => null, 'to' => null, 'total' => 0, 'previousUrl' => null, 'nextUrl' => null] : $referralDirectory->paginate(
                $search, isset($filters['kunjungan_id']) ? (int) $filters['kunjungan_id'] : null,
                $doctorId, null, true, 'referrals_page', originClinicType: $clinic,
            ),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $this->authorizeTemplateManagement($request->user());
        CorrespondenceTemplate::query()->create($this->validatedTemplate($request));

        return back()->with('success', 'Template persuratan berhasil ditambahkan.');
    }

    public function createLetter(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $notes): Response
    {
        $this->authorizeLetterIssue($request->user(), $kunjungan, $notes);
        $kunjungan->load(['pasien', 'poliklinik', 'dokter', 'pemeriksaan']);

        return Inertia::render('persuratan/terbitkan', [
            'visit' => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'date' => $kunjungan->tanggal->format('d/m/Y'),
                'patient' => $kunjungan->pasien->nama,
                'medicalRecordNumber' => $kunjungan->pasien->no_rm,
                'clinic' => $kunjungan->poliklinik?->nama ?? 'Belum diisi',
                'doctor' => $kunjungan->dokter?->nama ?? 'Dokter klinik',
            ],
            'templates' => CorrespondenceTemplate::query()->where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'jenis']),
            'today' => today()->toDateString(),
            'replaceableLetters' => $kunjungan->suratMedis()->whereIn('id', MedicalDocumentVerification::select('surat_medis_id'))
                ->orderByDesc('id')->get(['id', 'jenis', 'nomor_surat']),
        ]);
    }

    public function storeLetter(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $notes): RedirectResponse
    {
        $this->authorizeLetterIssue($request->user(), $kunjungan, $notes);
        $data = $request->validate([
            'jenis' => ['required', Rule::in(MedicalLetterData::TYPES)],
            'konten' => ['nullable', 'string', 'max:10000'],
            'tanggal' => ['required', 'date'],
            'surat_template_id' => ['nullable', 'exists:correspondence_templates,id'],
            'replacement_id' => ['nullable', 'integer'],
            'replacement_reason' => ['required_with:replacement_id', 'nullable', 'string', 'min:10', 'max:2000'],
            'signature_password' => ['required_with:replacement_id', 'nullable', 'current_password'],
        ]);

        $details = app(MedicalLetterData::class)->validate($request);
        DB::transaction(function () use ($request, $kunjungan, $notes, $data, $details): void {
            $visit = Kunjungan::query()->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeLetterIssue($request->user(), $visit, $notes);
            $replacement = null;
            if (filled($data['replacement_id'] ?? null)) {
                $replacement = $visit->suratMedis()->whereKey($data['replacement_id'])->where('jenis', $data['jenis'])->firstOrFail();
                $proof = MedicalDocumentVerification::where('surat_medis_id', $replacement->id)->lockForUpdate()->firstOrFail();
                if (DB::table('medical_document_verification_events')->where('verification_id', $proof->id)->where('action', 'replaced')->exists()) {
                    throw ValidationException::withMessages(['replacement_id' => 'Dokumen sudah memiliki pengganti. Pilih dokumen terakhir dalam rangkaian.']);
                }
                if (mb_strlen(trim($data['replacement_reason'])) < 10) {
                    throw ValidationException::withMessages(['replacement_reason' => 'Tuliskan alasan penggantian minimal 10 karakter.']);
                }
            }
            $template = filled($data['surat_template_id'] ?? null)
                ? CorrespondenceTemplate::query()->where('is_active', true)->lockForUpdate()->findOrFail($data['surat_template_id'])
                : null;
            if ($template && $template->jenis !== $data['jenis']) {
                throw ValidationException::withMessages(['surat_template_id' => 'Template tidak sesuai dengan jenis surat.']);
            }
            $date = Carbon::parse($data['tanggal']);
            $template ??= $this->correspondence->defaultTemplate($data['jenis']);
            $number = $this->correspondence->nextNumber($template, $date);
            $snapshot = app(MedicalLetterData::class)->snapshot($visit, $visit->dokter, $date);
            $content = filled($data['konten'] ?? null) ? $data['konten'] : $template?->isi;
            if ($content !== null) {
                $content = $this->correspondence->renderText($content, [
                    '[nomor]' => (string) ($number ?? ''), '[pasien]' => $visit->pasien->nama,
                    '[no_rm]' => $visit->pasien->no_rm, '[dokter]' => $visit->dokter?->nama ?? 'Dokter klinik',
                    '[anamnesis]' => $snapshot['clinical']['anamnesis'] ?? '',
                    '[diagnosis]' => implode('; ', $snapshot['clinical']['diagnoses']),
                    '[terapi]' => $snapshot['clinical']['therapy'],
                    '[poli]' => $visit->poliklinik?->nama ?? '', '[tanggal]' => $date->locale('id')->isoFormat('D MMMM Y'),
                ]);
            }
            $letter = $visit->suratMedis()->create([
                'dokter_id' => $visit->dokter_id, 'surat_template_id' => $template?->id,
                'jenis' => $data['jenis'], 'konten' => $content, 'nomor_surat' => $number, 'tanggal' => $date,
                'document_data' => $snapshot + ['details' => $details, 'replacement' => $replacement ? [
                    'document_id' => $replacement->id, 'number' => $replacement->nomor_surat,
                    'reason' => trim($data['replacement_reason']), 'actor_id' => $request->user()->id,
                    'recorded_at' => now()->toIso8601String(),
                ] : null],
            ]);
            app(MedicalDocumentVerificationService::class)->issue($letter, $request->user());
            if ($replacement) {
                DB::table('medical_document_verification_events')->insert([
                    'verification_id' => $proof->id, 'action' => 'replaced',
                    'reason' => json_encode(['replacement_id' => $letter->id, 'reason' => trim($data['replacement_reason'])], JSON_THROW_ON_ERROR),
                    'actor_id' => $request->user()->id, 'recorded_at' => now(),
                ]);
                app(ClinicalAuditRecorder::class)->record($request, 'medical_letter.replaced', $visit->pasien_id, $visit->id,
                    ['original_document_id' => $replacement->id, 'replacement_document_id' => $letter->id]);
            }
            app(ClinicalAuditRecorder::class)->record($request, 'medical_letter.issue', $visit->pasien_id, $visit->id);
        }, attempts: 5);

        return redirect()->route('persuratan.index')->with('success', 'Dokumen berhasil diterbitkan.');
    }

    public function updateTemplate(Request $request, CorrespondenceTemplate $template): RedirectResponse
    {
        $this->authorizeTemplateManagement($request->user());
        DB::transaction(function () use ($request, $template): void {
            $locked = CorrespondenceTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $locked->update($this->validatedTemplate($request, $locked));
        });

        return back()->with('success', 'Template persuratan berhasil diperbarui.');
    }

    public function destroyTemplate(Request $request, CorrespondenceTemplate $template): RedirectResponse
    {
        $this->authorizeTemplateManagement($request->user());
        abort_if($template->suratMedis()->exists() || $template->rujukanInternal()->exists(), 422, 'Template sudah digunakan dokumen; nonaktifkan template agar nomor dan arsip tetap terlacak.');
        $template->delete();

        return back()->with('success', 'Template persuratan berhasil dihapus.');
    }

    public function printPrescription(Request $request, Kunjungan $kunjungan, ClinicalAuditRecorder $auditRecorder): Response
    {
        if ($request->routeIs('farmasi.resep-luar.cetak')) {
            abort_unless(in_array($request->user()?->role, ['admin', 'farmasi'], true) && $kunjungan->pemeriksaan?->signed_at !== null, 404);
        } else {
            $this->authorizeVisitDocument($request->user(), $kunjungan);
        }
        $kunjungan->load(['pasien', 'poliklinik', 'dokter.user', 'resep.dokter.user', 'resep.resepObat.obat', 'resep.resepObat.farmasiResepLuar']);
        abort_unless($kunjungan->resep && $kunjungan->resep->resepObat->isNotEmpty(), 404);
        $auditRecorder->record($request, 'prescription.print', $kunjungan->pasien_id, $kunjungan->id);

        $items = $kunjungan->resep->resepObat->filter(fn ($item): bool => $item->is_resep_luar || $item->farmasiResepLuar !== null)->values();
        abort_unless($items->isNotEmpty(), 404, 'Resep luar belum tersedia untuk dicetak.');

        return Inertia::render('persuratan/cetak', [
            'document' => $this->baseDocument($kunjungan, 'resep_luar', $kunjungan->resep->dokter_id ? $kunjungan->resep->dokter : $kunjungan->dokter) + [
                'number' => $kunjungan->resep->no_resep,
                'date' => $kunjungan->tanggal?->locale('id')->isoFormat('D MMMM Y'),
                'items' => $items->map(fn ($item): array => [
                    'name' => $item->nama_obat ?? $item->obat?->nama,
                    'type' => $item->jenis,
                    'quantity' => $item->jumlah,
                    'unit' => $item->satuan,
                    'instructions' => $item->aturan_pakai,
                    'note' => collect([$item->catatan, $item->farmasiResepLuar ? 'Dialihkan oleh farmasi karena stok klinik kosong.' : null])->filter()->implode(' · '),
                ])->all(),
            ] + $this->documentReturn($request, $kunjungan, 'resep-luar', 'Kembali ke resep luar'),
        ]);
    }

    public function printLetter(Request $request, SuratMedis $suratMedis, ClinicalAuditRecorder $auditRecorder): Response
    {
        $suratMedis->load(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.dokter.user', 'dokter.user']);
        $visit = $suratMedis->kunjungan;
        abort_unless($visit, 404);
        $this->authorizeVisitDocument($request->user(), $visit);
        $auditRecorder->record($request, 'medical_letter.print', $visit->pasien_id, $visit->id, ['document_id' => $suratMedis->id]);

        return Inertia::render('persuratan/cetak', [
            'document' => $this->baseDocument($visit, 'surat', $suratMedis->dokter_id ? $suratMedis->dokter : $visit->dokter, $suratMedis->document_data, $suratMedis->tanggal) + [
                'number' => $suratMedis->nomor_surat,
                'type' => $suratMedis->jenis,
                'date' => $suratMedis->tanggal?->locale('id')->isoFormat('D MMMM Y'),
                'content' => $suratMedis->konten,
                'verification' => app(MedicalDocumentVerificationService::class)->printData($suratMedis),
                'replacementNumber' => $suratMedis->document_data['replacement']['number'] ?? null,
            ] + $this->documentReturn($request, $visit, 'surat', 'Kembali ke surat medis'),
        ]);
    }

    public function printReferral(Request $request, RujukanInternal $rujukanInternal, ClinicalAuditRecorder $auditRecorder): Response
    {
        $rujukanInternal->load(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.dokter.user', 'dariPoli', 'kePoli']);
        $visit = $rujukanInternal->kunjungan;
        abort_unless($visit, 404);
        $this->authorizeVisitDocument($request->user(), $visit);
        $auditRecorder->record($request, 'internal_referral.print', $visit->pasien_id, $visit->id, ['document_id' => $rujukanInternal->id]);

        return Inertia::render('persuratan/cetak', [
            'document' => $this->baseDocument($visit, 'rujukan_internal', $visit->dokter, $rujukanInternal->document_data, $rujukanInternal->created_at) + [
                'number' => $rujukanInternal->nomor_surat,
                'date' => $rujukanInternal->created_at?->locale('id')->isoFormat('D MMMM Y'),
                'fromClinic' => $rujukanInternal->document_data['fromClinic'] ?? $rujukanInternal->dariPoli?->nama,
                'toClinic' => $rujukanInternal->document_data['toClinic'] ?? $rujukanInternal->kePoli?->nama,
                'content' => $rujukanInternal->konten_surat,
                'notes' => $rujukanInternal->catatan,
            ] + $this->documentReturn($request, $visit, 'rujukan', 'Kembali ke rujukan internal'),
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedTemplate(Request $request, ?CorrespondenceTemplate $template = null): array
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:60', Rule::unique('correspondence_templates', 'kode')->ignore($template?->id)],
            'nama' => ['required', 'string', 'max:180'],
            'jenis' => ['required', Rule::in([...MedicalLetterData::TYPES, 'rujukan_internal'])],
            'prefix' => ['required', 'string', 'max:40'],
            'format_nomor' => ['required', 'string', 'max:100', 'regex:/\[urut\]/', 'not_regex:/\[(?!(?:prefix|urut|bulan|tahun)\])[^\]]+\]/'],
            'nomor_berikutnya' => ['required', 'integer', 'min:'.($template?->nomor_berikutnya ?? 1)],
            'isi' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ]);

        return [...$data, 'isi' => $data['isi'] ?? ''];
    }

    /** @return array<string, mixed> */
    private function baseDocument(Kunjungan $visit, string $kind, ?Nakes $doctor, ?array $snapshot = null, ?CarbonInterface $date = null): array
    {
        $visit->loadMissing(['pasien', 'poliklinik', 'dokter.user']);
        $snapshot ??= app(MedicalLetterData::class)->snapshot($visit, $doctor, $date ?? $visit->tanggal);

        return [
            'kind' => $kind,
            'clinic' => $snapshot['facility'] ?? app(ClinicDocumentProfile::class)->data(),
            'patient' => $snapshot['patient'],
            'clinical' => $kind === 'resep_luar' ? null : $snapshot['clinical'],
            'details' => $snapshot['details'] ?? [],
            'visitNumber' => $visit->no_kunjungan,
            'clinicName' => $visit->poliklinik?->nama ?? 'Belum diisi',
            'doctor' => $snapshot['doctor'],
        ];
    }

    /** @return array{backUrl: string, backLabel: string} */
    private function documentReturn(Request $request, Kunjungan $visit, string $tab, string $label): array
    {
        if ($request->routeIs('farmasi.resep-luar.cetak')) {
            if ($request->query('asal') === 'pasien') {
                return ['backUrl' => route('farmasi.pasien.kunjungan', $visit), 'backLabel' => 'Kembali ke detail pasien'];
            }

            if ($request->query('asal') === 'penyerahan') {
                return ['backUrl' => route('pelayanan.farmasi.show', $visit), 'backLabel' => 'Kembali ke penyerahan obat'];
            }

            if ($request->query('asal') === 'laporan') {
                return ['backUrl' => route('farmasi.laporan.resep-luar'), 'backLabel' => 'Kembali ke laporan resep luar'];
            }

            return ['backUrl' => route('farmasi.resep-luar.index'), 'backLabel' => 'Kembali ke resep luar'];
        }
        if ($request->query('asal') === 'rme') {
            return ['backUrl' => route('pelayanan.pasien.rekam-medis', $visit->pasien_id), 'backLabel' => 'Kembali ke rekam medis pasien'];
        }
        if ($request->query('asal') === 'pemeriksaan') {
            return [
                'backUrl' => route('pelayanan.pemeriksaan.show', $visit).'?tab='.$tab,
                'backLabel' => $label,
            ];
        }

        return [
            'backUrl' => route('persuratan.index'),
            'backLabel' => 'Kembali ke persuratan',
        ];
    }

    private function doctorId(User $user): int
    {
        return $this->doctorAccess->doctorId($user);
    }

    private function authorizeTemplateManagement(User $user): void
    {
        abort_unless($user->role === 'admin', 403);
    }

    private function authorizeLetterIssue(User $user, Kunjungan $visit, ClinicalNoteRecorder $notes): void
    {
        abort_unless($user->role === 'dokter', 403);
        abort_unless($visit->dokter_id === $this->doctorId($user), 404);
        abort_unless(in_array($visit->status, Kunjungan::POST_EXAMINATION_STATUSES, true), 422, 'Surat hanya dapat diterbitkan setelah pemeriksaan selesai.');
        $examination = $visit->pemeriksaan;
        abort_unless($examination?->signed_at && $notes->verify($examination), 422, 'Pemeriksaan final belum tersedia atau perlu diverifikasi.');
    }

    private function authorizeVisitDocument(User $user, Kunjungan $visit): void
    {
        abort_unless($user->role === 'dokter' && $visit->dokter_id === $this->doctorId($user), 404);
    }
}
