<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\SuratMedis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminMedicalRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reads_cross_clinic_history_with_filters_audit_and_document_return(): void
    {
        [$patient, $general, $dental] = $this->context();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $letter = SuratMedis::create(['kunjungan_id' => $general->id, 'jenis' => 'sakit', 'nomor_surat' => 'SK-001', 'tanggal' => today(), 'konten' => 'Surat yang sudah diterbitkan']);
        $url = route('pelayanan.pasien.rekam-medis', $patient);
        $response = $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canSelectClinic', true)->has('visits', 2)->has('clinics', 2)->where('pagination.total', 2));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'medical_record.view', 'actor_id' => $admin->id, 'patient_id' => $patient->id]);
        $this->get($url.'?poliklinik_id='.$dental->poliklinik_id)->assertInertia(fn (Assert $page) => $page->has('visits', 1)->where('visits.0.id', $dental->id));
        $this->get($url.'?search=KNJ-GENERAL')->assertInertia(fn (Assert $page) => $page->has('visits', 1)->where('visits.0.documents.0.name', 'SK-001'));
        $this->get(route('persuratan.surat.cetak', $letter).'?asal=rme')->assertInertia(fn (Assert $page) => $page->where('document.backUrl', $url)->where('document.backLabel', 'Kembali ke rekam medis pasien'));
        $this->get(route('pelayanan.pasien.show', $patient))->assertInertia(fn (Assert $page) => $page->where('permissions.viewRme', true));
        $this->get(route('persuratan.surat.create', $general))->assertForbidden();
        $this->post(route('persuratan.surat.store', $general), [])->assertForbidden();
        $this->assertDatabaseCount('surat_medis', 1);
    }

    public function test_new_admin_history_access_does_not_expand_other_roles_or_accept_external_back_links(): void
    {
        [$patient, $general] = $this->context();
        $url = route('pelayanan.pasien.rekam-medis', $patient);
        foreach (['farmasi', 'manajemen', 'kasir', 'pendaftaran'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get($url)->assertForbidden();
        }
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        Nakes::create(['kode' => 'DOC-OTHER', 'nama' => 'Dokter lain', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctor->id, 'is_active' => true]);
        $this->actingAs($doctor)->get($url)->assertForbidden();
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        Nakes::create(['kode' => 'NUR-GENERAL', 'nama' => 'Perawat umum', 'kategori' => 'medis', 'jabatan' => 'perawat', 'poliklinik_id' => $general->poliklinik_id, 'user_id' => $nurse->id, 'is_active' => true]);
        $this->actingAs($nurse)->get($url)->assertInertia(fn (Assert $page) => $page->has('visits', 1)->where('visits.0.id', $general->id)->where('visits.0.documents', []));
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->withHeader('Referer', 'https://outside.example/path')->get($url)->assertInertia(fn (Assert $page) => $page->where('backUrl', route('pelayanan.pasien.show', $patient)));
        $admin->update(['is_active' => false]);
        $this->actingAs($admin)->get($url)->assertForbidden();
    }

    /** @return array{Pasien, Kunjungan, Kunjungan} */
    private function context(): array
    {
        $patient = Pasien::create(['no_rm' => 'RM-ADMIN', 'nama' => 'Pasien Riwayat Sintetis']);
        $visits = [];
        foreach (['umum' => 'GENERAL', 'gigi' => 'DENTAL'] as $type => $code) {
            $clinic = Poliklinik::create(['kode' => $code, 'nama' => 'Poli '.$type, 'jenis' => $type, 'is_active' => true]);
            $visits[] = Kunjungan::create(['no_kunjungan' => 'KNJ-'.$code, 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'selesai']);
        }

        return [$patient, ...$visits];
    }
}
