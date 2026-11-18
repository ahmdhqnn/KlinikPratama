<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminActivityLogController extends Controller
{
    private const ACTION_LABELS = [
        'user.created' => 'Akun ditambahkan',
        'user.updated' => 'Akun diperbarui',
        'user.deleted' => 'Akun dihapus',
        'user.status_changed' => 'Status akun diubah',
        'user.password_reset' => 'Kata sandi akun diatur ulang',
        'staff.created' => 'Profil petugas ditambahkan',
        'staff.updated' => 'Profil petugas diperbarui',
        'staff.archived' => 'Profil petugas diarsipkan',
        'admin_assistance.started' => 'Bantuan pemeriksaan dimulai',
        'admin_assistance.draft_write' => 'Draf bantuan disimpan',
        'admin_assistance.reviewed' => 'Draf bantuan ditinjau dokter',
        'admin_assistance.attempt_rejected' => 'Percobaan bantuan ditolak atau gagal',
        'admin_assistance.screening_start' => 'Bantuan pengkajian dimulai',
        'admin_assistance.screening_write' => 'Bantuan pengkajian disimpan',
        'medical_record.view' => 'Rekam medis dilihat',
        'visit.view' => 'Detail kunjungan dilihat',
        'patient_profile.view' => 'Profil pasien dilihat',
        'patient_directory.view' => 'Daftar pasien dilihat',
        'patient_directory.export' => 'Data pasien diekspor',
        'patient.identity_updated' => 'Identitas pasien diperbarui',
        'patient.manual_registration' => 'Pasien didaftarkan manual',
        'patient.membership_verified' => 'Kepesertaan pasien diverifikasi',
        'patient.merge' => 'Data pasien digabung',
        'membership.view' => 'Kepesertaan dilihat',
        'membership.updated' => 'Kepesertaan diperbarui',
        'membership.verified' => 'Kepesertaan diverifikasi',
        'membership.imported' => 'Kepesertaan diimpor',
        'membership.deleted' => 'Kepesertaan dihapus',
        'visit_report.export' => 'Laporan kunjungan diekspor',
        'inventory_report.export' => 'Laporan stok diekspor',
        'diagnosis_report.export' => 'Laporan diagnosis diekspor',
        'examination.report_export' => 'Laporan pemeriksaan diekspor',
        'doctor.tindakan.report_export' => 'Laporan tindakan dokter diekspor',
        'doctor.obat.report_export' => 'Laporan obat dokter diekspor',
        'admin.report.doctor.view' => 'Laporan aktivitas dokter dilihat',
        'admin.report.examination.view' => 'Laporan pemeriksaan dilihat',
        'admin.report.screening.view' => 'Laporan pengkajian dilihat',
        'screening.view' => 'Pengkajian dilihat',
        'screening.started' => 'Pengkajian dimulai',
        'screening.print' => 'Pengkajian dicetak',
        'screening.report_export' => 'Laporan pengkajian diekspor',
        'nursing_procedure.started' => 'Tindakan perawat dimulai',
        'nursing_procedure.complete' => 'Tindakan perawat diselesaikan',
        'nursing_procedure.view' => 'Tindakan perawat dilihat',
        'nursing_procedure.report_view' => 'Laporan tindakan perawat dilihat',
        'nursing_procedure.report_print' => 'Laporan tindakan perawat dicetak',
        'nursing_procedure.report_export' => 'Laporan tindakan perawat diekspor',
        'pharmacy.view' => 'Penyerahan obat dilihat',
        'pharmacy.draft_saved' => 'Draf farmasi disimpan',
        'pharmacy.external_prescription' => 'Resep luar dicatat',
        'pharmacy.dispensed' => 'Obat resep diserahkan',
        'pharmacy.direct_dispensed' => 'Obat langsung diserahkan',
        'prescription.print' => 'Resep dicetak',
        'medical_letter.issue' => 'Surat medis diterbitkan',
        'medical_letter.print' => 'Surat medis dicetak',
        'internal_referral.print' => 'Rujukan internal dicetak',
        'internal_referral.register' => 'Rujukan internal didaftarkan',
        'queue_ticket.print' => 'Tiket antrean dicetak',
        'reservation.confirm' => 'Reservasi dikonfirmasi',
        'attachment.upload' => 'Lampiran ditambahkan',
        'attachment.download' => 'Lampiran diunduh',
        'attachment.delete' => 'Lampiran dihapus',
        'clinical_examination.view' => 'Pemeriksaan dilihat',
        'clinical_examination.started' => 'Pemeriksaan dimulai',
        'clinical_examination.finalize' => 'Pemeriksaan ditandatangani',
        'clinical_examination.addendum' => 'Adendum pemeriksaan disimpan',
        'clinical_examination.visit_claim' => 'Kunjungan diambil dokter',
        'clinical_draft.store' => 'Draf pemeriksaan dibuat',
        'clinical_draft.update' => 'Draf pemeriksaan diperbarui',
        'clinical_draft.storeDiagnosa' => 'Diagnosis disimpan',
        'clinical_draft.primaryDiagnosa' => 'Diagnosis utama diubah',
        'clinical_draft.storeResep' => 'Resep disimpan',
        'clinical_draft.storeRujukan' => 'Rujukan disimpan',
        'clinical_draft.storeTindakan' => 'Tindakan disimpan',
        'odontogram.addendum' => 'Adendum odontogram disimpan',
        'odontogram.draft_update' => 'Draf odontogram diperbarui',
        'odontogram.sound_batch' => 'Gigi sehat dicatat',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d', 'required_with:sampai', 'before_or_equal:sampai'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'required_with:dari', 'after_or_equal:dari'],
            'aksi' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $from = $filters['dari'] ?? today()->subDays(30)->toDateString();
        $to = $filters['sampai'] ?? today()->toDateString();
        $events = DB::table('clinical_audit_events as events')
            ->leftJoin('users as actors', 'actors.id', '=', 'events.actor_id')
            ->leftJoin('kunjungan', 'kunjungan.id', '=', 'events.kunjungan_id')
            ->leftJoin('poliklinik', 'poliklinik.id', '=', 'kunjungan.poliklinik_id')
            ->whereDate('events.occurred_at', '>=', $from)
            ->whereDate('events.occurred_at', '<=', $to)
            ->when($filters['aksi'] ?? null, fn ($query, string $action) => $query->where('events.action', $action))
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('kunjungan.no_kunjungan', 'like', '%'.trim($search).'%')
                    ->orWhere('actors.name', 'like', '%'.trim($search).'%');
            }))
            ->select(['events.id', 'events.action', 'events.occurred_at', 'events.metadata', 'actors.name as actor_name', 'kunjungan.no_kunjungan as visit_number', 'poliklinik.nama as clinic_name'])
            ->latest('events.occurred_at')->latest('events.id')->paginate(15)->withQueryString();

        return Inertia::render('admin/audit-bantuan', [
            'activityLog' => true,
            'filters' => ['from' => $from, 'to' => $to, 'action' => $filters['aksi'] ?? '', 'search' => $filters['q'] ?? ''],
            'actions' => DB::table('clinical_audit_events')->distinct()->orderBy('action')->pluck('action')
                ->map(fn (string $action): array => ['key' => $action, 'label' => self::ACTION_LABELS[$action] ?? 'Aktivitas lainnya']),
            'events' => [
                'data' => $events->getCollection()->map(fn (object $event): array => [
                    'id' => (int) $event->id,
                    'label' => self::ACTION_LABELS[$event->action] ?? 'Aktivitas lainnya',
                    'actor' => $event->actor_name ?? 'Akun tidak tersedia',
                    'occurredAt' => $event->occurred_at,
                    'visitNumber' => $event->visit_number,
                    'clinic' => $event->clinic_name,
                    'reason' => null,
                    'subject' => $this->subject($event),
                    'result' => $event->action === 'admin_assistance.attempt_rejected' ? 'Ditolak / gagal' : 'Tercatat',
                ])->values(),
                'currentPage' => $events->currentPage(), 'lastPage' => $events->lastPage(),
                'from' => $events->firstItem(), 'to' => $events->lastItem(), 'total' => $events->total(),
                'previousUrl' => $events->previousPageUrl(), 'nextUrl' => $events->nextPageUrl(),
            ],
        ]);
    }

    private function subject(object $event): ?string
    {
        $metadata = json_decode($event->metadata ?? '{}', true);
        if (str_starts_with($event->action, 'user.') && isset($metadata['user_id'])) {
            return 'Akun #'.(int) $metadata['user_id'];
        }
        if (str_starts_with($event->action, 'staff.') && isset($metadata['staff_id'])) {
            return 'Petugas #'.(int) $metadata['staff_id'];
        }

        return null;
    }
}
