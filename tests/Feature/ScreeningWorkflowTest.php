<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScreeningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_marks_the_visit_in_progress_and_records_the_actual_nurse(): void
    {
        [$nurse, $visit, $staff] = $this->context();

        $this->actingAs($nurse)->post(route('pelayanan.screening.start', $visit))
            ->assertRedirect(route('pelayanan.screening.show', $visit));

        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'screening']);
        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'petugas_id' => $staff->id, 'submitted_at' => null, 'kesimpulan_triase' => null]);
        $this->assertNotNull($visit->fresh()->screening->started_at);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'screening.started', 'kunjungan_id' => $visit->id]);
    }

    public function test_dental_draft_allows_incomplete_answers_without_forwarding_or_replacing_verified_allergy(): void
    {
        [$nurse, $visit] = $this->context('gigi');
        $visit->pasien->update(['riwayat_alergi' => 'Penisilin']);

        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), [
            'tindakan' => 'draf', 'keluhan' => 'Nyeri gigi', 'konfirmasi_riwayat' => false,
            'risiko_medis_gigi' => [], 'status_alergi' => 'tidak_ada',
        ])->assertSessionHasNoErrors()->assertRedirect(route('pelayanan.screening.show', $visit));

        $this->assertSame('screening', $visit->fresh()->status);
        $this->assertNull($visit->screening->submitted_at);
        $this->assertNull($visit->screening->kesimpulan_triase);
        $this->assertSame('Penisilin', $visit->pasien->fresh()->riwayat_alergi);
        $this->get(route('pelayanan.screening.print', $visit))->assertSee('DRAF — BELUM DITERUSKAN KE DOKTER');
    }

    public function test_sending_requires_assessment_and_explicit_history_confirmation(): void
    {
        [$nurse, $visit] = $this->context('gigi');

        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), [])
            ->assertSessionHasErrors(['keluhan', 'lama_keluhan', 'sumber_informasi', 'status_alergi', 'status_riwayat_penyakit', 'konfirmasi_riwayat', 'risiko_medis_gigi']);

        $this->assertDatabaseCount('screening', 0);
        $this->assertSame('menunggu', $visit->fresh()->status);
    }

    public function test_vitals_remain_required_while_anthropometry_and_triage_are_optional(): void
    {
        [$nurse, $visit] = $this->context();
        $payload = $this->payload();
        unset($payload['td_sistole'], $payload['berat_badan']);

        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), $payload)
            ->assertSessionHasErrors(['td_sistole' => 'Isi hasil pengukuran sebelum meneruskan pasien.']);

        $this->assertDatabaseCount('screening', 0);
        $this->assertSame('menunggu', $visit->fresh()->status);
    }

    public function test_urgent_handoff_allows_optional_anthropometry_and_triage_and_cannot_downgrade_red_flags(): void
    {
        [$nurse, $visit] = $this->context();
        $payload = $this->payload();
        unset($payload['berat_badan'], $payload['tinggi_badan'], $payload['kejang'], $payload['nadi_teraba'], $payload['pola_pernapasan'], $payload['kesadaran'], $payload['kondisi_psikiatri'], $payload['risiko_jatuh_visual']);
        $payload = [...$payload, 'nyeri_dada' => 'ya', 'triase_perawat' => 'hijau'];

        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('pelayanan.kunjungan.show', $visit));

        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'berat_badan' => null, 'tinggi_badan' => null, 'kesimpulan_triase' => 'merah', 'prioritas_layanan' => 'darurat']);
        $this->assertNotNull($visit->fresh()->screening->submitted_at);
        $this->assertSame('pemeriksaan', $visit->fresh()->status);
        $this->post(route('pelayanan.screening.store', $visit), [...$payload, 'tindakan' => 'draf'])->assertUnprocessable();
        $this->assertSame('merah', $visit->fresh()->screening->kesimpulan_triase);
    }

    public function test_dental_therapist_can_submit_and_print_a_complete_assessment_with_unknowns_preserved(): void
    {
        [$nurse, $visit] = $this->context('gigi', 'terapis_gigi');
        $payload = [...$this->payload(), 'riwayat_penyakit' => 'Diabetes', 'status_riwayat_penyakit' => 'ada', 'risiko_medis_gigi' => ['diabetes'], 'riwayat_obat' => 'Obat rutin sesuai keterangan pasien', 'keluhan_tambahan' => '<script>alert(1)</script>'];

        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'riwayat_obat' => 'Obat rutin sesuai keterangan pasien', 'skala_nyeri' => null]);
        $this->get(route('pelayanan.screening.print', $visit))->assertSee('ASESMEN KHUSUS GIGI DAN MULUT')
            ->assertSee('Diabetes melitus')->assertSee('Belum dikaji')->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('DRAF — BELUM DITERUSKAN');
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'screening.print', 'kunjungan_id' => $visit->id]);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.screening.summary.Lama keluhan', '2 hari')
            ->where('visits.0.screening.summary.Obat yang digunakan', 'Obat rutin sesuai keterangan pasien'));
    }

    public function test_dental_risks_cannot_combine_none_or_unknown_with_a_known_condition(): void
    {
        [$nurse, $visit] = $this->context('gigi');

        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), [...$this->payload(), 'risiko_medis_gigi' => ['tidak_ada', 'diabetes']])
            ->assertSessionHasErrors('risiko_medis_gigi');

        $this->assertDatabaseCount('screening', 0);
    }

    public function test_unknown_dental_systemic_history_requires_a_handoff_note(): void
    {
        [$nurse, $visit] = $this->context('gigi');

        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), [...$this->payload(), 'risiko_medis_gigi' => ['belum_diketahui']])
            ->assertSessionHasErrors('catatan_medis_gigi');

        $this->assertDatabaseCount('screening', 0);
    }

    public function test_print_and_start_remain_scoped_to_the_nurses_clinic(): void
    {
        [$nurse, $visit] = $this->context();
        $foreign = Poliklinik::create(['kode' => 'FOREIGN', 'nama' => 'Poli Lain', 'jenis' => 'gigi', 'is_active' => true]);
        $visit->update(['poliklinik_id' => $foreign->id]);
        Screening::create(['kunjungan_id' => $visit->id, 'keluhan' => 'Rahasia poli lain']);

        $this->actingAs($nurse)->get(route('pelayanan.screening.print', $visit))->assertNotFound();
        $this->post(route('pelayanan.screening.start', $visit))->assertNotFound();

        $this->assertSame('menunggu', $visit->fresh()->status);
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'screening.print', 'kunjungan_id' => $visit->id]);
    }

    public function test_print_requires_login_and_rejects_registration_role(): void
    {
        [$nurse, $visit] = $this->context();
        Screening::create(['kunjungan_id' => $visit->id]);
        $registration = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);

        $this->get(route('pelayanan.screening.print', $visit))->assertRedirect(route('login'));
        $this->actingAs($registration)->get(route('pelayanan.screening.print', $visit))->assertForbidden();
    }

    /** @return array{User, Kunjungan, Nakes} */
    private function context(string $type = 'umum', string $position = 'perawat'): array
    {
        $this->travelTo('2026-10-21 09:00:00');
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'SKR-'.$type, 'nama' => 'Poli '.$type, 'jenis' => $type, 'is_active' => true]);
        $staff = Nakes::create(['kode' => 'SKR-NURSE', 'nama' => 'Petugas Asesmen', 'kategori' => 'medis', 'jabatan' => $position, 'poliklinik_id' => $clinic->id, 'user_id' => $nurse->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-SKR', 'nama' => 'Pasien Asesmen', 'tanggal_lahir' => '1990-01-01']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-SKR', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'menunggu', 'jenis_pasien' => 'lama']);

        return [$nurse, $visit, $staff];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'keluhan' => 'Keluhan pasien', 'lama_keluhan' => '2 hari', 'sumber_informasi' => 'pasien',
            'status_alergi' => 'belum_diketahui', 'status_riwayat_penyakit' => 'belum_diketahui', 'konfirmasi_riwayat' => true,
            'td_sistole' => 120, 'td_diastole' => 80, 'nadi' => 80, 'suhu' => 36.5, 'respirasi' => 18, 'berat_badan' => 60, 'tinggi_badan' => 160,
            'nyeri_dada' => 'tidak', 'kejang' => 'tidak', 'nadi_teraba' => 'teraba', 'pola_pernapasan' => 'normal',
            'kesadaran' => 'sadar', 'kondisi_psikiatri' => 'normal', 'risiko_jatuh_visual' => 'rendah',
        ];
    }
}
