<?php

namespace Tests\Feature;

use App\ClinicalBackup;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\PatientDocument;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function samplePdf(string $name = 'hasil-lab.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    }

    public function test_upload_preview_download_archive_integrity_and_rbac_matrix(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $pendaftaran = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $dokter = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $perawat = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $farmasi = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);

        $poli = Poliklinik::create(['kode' => 'PU-DOC', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $nakesDokter = Nakes::create(['kode' => 'DR-DOC', 'nama' => 'dr. Dokumen', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $dokter->id, 'poliklinik_id' => $poli->id, 'is_active' => true]);
        Nakes::create(['kode' => 'PR-DOC', 'nama' => 'Ns. Dokumen', 'kategori' => 'medis', 'jabatan' => 'perawat', 'user_id' => $perawat->id, 'poliklinik_id' => $poli->id, 'is_active' => true]);
        Nakes::create(['kode' => 'APT-DOC', 'nama' => 'apt. Dokumen', 'kategori' => 'medis', 'jabatan' => 'farmasi', 'user_id' => $farmasi->id, 'poliklinik_id' => $poli->id, 'is_active' => true]);

        $patient = Pasien::create(['no_rm' => 'RM-DOC-01', 'nama' => 'Pasien Dokumen']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-DOC-01',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $poli->id,
            'dokter_id' => $nakesDokter->id,
            'tanggal' => today(),
            'status' => 'pemeriksaan',
        ]);

        // 1. Pendaftaran can upload identity document, but CANNOT upload clinical lab document
        $this->actingAs($pendaftaran)->post(route('pelayanan.pasien.documents.store', $patient), [
            'title' => 'Hasil Lab Eksternal',
            'category' => PatientDocument::CATEGORY_HASIL_LAB,
            'document_date' => today()->toDateString(),
            'file' => $this->samplePdf(),
        ])->assertForbidden();

        $this->actingAs($pendaftaran)->post(route('pelayanan.pasien.documents.store', $patient), [
            'title' => 'Kartu Kepesertaan Kemenpar',
            'category' => PatientDocument::CATEGORY_DOKUMEN_IDENTITAS,
            'document_date' => today()->toDateString(),
            'file' => $this->samplePdf('kartu.pdf'),
        ])->assertSessionHasNoErrors();

        $idDoc = PatientDocument::where('category', PatientDocument::CATEGORY_DOKUMEN_IDENTITAS)->sole();

        // 2. Doctor can upload clinical lab PDF with visit link
        $this->actingAs($dokter)->post(route('pelayanan.pasien.documents.store', $patient), [
            'title' => 'Darah Rutin RSUD',
            'category' => PatientDocument::CATEGORY_HASIL_LAB,
            'document_date' => today()->toDateString(),
            'issuer_facility' => 'RSUD Jakarta',
            'kunjungan_id' => $visit->id,
            'notes' => 'Hb 13.5 g/dL, Leukosit 7.200',
            'file' => $this->samplePdf('lab-rsud.pdf'),
        ])->assertSessionHasNoErrors();

        $labDoc = PatientDocument::where('category', PatientDocument::CATEGORY_HASIL_LAB)->sole();
        $this->assertSame(PatientDocument::SENSITIVITY_CLINICAL, $labDoc->sensitivity);
        $this->assertNotEmpty($labDoc->sha256);

        // 3. Magic-byte spoofing is rejected
        $fakePdf = UploadedFile::fake()->createWithContent('malicious.pdf', '<html><script>alert(1)</script></html>');
        $this->actingAs($dokter)->post(route('pelayanan.pasien.documents.store', $patient), [
            'title' => 'File Palsu',
            'category' => PatientDocument::CATEGORY_HASIL_LAB,
            'document_date' => today()->toDateString(),
            'file' => $fakePdf,
        ])->assertSessionHasErrors('file');

        // 4. Farmasi cannot upload or preview
        $this->actingAs($farmasi)->post(route('pelayanan.pasien.documents.store', $patient), [
            'title' => 'Coba Farmasi',
            'category' => PatientDocument::CATEGORY_HASIL_LAB,
            'document_date' => today()->toDateString(),
            'file' => $this->samplePdf(),
        ])->assertForbidden();
        $this->actingAs($farmasi)->get(route('pelayanan.pasien.documents.preview', [$patient, $labDoc]))->assertForbidden();

        // 5. Clinical sensitivity gate: Admin/Pendaftaran can preview identity doc, NOT clinical lab doc
        $this->actingAs($pendaftaran)->get(route('pelayanan.pasien.documents.preview', [$patient, $idDoc]))->assertOk();
        $this->actingAs($pendaftaran)->get(route('pelayanan.pasien.documents.preview', [$patient, $labDoc]))->assertForbidden();
        $this->actingAs($admin)->get(route('pelayanan.pasien.documents.preview', [$patient, $labDoc]))->assertForbidden();

        // Doctor & Nurse CAN preview and download clinical lab doc
        $this->actingAs($dokter)->get(route('pelayanan.pasien.documents.preview', [$patient, $labDoc]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($perawat)->get(route('pelayanan.pasien.documents.download', [$patient, $labDoc]))->assertOk();

        // 6. SHA-256 tamper detection returns 409 Conflict
        Storage::disk('local')->put($labDoc->file_path, '%PDF-1.4 TAMPERED');
        $this->actingAs($dokter)->get(route('pelayanan.pasien.documents.preview', [$patient, $labDoc]))->assertStatus(409);

        // 7. Archiving requires min 10-char reason and persists without physical deletion
        $this->actingAs($dokter)->post(route('pelayanan.pasien.documents.archive', [$patient, $labDoc]), [
            'archive_reason' => 'pendek',
        ])->assertSessionHasErrors('archive_reason');

        $this->actingAs($dokter)->post(route('pelayanan.pasien.documents.archive', [$patient, $labDoc]), [
            'archive_reason' => 'Digantikan hasil pemeriksaan laboratorium versi terbaru.',
        ])->assertSessionHasNoErrors();

        $labDoc->refresh();
        $this->assertNotNull($labDoc->archived_at);
        $this->assertSame($dokter->id, $labDoc->archived_by);

        // 8. Database trigger blocks physical DELETE on patient_documents (PMK 24/2022)
        $this->expectException(\Throwable::class);
        DB::table('patient_documents')->where('id', $labDoc->id)->delete();
    }

    public function test_patient_with_documents_cannot_be_deleted_and_backup_includes_patient_documents(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-DOC-NODELETE', 'nama' => 'Pasien Dengan Dokumen']);

        $this->actingAs($admin)->post(route('pelayanan.pasien.documents.store', $patient), [
            'title' => 'Identitas Pasien',
            'category' => PatientDocument::CATEGORY_DOKUMEN_IDENTITAS,
            'document_date' => today()->toDateString(),
            'file' => $this->samplePdf(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete(route('pelayanan.pasien.destroy', $patient))->assertStatus(409);
        $this->assertNotSoftDeleted($patient);

        $backup = app(ClinicalBackup::class);
        $reflection = new \ReflectionClass($backup);
        $constant = $reflection->getConstant('REFERENCED_FILE_COLUMNS');
        $this->assertContains(['patient_documents', 'file_path'], $constant);
    }

    public function test_clinician_can_upload_external_rme_pdf_without_app_visit_and_view_it_in_medical_record(): void
    {
        Storage::fake('local');

        $dokter = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $poli = Poliklinik::create(['kode' => 'PU-RME', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $nakesDokter = Nakes::create([
            'kode' => 'DR-RME',
            'nama' => 'dr. Riwayat',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'user_id' => $dokter->id,
            'poliklinik_id' => $poli->id,
            'is_active' => true,
        ]);

        $patient = Pasien::create(['no_rm' => 'RM-EXT-01', 'nama' => 'Pasien RME Eksternal']);
        Kunjungan::create([
            'no_kunjungan' => 'KNJ-EXT-01',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $poli->id,
            'dokter_id' => $nakesDokter->id,
            'tanggal' => today(),
            'status' => 'pemeriksaan',
        ]);

        $this->actingAs($dokter)->post(route('pelayanan.pasien.documents.store', $patient), [
            'title' => 'RME Sebelumnya 2024',
            'category' => PatientDocument::CATEGORY_RME_LAMA,
            'document_date' => today()->subMonth()->toDateString(),
            'issuer_facility' => 'RSUP Cipto Mangunkusumo',
            'kunjungan_id' => null,
            'notes' => 'Riwayat hipertensi dan terapi rutin dari fasyankes sebelumnya.',
            'file' => $this->samplePdf('rme-lama-2024.pdf'),
        ])->assertSessionHasNoErrors();

        $doc = PatientDocument::where('pasien_id', $patient->id)->sole();
        $this->assertNull($doc->kunjungan_id);
        $this->assertSame(PatientDocument::CATEGORY_RME_LAMA, $doc->category);

        $this->actingAs($dokter)->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'dokumen']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pelayanan/pasien/show')
                ->has('documents', 1)
                ->where('documents.0.title', 'RME Sebelumnya 2024')
                ->where('documents.0.visitNumber', null)
                ->where('documents.0.uploadedBy', $dokter->name)
            );

        $this->actingAs($dokter)->get(route('pelayanan.pasien.rekam-medis', $patient))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pelayanan/pasien/rekam-medis')
                ->has('externalDocuments', 1)
                ->where('externalDocuments.0.title', 'RME Sebelumnya 2024')
                ->where('externalDocuments.0.visitNumber', null)
                ->where('documentPermissions.canUpload', true)
            );
    }

    public function test_profile_documents_and_categories_are_scoped_by_role_and_clinic_context(): void
    {
        Storage::fake('local');

        $pendaftaran = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $dokterUmum = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $dokterGigi = User::factory()->create(['role' => 'dokter', 'is_active' => true]);

        $poliUmum = Poliklinik::create(['kode' => 'PU-CTX', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $poliGigi = Poliklinik::create(['kode' => 'PG-CTX', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);

        $nakesUmum = Nakes::create(['kode' => 'DR-UM-CTX', 'nama' => 'dr. Umum', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $dokterUmum->id, 'poliklinik_id' => $poliUmum->id, 'is_active' => true]);
        $nakesGigi = Nakes::create(['kode' => 'DRG-CTX', 'nama' => 'drg. Gigi', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $dokterGigi->id, 'poliklinik_id' => $poliGigi->id, 'is_active' => true]);

        $patient = Pasien::create(['no_rm' => 'RM-CTX-01', 'nama' => 'Pasien Konteks']);
        Kunjungan::create(['no_kunjungan' => 'KNJ-UM-1', 'pasien_id' => $patient->id, 'poliklinik_id' => $poliUmum->id, 'dokter_id' => $nakesUmum->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Kunjungan::create(['no_kunjungan' => 'KNJ-GG-1', 'pasien_id' => $patient->id, 'poliklinik_id' => $poliGigi->id, 'dokter_id' => $nakesGigi->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);

        $this->actingAs($pendaftaran)->post(route('pelayanan.pasien.documents.store', $patient), [
            'title' => 'Salinan KTP',
            'category' => PatientDocument::CATEGORY_DOKUMEN_IDENTITAS,
            'document_date' => today()->toDateString(),
            'file' => $this->samplePdf('ktp.pdf'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($dokterUmum)->post(route('pelayanan.pasien.documents.store', $patient), [
            'title' => 'Hasil Lab Rahasia',
            'category' => PatientDocument::CATEGORY_HASIL_LAB,
            'document_date' => today()->toDateString(),
            'file' => $this->samplePdf('lab.pdf'),
        ])->assertSessionHasNoErrors();

        // Pendaftaran only sees administrative documents and administrative category options
        $this->actingAs($pendaftaran)->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'dokumen']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pelayanan/pasien/show')
                ->has('documents', 1)
                ->where('documents.0.title', 'Salinan KTP')
                ->where('documentPermissions.role', 'pendaftaran')
                ->has('documentPermissions.allowedCategories', 1)
                ->where('documentPermissions.allowedCategories.0.value', PatientDocument::CATEGORY_DOKUMEN_IDENTITAS)
                ->has('documentPermissions.allCategories', 1)
            );

        // Poli Umum doctor sees both docs, upload categories exclude dental-only radiologi_gigi
        $this->actingAs($dokterUmum)->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'dokumen']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pelayanan/pasien/show')
                ->has('documents', 2)
                ->where('documentPermissions.clinicType', 'umum')
                ->where('documentPermissions.allowedCategories', fn ($cats) => ! collect($cats)->pluck('value')->contains(PatientDocument::CATEGORY_RADIOLOGI_GIGI))
            );

        // Poli Gigi doctor gets radiologi_gigi prioritized first in upload categories
        $this->actingAs($dokterGigi)->get(route('pelayanan.pasien.show', [$patient, 'bagian' => 'dokumen']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pelayanan/pasien/show')
                ->has('documents', 2)
                ->where('documentPermissions.clinicType', 'gigi')
                ->where('documentPermissions.allowedCategories.0.value', PatientDocument::CATEGORY_RADIOLOGI_GIGI)
            );
    }
}
