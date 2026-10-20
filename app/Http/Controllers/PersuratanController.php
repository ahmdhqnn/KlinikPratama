<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\Models\CorrespondenceTemplate;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\RujukanInternal;
use App\Models\SuratMedis;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersuratanController extends Controller
{
    public function __construct(private \App\DoctorAccessService $doctorAccess) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $filters['search'] ?? '';
        $user = $request->user();
        $isAdmin = $user->role === 'admin';
        $doctorId = $isAdmin ? null : $this->doctorId($user);

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
        $kunjungan->load(['pasien', 'poliklinik', 'dokter.user', 'resep.resepObat']);
        abort_unless($kunjungan->resep && $kunjungan->resep->resepObat->isNotEmpty(), 404);
        $auditRecorder->record($request, 'prescription.print', $kunjungan->pasien_id, $kunjungan->id);

        $items = $kunjungan->resep->resepObat->where('is_resep_luar', true)->values();
        abort_unless($items->isNotEmpty(), 404, 'Resep luar belum tersedia untuk dicetak.');

        return Inertia::render('persuratan/cetak', [
            'document' => $this->baseDocument($kunjungan, 'resep_luar') + [
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
        $suratMedis->load(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.dokter.user']);
        $visit = $suratMedis->kunjungan;
        abort_unless($visit, 404);
        $this->authorizeVisitDocument($request->user(), $visit);
        $auditRecorder->record($request, 'medical_letter.print', $visit->pasien_id, $visit->id, ['document_id' => $suratMedis->id]);

        return Inertia::render('persuratan/cetak', [
            'document' => $this->baseDocument($visit, 'surat') + [
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
            'document' => $this->baseDocument($visit, 'rujukan_internal') + [
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
    private function baseDocument(Kunjungan $visit, string $kind): array
    {
        $visit->loadMissing(['pasien', 'poliklinik', 'dokter.user']);
        $doctor = $visit->dokter;
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
                'signatureDataUrl' => $this->signatureDataUrl($user),
            ],
        ];
    }

    private function signatureDataUrl(?User $user): ?string
    {
        $path = $user?->signature_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path) ?? 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($path));
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

    private function authorizeVisitDocument(User $user, Kunjungan $visit): void
    {
        if ($user->role === 'admin') {
            return;
        }

        abort_unless($user->role === 'dokter' && $visit->dokter_id === $this->doctorId($user), 404);
    }
}
