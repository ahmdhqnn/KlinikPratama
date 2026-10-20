<?php

namespace Tests\Feature;

use App\HakLayananVerifier;
use App\Models\JadwalDokter;
use App\Models\Kepesertaan;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManualPatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_registration_creates_patient_and_visit_without_granting_membership(): void
    {
        [$user, $clinic] = $this->registrationContext();
        $this->actingAs($user)->post(route('pendaftaran.store-pasien-baru'), [
            ...$this->identity(), 'registration_type' => 'manual', 'poliklinik_id' => $clinic->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $patient = Pasien::sole();
        $visit = Kunjungan::sole();
        $this->assertSame('001990052020101001', $patient->nip);
        $this->assertNull($patient->kepesertaan_id);
        $this->assertSame($patient->id, $visit->pasien_id);
        $this->assertSame('2026-10-20', $visit->tanggal->toDateString());
        $this->assertSame('menunggu', $visit->status);
        $this->assertNull($visit->verified_at);
        $this->assertNull($visit->verified_by);
        $this->assertSame('pending_verification', $visit->hak_layanan_snapshot['status']);
        $this->assertDatabaseCount('kepesertaan', 0);
    }

    public function test_registration_with_legacy_screening_setting_redirects_to_an_accessible_page(): void
    {
        [$user, $clinic] = $this->registrationContext();
        KlinikSetting::create(['nama_klinik' => 'Klinik Uji', 'pelaksana_ttv' => 'pendaftaran']);

        $response = $this->actingAs($user)->post(route('pendaftaran.store-pasien-baru'), [
            ...$this->identity(), 'registration_type' => 'manual', 'poliklinik_id' => $clinic->id,
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect(route('pendaftaran.laporan-kunjungan'));
        $this->get($response->headers->get('Location'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/laporan-kunjungan')
            ->has('visits.data', 1)
        );
    }

    public function test_invalid_schedule_does_not_leave_a_patient_without_a_visit(): void
    {
        [$user, $clinic] = $this->registrationContext();
        $this->actingAs($user)->post(route('pendaftaran.store-pasien-baru'), [
            ...$this->identity(), 'registration_type' => 'manual', 'poliklinik_id' => $clinic->id, 'tanggal' => '2026-10-19',
        ])->assertSessionHasErrors('tanggal');
        $this->assertDatabaseCount('pasien', 0);
        $this->assertDatabaseCount('kunjungan', 0);
    }

    public function test_manual_dental_registration_still_requires_a_future_reservation(): void
    {
        [$user, $clinic] = $this->registrationContext('gigi');
        $data = [...$this->identity(), 'registration_type' => 'manual', 'poliklinik_id' => $clinic->id];
        $this->actingAs($user)->post(route('pendaftaran.store-pasien-baru'), $data)->assertSessionHasErrors('tanggal');
        $this->assertDatabaseCount('pasien', 0);
        $this->post(route('pendaftaran.store-pasien-baru'), [...$data, 'tanggal' => '2026-10-21'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-21', Kunjungan::sole()->tanggal->toDateString());
        $this->assertDatabaseCount('kepesertaan', 0);
    }

    /** @return array<string, array{string}> */
    public static function identifiers(): array
    {
        return ['NIK' => ['nik'], 'NIP' => ['nip']];
    }

    #[DataProvider('identifiers')]
    public function test_duplicate_identity_is_rejected_without_a_partial_visit(string $field): void
    {
        [$user, $clinic] = $this->registrationContext();
        Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Pasien terdaftar', $field => $this->identity()[$field]]);
        $this->actingAs($user)->post(route('pendaftaran.store-pasien-baru'), [
            ...$this->identity(), 'registration_type' => 'manual', 'poliklinik_id' => $clinic->id,
        ])->assertSessionHasErrors($field);
        $this->assertDatabaseCount('pasien', 1);
        $this->assertDatabaseCount('kunjungan', 0);
    }

    public function test_invalid_nik_and_nip_are_rejected(): void
    {
        [$user, $clinic] = $this->registrationContext();
        $this->actingAs($user)->post(route('pendaftaran.store-pasien-baru'), [
            ...$this->identity(), 'nik' => '123', 'nip' => 'ABC123',
            'registration_type' => 'manual', 'poliklinik_id' => $clinic->id,
        ])->assertSessionHasErrors(['nik', 'nip']);
        $this->assertDatabaseCount('pasien', 0);
    }

    public function test_pending_patient_is_searchable_by_nip_and_can_return_without_membership(): void
    {
        [$user, $clinic] = $this->registrationContext();
        $patient = Pasien::create(['no_rm' => 'RM-000001', ...$this->identity()]);
        $this->actingAs($user)->getJson(route('pendaftaran.pasien.search', ['q' => $patient->nip]))
            ->assertOk()->assertJsonPath('0.id', $patient->id)->assertJsonPath('0.eligibilityReason', null);
        $data = ['pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id];
        $this->post(route('pendaftaran.store-pasien-lama'), $data)->assertSessionHasNoErrors();
        $this->post(route('pendaftaran.store-pasien-lama'), $data)->assertSessionHasErrors('tanggal');
        $this->assertDatabaseCount('kunjungan', 1);
        $this->assertNull($patient->fresh()->kepesertaan_id);
    }

    public function test_linked_ineligible_membership_cannot_be_bypassed(): void
    {
        [$user, $clinic] = $this->registrationContext();
        $member = Kepesertaan::factory()->create(['hak_layanan' => false, 'status_kepegawaian' => 'nonaktif']);
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => $member->nama, 'nik' => $member->nik, 'kepesertaan_id' => $member->id]);
        $this->actingAs($user)->post(route('pendaftaran.store-pasien-lama'), [
            'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id,
        ])->assertSessionHasErrors('pasien_id');
        $this->assertDatabaseCount('kunjungan', 0);
    }

    /** @return array<string, array{string}> */
    public static function categories(): array
    {
        return ['ASN' => ['asn'], 'Lainnya' => ['lainnya']];
    }

    #[DataProvider('categories')]
    public function test_admin_verifies_membership_separately_in_a_normal_category(string $category): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', ...$this->identity()]);
        $this->actingAs($admin)->post(route('pelayanan.pasien.verify-membership', $patient), [
            ...$this->identity(), 'kategori' => $category, 'jenis_lainnya' => 'lainnya', 'status_kepegawaian' => 'aktif',
            'unit_kerja' => 'Biro Umum', 'referensi_bukti' => 'Daftar instansi TEST-001', 'hak_layanan' => true,
        ])->assertSessionHasNoErrors();

        $membership = $patient->fresh()->kepesertaan;
        $this->assertSame($category, $membership->kategori);
        $this->assertSame($category === 'asn' ? null : 'lainnya', $membership->jenis_lainnya);
        $this->assertSame($admin->id, $membership->verified_by);
        $this->assertNull(app(HakLayananVerifier::class)->reason($membership));
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'patient.membership_verified', 'patient_id' => $patient->id]);
        $this->assertDatabaseCount('pasien', 1);
    }

    public function test_verification_links_a_matching_directory_entry_without_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', ...$this->identity()]);
        $member = Kepesertaan::factory()->create($this->identity());
        $this->actingAs($admin)->post(route('pelayanan.pasien.verify-membership', $patient), [
            ...$this->identity(), 'kategori' => 'asn', 'status_kepegawaian' => 'aktif',
            'unit_kerja' => 'Biro Umum', 'referensi_bukti' => 'Daftar TEST-001', 'hak_layanan' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame($member->id, $patient->fresh()->kepesertaan_id);
        $this->assertDatabaseCount('kepesertaan', 1);
    }

    public function test_staff_cannot_verify_membership_or_forge_verification_in_registration(): void
    {
        [$user, $clinic] = $this->registrationContext();
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Pasien terdaftar']);
        $this->actingAs($user)->post(route('pelayanan.pasien.verify-membership', $patient), [])->assertRedirect(route('pendaftaran.dashboard'));
        $this->post(route('pendaftaran.store-pasien-baru'), [
            ...$this->identity(), 'registration_type' => 'manual', 'grant_special_access' => true, 'poliklinik_id' => $clinic->id,
        ])->assertSessionHasErrors('grant_special_access');
        $this->assertDatabaseCount('kepesertaan', 0);
        $this->assertDatabaseCount('kunjungan', 0);
    }

    public function test_admin_can_correct_pending_patient_without_creating_membership(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', ...$this->identity()]);
        $this->actingAs($admin)->put(route('pelayanan.pasien.update', $patient), [...$this->identity(), 'telepon' => '081234567890'])
            ->assertSessionHasNoErrors();
        $this->assertSame('081234567890', $patient->fresh()->telepon);
        $this->assertNull($patient->fresh()->kepesertaan_id);
    }

    public function test_directory_exposes_pending_verification_and_normalizes_legacy_special_labels(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Pasien::create(['no_rm' => 'RM-000001', ...$this->identity()]);
        $this->actingAs($admin)->get(route('pelayanan.pasien.index'))->assertInertia(fn (Assert $page) => $page
            ->where('patients.data.0.canVerify', true)->where('patients.data.0.verificationData.nip', $this->identity()['nip'])
            ->missing('otherTypes.khusus'));
        $member = Kepesertaan::factory()->make(['kategori' => 'lainnya', 'jenis_lainnya' => 'khusus']);
        $this->assertSame('Lainnya', $member->membershipLabel());
    }

    /** @return array{User, Poliklinik} */
    private function registrationContext(string $type = 'umum'): array
    {
        $this->travelTo('2026-10-20 09:00:00');
        $user = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'POL-TEST', 'nama' => 'Poli Uji', 'jenis' => $type, 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-TEST', 'nama' => 'Dokter Uji', 'jabatan' => 'dokter', 'kategori' => 'medis', 'is_active' => true]);
        foreach (['selasa', 'rabu'] as $day) {
            JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => $day, 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);
        }

        return [$user, $clinic];
    }

    /** @return array<string, string> */
    private function identity(): array
    {
        return [
            'nama' => 'Siti Rahmawati', 'nik' => '3273015205900001', 'nip' => '001990052020101001',
            'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-05-20', 'jenis_kelamin' => 'P',
            'golongan_darah' => 'O', 'agama' => 'Islam', 'alamat' => 'Jl. Merdeka',
            'rt' => '004', 'rw' => '002', 'kelurahan' => 'Citarum', 'kecamatan' => 'Bandung Wetan',
        ];
    }
}
