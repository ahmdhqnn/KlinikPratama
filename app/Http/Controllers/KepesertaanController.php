<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\Exports\KepesertaanTemplateExport;
use App\HakLayananVerifier;
use App\Imports\KepesertaanImport;
use App\KepesertaanRegistry;
use App\Models\Kepesertaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KepesertaanController extends Controller
{
    public function index(Request $request, HakLayananVerifier $verifier): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $members = Kepesertaan::with('penanggung')->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query
            ->where('nama', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%")->orWhere('unit_kerja', 'like', "%{$search}%")))
            ->latest('created_at')->latest('id')->paginate(10)->withQueryString()
            ->through(fn (Kepesertaan $member): array => [
                'id' => $member->id, 'nama' => $member->nama, 'nik' => $member->nik, 'nip' => $member->nip,
                'kategori' => $member->categoryKey(), 'jenis_lainnya' => $member->otherTypeKey(), 'status_kepegawaian' => $member->status_kepegawaian,
                'unit_kerja' => $member->unit_kerja, 'pegawai_penanggung_id' => $member->pegawai_penanggung_id,
                'hubungan_keluarga' => $member->hubungan_keluarga, 'referensi_bukti' => $member->referensi_bukti,
                'tempat_lahir' => $member->tempat_lahir, 'tanggal_lahir' => $member->tanggal_lahir?->toDateString(),
                'jenis_kelamin' => $member->jenis_kelamin, 'agama' => $member->agama, 'golongan_darah' => $member->golongan_darah,
                'alamat' => $member->alamat, 'rt' => $member->rt, 'rw' => $member->rw,
                'kelurahan' => $member->kelurahan, 'kecamatan' => $member->kecamatan,
                'hak_layanan' => $member->hak_layanan, 'reason' => $verifier->reason($member),
                'detail_url' => route('kepesertaan.show', $member),
            ]);

        return Inertia::render('kepesertaan/index', [
            'members' => $this->pageData($members), 'search' => $filters['search'] ?? '', 'categories' => Kepesertaan::CATEGORIES,
            'otherTypes' => Kepesertaan::OTHER_TYPES,
            'sponsors' => Kepesertaan::where('kategori', 'asn')->orderBy('nama')->get(['id', 'nama', 'nip']),
            'urls' => ['index' => route('kepesertaan.index'), 'import' => route('kepesertaan.import'), 'template' => route('kepesertaan.template')],
        ]);
    }

    public function search(Request $request, HakLayananVerifier $verifier): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $members = Kepesertaan::with('penanggung')->where(fn ($query) => $query
            ->where('nama', 'like', "%{$data['q']}%")->orWhere('nip', 'like', "%{$data['q']}%")->orWhere('nik', 'like', "%{$data['q']}%"))
            ->latest('created_at')->latest('id')->limit(10)->get()->map(fn (Kepesertaan $member): array => [
                'id' => $member->id, 'name' => $member->nama, 'nik' => $member->nik, 'nip' => $member->nip,
                'category' => $member->membershipLabel(), 'unit' => $member->unit_kerja,
                'reason' => $verifier->reason($member),
                'tempatLahir' => $member->tempat_lahir, 'tanggalLahir' => $member->tanggal_lahir?->toDateString(),
                'jenisKelamin' => $member->jenis_kelamin, 'agama' => $member->agama, 'golonganDarah' => $member->golongan_darah,
                'alamat' => $member->alamat, 'rt' => $member->rt, 'rw' => $member->rw,
                'kelurahan' => $member->kelurahan, 'kecamatan' => $member->kecamatan,
            ]);

        return response()->json($members);
    }

    public function store(Request $request, KepesertaanRegistry $registry, ClinicalAuditRecorder $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $registry, $audit): void {
            $registry->save($request->all(), $request->user());
            $audit->record($request, 'membership.verified');
        });

        return back()->with('success', 'Kepesertaan berhasil diverifikasi.');
    }

    public function update(Request $request, Kepesertaan $kepesertaan, KepesertaanRegistry $registry, ClinicalAuditRecorder $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $kepesertaan, $registry, $audit): void {
            $registry->save($request->all(), $request->user(), Kepesertaan::whereKey($kepesertaan->id)->lockForUpdate()->firstOrFail());
            $audit->record($request, 'membership.updated');
        });

        return back()->with('success', 'Status kepesertaan berhasil diperbarui.');
    }

    public function show(Request $request, Kepesertaan $kepesertaan, ClinicalAuditRecorder $audit): Response
    {
        $audit->record($request, 'membership.view', metadata: ['membership_id' => $kepesertaan->id]);
        $kepesertaan->load('penanggung:id,nama,nip');

        return Inertia::render('kepesertaan/show', [
            'member' => [
                'id' => $kepesertaan->id, 'nama' => $kepesertaan->nama, 'nik' => $kepesertaan->nik, 'nip' => $kepesertaan->nip,
                'kategori' => $kepesertaan->categoryLabel(), 'jenisLainnya' => $kepesertaan->otherTypeLabel(), 'status' => $kepesertaan->status_kepegawaian,
                'unitKerja' => $kepesertaan->unit_kerja,
                'tempatLahir' => $kepesertaan->tempat_lahir, 'tanggalLahir' => $kepesertaan->tanggal_lahir?->isoFormat('D MMMM Y'),
                'jenisKelamin' => ['L' => 'Laki-laki', 'P' => 'Perempuan'][$kepesertaan->jenis_kelamin] ?? 'Belum diisi',
                'agama' => $kepesertaan->agama, 'golonganDarah' => $kepesertaan->golongan_darah,
                'alamat' => $kepesertaan->alamat, 'rt' => $kepesertaan->rt, 'rw' => $kepesertaan->rw,
                'kelurahan' => $kepesertaan->kelurahan, 'kecamatan' => $kepesertaan->kecamatan,
                'penanggung' => $kepesertaan->penanggung ? $kepesertaan->penanggung->nama.' · '.($kepesertaan->penanggung->nip ?? 'Belum diisi') : null,
                'hubunganKeluarga' => $kepesertaan->hubungan_keluarga,
                'hakLayanan' => $kepesertaan->hak_layanan,
                'referensiBukti' => $kepesertaan->referensi_bukti, 'verifiedAt' => $kepesertaan->verified_at?->isoFormat('D MMMM Y HH:mm'),
            ],
            'urls' => ['index' => route('kepesertaan.index')],
        ]);
    }

    public function import(Request $request, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'referensi_bukti' => ['required', 'string', 'max:255'],
        ]);
        $import = new KepesertaanImport($request->user(), $data['referensi_bukti'], $request->file('file')->getRealPath());
        DB::transaction(function () use ($request, $import, $audit): void {
            try {
                Excel::import($import, $request->file('file'));
            } catch (Exception|\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['file' => 'Berkas Excel tidak dapat dibaca. Gunakan berkas .xlsx dari template yang tersedia.']);
            }
            $audit->record($request, 'membership.imported', metadata: ['reference' => $request->input('referensi_bukti'), 'count' => $import->count]);
        });

        return back()->with('success', "{$import->count} peserta diimpor dan diverifikasi. Data identitas pasien serta bukti kunjungan lama tetap tersimpan.");
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new KepesertaanTemplateExport, 'template-kepesertaan-internal.xlsx');
    }
}
