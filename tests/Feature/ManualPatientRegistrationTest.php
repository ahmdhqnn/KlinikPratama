<?php

namespace Tests\Feature;

use App\HakLayananVerifier;
use App\Models\Kepesertaan;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManualPatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_patient_registration_does_not_grant_internal_service_eligibility_or_create_a_visit(): void
    {
        $registrationUser = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);

        $response = $this->actingAs($registrationUser)->post(route('pendaftaran.store-pasien-baru'), [
            'registration_type' => 'manual',
            'nama' => 'Siti Rahmawati',
            'nik' => '3273015205900001',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1990-05-20',
            'jenis_kelamin' => 'P',
            'golongan_darah' => 'O',
            'agama' => 'Islam',
            'alamat' => 'Jl. Merdeka',
            'rt' => '004',
            'rw' => '002',
            'kelurahan' => 'Citarum',
            'kecamatan' => 'Bandung Wetan',
            'telepon' => '081234567890',
        ]);

        $response->assertRedirect(route('pendaftaran.database-pasien'));
        $patient = Pasien::where('nik', '3273015205900001')->firstOrFail();
        $this->assertSame('Siti Rahmawati', $patient->nama);
        $this->assertSame('004', $patient->rt);
        $this->assertNull($patient->riwayat_alergi);
        $this->assertNull($patient->kepesertaan_id);
        $this->assertDatabaseCount('kunjungan', 0);
        $response->assertSessionHas('success', "Pasien Siti Rahmawati (No. RM: {$patient->no_rm}) berhasil didaftarkan. Hak layanan belum diverifikasi; tautkan kepesertaan sebelum membuat kunjungan internal.");
    }

    public function test_manual_patient_registration_rejects_an_existing_nik_without_creating_a_duplicate(): void
    {
        $registrationUser = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Pasien Lama', 'nik' => '3273015205900001']);

        $response = $this->actingAs($registrationUser)->from(route('pendaftaran.pendaftaran-baru'))->post(route('pendaftaran.store-pasien-baru'), [
            'registration_type' => 'manual',
            'nama' => 'Pasien Duplikat',
            'nik' => '3273015205900001',
        ]);

        $response->assertSessionHasErrors(['nik' => 'NIK sudah terdaftar. Cari data pasien sebelum membuat rekam medis baru.']);
        $this->assertDatabaseCount('pasien', 1);
        $this->assertDatabaseCount('kunjungan', 0);
    }

    public function test_manual_patient_registration_requires_nik_to_have_exactly_sixteen_digits_when_provided(): void
    {
        $registrationUser = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);

        foreach (['123456789012345', '12345678901234567', '123456789012345a'] as $nik) {
            $this->actingAs($registrationUser)->from(route('pendaftaran.pendaftaran-baru'))
                ->post(route('pendaftaran.store-pasien-baru'), [
                    'registration_type' => 'manual',
                    'nama' => 'Pasien Uji',
                    'nik' => $nik,
                ])
                ->assertSessionHasErrors('nik');
        }

        $this->assertDatabaseCount('pasien', 0);
    }

    public function test_admin_manual_registration_uses_the_same_patient_identity_fields_and_can_verify_special_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $data = [
            'registration_type' => 'manual',
            'nama' => 'Budi Santoso',
            'nik' => '3273015205900002',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-05-20',
            'jenis_kelamin' => 'L',
            'golongan_darah' => 'O',
            'agama' => 'Islam',
            'nama_ibu' => 'Siti Aminah',
            'telepon' => '081234567891',
            'alamat' => 'Jl. Merdeka',
            'rt' => '004',
            'rw' => '002',
            'kelurahan' => 'Cikini',
            'kecamatan' => 'Menteng',
            'grant_special_access' => true,
            'special_unit_kerja' => 'Unit Protokol',
            'special_reference' => 'Surat tugas ST-001',
        ];

        $response = $this->actingAs($admin)->post(route('pelayanan.pasien.store'), $data);

        $patient = Pasien::where('nik', $data['nik'])->firstOrFail();
        $membership = Kepesertaan::whereKey($patient->kepesertaan_id)->firstOrFail();
        $response->assertRedirect(route('pelayanan.pasien.show', $patient));
        $this->assertSame('Budi Santoso', $patient->nama);
        $this->assertSame('Siti Aminah', $patient->nama_ibu);
        $this->assertSame('004', $patient->rt);
        $this->assertNull($patient->riwayat_alergi);
        $this->assertSame('lainnya', $membership->kategori);
        $this->assertSame('khusus', $membership->jenis_lainnya);
        $this->assertSame($admin->id, $membership->verified_by);
        $this->assertNull(app(HakLayananVerifier::class)->reason($membership));
        $this->assertDatabaseCount('kunjungan', 0);

        $this->actingAs($admin)->put(route('kepesertaan.update', $membership), [
            'nama' => $membership->nama,
            'nik' => $membership->nik,
            'kategori' => 'lainnya',
            'jenis_lainnya' => 'khusus',
            'status_kepegawaian' => 'nonaktif',
            'unit_kerja' => $membership->unit_kerja,
            'tempat_lahir' => $membership->tempat_lahir,
            'tanggal_lahir' => $membership->tanggal_lahir->toDateString(),
            'jenis_kelamin' => $membership->jenis_kelamin,
            'agama' => $membership->agama,
            'golongan_darah' => $membership->golongan_darah,
            'alamat' => $membership->alamat,
            'rt' => $membership->rt,
            'rw' => $membership->rw,
            'kelurahan' => $membership->kelurahan,
            'kecamatan' => $membership->kecamatan,
            'hak_layanan' => false,
            'referensi_bukti' => $membership->referensi_bukti,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(app(HakLayananVerifier::class)->reason($membership->fresh()));
    }

    public function test_registration_staff_cannot_grant_special_internal_service_access(): void
    {
        $registrationUser = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);

        $response = $this->actingAs($registrationUser)->from(route('pendaftaran.pendaftaran-baru'))->post(route('pendaftaran.store-pasien-baru'), [
            'registration_type' => 'manual',
            'nama' => 'Dewi Anggraini',
            'nik' => '3273015205900003',
            'grant_special_access' => true,
        ]);

        $response->assertSessionHasErrors('grant_special_access');
        $this->assertDatabaseCount('pasien', 0);
        $this->assertDatabaseCount('kepesertaan', 0);
    }

    public function test_admin_can_verify_a_registered_patient_from_the_patient_database(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create([
            'no_rm' => 'RM-000010', 'nama' => 'Rina Puspita', 'nik' => '3273015205900010',
            'jenis_kelamin' => 'P', 'alamat' => 'Jl. Merdeka',
        ]);
        $verification = [
            'nama' => $patient->nama, 'nik' => $patient->nik, 'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1990-05-20', 'jenis_kelamin' => 'P', 'golongan_darah' => 'O',
            'agama' => 'Islam', 'alamat' => 'Jl. Merdeka', 'rt' => '004', 'rw' => '002',
            'kelurahan' => 'Citarum', 'kecamatan' => 'Bandung Wetan', 'unit_kerja' => 'Unit Protokol',
            'referensi_bukti' => 'Surat tugas ST-010',
            'hak_layanan' => true,
        ];

        $this->actingAs($admin)->from(route('pelayanan.pasien.index'))
            ->post(route('pelayanan.pasien.verify-special-access', $patient), $verification)
            ->assertRedirect(route('pelayanan.pasien.index'))
            ->assertSessionHasNoErrors();

        $patient->refresh();
        $membership = Kepesertaan::findOrFail($patient->kepesertaan_id);
        $this->assertSame('lainnya', $membership->kategori);
        $this->assertSame('khusus', $membership->jenis_lainnya);
        $this->assertSame($admin->id, $membership->verified_by);
        $this->assertSame('004', $patient->rt);
        $this->assertNull(app(HakLayananVerifier::class)->reason($membership));
        $this->assertDatabaseHas('clinical_audit_events', [
            'action' => 'patient.special_access_verified', 'patient_id' => $patient->id,
        ]);
    }

    public function test_admin_patient_table_marks_unverified_patients_for_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Pasien::create([
            'no_rm' => 'RM-000012', 'nama' => 'Rina Puspita', 'nik' => '3273015205900012',
            'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-05-20', 'jenis_kelamin' => 'P',
        ]);

        $this->actingAs($admin)->get(route('pelayanan.pasien.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/pasien/index')
                ->where('patients.data.0.canVerify', true)
                ->where('patients.data.0.verificationData.nik', '3273015205900012')
                ->where('today', today()->toDateString())
            );
    }

    public function test_only_admin_can_verify_patient_special_access_from_the_patient_database(): void
    {
        $registrationUser = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-000011', 'nama' => 'Rina Puspita']);

        $this->actingAs($registrationUser)
            ->post(route('pelayanan.pasien.verify-special-access', $patient), [])
            ->assertRedirect(route('pendaftaran.dashboard'));

        $this->assertDatabaseCount('kepesertaan', 0);
    }
}
