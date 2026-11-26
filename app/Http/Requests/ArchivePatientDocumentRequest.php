<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchivePatientDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('archive_reason'))) {
            $this->merge(['archive_reason' => trim($this->input('archive_reason'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archive_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'archive_reason.required' => 'Alasan pengarsipan wajib diisi.',
            'archive_reason.min' => 'Alasan pengarsipan minimal 10 karakter agar jejak audit lengkap.',
        ];
    }
}
