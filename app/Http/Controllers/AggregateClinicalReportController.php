<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\DateRangeFilter;
use App\Exports\OperationalReportExport;
use App\Models\Kunjungan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AggregateClinicalReportController extends Controller
{
    private function rows(Request $request): Builder
    {
        $period = DateRangeFilter::fromRequest($request);

        return Kunjungan::query()->join('poliklinik', 'poliklinik.id', '=', 'kunjungan.poliklinik_id')
            ->when($period->from, fn ($query, $from) => $query->whereDate('kunjungan.tanggal', '>=', $from))
            ->when($period->to, fn ($query, $to) => $query->whereDate('kunjungan.tanggal', '<=', $to))
            ->selectRaw('poliklinik.nama as clinic, kunjungan.status as status, COUNT(*) as total')
            ->groupBy('poliklinik.nama', 'kunjungan.status')->orderBy('poliklinik.nama')->orderBy('kunjungan.status');
    }

    public function index(Request $request, ClinicalAuditRecorder $audit): Response
    {
        $period = DateRangeFilter::fromRequest($request);
        $audit->record($request, 'aggregate_report.view');

        return Inertia::render('laporan/agregat', [
            'filters' => ['from' => $period->from ?? '', 'to' => $period->to ?? ''],
            'rows' => $this->rows($request)->get()->map(fn ($row) => ['clinic' => $row->clinic, 'status' => $row->status, 'total' => (int) $row->total]),
            'exportUrl' => route('laporan.kunjungan.export', ['dari' => $period->from, 'sampai' => $period->to]),
        ]);
    }

    public function export(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $audit->record($request, 'aggregate_report.export');

        return Excel::download(new OperationalReportExport($this->rows($request), ['Poliklinik', 'Status', 'Jumlah kunjungan'],
            fn ($row): array => [$row->clinic, $row->status, (int) $row->total]), 'ringkasan-kunjungan-'.today()->toDateString().'.xlsx');
    }
}
