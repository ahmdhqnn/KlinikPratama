<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\DateRangeFilter;
use App\Exports\OperationalReportExport;
use App\Models\Kepesertaan;
use App\Models\Kunjungan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AggregateClinicalReportController extends Controller
{
    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'kelompok' => ['nullable', 'in:status,unit,kepesertaan,pengkajian,pemeriksaan,tindakan,tindakan_perawat,tindakan_dokter,obat,rujukan'],
            'status' => ['nullable', 'in:'.implode(',', Kunjungan::STATUSES)],
            'unit_kerja' => ['nullable', 'string', 'max:255'],
            'kategori' => ['nullable', 'in:asn,lainnya'],
            'status_kepegawaian' => ['nullable', 'in:aktif,nonaktif'],
            'poli' => ['nullable', 'in:umum,gigi'],
        ]);
        $filters['kelompok'] ??= match (true) {
            $request->routeIs('admin.laporan.pengkajian*') => 'pengkajian',
            $request->routeIs('admin.laporan.pemeriksaan*') => 'pemeriksaan',
            $request->routeIs('admin.laporan.tindakan-perawat*') => 'tindakan_perawat',
            $request->routeIs('admin.laporan.dokter*') => $request->route('jenis') === 'obat' ? 'obat' : 'tindakan_dokter',
            default => 'status',
        };
        abort_if(! $request->user()->isAdmin() && ($filters['kelompok'] !== 'status'
            || isset($filters['unit_kerja']) || isset($filters['kategori']) || isset($filters['status_kepegawaian'])), 403);

        return $filters;
    }

    private function rows(Request $request, array $filters): Builder
    {
        $period = DateRangeFilter::fromRequest($request);

        $group = $filters['kelompok'] ?? 'status';
        $column = match ($group) {
            'unit' => "COALESCE(NULLIF(kunjungan.unit_kerja, ''), 'Belum tercatat')",
            'kepesertaan' => "COALESCE(NULLIF(kunjungan.kategori_peserta, ''), 'Belum tercatat')",
            default => 'kunjungan.status',
        };

        return Kunjungan::query()->join('poliklinik', 'poliklinik.id', '=', 'kunjungan.poliklinik_id')
            ->when($group !== 'rujukan' && $period->from, fn ($query) => $query->whereDate('kunjungan.tanggal', '>=', $period->from))
            ->when($group !== 'rujukan' && $period->to, fn ($query) => $query->whereDate('kunjungan.tanggal', '<=', $period->to))
            ->when($filters['unit_kerja'] ?? null, fn ($query, $unit) => $query->where('kunjungan.unit_kerja', $unit))
            ->when($filters['kategori'] ?? null, fn ($query, $category) => $query->whereIn('kunjungan.kategori_peserta', Kepesertaan::persistedCategoryKeys($category)))
            ->when($filters['status_kepegawaian'] ?? null, fn ($query, $status) => $query->where('kunjungan.hak_layanan_snapshot->status_kepegawaian', $status))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('kunjungan.status', $status))
            ->when($filters['poli'] ?? null, fn ($query, $poli) => $query->where('poliklinik.jenis', $poli))
            ->when($group !== 'status', fn ($query) => $query->where('kunjungan.status', '!=', 'batal')->where('kontrol_pending', false))
            ->when($group === 'pengkajian', fn ($query) => $query->whereHas('screening', fn ($row) => $row->whereNotNull('submitted_at')))
            ->when($group === 'pemeriksaan', fn ($query) => $query->whereHas('pemeriksaan', fn ($row) => $row->whereNotNull('signed_at')))
            ->when(in_array($group, ['tindakan', 'tindakan_perawat', 'tindakan_dokter'], true), fn ($query) => $query->whereHas('tindakanKunjungan', fn ($row) => $row->whereNotNull('performed_at')->when($group !== 'tindakan', fn ($procedure) => $procedure->where('execution_role', substr($group, 9)))))
            ->when($group === 'obat', fn ($query) => $query->whereHas('farmasi', fn ($row) => $row->where('status', 'selesai')->whereHas('items', fn ($item) => $item->where('jumlah_diberikan', '>', 0))))
            ->when($group === 'rujukan', fn ($query) => $query->whereHas('suratMedis', fn ($row) => $row->externalReferral()
                ->when($period->from, fn ($letters, $from) => $letters->whereDate('tanggal', '>=', $from))
                ->when($period->to, fn ($letters, $to) => $letters->whereDate('tanggal', '<=', $to))))
            ->selectRaw("poliklinik.nama as clinic, {$column} as status, COUNT(*) as total")
            ->groupByRaw("poliklinik.nama, {$column}")->orderBy('clinic')->orderBy('status');
    }

    public function index(Request $request, ClinicalAuditRecorder $audit): Response
    {
        $period = DateRangeFilter::fromRequest($request);
        $filters = $this->filters($request);
        $audit->record($request, 'aggregate_report.view', metadata: $filters);

        return Inertia::render('laporan/agregat', [
            'canChooseGroup' => $request->user()->isAdmin(),
            'filters' => ['from' => $period->from ?? '', 'to' => $period->to ?? '', 'group' => $filters['kelompok'] ?? 'status', 'clinic' => $filters['poli'] ?? '', 'status' => $filters['status'] ?? '', 'unit' => $filters['unit_kerja'] ?? '', 'category' => $filters['kategori'] ?? '', 'employment' => $filters['status_kepegawaian'] ?? ''],
            'rows' => $this->rows($request, $filters)->get()->map(fn ($row) => ['clinic' => $row->clinic, 'status' => $row->status, 'total' => (int) $row->total]),
            'exportUrl' => route('laporan.kunjungan.export', [...$filters, 'dari' => $period->from, 'sampai' => $period->to]),
        ]);
    }

    public function export(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $audit->record($request, 'aggregate_report.export', metadata: $filters);

        return Excel::download(new OperationalReportExport($this->rows($request, $filters), ['Poliklinik', in_array($filters['kelompok'] ?? '', ['unit', 'kepesertaan'], true) ? 'Kelompok' : 'Status', 'Jumlah kunjungan'],
            fn ($row): array => [$row->clinic, $row->status, (int) $row->total]), 'ringkasan-kunjungan-'.today()->toDateString().'.xlsx');
    }
}
