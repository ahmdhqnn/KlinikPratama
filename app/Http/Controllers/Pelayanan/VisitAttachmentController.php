<?php

namespace App\Http\Controllers\Pelayanan;

use App\ClinicalAuditRecorder;
use App\DoctorAccessService;
use App\Http\Controllers\Controller;
use App\Models\Kunjungan;
use App\PerawatClinicResolver;
use App\VisitSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VisitAttachmentController extends Controller
{
    public function __construct(private PerawatClinicResolver $nurses, private DoctorAccessService $doctors, private VisitSchedulingService $scheduling, private ClinicalAuditRecorder $audit) {}

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $this->authorizeVisit($request, $kunjungan, true);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'file.required' => 'Pilih dokumen yang akan diunggah.',
            'file.mimes' => 'Gunakan dokumen PDF atau gambar JPG, PNG, atau WebP.',
            'file.extensions' => 'Ekstensi berkas harus PDF, JPG, JPEG, PNG, atau WebP.',
            'file.max' => 'Ukuran setiap lampiran maksimal 5 MB.',
        ]);
        $path = null;

        try {
            DB::transaction(function () use ($request, $kunjungan, $validated, &$path): void {
                $visit = Kunjungan::query()->lockForUpdate()->findOrFail($kunjungan->id);
                $this->authorizeVisit($request, $visit, true);
                $attachments = $visit->lampiran ?? [];
                if (count($attachments) >= 10) {
                    throw ValidationException::withMessages(['file' => 'Maksimal 10 lampiran untuk satu kunjungan.']);
                }
                $file = $validated['file'];
                $path = $file->store('clinical-attachments/'.$visit->id, 'local');
                if ($path === false) {
                    throw ValidationException::withMessages(['file' => 'Berkas belum dapat disimpan. Silakan coba lagi.']);
                }
                $name = Str::limit(preg_replace('/[\x00-\x1F\x7F]/u', '_', str_replace(['\\', '/'], '_', $file->getClientOriginalName())) ?: 'Dokumen pasien', 200, '');
                $id = (string) Str::uuid();
                $attachments[] = [
                    'id' => $id, 'path' => $path, 'name' => $name,
                    'mime' => $file->getMimeType(), 'size' => $file->getSize(),
                    'note' => $validated['note'] ?? null,
                    'uploaded_at' => now()->toIso8601String(), 'uploaded_by' => $request->user()->id,
                ];
                $visit->lampiran = $attachments;
                $visit->save();
                $this->audit->record($request, 'attachment.upload', $visit->pasien_id, $visit->id, ['attachment_id' => $id]);
            });
        } catch (Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('success', 'Lampiran pasien berhasil disimpan.');
    }

    public function show(Request $request, Kunjungan $kunjungan, string $attachment): StreamedResponse
    {
        $this->authorizeVisit($request, $kunjungan);
        $document = collect($kunjungan->lampiran ?? [])->firstWhere('id', $attachment);
        abort_unless($document && Storage::disk('local')->exists($document['path']), 404);
        $this->audit->record($request, 'attachment.download', $kunjungan->pasien_id, $kunjungan->id, ['attachment_id' => $attachment]);

        return Storage::disk('local')->download($document['path'], $document['name'], [
            'Content-Type' => $document['mime'], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, Kunjungan $kunjungan, string $attachment): RedirectResponse
    {
        $this->authorizeVisit($request, $kunjungan, true);
        DB::transaction(function () use ($request, $kunjungan, $attachment): void {
            $visit = Kunjungan::query()->lockForUpdate()->findOrFail($kunjungan->id);
            $this->authorizeVisit($request, $visit, true);
            $documents = collect($visit->lampiran ?? []);
            $document = $documents->firstWhere('id', $attachment);
            abort_unless($document, 404);
            $visit->lampiran = $documents->reject(fn (array $item): bool => $item['id'] === $attachment)->values()->all();
            $visit->save();
            $this->audit->record($request, 'attachment.delete', $visit->pasien_id, $visit->id, ['attachment_id' => $attachment]);
            DB::afterCommit(fn () => Storage::disk('local')->delete($document['path']));
        });

        return back()->with('success', 'Lampiran berhasil dihapus.');
    }

    private function authorizeVisit(Request $request, Kunjungan $visit, bool $editing = false): void
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'perawat', 'dokter'], true), 403);
        if ($user->role === 'perawat') {
            abort_unless((int) $visit->poliklinik_id === $this->nurses->clinicId($user), 404);
        }
        if ($user->role === 'dokter') {
            abort_if($editing, 403);
            abort_unless($this->doctors->visibleVisits($user, today()->toDateString())->whereKey($visit->id)->exists(), 404);
        }
        if ($editing) {
            $this->scheduling->ensureServiceDate($visit);
            abort_unless(in_array($visit->status, ['menunggu', 'screening'], true), 409, 'Lampiran terkunci setelah asesmen dikirim ke dokter.');
        }
    }
}
