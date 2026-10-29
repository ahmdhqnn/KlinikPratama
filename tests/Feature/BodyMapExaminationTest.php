<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Models\ClinicalNoteVersion;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BodyMapExaminationTest extends TestCase
{
    use RefreshDatabase;

    private function visit(): Kunjungan
    {
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOK-BODY', 'nama' => 'dr. Tubuh', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM-BODY', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-BODY', 'nama' => 'Pasien Pemeriksaan']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-BODY', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'anamnesis' => 'Benjolan di dahi', 'pemeriksaan_fisik' => 'Keadaan umum baik']);
        $this->actingAs($user);

        return $visit;
    }

    private function bodyMap(): array
    {
        return [
            'version' => 1,
            'findings' => [['id' => 'finding-head', 'locationId' => 'front-head', 'mode' => 'body', 'view' => 'front', 'model' => 'male', 'category' => 'Kepala dan leher', 'side' => '', 'detail' => 'Dahi kanan', 'method' => 'Palpasi', 'symptom' => '', 'finding' => 'Benjolan 1 cm, tidak nyeri tekan', 'pain' => 0]],
            'annotation' => ['strokes' => [['id' => 'stroke-1', 'tool' => 'pen', 'color' => '#dc2626', 'width' => 2, 'points' => [['x' => 10, 'y' => 20], ['x' => 14.5, 'y' => 25]]]], 'note' => 'Tanda merah: lokasi benjolan'],
        ];
    }

    public function test_selections_and_annotations_round_trip_to_examination_and_rme_without_being_lost_by_other_section_saves(): void
    {
        $visit = $this->visit();
        $map = $this->bodyMap();
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'body_map' => $map])->assertSessionHasNoErrors();
        $this->assertSame($map, $visit->pemeriksaan->fresh()->body_map);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page->where('visit.examination.bodyMap', $map));
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page->where('visits.0.examination.bodyMap', $map));
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'anamnesis', 'anamnesis' => 'Keluhan dikonfirmasi', 'body_map' => null])->assertSessionHasNoErrors();
        $this->assertSame($map, $visit->pemeriksaan->fresh()->body_map);
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'pemeriksaan_fisik' => 'Evaluasi ulang'])->assertSessionHasNoErrors();
        $this->assertSame($map, $visit->pemeriksaan->fresh()->body_map);
        $empty = ['version' => 1, 'findings' => [], 'annotation' => ['strokes' => [], 'note' => '']];
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'body_map' => $empty])->assertSessionHasNoErrors();
        $this->assertNull($visit->pemeriksaan->fresh()->body_map);
    }

    public function test_physical_summary_is_optional_when_an_interactive_finding_is_recorded(): void
    {
        $visit = $this->visit();
        $map = $this->bodyMap();
        $map['annotation'] = ['strokes' => [], 'note' => ''];

        $this->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'pemeriksaan', 'pemeriksaan_fisik' => '', 'body_map' => $map,
        ])->assertSessionHasNoErrors();
        $this->assertNull($visit->pemeriksaan->fresh()->pemeriksaan_fisik);
        $this->assertSame($map, $visit->pemeriksaan->fresh()->body_map);
        Diagnosa::create(['pemeriksaan_id' => $visit->pemeriksaan->id, 'kode_icd10' => 'R22.0', 'nama_diagnosa' => 'Benjolan kepala', 'jenis' => 'utama']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame($map, ClinicalNoteVersion::sole()->payload['body_map']);
        $this->assertNull(ClinicalNoteVersion::sole()->payload['pemeriksaan_fisik']);
    }

    public function test_annotation_only_is_saved_without_a_phantom_interactive_finding(): void
    {
        $visit = $this->visit();
        $map = $this->bodyMap();
        $map['findings'] = [];

        $this->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'pemeriksaan', 'pemeriksaan_fisik' => '', 'body_map' => $map,
        ])->assertSessionHasNoErrors();
        $this->assertSame([], $visit->pemeriksaan->fresh()->body_map['findings']);
        $this->assertNull($visit->pemeriksaan->fresh()->pemeriksaan_fisik);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examination.bodyMap', $map)->where('visit.examination.physicalExam', null));
    }

    public function test_finalization_still_requires_a_real_physical_finding(): void
    {
        $visit = $this->visit();
        $empty = ['version' => 1, 'findings' => [], 'annotation' => ['strokes' => [], 'note' => '']];
        $this->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'pemeriksaan', 'pemeriksaan_fisik' => '', 'body_map' => $empty,
        ])->assertSessionHasNoErrors();
        $this->assertNull($visit->pemeriksaan->fresh()->body_map);
        Diagnosa::create(['pemeriksaan_id' => $visit->pemeriksaan->id, 'kode_icd10' => 'R22.0', 'nama_diagnosa' => 'Benjolan kepala', 'jenis' => 'utama']);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertStatus(422);
        $this->assertNull($visit->pemeriksaan->fresh()->signed_at);
    }

    public function test_finalization_and_addendum_preserve_each_visual_version_and_include_it_in_integrity_checks(): void
    {
        $visit = $this->visit();
        $map = $this->bodyMap();
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'body_map' => $map])->assertSessionHasNoErrors();
        Diagnosa::create(['pemeriksaan_id' => $visit->pemeriksaan->id, 'kode_icd10' => 'R22.0', 'nama_diagnosa' => 'Benjolan kepala', 'jenis' => 'utama']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasNoErrors();
        $final = ClinicalNoteVersion::sole();
        $this->assertSame($map, $final->payload['body_map']);
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'body_map' => null])->assertUnprocessable();
        $corrected = $map;
        $corrected['findings'][0]['finding'] = 'Benjolan 2 cm, tidak nyeri tekan';
        $corrected['annotation']['strokes'][0]['points'][1]['x'] = 24.5;
        $this->post(route('pelayanan.pemeriksaan.addendum', $visit), ['reason' => 'Koreksi ukuran setelah pemeriksaan ulang', 'body_map' => $corrected])->assertSessionHasNoErrors();
        $this->assertSame($map, $visit->pemeriksaan->fresh()->body_map);
        $this->assertSame($map, $final->fresh()->payload['body_map']);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.examination.bodyMap', $corrected)
            ->where('visits.0.examination.versions.0.bodyMap', $map)
            ->where('visits.0.examination.versions.1.bodyMap', $corrected));
        $this->assertTrue(app(ClinicalNoteRecorder::class)->verify($visit->pemeriksaan->fresh()));
        $version = ClinicalNoteVersion::where('version', 2)->sole();
        $tampered = $version->payload;
        $tampered['body_map']['annotation']['strokes'] = [];
        DB::table('clinical_note_versions')->where('id', $version->id)->update(['payload' => json_encode($tampered)]);
        $this->assertFalse(app(ClinicalNoteRecorder::class)->verify($visit->pemeriksaan->fresh()));
    }

    public function test_invalid_or_unsafe_visual_data_is_rejected_without_overwriting_the_existing_record(): void
    {
        $visit = $this->visit();
        $map = $this->bodyMap();
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'body_map' => $map])->assertSessionHasNoErrors();
        $mutations = [
            fn (&$data) => $data['findings'][0]['locationId'] = 'unknown-location',
            fn (&$data) => $data['findings'][0]['view'] = 'back',
            fn (&$data) => $data['findings'][0]['pain'] = 11,
            fn (&$data) => $data['findings'][0]['finding'] = 'Belum dicatat',
            fn (&$data) => $data['annotation']['strokes'][0]['points'][0]['x'] = -1,
            fn (&$data) => $data['annotation']['strokes'][0]['points'][0]['y'] = 559,
            fn (&$data) => $data['annotation']['strokes'][0]['color'] = 'url(https://example.com)',
            fn (&$data) => $data['annotation']['strokes'][0]['svg'] = '<script>alert(1)</script>',
            fn (&$data) => $data['annotation']['strokes'][0]['points'] = array_fill(0, 401, ['x' => 10, 'y' => 10]),
            fn (&$data) => $data['annotation']['strokes'][] = $data['annotation']['strokes'][0],
            fn (&$data) => $data['findings'][0]['locationId'] = 'front-hand-kiri',
        ];
        foreach ($mutations as $mutate) {
            $invalid = $map;
            $mutate($invalid);
            $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'body_map' => $invalid])->assertSessionHasErrors('body_map');
            $this->assertSame($map, $visit->pemeriksaan->fresh()->body_map);
        }
    }

    public function test_manual_and_quick_findings_preserve_their_locations_and_fields_in_rme(): void
    {
        $visit = $this->visit();
        $map = $this->bodyMap();
        $map['findings'][0] = array_replace($map['findings'][0], [
            'entryMode' => 'manual', 'method' => 'Lainnya', 'methodDetail' => 'Pemeriksaan lokal',
            'detail' => 'Dahi sisi kanan', 'symptom' => 'Benjolan dirasakan pasien', 'pain' => 0,
        ]);
        $map['findings'][] = array_replace($map['findings'][0], [
            'id' => 'quick-other', 'entryMode' => 'quick', 'locationId' => 'other', 'locationLabel' => 'Area bekas luka',
            'category' => 'Lainnya', 'side' => '', 'detail' => '', 'method' => '', 'methodDetail' => '', 'symptom' => '',
            'finding' => 'Tepi luka menutup', 'pain' => null,
        ]);
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'body_map' => $map])->assertSessionHasNoErrors();
        $this->assertSame($map, $visit->pemeriksaan->fresh()->body_map);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.examination.bodyMap.findings.0.methodDetail', 'Pemeriksaan lokal')
            ->where('visits.0.examination.bodyMap.findings.0.pain', 0)
            ->where('visits.0.examination.bodyMap.findings.1.entryMode', 'quick')
            ->where('visits.0.examination.bodyMap.findings.1.locationLabel', 'Area bekas luka'));
        $mutations = [
            fn (&$data) => $data['findings'][0]['methodDetail'] = '',
            fn (&$data) => $data['findings'][1]['locationLabel'] = '',
            fn (&$data) => $data['findings'][1]['locationLabel'] = 'Belum dicatat',
            fn (&$data) => $data['findings'][1]['entryMode'] = 'unknown',
            fn (&$data) => $data['findings'][1]['pain'] = 3,
            fn (&$data) => $data['findings'][1]['symptom'] = 'Hidden complaint',
        ];
        foreach ($mutations as $mutate) {
            $invalid = $map;
            $mutate($invalid);
            $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'body_map' => $invalid])->assertSessionHasErrors('body_map');
            $this->assertSame($map, $visit->pemeriksaan->fresh()->body_map);
        }
    }

    public function test_compact_finding_can_be_saved_without_an_anatomical_location_and_keeps_the_physical_summary(): void
    {
        $visit = $this->visit();
        $map = $this->bodyMap();
        $map['findings'][0] = array_replace($map['findings'][0], [
            'entryMode' => 'quick', 'locationId' => 'general', 'category' => 'Lainnya',
            'detail' => '', 'method' => '', 'symptom' => '', 'pain' => null,
            'finding' => 'Temuan pemeriksaan naratif dokter',
        ]);
        $this->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'pemeriksaan', 'body_map' => $map, 'pemeriksaan_fisik' => 'Ringkasan pemeriksaan fisik dokter',
        ])->assertSessionHasNoErrors();
        $this->assertSame($map, $visit->pemeriksaan->fresh()->body_map);
        $this->assertSame('Ringkasan pemeriksaan fisik dokter', $visit->pemeriksaan->fresh()->pemeriksaan_fisik);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.examination.bodyMap.findings.0.locationId', 'general')
            ->where('visits.0.examination.bodyMap.findings.0.finding', 'Temuan pemeriksaan naratif dokter')
            ->where('visits.0.examination.physicalExamination', 'Ringkasan pemeriksaan fisik dokter'));
        foreach (['entryMode' => 'interactive', 'category' => 'Thorax', 'locationLabel' => 'Lokasi tersembunyi', 'side' => 'kanan'] as $field => $value) {
            $invalid = $map;
            $invalid['findings'][0][$field] = $value;
            $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'body_map' => $invalid])->assertSessionHasErrors('body_map');
        }
        $this->assertSame($map, $visit->pemeriksaan->fresh()->body_map);
    }

    public function test_another_doctor_cannot_change_visual_findings(): void
    {
        $visit = $this->visit();
        $other = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        Nakes::create(['kode' => 'DOK-BODY-OTHER', 'nama' => 'dr. Lain', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $other->id, 'is_active' => true]);
        $this->actingAs($other)->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'pemeriksaan', 'body_map' => $this->bodyMap()])->assertForbidden();
        $this->assertNull($visit->pemeriksaan->fresh()->body_map);
    }
}
