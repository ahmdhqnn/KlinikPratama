<?php

namespace App\Http\Controllers;

use App\ClinicalNoteRecorder;
use App\DateRangeFilter;
use App\DoctorAccessService;
use App\DoctorActivityReportQuery;
use App\Models\Kunjungan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokterTindakanController extends Controller
{
    public function index(Request $request, DoctorAccessService $doctorAccess, DoctorActivityReportQuery $reports, ClinicalNoteRecorder $notes): Response
    {
        $filters = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,pending,completed'],
        ]);
        $doctorId = $doctorAccess->doctorId($request->user());
        $period = DateRangeFilter::fromRequest($request);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? 'all';
        $summary = $reports->treatments($doctorId, $period, $search);
        $base = (clone $summary)->when($status !== 'all', fn (Builder $query) => $query->where('execution_status', $status));
        $rows = $base->paginate(10)->withQueryString();

        return Inertia::render('dokter/treatments', [
            'filters' => [
                'dari' => $period->from ?? '',
                'sampai' => $period->to ?? '',
                'search' => $search,
                'status' => $status,
            ],
            'stats' => [
                'total' => (clone $summary)->count(),
                'visits' => (clone $summary)->distinct('kunjungan_id')->count('kunjungan_id'),
                'pending' => (clone $summary)->where('execution_status', 'pending')->count(),
                'completed' => (clone $summary)->where('execution_status', 'completed')->count(),
            ],
            'actions' => [
                'data' => $rows->getCollection()->map(fn ($item): array => [
                    'id' => $item->id,
                    'date' => $item->kunjungan?->tanggal?->format('d/m/Y') ?? '—',
                    'number' => $item->kunjungan?->no_kunjungan ?? '—',
                    'patient' => $item->kunjungan?->pasien?->nama ?? 'Pasien tidak tersedia',
                    'medicalRecordNumber' => $item->kunjungan?->pasien?->no_rm ?? '—',
                    'clinic' => $item->kunjungan?->poliklinik?->nama ?? '—',
                    'name' => $item->nama_tindakan ?: $item->nama_tindakan_manual ?: $item->tindakan?->nama ?: 'Tindakan tidak tersedia',
                    'code' => $item->kode_icd9cm,
                    'quantity' => $item->jumlah,
                    'executionRole' => $item->execution_role,
                    'status' => $item->execution_status,
                    'statusLabel' => $item->execution_status === 'completed'
                        ? 'Selesai'
                        : ($item->execution_role === 'perawat' ? 'Menunggu tindakan perawat' : 'Belum difinalisasi'),
                    'performedBy' => $item->performedBy?->name,
                    'performedAt' => $item->performed_at?->format('d/m/Y H:i'),
                    'detailUrl' => $item->kunjungan ? route('pelayanan.pemeriksaan.show', $item->kunjungan) : route('dokter.kunjungan'),
                    'letterUrl' => $this->letterUrl($item->kunjungan, $notes),
                    'recordUrl' => $item->kunjungan ? route('pelayanan.pasien.rekam-medis', $item->kunjungan->pasien_id) : null,
                ])->values(),
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
                'total' => $rows->total(),
                'previousUrl' => $rows->previousPageUrl(),
                'nextUrl' => $rows->nextPageUrl(),
            ],
        ]);
    }

    private function letterUrl(?Kunjungan $visit, ClinicalNoteRecorder $notes): ?string
    {
        $examination = $visit?->pemeriksaan;
        if (! $visit || ! in_array($visit->status, ['tindakan', 'farmasi', 'kasir', 'selesai'], true)
            || ! $examination?->signed_at || ! $notes->verify($examination)) {
            return null;
        }

        return route('persuratan.surat.create', $visit);
    }
}
