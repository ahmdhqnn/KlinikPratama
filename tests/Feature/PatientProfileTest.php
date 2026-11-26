<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\RujukanInternal;
use App\Models\TindakanKunjungan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PatientProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_can_navigate_paginated_history_consent_and_internal_referrals(): void
    {
        $user = User::factory()->create(['role' => 'pendaftaran']);
        $patient = Pasien::create(['no_rm' => 'RM-PROFILE', 'nama' => 'Pasien Profil', 'riwayat_alergi' => 'Rahasia']);
        $clinic = $this->clinic('umum');
        $destination = $this->clinic('gigi');
        for ($number = 1; $number <= 11; $number++) {
            $visit = $this->visit($patient, $clinic, 'KNJ-PROFILE-'.$number);
        }
        $referral = RujukanInternal::create([
            'kunjungan_id' => $visit->id, 'dari_poli_id' => $clinic->id, 'ke_poli_id' => $destination->id,
            'nomor_surat' => 'RJ-PROFILE', 'status' => 'menunggu',
        ]);

        $this->actingAs($user)->get(route('pelayanan.pasien.show', $patient))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page->where('section', 'informasi')->has('patient.visits', 5)
                ->where('patient.allergies', null)->where('recordUrl', null)
                ->where('menu.2.id', 'kontrol')->where('menu.3.id', 'persetujuan')->where('menu.4.id', 'rujukan')->where('menu.5.id', 'pelepasan'));
        $this->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'kunjungan', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 11)->has('patient.visits', 1)
                ->where('patient.visits.0.number', 'KNJ-PROFILE-1'));
        $this->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'persetujuan']))
            ->assertInertia(fn (Assert $page) => $page->where('generalConsent.status', 'undocumented')
                ->missing('patient.nik')->missing('patient.address')->has('patient.visits.0.actions', 1)
                ->where('patient.visits.0.actions.0.url', route('general-consent.show', [$visit, 'asal' => 'profil'])));
        $this->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'rujukan']))
            ->assertInertia(fn (Assert $page) => $page->has('patient.visits', 1)
                ->where('patient.visits.0.actions.0.url', route('pendaftaran.pendaftaran-baru', ['rujukan_internal_id' => $referral->id])));
        $this->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'catatan-medis']))->assertForbidden();
        $this->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'tidak-ada']))->assertNotFound();
    }

    public function test_doctor_can_open_procedure_consent_only_for_an_assigned_visit(): void
    {
        $user = User::factory()->create(['role' => 'dokter']);
        $doctor = Nakes::create([
            'kode' => 'DR-PROFILE', 'nama' => 'Dokter Profil', 'kategori' => 'medis', 'jabatan' => 'dokter',
            'is_active' => true, 'user_id' => $user->id,
        ]);
        $clinic = $this->clinic('umum');
        $patient = Pasien::create(['no_rm' => 'RM-DR', 'nama' => 'Pasien Dokter']);
        $assigned = $this->visit($patient, $clinic, 'KNJ-ASSIGNED', ['dokter_id' => $doctor->id]);
        $unassigned = $this->visit($patient, $clinic, 'KNJ-UNASSIGNED');
        $procedure = TindakanKunjungan::create([
            'kunjungan_id' => $assigned->id, 'nama_tindakan_manual' => 'Tindakan Uji', 'consent_review_required' => true,
        ]);

        $this->actingAs($user)->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'persetujuan']))
            ->assertInertia(fn (Assert $page) => $page->has('patient.visits', 1)
                ->where('patient.visits.0.number', 'KNJ-ASSIGNED')
                ->where('patient.visits.0.actions.1.url', route('procedure-consent.show', [$assigned, $procedure, 'asal' => 'profil'])));
        $this->get(route('pelayanan.pasien.rekam-medis', [$patient, 'kunjungan_id' => $assigned->id]))
            ->assertInertia(fn (Assert $page) => $page->where('focusedVisitId', $assigned->id)->has('visits', 1));
        $this->get(route('pelayanan.pasien.rekam-medis', [$patient, 'kunjungan_id' => $unassigned->id]))
            ->assertInertia(fn (Assert $page) => $page->has('visits', 0));
    }

    public function test_nurse_profile_sections_are_limited_to_the_assigned_clinic(): void
    {
        $user = User::factory()->create(['role' => 'perawat']);
        $clinic = $this->clinic('umum');
        $otherClinic = $this->clinic('gigi');
        Nakes::create([
            'kode' => 'PR-PROFILE', 'nama' => 'Perawat Profil', 'kategori' => 'paramedis', 'jabatan' => 'perawat',
            'is_active' => true, 'user_id' => $user->id, 'poliklinik_id' => $clinic->id,
        ]);
        $patient = Pasien::create(['no_rm' => 'RM-PR', 'nama' => 'Pasien Perawat']);
        $local = $this->visit($patient, $clinic, 'KNJ-LOCAL');
        $other = $this->visit($patient, $otherClinic, 'KNJ-OTHER');

        $this->actingAs($user)->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'penunjang']))
            ->assertInertia(fn (Assert $page) => $page->has('patient.visits', 0));
        $this->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'kunjungan']))
            ->assertInertia(fn (Assert $page) => $page->has('patient.visits', 1)->where('patient.visits.0.id', $local->id));
        $this->get(route('pelayanan.pasien.rekam-medis', [$patient, 'kunjungan_id' => $other->id]))
            ->assertInertia(fn (Assert $page) => $page->has('visits', 0));
        $this->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'pelepasan']))->assertForbidden();
    }

    public function test_pharmacy_receives_only_relevant_visit_and_minimum_profile_fields(): void
    {
        $user = User::factory()->create(['role' => 'farmasi']);
        $patient = Pasien::create([
            'no_rm' => 'RM-FARM', 'nama' => 'Pasien Farmasi', 'riwayat_alergi' => 'Alergi uji',
            'nik' => '3210000000000001', 'alamat' => 'Alamat uji', 'telepon' => '08123456789',
        ]);
        $clinic = $this->clinic('umum');
        Nakes::create([
            'kode' => 'APT-PROFILE', 'nama' => 'Apoteker Profil', 'kategori' => 'medis', 'jabatan' => 'farmasi',
            'is_active' => true, 'user_id' => $user->id, 'poliklinik_id' => $clinic->id,
        ]);
        $this->visit($patient, $clinic, 'KNJ-ADMIN');

        $this->actingAs($user)->get(route('pelayanan.pasien.show', $patient))->assertNotFound();
        $pharmacyVisit = $this->visit($patient, $clinic, 'KNJ-PHARMACY', ['status' => 'farmasi']);
        $this->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'obat']))
            ->assertInertia(fn (Assert $page) => $page->has('menu', 3)->has('patient.visits', 1)
                ->where('patient.visits.0.id', $pharmacyVisit->id)->where('patient.allergies', 'Alergi uji')
                ->missing('patient.nik')->missing('patient.address')->missing('patient.phone'));
        $this->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'persetujuan']))->assertForbidden();
        $this->get(route('pelayanan.pasien.index'))->assertRedirect(route('farmasi.pasien'));
    }

    public function test_admin_legacy_profile_update_cannot_change_allergies(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = Pasien::create(['no_rm' => 'RM-ADMIN', 'nama' => 'Pasien Lama', 'riwayat_alergi' => 'Alergi awal']);

        $this->actingAs($admin)->put(route('pelayanan.pasien.update', $patient), [
            'nama' => 'Pasien Diperbarui', 'riwayat_alergi' => 'Alergi diganti',
        ])->assertRedirect(route('pelayanan.pasien.show', $patient))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pasien', ['id' => $patient->id, 'nama' => 'Pasien Diperbarui', 'riwayat_alergi' => 'Alergi awal']);
        $this->assertDatabaseHas('clinical_audit_events', ['actor_id' => $admin->id, 'patient_id' => $patient->id, 'action' => 'patient.identity_updated']);
    }

    public function test_admin_can_archive_an_empty_duplicate_but_cannot_move_recorded_visits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $main = Pasien::create(['no_rm' => 'RM-MAIN', 'nama' => 'Pasien Utama', 'nik' => '3210000000000002']);
        $duplicate = Pasien::create(['no_rm' => 'RM-DUP', 'nama' => 'Pasien Duplikat', 'nik' => $main->nik]);
        $empty = Pasien::create(['no_rm' => 'RM-EMPTY', 'nama' => 'Pasien Kosong', 'nik' => $main->nik]);
        $visit = $this->visit($duplicate, $this->clinic('umum'), 'KNJ-DUP');

        $this->actingAs($admin)->post(route('pelayanan.pasien.gabung'), [
            'pasien_utama_id' => $main->id, 'pasien_hapus_id' => $duplicate->id,
        ])->assertSessionHasErrors('pasien_hapus_id');
        $this->assertNotSoftDeleted($duplicate);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'pasien_id' => $duplicate->id]);

        $this->post(route('pelayanan.pasien.gabung'), [
            'pasien_utama_id' => $main->id, 'pasien_hapus_id' => $empty->id,
        ])->assertRedirect(route('pelayanan.pasien.show', $main))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($empty);

        $this->put(route('pelayanan.pasien.update', $main), [
            'registration_type' => 'manual', 'nama' => $main->nama, 'nik' => $main->nik, 'telepon' => '08123456789',
        ])->assertRedirect(route('pelayanan.pasien.show', $main))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pasien', ['id' => $main->id, 'telepon' => '08123456789']);
    }

    public function test_admin_can_verify_the_existing_identity_after_archiving_an_empty_duplicate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $identity = ['nama' => 'Pasien Kepesertaan', 'nik' => '3210000000000003', 'nip' => '199503072025011001'];
        $patient = Pasien::create(['no_rm' => 'RM-VERIFY', ...$identity]);
        $duplicate = Pasien::create(['no_rm' => 'RM-VERIFY-DUP', 'nama' => $identity['nama'], 'nik' => $identity['nik']]);

        $this->actingAs($admin)->post(route('pelayanan.pasien.gabung'), [
            'pasien_utama_id' => $patient->id, 'pasien_hapus_id' => $duplicate->id,
        ])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pasien.verify-membership', $patient), [
            ...$identity, 'kategori' => 'asn', 'status_kepegawaian' => 'aktif', 'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1995-03-07', 'jenis_kelamin' => 'P', 'agama' => 'Islam', 'alamat' => 'Jl. Contoh',
            'unit_kerja' => 'Biro Uji', 'referensi_bukti' => 'Daftar Uji 001', 'hak_layanan' => true,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($patient->refresh()->kepesertaan_id);
        $this->assertSoftDeleted($duplicate);
    }

    private function clinic(string $type): Poliklinik
    {
        return Poliklinik::create(['kode' => strtoupper($type), 'nama' => 'Poli '.$type, 'jenis' => $type, 'is_active' => true]);
    }

    private function visit(Pasien $patient, Poliklinik $clinic, string $number, array $attributes = []): Kunjungan
    {
        return $patient->kunjungan()->create([
            'no_kunjungan' => $number, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'selesai',
            ...$attributes,
        ]);
    }
}
