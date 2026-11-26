<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Requests\ArchivePatientDocumentRequest;
use App\Http\Requests\StorePatientDocumentRequest;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\PatientDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PatientDocumentController extends Controller
{
    public function __construct(private ClinicalAuditRecorder $audit) {}

    public function store(StorePatientDocumentRequest $request, Pasien $pasien): RedirectResponse
    {
        $validated = $request->validated();
        Gate::authorize('upload', [PatientDocument::class, $pasien, $validated['category']]);

        $storedPath = null;

        try {
            $document = DB::transaction(function () use ($request, $pasien, $validated, &$storedPath): PatientDocument {
                $file = $request->file('file');
                $uuid = (string) Str::uuid();
                $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin'));
                $directory = 'documents/patients/'.$pasien->id;
                $storedPath = $file->storeAs($directory, $uuid.'.'.$extension, 'local');

                if ($storedPath === false) {
                    throw ValidationException::withMessages(['file' => 'Berkas belum dapat disimpan ke penyimpanan privat.']);
                }

                $safeName = Str::limit(
                    preg_replace('/[\x00-\x1F\x7F]/u', '_', str_replace(['\\', '/'], '_', $file->getClientOriginalName())) ?: 'dokumen-pasien.'.$extension,
                    255,
                    ''
                );

                $visit = ! empty($validated['kunjungan_id']) ? Kunjungan::query()->find($validated['kunjungan_id']) : null;
                $poliklinikId = $visit?->poliklinik_id ?? $request->user()->nakes?->poliklinik_id;

                $document = PatientDocument::query()->create([
                    'uuid' => $uuid,
                    'pasien_id' => $pasien->id,
                    'kunjungan_id' => $visit?->id,
                    'poliklinik_id' => $poliklinikId,
                    'category' => $validated['category'],
                    'sensitivity' => PatientDocument::sensitivityForCategory($validated['category']),
                    'title' => trim($validated['title']),
                    'document_date' => $validated['document_date'],
                    'issuer_facility' => filled($validated['issuer_facility'] ?? null) ? trim($validated['issuer_facility']) : null,
                    'tooth_fdi' => $validated['category'] === PatientDocument::CATEGORY_RADIOLOGI_GIGI ? ($validated['tooth_fdi'] ?? null) : null,
                    'file_path' => $storedPath,
                    'original_name' => $safeName,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'file_size' => (int) $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
                    'uploaded_by' => $request->user()->id,
                    'loinc_document_type' => PatientDocument::loincForCategory($validated['category']),
                ]);

                $this->audit->record($request, 'patient_document.uploaded', $pasien->id, $visit?->id, [
                    'document_id' => $document->id,
                    'document_uuid' => $document->uuid,
                    'category' => $document->category,
                    'sensitivity' => $document->sensitivity,
                    'sha256' => $document->sha256,
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        return back()->with('success', "Dokumen \"{$document->title}\" berhasil diunggah ke arsip pasien.");
    }

    public function preview(Request $request, Pasien $pasien, PatientDocument $document): StreamedResponse
    {
        abort_unless((int) $document->pasien_id === (int) $pasien->id, 404);
        Gate::authorize('preview', $document);
        $this->ensureFileIntact($document);

        $this->audit->record($request, 'patient_document.previewed', $pasien->id, $document->kunjungan_id, [
            'document_id' => $document->id,
            'document_uuid' => $document->uuid,
            'archived' => $document->isArchived(),
        ]);

        return Storage::disk('local')->response($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($document->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(Request $request, Pasien $pasien, PatientDocument $document): StreamedResponse
    {
        abort_unless((int) $document->pasien_id === (int) $pasien->id, 404);
        Gate::authorize('download', $document);
        $this->ensureFileIntact($document);

        $this->audit->record($request, 'patient_document.downloaded', $pasien->id, $document->kunjungan_id, [
            'document_id' => $document->id,
            'document_uuid' => $document->uuid,
            'archived' => $document->isArchived(),
        ]);

        return Storage::disk('local')->download($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function archive(ArchivePatientDocumentRequest $request, Pasien $pasien, PatientDocument $document): RedirectResponse
    {
        abort_unless((int) $document->pasien_id === (int) $pasien->id, 404);
        Gate::authorize('archive', $document);
        $reason = $request->validated('archive_reason');

        DB::transaction(function () use ($request, $pasien, $document, $reason): void {
            $locked = PatientDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->isArchived(), 409, 'Dokumen sudah berada dalam status arsip.');

            $locked->update([
                'archived_at' => now(),
                'archived_by' => $request->user()->id,
                'archive_reason' => $reason,
            ]);

            $this->audit->record($request, 'patient_document.archived', $pasien->id, $locked->kunjungan_id, [
                'document_id' => $locked->id,
                'document_uuid' => $locked->uuid,
                'archive_reason' => $reason,
                'sha256' => $locked->sha256,
            ]);
        });

        return back()->with('success', 'Dokumen berhasil diarsipkan. Berkas asli tetap tersimpan untuk kepatuhan retensi RME.');
    }

    private function ensureFileIntact(PatientDocument $document): void
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'Berkas dokumen tidak ditemukan di penyimpanan privat.');
        $actualHash = hash_file('sha256', Storage::disk('local')->path($document->file_path));
        abort_unless(is_string($actualHash) && hash_equals($document->sha256, $actualHash), 409, 'Integritas berkas dokumen gagal diverifikasi.');
    }
}
