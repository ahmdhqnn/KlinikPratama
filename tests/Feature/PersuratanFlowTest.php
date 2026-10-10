<?php

namespace Tests\Feature;

use App\Models\CorrespondenceTemplate;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\ResepObat;
use App\Models\RujukanInternal;
use App\Models\SuratMedis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersuratanFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_templates_while_doctors_only_see_active_templates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $this->createDoctor($doctorUser, 'DOK-TEMPLATE');
        $active = CorrespondenceTemplate::factory()->create(['is_active' => true]);
        CorrespondenceTemplate::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->get(route('persuratan.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/index')->where('isAdmin', true)->has('templates', 2));
        $this->actingAs($doctorUser)->get(route('persuratan.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/index')->where('isAdmin', false)->has('templates', 1));
        $this->post(route('persuratan.template.store'), $this->templatePayload())->assertForbidden();
        $this->actingAs($admin)->post(route('persuratan.template.store'), $this->templatePayload())
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('correspondence_templates', ['kode' => 'SKS-TEST', 'is_active' => true]);
        $this->put(route('persuratan.template.update', $active), $this->templatePayload(['kode' => 'SKS-UPDATED']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('correspondence_templates', ['id' => $active->id, 'kode' => 'SKS-UPDATED']);
    }

    public function test_doctor_can_issue_numbered_letter_and_internal_referral_then_print_them(): void
    {
        [$doctorUser, $doctor, $visit, $origin, $target, $patient] = $this->visitFixture();
        $letterTemplate = CorrespondenceTemplate::factory()->create([
            'jenis' => 'sakit', 'prefix' => 'SKS', 'nomor_berikutnya' => 7,
            'isi' => 'Pasien [pasien] diperiksa oleh [dokter] pada [tanggal].',
        ]);
        $referralTemplate = CorrespondenceTemplate::factory()->create([
            'jenis' => 'rujukan_internal', 'prefix' => 'RI', 'nomor_berikutnya' => 2,
            'isi' => 'Mohon konsultasi [pasien] ke [poli_tujuan].',
        ]);

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.surat.store', $visit), [
            'jenis' => 'sakit', 'tanggal' => today()->toDateString(), 'surat_template_id' => $letterTemplate->id, 'konten' => '',
        ])->assertSessionHasNoErrors();
        $letter = SuratMedis::sole();
        $this->assertSame('SKS/0007/'.today()->format('m').'/'.today()->format('Y'), $letter->nomor_surat);
        $this->assertSame('Pasien '.$patient->nama.' diperiksa oleh '.$doctor->nama.' pada '.today()->locale('id')->isoFormat('D MMMM Y').'.', $letter->konten);

        $this->post(route('pelayanan.pemeriksaan.rujukan.store', $visit), [
            'ke_poli_id' => $target->id, 'surat_template_id' => $referralTemplate->id, 'catatan' => 'Evaluasi lanjutan', 'konten_surat' => '',
        ])->assertSessionHasNoErrors();
        $referral = RujukanInternal::sole();
        $this->assertSame('RI/0002/'.today()->format('m').'/'.today()->format('Y'), $referral->nomor_surat);
        $this->assertSame('Mohon konsultasi '.$patient->nama.' ke '.$target->nama.'.', $referral->konten_surat);

        $this->get(route('persuratan.surat.cetak', $letter))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.kind', 'surat')->where('document.number', $letter->nomor_surat)->where('document.backUrl', route('persuratan.index')));
        $this->get(route('persuratan.surat.cetak', $letter).'?asal=pemeriksaan')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.backUrl', route('pelayanan.pemeriksaan.show', $visit).'?tab=surat')->where('document.backLabel', 'Kembali ke surat medis'));
        $this->get(route('persuratan.rujukan.cetak', $referral))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.kind', 'rujukan_internal')->where('document.toClinic', $target->nama)->where('document.backUrl', route('persuratan.index')));
        $this->get(route('persuratan.rujukan.cetak', $referral).'?asal=pemeriksaan')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.backUrl', route('pelayanan.pemeriksaan.show', $visit).'?tab=rujukan'));
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'medical_letter.print', 'kunjungan_id' => $visit->id]);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'internal_referral.print', 'kunjungan_id' => $visit->id]);
    }

    public function test_doctor_can_print_external_prescription_but_cannot_read_another_doctors_documents(): void
    {
        [$doctorUser, , $visit] = $this->visitFixture();
        $otherUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $otherDoctor = $this->createDoctor($otherUser, 'DOK-OTHER');
        [, , $otherVisit] = $this->visitFixture($otherUser, $otherDoctor);
        $prescription = Resep::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $visit->dokter_id,
            'no_resep' => 'RSP-TEST-1', 'status' => 'menunggu',
        ]);
        ResepObat::create([
            'resep_id' => $prescription->id, 'nama_obat' => 'Paracetamol', 'jumlah' => 10,
            'satuan' => 'tablet', 'aturan_pakai' => '3 x 1 sesudah makan', 'jenis' => 'jadi',
            'is_resep_luar' => true, 'stok_dikurangi' => false,
        ]);

        $this->actingAs($doctorUser)->get(route('persuratan.resep.cetak', $visit))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.kind', 'resep_luar')->where('document.items.0.name', 'Paracetamol')->where('document.backUrl', route('persuratan.index')));
        $this->get(route('persuratan.resep.cetak', $visit).'?asal=pemeriksaan')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.backUrl', route('pelayanan.pemeriksaan.show', $visit).'?tab=resep-luar'));
        $this->get(route('persuratan.resep.cetak', $otherVisit))->assertNotFound();
    }

    /** @return array{User, Nakes, Kunjungan, Poliklinik, Poliklinik, Pasien} */
    private function visitFixture(?User $user = null, ?Nakes $doctor = null): array
    {
        $user ??= User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor ??= $this->createDoctor($user, 'DOK-LETTER');
        $origin = Poliklinik::create(['kode' => 'UMUM-L-'.$doctor->id, 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $target = Poliklinik::create(['kode' => 'GIGI-L-'.$doctor->id, 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-LETTER-'.$doctor->id, 'nama' => 'Pasien Surat', 'jenis_kelamin' => 'P']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-LETTER-'.$doctor->id,
            'pasien_id' => $patient->id,
            'poliklinik_id' => $origin->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today(),
            'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);

        return [$user, $doctor, $visit, $origin, $target, $patient];
    }

    private function createDoctor(User $user, string $code): Nakes
    {
        return Nakes::create([
            'kode' => $code, 'nama' => 'dr. Uji Persuratan', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function templatePayload(array $overrides = []): array
    {
        return array_merge([
            'kode' => 'SKS-TEST', 'nama' => 'Surat keterangan sakit', 'jenis' => 'sakit',
            'prefix' => 'SKS', 'format_nomor' => '[prefix]/[urut]/[bulan]/[tahun]',
            'nomor_berikutnya' => 1, 'isi' => 'Untuk [pasien] pada [tanggal].', 'is_active' => true,
        ], $overrides);
    }
}
