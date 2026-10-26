<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_is_private_and_available_in_assessment_and_patient_history(): void
    {
        [$nurse, $visit] = $this->context();
        $this->actingAs($nurse)->from(route('pelayanan.screening.show', $visit))
            ->post(route('pelayanan.kunjungan.attachments.store', $visit), ['file' => $this->pdf(), 'note' => 'Hasil lab dari pasien'])
            ->assertRedirect(route('pelayanan.screening.show', $visit))->assertSessionHasNoErrors();

        $document = $visit->fresh()->lampiran[0];
        Storage::disk('local')->assertExists($document['path']);
        $this->assertSame('hasil-lab.pdf', $document['name']);
        $this->assertArrayNotHasKey('lampiran', $visit->fresh()->toArray());
        $this->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->has('visit.attachments', 1)->where('visit.attachments.0.note', 'Hasil lab dari pasien')->missing('visit.attachments.0.path'));
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien))->assertInertia(fn (Assert $page) => $page
            ->has('visits.0.attachments', 1)->missing('visits.0.attachments.0.path'));
        $this->get(route('pelayanan.kunjungan.attachments.show', [$visit, $document['id']]))
            ->assertOk()->assertDownload('hasil-lab.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'attachment.upload', 'kunjungan_id' => $visit->id]);
    }

    public function test_invalid_disguised_and_oversized_files_are_rejected(): void
    {
        [$nurse, $visit] = $this->context();
        $this->actingAs($nurse);
        foreach ([
            UploadedFile::fake()->createWithContent('script.pdf', '<script>alert(1)</script>')->mimeType('text/html'),
            UploadedFile::fake()->createWithContent('script.php', "%PDF-1.4\nDocument\n%%EOF"),
            UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf'),
        ] as $file) {
            $this->post(route('pelayanan.kunjungan.attachments.store', $visit), ['file' => $file])->assertSessionHasErrors('file');
        }
        $this->assertNull($visit->fresh()->lampiran);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_limit_and_visit_lock_prevent_changes_after_handoff(): void
    {
        [$nurse, $visit] = $this->context();
        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.attachments.store', $visit), ['file' => $this->pdf()])->assertSessionHasNoErrors();
        $document = $visit->fresh()->lampiran[0];
        $visit->lampiran = array_fill(0, 10, $document);
        $visit->save();
        $this->post(route('pelayanan.kunjungan.attachments.store', $visit), ['file' => $this->pdf()])->assertSessionHasErrors('file');
        $visit->update(['status' => 'pemeriksaan']);
        $this->post(route('pelayanan.kunjungan.attachments.store', $visit), ['file' => $this->pdf()])->assertStatus(409);
        $this->delete(route('pelayanan.kunjungan.attachments.destroy', [$visit, $document['id']]))->assertStatus(409);
        $this->assertCount(10, $visit->fresh()->lampiran);
        Storage::disk('local')->assertExists($document['path']);
    }

    public function test_download_requires_authorized_role_clinic_and_matching_visit(): void
    {
        [$nurse, $visit] = $this->context();
        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.attachments.store', $visit), ['file' => $this->pdf()])->assertSessionHasNoErrors();
        $document = $visit->fresh()->lampiran[0];
        $url = route('pelayanan.kunjungan.attachments.show', [$visit, $document['id']]);
        $otherVisit = $visit->replicate(['lampiran']);
        $otherVisit->no_kunjungan = 'KNJ-LAMP-LAIN';
        $otherVisit->save();
        $this->get(route('pelayanan.kunjungan.attachments.show', [$otherVisit, $document['id']]))->assertNotFound();
        $this->get(route('pelayanan.kunjungan.attachments.show', [$visit, Str::uuid()]))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'farmasi', 'is_active' => true]))->get($url)->assertForbidden();
        $foreign = Poliklinik::create(['kode' => 'LAMP-LAIN', 'nama' => 'Poli lain', 'jenis' => 'gigi', 'is_active' => true]);
        $visit->update(['poliklinik_id' => $foreign->id]);
        $this->actingAs($nurse)->get($url)->assertNotFound();
        $this->delete(route('pelayanan.kunjungan.attachments.destroy', [$visit, $document['id']]))->assertNotFound();
        $this->post(route('pelayanan.kunjungan.attachments.store', $visit), ['file' => $this->pdf()])->assertNotFound();
    }

    public function test_assigned_doctor_can_download_but_cannot_modify_attachments(): void
    {
        [$nurse, $visit] = $this->context();
        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.attachments.store', $visit), ['file' => $this->pdf()])->assertSessionHasNoErrors();
        $document = $visit->fresh()->lampiran[0];
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $staff = Nakes::create(['kode' => 'LAMP-DOK', 'nama' => 'Dokter Uji', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctor->id, 'is_active' => true]);
        $visit->update(['dokter_id' => $staff->id, 'status' => 'pemeriksaan']);
        $this->actingAs($doctor)->get(route('pelayanan.kunjungan.attachments.show', [$visit, $document['id']]))->assertOk()->assertDownload();
        $this->delete(route('pelayanan.kunjungan.attachments.destroy', [$visit, $document['id']]))->assertForbidden();
        $this->post(route('pelayanan.kunjungan.attachments.store', $visit), ['file' => $this->pdf()])->assertForbidden();
        $visit->update(['dokter_id' => null, 'status' => 'selesai']);
        $this->get(route('pelayanan.kunjungan.attachments.show', [$visit, $document['id']]))->assertNotFound();
    }

    public function test_attachment_can_be_removed_during_screening_and_cannot_be_downloaded_afterward(): void
    {
        [$nurse, $visit] = $this->context();
        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.attachments.store', $visit), ['file' => $this->pdf()])->assertSessionHasNoErrors();
        $document = $visit->fresh()->lampiran[0];
        $this->delete(route('pelayanan.kunjungan.attachments.destroy', [$visit, $document['id']]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], $visit->fresh()->lampiran);
        Storage::disk('local')->assertMissing($document['path']);
        $this->get(route('pelayanan.kunjungan.attachments.show', [$visit, $document['id']]))->assertNotFound();
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'attachment.delete', 'kunjungan_id' => $visit->id]);
    }

    /** @return array{User, Kunjungan} */
    private function context(): array
    {
        Storage::fake('local');
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'LAMP-UMUM', 'nama' => 'Poli umum', 'jenis' => 'umum', 'is_active' => true]);
        Nakes::create(['kode' => 'LAMP-NURSE', 'nama' => 'Perawat Uji', 'kategori' => 'medis', 'jabatan' => 'perawat', 'poliklinik_id' => $clinic->id, 'user_id' => $nurse->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-LAMP', 'nama' => 'Pasien Uji', 'tanggal_lahir' => '1990-01-01']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-LAMP', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'screening', 'jenis_pasien' => 'lama']);

        return [$nurse, $visit];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('hasil-lab.pdf', "%PDF-1.4\nDocument\n%%EOF");
    }
}
