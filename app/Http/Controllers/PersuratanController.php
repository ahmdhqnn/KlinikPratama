<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\CorrespondenceService;
use App\DoctorAccessService;
use App\Models\CorrespondenceTemplate;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\RujukanInternal;
use App\Models\SuratMedis;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersuratanController extends Controller
{
    public function __construct(private DoctorAccessService $doctorAccess, private CorrespondenceService $correspondence) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $filters['search'] ?? '';
        $user = $request->user();
        $isAdmin = $user->role === 'admin';
        $doctorId = $isAdmin ? null : $this->doctorId($user);
        $eligibleVisits = $isAdmin ? null : Kunjungan::query()
            ->where('dokter_id', $doctorId)
            ->whereIn('status', ['tindakan', 'farmasi', 'kasir', 'selesai'])
            ->whereHas('pemeriksaan', fn (Builder $query) => $query->whereNotNull('signed_at'))
            ->with(['pasien', 'poliklinik'])
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('no_kunjungan', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->latest('tanggal')->latest('id')->paginate(10, ['*'], 'visits_page')->withQueryString();

        $letters = SuratMedis::query()
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('nomor_surat', 'like', "%{$search}%")
                ->orWhereHas('kunjungan.pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->with(['kunjungan.pasien', 'kunjungan.poliklinik', 'dokter'])
            ->when(! $isAdmin, fn (Builder $query) => $query->whereHas('kunjungan', fn (Builder $visit) => $visit->where('dokter_id', $doctorId)))
            ->latest('id')->paginate(10, ['*'], 'letters_page')->withQueryString();
        $referrals = RujukanInternal::query()
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('nomor_surat', 'like', "%{$search}%")
                ->orWhereHas('kunjungan.pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->with(['kunjungan.pasien', 'kunjungan.dokter', 'dariPoli', 'kePoli'])
            ->when(! $isAdmin, fn (Builder $query) => $query->whereHas('kunjungan', fn (Builder $visit) => $visit->where('dokter_id', $doctorId)))
            ->latest('id')->paginate(10, ['*'], 'referrals_page')->withQueryString();
        $templates = CorrespondenceTemplate::query()
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%")))
            ->withCount(['suratMedis', 'rujukanInternal'])
            ->when(! $isAdmin, fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('nama')->paginate(10, ['*'], 'templates_page')->withQueryString();

        return Inertia::render('persuratan/index', [
            'isAdmin' => $isAdmin,
            'filters' => ['search' => $search],
            'eligibleVisits' => $eligibleVisits ? [
                'data' => $eligibleVisits->getCollection()->map(fn (Kunjungan $visit): array => [
                    'id' => $visit->id,
                    'date' => $visit->tanggal->format('d/m/Y'),
                    'number' => $visit->no_kunjungan,
                    'patient' => $visit->pasien->nama,
                    'medicalRecordNumber' => $visit->pasien->no_rm,
                    'clinic' => $visit->poliklinik->nama,
                    'letterUrl' => route('persuratan.surat.create', $visit),
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
            'referrals' => [
                'data' => $referrals->getCollection()->map(fn (RujukanInternal $referral): array => [
                    'id' => $referral->id,
                    'number' => $referral->nomor_surat,
                    'date' => $referral->kunjungan?->tanggal?->format('d/m/Y'),
                    'patient' => $referral->kunjungan?->pasien?->nama ?? 'Belum diisi',
                    'visitNumber' => $referral->kunjungan?->no_kunjungan ?? 'Belum diisi',
                    'fromClinic' => $referral->dariPoli?->nama ?? 'Belum diisi',
                    'toClinic' => $referral->kePoli?->nama ?? 'Belum diisi',
                    'doctor' => $referral->kunjungan?->dokter?->nama ?? 'Belum diisi',
                    'status' => $referral->status,
                    'printUrl' => route('persuratan.rujukan.cetak', $referral),
                ])->values(),
                'currentPage' => $referrals->currentPage(), 'lastPage' => $referrals->lastPage(),
                'from' => $referrals->firstItem(), 'to' => $referrals->lastItem(), 'total' => $referrals->total(),
                'previousUrl' => $referrals->previousPageUrl(), 'nextUrl' => $referrals->nextPageUrl(),
            ],
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
        ]);
    }

    public function storeLetter(Request $request, Kunjungan $kunjungan, ClinicalNoteRecorder $notes): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'in:sakit,sehat,rujukan,lainnya'],
            'konten' => ['nullable', 'string', 'max:10000'],
            'tanggal' => ['required', 'date'],
            'surat_template_id' => ['nullable', 'exists:correspondence_templates,id'],
        ]);

        DB::transaction(function () use ($request, $kunjungan, $notes, $data): void {
            $visit = Kunjungan::query()->whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            $this->authorizeLetterIssue($request->user(), $visit, $notes);
            $template = filled($data['surat_template_id'] ?? null)
                ? CorrespondenceTemplate::query()->where('is_active', true)->lockForUpdate()->findOrFail($data['surat_template_id'])
                : null;
            if ($template && $template->jenis !== $data['jenis']) {
                throw ValidationException::withMessages(['surat_template_id' => 'Template tidak sesuai dengan jenis surat.']);
            }
            $date = Carbon::parse($data['tanggal']);
            $number = $template ? $this->correspondence->nextNumber($template, $date) : null;
            $content = filled($data['konten'] ?? null) ? $data['konten'] : $template?->isi;
            if ($content !== null) {
                $content = $this->correspondence->renderText($content, [
                    '[nomor]' => (string) ($number ?? ''), '[pasien]' => $visit->pasien->nama,
                    '[no_rm]' => $visit->pasien->no_rm, '[dokter]' => $visit->dokter?->nama ?? 'Dokter klinik',
                    '[poli]' => $visit->poliklinik?->nama ?? '', '[tanggal]' => $date->locale('id')->isoFormat('D MMMM Y'),
                ]);
            }
            $visit->suratMedis()->create([
                'dokter_id' => $visit->dokter_id, 'surat_template_id' => $template?->id,
                'jenis' => $data['jenis'], 'konten' => $content, 'nomor_surat' => $number, 'tanggal' => $date,
            ]);
            app(ClinicalAuditRecorder::class)->record($request, 'medical_letter.issue', $visit->pasien_id, $visit->id);
        });

        return redirect()->route('persuratan.index')->with('success', 'Dokumen berhasil diterbitkan.');
    }

    public function updateTemplate(Request $request, CorrespondenceTemplate $template): RedirectResponse
    {
        $this->authorizeTemplateManagement($request->user());
        $template->update($this->validatedTemplate($request, $template));

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
        $this->authorizeVisitDocument($request->user(), $kunjungan);
        $kunjungan->load(['pasien', 'poliklinik', 'dokter.user', 'resep.dokter.user', 'resep.resepObat']);
        abort_unless($kunjungan->resep && $kunjungan->resep->resepObat->isNotEmpty(), 404);
        $auditRecorder->record($request, 'prescription.print', $kunjungan->pasien_id, $kunjungan->id);

        $items = $kunjungan->resep->resepObat->where('is_resep_luar', true)->values();
        abort_unless($items->isNotEmpty(), 404, 'Resep luar belum tersedia untuk dicetak.');

        return Inertia::render('persuratan/cetak', [
            'document' => $this->baseDocument($kunjungan, 'resep_luar', $kunjungan->resep->dokter_id ? $kunjungan->resep->dokter : $kunjungan->dokter) + [
                'number' => $kunjungan->resep->no_resep,
                'date' => $kunjungan->tanggal?->locale('id')->isoFormat('D MMMM Y'),
                'items' => $items->map(fn ($item): array => [
                    'name' => $item->nama_obat,
                    'type' => $item->jenis,
                    'quantity' => $item->jumlah,
                    'unit' => $item->satuan,
                    'instructions' => $item->aturan_pakai,
                    'note' => $item->catatan,
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
            'document' => $this->baseDocument($visit, 'surat', $suratMedis->dokter_id ? $suratMedis->dokter : $visit->dokter) + [
                'number' => $suratMedis->nomor_surat,
                'type' => $suratMedis->jenis,
                'date' => $suratMedis->tanggal?->locale('id')->isoFormat('D MMMM Y'),
                'content' => $suratMedis->konten,
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
            'document' => $this->baseDocument($visit, 'rujukan_internal', $visit->dokter) + [
                'number' => $rujukanInternal->nomor_surat,
                'date' => $visit->tanggal?->locale('id')->isoFormat('D MMMM Y'),
                'fromClinic' => $rujukanInternal->dariPoli?->nama,
                'toClinic' => $rujukanInternal->kePoli?->nama,
                'content' => $rujukanInternal->konten_surat,
                'notes' => $rujukanInternal->catatan,
            ] + $this->documentReturn($request, $visit, 'rujukan', 'Kembali ke rujukan internal'),
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedTemplate(Request $request, ?CorrespondenceTemplate $template = null): array
    {
        return $request->validate([
            'kode' => ['required', 'string', 'max:60', Rule::unique('correspondence_templates', 'kode')->ignore($template?->id)],
            'nama' => ['required', 'string', 'max:180'],
            'jenis' => ['required', Rule::in(['sakit', 'sehat', 'rujukan', 'lainnya', 'rujukan_internal'])],
            'prefix' => ['required', 'string', 'max:40'],
            'format_nomor' => ['required', 'string', 'max:120'],
            'nomor_berikutnya' => ['required', 'integer', 'min:1'],
            'isi' => ['required', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function baseDocument(Kunjungan $visit, string $kind, ?Nakes $doctor): array
    {
        $visit->loadMissing(['pasien', 'poliklinik', 'dokter.user']);
        $user = $doctor?->user;
        $settings = KlinikSetting::query()->first();

        return [
            'kind' => $kind,
            'clinic' => [
                'name' => $settings?->nama_klinik ?? 'Klinik Pratama',
                'address' => $settings?->alamat,
                'phone' => $settings?->telepon,
                'email' => $settings?->email,
                'logoUrl' => $settings?->logo ? Storage::disk('public')->url($settings->logo) : null,
            ],
            'patient' => [
                'name' => $visit->pasien->nama,
                'medicalRecordNumber' => $visit->pasien->no_rm,
                'gender' => $visit->pasien->jenis_kelamin,
                'age' => $visit->pasien->umur,
                'address' => $visit->pasien->alamat,
            ],
            'visitNumber' => $visit->no_kunjungan,
            'clinicName' => $visit->poliklinik?->nama ?? 'Belum diisi',
            'doctor' => [
                'name' => $doctor?->nama ?? 'Dokter klinik',
                'sip' => $doctor?->no_sip,
                'str' => $doctor?->no_str,
                'signatureDataUrl' => $this->correspondence->signatureDataUrl($user),
            ],
        ];
    }

    /** @return array{backUrl: string, backLabel: string} */
    private function documentReturn(Request $request, Kunjungan $visit, string $tab, string $label): array
    {
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
        abort_unless(in_array($visit->status, ['tindakan', 'farmasi', 'kasir', 'selesai'], true), 422, 'Surat hanya dapat diterbitkan setelah pemeriksaan selesai.');
        $examination = $visit->pemeriksaan;
        abort_unless($examination?->signed_at && $notes->verify($examination), 422, 'Pemeriksaan final belum tersedia atau perlu diverifikasi.');
    }

    private function authorizeVisitDocument(User $user, Kunjungan $visit): void
    {
        if ($user->role === 'admin') {
            return;
        }

        abort_unless($user->role === 'dokter' && $visit->dokter_id === $this->doctorId($user), 404);
    }
}
