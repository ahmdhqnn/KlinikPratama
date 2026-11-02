<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminAssistanceAuditController extends Controller
{
    private const ACTION_LABELS = [
        'admin_assistance.started' => 'Bantuan pemeriksaan dimulai',
        'admin_assistance.draft_write' => 'Draf pemeriksaan disimpan',
        'admin_assistance.reviewed' => 'Draf ditinjau dokter',
        'admin_assistance.attempt_rejected' => 'Percobaan bantuan ditolak/gagal',
        'admin_assistance.screening_start' => 'Bantuan pengkajian dimulai',
        'admin_assistance.screening_write' => 'Pengkajian dibantu admin',
        'nursing_procedure.started' => 'Tindakan perawat dimulai',
        'nursing_procedure.complete' => 'Tindakan perawat diselesaikan',
        'pharmacy.draft_saved' => 'Draf farmasi disimpan',
        'pharmacy.external_prescription' => 'Resep luar dicatat',
        'pharmacy.dispensed' => 'Penyerahan resep diselesaikan',
        'pharmacy.direct_dispensed' => 'Obat langsung diberikan',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d', 'required_with:sampai', 'before_or_equal:sampai'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'required_with:dari', 'after_or_equal:dari'],
            'aksi' => ['nullable', 'in:'.implode(',', array_keys(self::ACTION_LABELS))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $from = $filters['dari'] ?? today()->subDays(30)->toDateString();
        $to = $filters['sampai'] ?? today()->toDateString();

        $events = DB::table('clinical_audit_events')
            ->join('users as actors', 'actors.id', '=', 'clinical_audit_events.actor_id')
            ->leftJoin('kunjungan', 'kunjungan.id', '=', 'clinical_audit_events.kunjungan_id')
            ->leftJoin('poliklinik', 'poliklinik.id', '=', 'kunjungan.poliklinik_id')
            ->whereIn('clinical_audit_events.action', array_keys(self::ACTION_LABELS))
            ->where(function ($query): void {
                $query->where('actors.role', 'admin')
                    ->orWhere(function ($review) {
                        $review->where('clinical_audit_events.action', 'admin_assistance.reviewed')
                            ->where('actors.role', 'dokter');
                    });
            })
            ->whereDate('clinical_audit_events.occurred_at', '>=', $from)
            ->whereDate('clinical_audit_events.occurred_at', '<=', $to)
            ->when($filters['aksi'] ?? null, fn ($query, string $action) => $query->where('clinical_audit_events.action', $action))
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where('kunjungan.no_kunjungan', 'like', '%'.trim($search).'%'))
            ->select([
                'clinical_audit_events.id',
                'clinical_audit_events.action',
                'clinical_audit_events.metadata',
                'clinical_audit_events.occurred_at',
                'actors.name as actor_name',
                'kunjungan.no_kunjungan as visit_number',
                'poliklinik.nama as clinic_name',
            ])
            ->latest('clinical_audit_events.occurred_at')
            ->latest('clinical_audit_events.id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/audit-bantuan', [
            'filters' => ['from' => $from, 'to' => $to, 'action' => $filters['aksi'] ?? '', 'search' => $filters['q'] ?? ''],
            'actions' => collect(self::ACTION_LABELS)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values(),
            'events' => [
                'data' => $events->getCollection()->map(function (object $event): array {
                    $metadata = is_string($event->metadata) ? json_decode($event->metadata, true) : [];
                    $metadata = is_array($metadata) ? $metadata : [];

                    return [
                        'id' => (int) $event->id,
                        'action' => $event->action,
                        'label' => self::ACTION_LABELS[$event->action] ?? $event->action,
                        'actor' => $event->actor_name,
                        'occurredAt' => $event->occurred_at,
                        'visitNumber' => $event->visit_number,
                        'clinic' => $event->clinic_name,
                        'reason' => $metadata['reason'] ?? $metadata['assistance_reason'] ?? null,
                        'result' => $event->action === 'admin_assistance.attempt_rejected'
                            ? ($metadata['failure_kind'] ?? 'unknown').' (HTTP '.(int) ($metadata['status'] ?? 0).')'
                            : null,
                    ];
                })->values(),
                'currentPage' => $events->currentPage(),
                'lastPage' => $events->lastPage(),
                'from' => $events->firstItem(),
                'to' => $events->lastItem(),
                'total' => $events->total(),
                'previousUrl' => $events->previousPageUrl(),
                'nextUrl' => $events->nextPageUrl(),
            ],
        ]);
    }
}
