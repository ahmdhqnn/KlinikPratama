<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
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
            ->latest('created_at')->latest('id')->paginate(20)->withQueryString()
            ->through(fn (Kepesertaan $member): array => [
                'id' => $member->id, 'nama' => $member->nama, 'nik' => $member->nik, 'nip' => $member->nip,
                'kategori' => $member->kategori, 'status_kepegawaian' => $member->status_kepegawaian,
                'unit_kerja' => $member->unit_kerja, 'cost_center' => $member->cost_center,
                'tempat_lahir' => $member->tempat_lahir, 'tanggal_lahir' => $member->tanggal_lahir?->toDateString(),
                'jenis_kelamin' => $member->jenis_kelamin, 'agama' => $member->agama, 'golongan_darah' => $member->golongan_darah,
                'alamat' => $member->alamat, 'rt' => $member->rt, 'rw' => $member->rw,
                'kelurahan' => $member->kelurahan, 'kecamatan' => $member->kecamatan,
                'hak_layanan' => $member->hak_layanan, 'berlaku_mulai' => $member->berlaku_mulai?->toDateString(),
                'berlaku_sampai' => $member->berlaku_sampai?->toDateString(), 'reason' => $verifier->reason($member, today()->toDateString()),
                'detail_url' => route('kepesertaan.show', $member),
            ]);

        return Inertia::render('kepesertaan/index', [
            'members' => $this->pageData($members), 'search' => $filters['search'] ?? '', 'categories' => $request->user()->role === 'admin'
                ? Kepesertaan::CATEGORIES
                : array_diff_key(Kepesertaan::CATEGORIES, ['khusus' => true]),
            'sponsors' => Kepesertaan::whereIn('kategori', ['pegawai_pusat', 'kontrak', 'pensiunan'])->orderBy('nama')->get(['id', 'nama', 'nip']),
            'today' => today()->toDateString(),
            'urls' => ['index' => route('kepesertaan.index'), 'import' => route('kepesertaan.import'), 'template' => route('kepesertaan.template')],
        ]);
    }

    public function search(Request $request, HakLayananVerifier $verifier): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $members = Kepesertaan::with('penanggung')->where(fn ($query) => $query
            ->where('nama', 'like', "%{$data['q']}%")->orWhere('nip', 'like', "%{$data['q']}%")->orWhere('nik', 'like', "%{$data['q']}%"))
            ->latest('created_at')->latest('id')->limit(15)->get()->map(fn (Kepesertaan $member): array => [
                'id' => $member->id, 'name' => $member->nama, 'nik' => $member->nik, 'nip' => $member->nip,
                'category' => Kepesertaan::CATEGORIES[$member->kategori], 'unit' => $member->unit_kerja,
                'costCenter' => $member->cost_center, 'reason' => $verifier->reason($member, today()->toDateString()),
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
                'kategori' => Kepesertaan::CATEGORIES[$kepesertaan->kategori], 'status' => $kepesertaan->status_kepegawaian,
                'unitKerja' => $kepesertaan->unit_kerja, 'costCenter' => $kepesertaan->cost_center,
                'tempatLahir' => $kepesertaan->tempat_lahir, 'tanggalLahir' => $kepesertaan->tanggal_lahir?->isoFormat('D MMMM Y'),
                'jenisKelamin' => ['L' => 'Laki-laki', 'P' => 'Perempuan'][$kepesertaan->jenis_kelamin] ?? '—',
                'agama' => $kepesertaan->agama, 'golonganDarah' => $kepesertaan->golongan_darah,
                'alamat' => $kepesertaan->alamat, 'rt' => $kepesertaan->rt, 'rw' => $kepesertaan->rw,
                'kelurahan' => $kepesertaan->kelurahan, 'kecamatan' => $kepesertaan->kecamatan,
                'penanggung' => $kepesertaan->penanggung ? $kepesertaan->penanggung->nama.' · '.($kepesertaan->penanggung->nip ?? '—') : null,
                'hubunganKeluarga' => $kepesertaan->hubungan_keluarga,
                'hakLayanan' => $kepesertaan->hak_layanan, 'berlakuMulai' => $kepesertaan->berlaku_mulai?->isoFormat('D MMMM Y'),
                'berlakuSampai' => $kepesertaan->berlaku_sampai?->isoFormat('D MMMM Y'),
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
        return response()->download(resource_path('templates/contoh-kepesertaan-internal.xlsx'));
    }
}
