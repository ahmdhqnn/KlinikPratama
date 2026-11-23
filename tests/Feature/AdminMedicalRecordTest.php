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
        [$patient, $general] = $this->context();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $letter = SuratMedis::create(['kunjungan_id' => $general->id, 'jenis' => 'sakit', 'nomor_surat' => 'SK-001', 'tanggal' => today(), 'konten' => 'Konten klinis rahasia sintetis']);
        $this->actingAs($admin)->get(route('pelayanan.pasien.rekam-medis', $patient))->assertForbidden();
        $this->get(route('persuratan.surat.cetak', $letter))->assertForbidden();
        $this->get(route('pelayanan.pasien.show', $patient))->assertInertia(fn (Assert $page) => $page->where('permissions.viewRme', false));
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'medical_record.view', 'actor_id' => $admin->id]);
    }

    public function test_admin_cannot_hide_a_patient_with_clinical_history(): void
    {
        [$patient, $visit] = $this->context();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->delete(route('pelayanan.pasien.destroy', $patient))->assertConflict();

        $this->assertNotSoftDeleted($patient);
        $this->assertModelExists($visit);
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
        $this->actingAs($admin)->withHeader('Referer', 'https://outside.example/path')->get($url)->assertForbidden();
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
