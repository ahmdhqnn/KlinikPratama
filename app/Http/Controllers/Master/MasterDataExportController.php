<?php

namespace App\Http\Controllers\Master;

use App\ClinicalAuditRecorder;
use App\Exports\OperationalReportExport;
use App\Http\Controllers\Controller;
use App\Models\ClinicalTerminology;
use App\Models\CorrespondenceTemplate;
use App\Models\Kepesertaan;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\RuangPoli;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MasterDataExportController extends Controller
{
    public function terminologyTemplate(): BinaryFileResponse
    {
        $workbook = new OperationalReportExport(ClinicalTerminology::query()->whereRaw('1 = 0'), ['code', 'title', 'type'], fn (): array => []);

        return Excel::download($workbook, 'template-referensi-medis.xlsx');
    }

    public function __invoke(Request $request, string $catalog, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $clinicId = $catalog === 'ruang-poli'
            ? $request->validate(['poliklinik_id' => ['required', 'integer', 'exists:poliklinik,id']])['poliklinik_id']
            : null;
        $status = static fn (bool $active): string => $active ? 'Aktif' : 'Nonaktif';
        $workbook = match ($catalog) {
            'pengguna' => new OperationalReportExport(User::query()->orderBy('id'),
                ['Nama akun', 'Peran', 'Status akses'],
                fn (User $row): array => [$row->name, $row->role_label, in_array($row->role, User::ROLES, true) ? $status($row->is_active) : 'Peran dihentikan']),
            'tenaga-kerja' => new OperationalReportExport(Nakes::query()->with('poliklinik')->orderBy('id'),
                ['Kode SDM', 'Nama', 'Kategori', 'Jabatan', 'Poliklinik', 'Status'],
                fn (Nakes $row): array => [$row->kode, $row->nama, $row->kategori, $row->jabatan, $row->poliklinik?->nama, $status($row->is_active)]),
            'kepesertaan' => new OperationalReportExport(Kepesertaan::query()->orderBy('id'),
                ['Nama peserta', 'Kategori', 'Unit kerja', 'Status kepegawaian'],
                fn (Kepesertaan $row): array => [$row->nama, $row->categoryLabel(), $row->unit_kerja, $row->status_kepegawaian]),
            'poliklinik' => new OperationalReportExport(Poliklinik::query()->whereIn('jenis', ['umum', 'gigi'])->withCount([
                'ruangPoli', 'ruangPoli as ruang_aktif_count' => fn ($rooms) => $rooms->where('is_active', true),
            ])->orderBy('id'),
                ['Kode poli', 'Nama poliklinik', 'Jenis', 'Status', 'Total ruang', 'Ruang aktif'],
                fn (Poliklinik $row): array => [$row->kode, $row->nama, $row->jenis, $status($row->is_active), $row->ruang_poli_count, $row->ruang_aktif_count]),
            'ruang-poli' => new OperationalReportExport(RuangPoli::query()->where('poliklinik_id', $clinicId)->whereHas('poliklinik', fn ($clinics) => $clinics->whereIn('jenis', ['umum', 'gigi']))
                ->with('poliklinik')->orderBy('id'),
                ['Poliklinik', 'Nama ruang', 'Status'],
                fn (RuangPoli $row): array => [$row->poliklinik?->nama, $row->nama, $status($row->is_active)]),
            'referensi-medis' => new OperationalReportExport(ClinicalTerminology::query()->orderBy('id'),
                ['Sistem kode', 'Rilis', 'Kode', 'Uraian', 'Jenis kode', 'Status'],
                fn (ClinicalTerminology $row): array => [$row->code_system, $row->release, $row->code, $row->display, $row->code_type, $status($row->is_active)]),
            'template-surat' => new OperationalReportExport(CorrespondenceTemplate::query()->orderBy('id'),
                ['Kode', 'Nama template', 'Jenis', 'Prefix', 'Format nomor', 'Nomor berikutnya', 'Isi template', 'Status'],
                fn (CorrespondenceTemplate $row): array => [$row->kode, $row->nama, $row->jenis, $row->prefix, $row->format_nomor, $row->nomor_berikutnya, $row->isi, $status($row->is_active)]),
        };
        $audit->record($request, 'master_data.exported', metadata: ['catalog' => $catalog]);

        return Excel::download($workbook, $catalog.'-'.today()->toDateString().'.xlsx');
    }
}
