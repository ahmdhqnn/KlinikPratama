<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAssistanceAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_assistance_audit_without_exposing_patient_or_raw_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-AUDIT-001', 'nama' => 'Pasien Tidak Ditampilkan']);
        $clinic = Poliklinik::create(['kode' => 'AUDIT', 'nama' => 'Poli Audit', 'jenis' => 'umum', 'is_active' => true]);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-AUDIT-001', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'farmasi']);
        DB::table('clinical_audit_events')->insert([
            'actor_id' => $admin->id,
            'patient_id' => $patient->id,
            'kunjungan_id' => $visit->id,
            'action' => 'pharmacy.dispensed',
            'route_name' => 'pelayanan.farmasi.selesai',
            'occurred_at' => now(),
            'metadata' => json_encode(['assistance_reason' => 'Petugas farmasi sedang tidak tersedia', 'patient_note' => 'Isi klinis rahasia'], JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($admin)->get(route('admin.audit-bantuan', [
            'dari' => today()->toDateString(),
            'sampai' => today()->toDateString(),
            'aksi' => 'pharmacy.dispensed',
            'q' => 'KNJ-AUDIT',
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit-bantuan')
            ->where('events.total', 1)
            ->where('events.data.0.actor', $admin->name)
            ->where('events.data.0.visitNumber', 'KNJ-AUDIT-001')
            ->where('events.data.0.clinic', 'Poli Audit')
            ->where('events.data.0.reason', 'Petugas farmasi sedang tidak tersedia')
            ->missing('events.data.0.metadata')
            ->missing('events.data.0.patient'));
    }

    public function test_assistance_audit_is_admin_only_and_excludes_non_admin_actors(): void
    {
        $clinic = Poliklinik::create(['kode' => 'AUDIT-2', 'nama' => 'Poli Audit', 'jenis' => 'gigi', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-AUDIT-002', 'nama' => 'Pasien Kedua']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-AUDIT-002', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'tindakan']);
        $adminActor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $nurseActor = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $doctorActor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        foreach ([[$adminActor, 'nursing_procedure.complete'], [$nurseActor, 'nursing_procedure.complete'], [$doctorActor, 'admin_assistance.reviewed']] as [$actor, $action]) {
            DB::table('clinical_audit_events')->insert([
                'actor_id' => $actor->id,
                'patient_id' => $patient->id,
                'kunjungan_id' => $visit->id,
                'action' => $action,
                'occurred_at' => now(),
                'metadata' => json_encode(['assistance_reason' => 'Alasan bantuan sintetis'], JSON_THROW_ON_ERROR),
            ]);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))
            ->get(route('admin.audit-bantuan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.total', 2)
                ->where('events.data.0.label', 'Draf ditinjau dokter')
                ->where('events.data.1.actor', $adminActor->name));

        foreach (['perawat', 'dokter', 'farmasi', 'manajemen'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->get(route('admin.audit-bantuan'))
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]))
            ->get(route('admin.audit-bantuan'))
            ->assertRedirect(route('pendaftaran.dashboard'));
    }

    public function test_audit_displays_safe_outcome_for_rejected_admin_attempts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-AUDIT-FAIL', 'nama' => 'Pasien Tidak Ditampilkan']);
        $clinic = Poliklinik::create(['kode' => 'AUDIT-FAIL', 'nama' => 'Poli Audit', 'jenis' => 'umum', 'is_active' => true]);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-AUDIT-FAIL', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        DB::table('clinical_audit_events')->insert([
            'actor_id' => $admin->id,
            'kunjungan_id' => $visit->id,
            'action' => 'admin_assistance.attempt_rejected',
            'route_name' => 'pelayanan.pemeriksaan.assist',
            'occurred_at' => now(),
            'metadata' => json_encode(['failure_kind' => 'validation_or_workflow', 'status' => 302, 'request_body' => 'must not leak'], JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($admin)->get(route('admin.audit-bantuan', ['aksi' => 'admin_assistance.attempt_rejected']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.total', 1)
                ->where('events.data.0.label', 'Percobaan bantuan ditolak/gagal')
                ->where('events.data.0.result', 'validation_or_workflow (HTTP 302)')
                ->where('events.data.0.reason', null)
                ->missing('events.data.0.metadata')
                ->missing('events.data.0.request_body'));
    }

    public function test_admin_dashboard_examination_link_can_filter_to_the_selected_service_stage(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-DASH-001', 'nama' => 'Pasien Dashboard']);
        $clinic = Poliklinik::create(['kode' => 'DASH', 'nama' => 'Poli Dashboard', 'jenis' => 'umum', 'is_active' => true]);
        foreach ([['pemeriksaan', 'KNJ-DASH-EXAM'], ['tindakan', 'KNJ-DASH-NURSE']] as [$status, $number]) {
            Kunjungan::create(['no_kunjungan' => $number, 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => $status]);
        }

        $this->actingAs($admin)->get(route('pelayanan.pemeriksaan.index', ['tanggal' => today()->toDateString(), 'status' => 'pemeriksaan']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visits.total', 1)
                ->where('visits.data.0.number', 'KNJ-DASH-EXAM')
                ->where('visits.data.0.status', 'pemeriksaan'));
    }
}
