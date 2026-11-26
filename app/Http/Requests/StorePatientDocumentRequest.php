<?php

namespace App\Http\Requests;

use App\Models\OdontogramFinding;
use App\Models\Pasien;
use App\Models\PatientDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePatientDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Pasien|null $patient */
        $patient = $this->route('pasien');

        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240'],
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', Rule::in(array_keys(PatientDocument::CATEGORY_META))],
            'document_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'issuer_facility' => ['nullable', 'string', 'max:150'],
            'kunjungan_id' => [
                'nullable',
                'integer',
                Rule::exists('kunjungan', 'id')->where(fn ($query) => $query->where('pasien_id', $patient?->id ?? 0)),
            ],
            'tooth_fdi' => ['nullable', 'string', 'max:10', Rule::in(OdontogramFinding::toothCodes())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas dokumen yang akan diunggah.',
            'file.mimes' => 'Format berkas harus berupa PDF, JPG, atau PNG.',
            'file.extensions' => 'Ekstensi berkas yang diizinkan hanya PDF, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
            'title.required' => 'Judul dokumen wajib diisi.',
            'category.required' => 'Pilih kategori dokumen.',
            'document_date.required' => 'Tanggal dokumen wajib diisi.',
            'document_date.before_or_equal' => 'Tanggal dokumen tidak boleh melebihi hari ini.',
            'tooth_fdi.in' => 'Nomor gigi harus menggunakan notasi FDI yang valid.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    return;
                }

                $path = $file->getRealPath();
                if (! is_string($path) || ! is_readable($path)) {
                    $validator->errors()->add('file', 'Berkas tidak dapat dibaca oleh sistem.');

                    return;
                }

                $handle = fopen($path, 'rb');
                $header = is_resource($handle) ? (string) fread($handle, 8) : '';
                if (is_resource($handle)) {
                    fclose($handle);
                }

                $isPdf = str_starts_with($header, '%PDF-');
                $isPng = str_starts_with($header, "\x89PNG\r\n\x1A\n");
                $isJpeg = str_starts_with($header, "\xFF\xD8\xFF");

                if (! $isPdf && ! $isPng && ! $isJpeg) {
                    $validator->errors()->add('file', 'Isi berkas tidak sesuai dengan format PDF, JPG, atau PNG yang valid.');
                }
            },
        ];
    }
}
