<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NurseScreeningDraftAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createVisitContext(string $jenis = 'umum'): array
    {
        $clinic = Poliklinik::create([
            'kode' => $jenis === 'gigi' ? 'GIGI-DRF' : 'UMUM-DRF',
            'nama' => $jenis === 'gigi' ? 'Poli Gigi' : 'Poli Umum',
            'jenis' => $jenis,
            'is_active' => true,
        ]);

        $doctor = Nakes::create([
            'kode' => 'DOK-DRF-'.strtoupper($jenis),
            'nama' => 'dr. Pemeriksa Uji',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'poliklinik_id' => $clinic->id,
            'is_active' => true,
        ]);

        $nurseUser = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        Nakes::create([
            'kode' => 'PRW-DRF-'.strtoupper($jenis),
            'user_id' => $nurseUser->id,
            'nama' => 'Ns. Perawat Uji',
            'kategori' => 'medis',
            'jabatan' => 'perawat',
            'poliklinik_id' => $clinic->id,
            'is_active' => true,
        ]);

        $patient = Pasien::create([
            'no_rm' => 'RM-DRF-'.strtoupper($jenis),
            'nama' => 'Pasien Uji Draf',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '1995-05-10',
        ]);

        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-DRF-'.strtoupper($jenis),
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today()->toDateString(),
            'status' => 'menunggu',
        ]);

        $this->actingAs($nurseUser)
            ->post(route('pelayanan.screening.start', $visit))
            ->assertRedirect(route('pelayanan.screening.show', $visit));

        return [$nurseUser, $visit->fresh()];
    }

    public function test_general_draft_persists_all_checklists_and_partial_conditional_fields(): void
    {
        [$nurse, $visit] = $this->createVisitContext('umum');

        $payload = [
            'tindakan' => 'draf',
            'screening_revision' => $visit->screening->revisionToken(),
            'keluhan' => 'Demam sejak malam',
            'lama_keluhan' => '1 hari',
            'sumber_informasi' => 'pasien',
            'status_alergi' => 'tidak_ada',
            'status_riwayat_penyakit' => 'tidak_ada',
            'konfirmasi_riwayat' => true,
            'vitals_unmeasurable' => true,
            'alasan_pengukuran_tidak_lengkap' => 'WIP',
            'nyeri_dada' => 'tidak',
            'kejang' => 'tidak',
            'nadi_teraba' => 'teraba',
            'pola_pernapasan' => 'normal',
            'kesadaran' => 'sadar',
            'kondisi_psikiatri' => 'normal',
            'risiko_jatuh_visual' => 'rendah',
            'skrining_gizi' => 'normal',
            'general_assessment' => [
                'general_condition' => 'baik',
                'airway' => 'bebas',
                'circulation' => 'tanpa_kelainan',
                'mobility' => 'mandiri',
                'special_needs_status' => 'ada',
                'special_needs' => '',
                'needs_status' => 'ada',
                'needs' => '',
                'initial_actions_status' => 'ada',
                'initial_actions' => '',
                'handover_follow_up' => 'Pasien sudah berada di ruang pemeriksaan, TTV stabil.',
                'systems' => [
                    'respiratory' => ['status' => 'positive', 'notes' => ''],
                ],
            ],
        ];

        $this->actingAs($nurse)
            ->post(route('pelayanan.screening.store', $visit), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertRedirect(route('pelayanan.screening.show', $visit));

        $this->actingAs($nurse)
            ->get(route('pelayanan.screening.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/screening/show')
                ->where('visit.screening.konfirmasi_riwayat', true)
                ->where('visit.screening.vitals_unmeasurable', true)
                ->where('visit.screening.alasan_pengukuran_tidak_lengkap', 'WIP')
                ->where('visit.screening.general_assessment.special_needs_status', 'ada')
                ->where('visit.screening.general_assessment.needs_status', 'ada')
                ->where('visit.screening.general_assessment.initial_actions_status', 'ada')
                ->where('visit.screening.general_assessment.handover_follow_up', 'Pasien sudah berada di ruang pemeriksaan, TTV stabil.')
                ->where('visit.screening.general_assessment.systems.respiratory.status', 'positive')
            );
    }

    public function test_dental_draft_persists_checklists_and_final_submit_still_enforces_required_details(): void
    {
        [$nurse, $visit] = $this->createVisitContext('gigi');

        $draftPayload = [
            'tindakan' => 'draf',
            'screening_revision' => $visit->screening->revisionToken(),
            'keluhan' => 'Gigi geraham ngilu',
            'status_alergi' => 'tidak_ada',
            'status_riwayat_penyakit' => 'tidak_ada',
            'konfirmasi_riwayat' => true,
            'dental_vitals_unmeasurable' => true,
            'dental_red_flags' => ['none'],
            'pemicu_nyeri_gigi' => ['dingin', 'manis'],
            'kebiasaan_gigi_opsi' => ['teh_kopi'],
            'mukosa_mulut' => [
                'lidah' => 'normal',
                'pipi' => 'normal',
                'bibir' => 'normal',
                'palatum' => 'normal',
                'gusi' => 'abnormal',
            ],
            'general_assessment' => [
                'general_condition' => 'baik',
                'needs_status' => 'ada',
                'needs' => '',
            ],
        ];

        $this->actingAs($nurse)
            ->post(route('pelayanan.screening.store', $visit), $draftPayload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pelayanan.screening.show', $visit));

        $visit->refresh();

        $this->actingAs($nurse)
            ->get(route('pelayanan.screening.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.screening.konfirmasi_riwayat', true)
                ->where('visit.screening.dental_vitals_unmeasurable', true)
                ->where('visit.screening.dental_red_flags', ['none'])
                ->where('visit.screening.pemicu_nyeri_gigi', ['dingin', 'manis'])
                ->where('visit.screening.kebiasaan_gigi_opsi', ['teh_kopi'])
                ->where('visit.screening.mukosa_mulut.gusi', 'abnormal')
                ->where('visit.screening.general_assessment.needs_status', 'ada')
            );

        // Final submission MUST still enforce required_if for needs when needs_status is 'ada'
        $this->actingAs($nurse)
            ->post(route('pelayanan.screening.store', $visit), [
                ...$draftPayload,
                'tindakan' => 'kirim',
                'screening_revision' => $visit->screening->revisionToken(),
                'lama_keluhan' => '2 hari',
                'sumber_informasi' => 'pasien',
                'alasan_pengukuran_tidak_lengkap' => 'Pasien menolak tensi karena cemas, observasi sadar.',
                'kesadaran' => 'sadar',
                'pola_pernapasan' => 'normal',
            ])
            ->assertSessionHasErrors(['general_assessment.needs']);
    }

    public function test_consecutive_draft_saves_produce_distinct_flash_nonces(): void
    {
        [$nurse, $visit] = $this->createVisitContext('umum');

        $saveOnce = function (string $complaint) use ($nurse, $visit): string {
            $visit->refresh();

            $this->actingAs($nurse)
                ->post(route('pelayanan.screening.store', $visit), [
                    'tindakan' => 'draf',
                    'screening_revision' => $visit->screening->revisionToken(),
                    'keluhan' => $complaint,
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('pelayanan.screening.show', $visit));

            $nonce = null;
            $this->actingAs($nurse)
                ->get(route('pelayanan.screening.show', $visit))
                ->assertOk()
                ->assertInertia(function (Assert $page) use (&$nonce): void {
                    $props = $page->toArray()['props'];
                    $this->assertNotEmpty($props['flash']['success']);
                    $this->assertNotEmpty($props['flash']['nonce']);
                    $nonce = $props['flash']['nonce'];
                });

            return (string) $nonce;
        };

        $firstNonce = $saveOnce('Draf pertama');
        $secondNonce = $saveOnce('Draf kedua');

        $this->assertNotSame($firstNonce, $secondNonce);
    }
}
