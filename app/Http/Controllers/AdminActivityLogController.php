<?php

namespace App\Http\Controllers;

use App\AuditLogQuery;
use App\ClinicalAuditRecorder;
use App\Exports\OperationalReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminActivityLogController extends Controller
{
    private const ACTION_LABELS = [
        'auth.login' => 'Masuk ke aplikasi',
        'auth.failed_login' => 'Percobaan masuk ditolak',
        'auth.logout' => 'Keluar dari aplikasi',
        'auth.session_expired' => 'Sesi berakhir',
        'auth.password_changed' => 'Kata sandi pribadi diubah',
        'access.denied' => 'Akses ditolak',
        'visit.cancelled' => 'Kunjungan dibatalkan',
        'attachment.voided' => 'Lampiran dibatalkan',
        'general_consent.recorded' => 'Keputusan persetujuan umum dicatat',
        'general_consent.view' => 'Riwayat persetujuan umum dilihat',
        'general_consent.download' => 'Bukti persetujuan umum diunduh',
        'general_consent.emergency_deferral' => 'Administrasi consent ditunda pada kondisi darurat',
        'procedure_consent.recorded' => 'Keputusan persetujuan tindakan dicatat',
        'procedure.doctor_started' => 'Dokter mengonfirmasi mulai tindakan',
        'procedure_consent.view' => 'Riwayat persetujuan tindakan dilihat',
        'procedure_signature.opened' => 'Sesi tanda tangan pasien dibuka',
        'procedure_signature.captured' => 'Keputusan ditandatangani oleh pasien atau wakil',
        'procedure_signature.witnessed' => 'Saksi mengonfirmasi dan bukti PDF diarsipkan',
        'procedure_signature.cancelled' => 'Sesi tanda tangan pasien dibatalkan',
        'procedure_consent_template.draft' => 'Draf template persetujuan disimpan',
        'procedure_consent_template.published' => 'Versi SOP persetujuan diterbitkan',
        'procedure_consent_template.updated' => 'Template SOP persetujuan diperbarui',
        'procedure_consent_template.archived' => 'Template SOP persetujuan diarsipkan',
        'procedure_consent_template.restored' => 'Template SOP persetujuan dipulihkan',
        'procedure_consent.download' => 'Bukti persetujuan tindakan diunduh',
        'attachment.restored' => 'Lampiran dipulihkan',
        'audit.export' => 'Log aktivitas diekspor',
        'audit.print' => 'Cetak log aktivitas diminta',
        'aggregate_report.view' => 'Ringkasan pelayanan dilihat',
        'aggregate_report.export' => 'Ringkasan pelayanan diekspor',
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
        'clinical_coding.correction_view' => 'Form koreksi coding dilihat',
        'clinical_coding.corrected' => 'Coding diagnosis dikoreksi melalui adendum',
        'medical_letter.void' => 'Dokumen medis dibatalkan tanpa menghapus arsip',
        'medical_letter.replaced' => 'Dokumen medis diganti dengan arsip penerbitan baru',
        'information_release.view' => 'Register pelepasan informasi dilihat',
        'information_release.requested' => 'Permintaan pelepasan informasi dicatat',
        'information_release.authorized' => 'Pelepasan informasi diotorisasi dokter',
        'information_release.denied' => 'Permintaan pelepasan informasi ditolak',
        'information_release.revoked' => 'Otorisasi pelepasan informasi dicabut',
        'information_release.delivered' => 'Penyerahan salinan medis dicatat',
        'pharmacy.credential_recorded' => 'Kualifikasi dan penugasan farmasi diverifikasi / dicabut',
        'pharmacy.credential_view' => 'Riwayat kualifikasi farmasi dilihat',
        'pharmacy.review_view' => 'Review resep farmasi dilihat',
        'pharmacy.review_recorded' => 'Review resep apoteker dicatat',
        'medical_document.verify' => 'Status arsip dokumen diverifikasi melalui QR',
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
        $events = app(AuditLogQuery::class)->query($filters)->paginate(15)->withQueryString();

        return Inertia::render('admin/audit-bantuan', [
            'activityLog' => true,
            'exportUrl' => route('admin.log-aktivitas.export', $filters),
            'printUrl' => route('admin.log-aktivitas.print', $filters),
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
                    'result' => $event->outcome === 'denied' || $event->action === 'admin_assistance.attempt_rejected' ? 'Ditolak / gagal' : 'Tercatat',
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

    public function export(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $filters = $this->exportFilters($request);
        $audit->record($request, 'audit.export');

        return Excel::download(new OperationalReportExport(
            app(AuditLogQuery::class)->query($filters),
            ['Waktu', 'Kegiatan', 'ID operator', 'Operator', 'Peran saat kejadian', 'Hasil', 'IP', 'User agent', 'No. kunjungan'],
            fn ($event): array => [$event->occurred_at, self::ACTION_LABELS[$event->action] ?? $event->action, $event->actor_id, $event->actor_name ?? 'Akun tidak tersedia', $event->actor_role, $event->outcome, $event->ip_address, $event->user_agent, $event->visit_number],
        ), 'log-aktivitas-'.today()->toDateString().'.xlsx');
    }

    public function print(Request $request, ClinicalAuditRecorder $audit): \Illuminate\Http\Response
    {
        $filters = $this->exportFilters($request);
        $audit->record($request, 'audit.print');
        $events = app(AuditLogQuery::class)->query($filters)->limit(1001)->get();
        abort_if($events->count() > 1000, 422, 'Persempit periode atau filter hingga maksimal 1.000 kegiatan untuk dicetak. Ekspor Excel tersedia untuk rentang lebih luas.');

        return response()->view('audit-print', ['events' => $events, 'labels' => self::ACTION_LABELS, 'printedAt' => now()->format('d/m/Y H:i')]);
    }

    private function exportFilters(Request $request): array
    {
        return $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d', 'required_with:sampai', 'before_or_equal:sampai'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'required_with:dari', 'after_or_equal:dari'],
            'aksi' => ['nullable', 'string', 'max:64'], 'q' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
