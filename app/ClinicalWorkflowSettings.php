<?php

namespace App;

use App\Models\KlinikSetting;
use App\Models\Screening;
use Illuminate\Validation\Rule;

class ClinicalWorkflowSettings
{
    /**
     * Default clinical workflow configuration for Klinik Pratama.
     * Poli Umum Rincian keluhan & Pengkajian sistem tubuh default to Dokter per clinical request,
     * while all regulatory safety cores (TTV, Alergi, Triase, Diagnosis, Tindakan, Resep) remain locked.
     *
     * @return array<string, string|bool>
     */
    public static function defaults(): array
    {
        return [
            'umum_complaint_details' => 'dokter',
            'umum_system_review' => 'dokter',
            'umum_nutrition_needs' => 'perawat',
            'umum_anthropometry_mode' => 'ringkas',
            'gigi_pain_details' => 'perawat',
            'gigi_mucosa_observation' => 'perawat',
            'gigi_additional_assessment' => 'perawat',
            'dokter_soap_tab' => true,
            'dokter_repeat_vitals' => true,
        ];
    }

    /**
     * Allowed enum values for each string setting key.
     *
     * @return array<string, list<string>>
     */
    public static function allowedValues(): array
    {
        return [
            'umum_complaint_details' => ['dokter', 'perawat', 'keduanya'],
            'umum_system_review' => ['dokter', 'perawat', 'keduanya'],
            'umum_nutrition_needs' => ['perawat', 'nonaktif'],
            'umum_anthropometry_mode' => ['ringkas', 'lengkap'],
            'gigi_pain_details' => ['perawat', 'dokter', 'keduanya'],
            'gigi_mucosa_observation' => ['perawat', 'dokter', 'keduanya'],
            'gigi_additional_assessment' => ['perawat', 'nonaktif'],
        ];
    }

    /**
     * Resolve stored settings safely against canonical keys and defaults.
     *
     * @param  array<string, mixed>|null  $stored
     * @return array<string, string|bool>
     */
    public static function resolve(?array $stored): array
    {
        $resolved = self::defaults();
        if (! is_array($stored)) {
            return $resolved;
        }

        foreach (self::allowedValues() as $key => $allowed) {
            $value = $stored[$key] ?? null;
            if (is_string($value) && in_array($value, $allowed, true)) {
                $resolved[$key] = $value;
            }
        }

        foreach (['dokter_soap_tab', 'dokter_repeat_vitals'] as $boolKey) {
            if (array_key_exists($boolKey, $stored) && is_bool($stored[$boolKey])) {
                $resolved[$boolKey] = $stored[$boolKey];
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, string|bool>
     */
    public static function current(): array
    {
        $setting = KlinikSetting::query()->select(['clinical_workflow'])->first();

        return self::resolve($setting?->clinical_workflow);
    }

    /**
     * Validation rules for Admin clinical workflow settings update.
     *
     * @return array<string, array<mixed>>
     */
    public static function validationRules(): array
    {
        $rules = [
            'pelaksana_ttv' => ['required', Rule::in(['perawat', 'pendaftaran'])],
            'clinical_workflow' => ['required', 'array'],
            'clinical_workflow.dokter_soap_tab' => ['required', 'boolean'],
            'clinical_workflow.dokter_repeat_vitals' => ['required', 'boolean'],
        ];

        foreach (self::allowedValues() as $key => $allowed) {
            $rules['clinical_workflow.'.$key] = ['required', 'string', Rule::in($allowed)];
        }

        return $rules;
    }

    /**
     * Validation rules for Doctor structured clinical_assessment payload.
     *
     * @return array<string, array<mixed>>
     */
    public static function doctorAssessmentRules(bool $isGeneral, bool $isDental): array
    {
        $mucosaSites = implode(',', array_keys(Screening::ORAL_AREAS));
        $allowedTopKeys = array_filter([
            $isGeneral ? 'complaint_details' : null,
            $isDental ? 'dental_pain' : null,
            $isDental ? 'mucosa_observation' : null,
        ]);

        $rules = [
            'clinical_assessment' => [
                Rule::excludeIf($allowedTopKeys === []),
                'nullable',
                'array:'.implode(',', $allowedTopKeys),
            ],
        ];

        if ($isGeneral) {
            $rules['clinical_assessment.complaint_details'] = ['nullable', 'array:location,character,pattern,triggers,relief,progression,progression_notes,pain_scale,notes'];
            foreach (['location', 'character', 'pattern', 'triggers', 'relief', 'progression_notes', 'notes'] as $field) {
                $rules['clinical_assessment.complaint_details.'.$field] = ['nullable', 'string', 'max:2000'];
            }
            $rules['clinical_assessment.complaint_details.progression'] = ['nullable', Rule::in(['membaik', 'menetap', 'memburuk', 'belum_diketahui'])];
            $rules['clinical_assessment.complaint_details.pain_scale'] = ['nullable', 'integer', 'between:0,10'];
        }

        if ($isDental) {
            $rules['clinical_assessment.dental_pain'] = ['nullable', 'array:location,pain_scale,duration,triggers,triggers_other,character,relief,impact,progression,progression_notes'];
            foreach (['location', 'duration', 'triggers_other', 'character', 'relief', 'impact', 'progression_notes'] as $field) {
                $rules['clinical_assessment.dental_pain.'.$field] = ['nullable', 'string', 'max:2000'];
            }
            $rules['clinical_assessment.dental_pain.pain_scale'] = ['nullable', 'integer', 'between:0,10'];
            $rules['clinical_assessment.dental_pain.triggers'] = ['nullable', 'array', 'max:7'];
            $rules['clinical_assessment.dental_pain.triggers.*'] = ['string', 'distinct', Rule::in(['dingin', 'manis', 'mengunyah', 'panas', 'spontan', 'lainnya', 'tidak_ada'])];
            $rules['clinical_assessment.dental_pain.progression'] = ['nullable', Rule::in(['membaik', 'menetap', 'memburuk', 'belum_diketahui'])];

            $rules['clinical_assessment.mucosa_observation'] = ['nullable', 'array:'.$mucosaSites.',catatan,kelainan_mukosa,observasi_mulut'];
            foreach (array_keys(Screening::ORAL_AREAS) as $site) {
                $rules['clinical_assessment.mucosa_observation.'.$site] = ['nullable', Rule::in(['normal', 'abnormal', 'belum_dinilai'])];
            }
            $rules['clinical_assessment.mucosa_observation.catatan'] = ['nullable', 'array:'.$mucosaSites];
            $rules['clinical_assessment.mucosa_observation.catatan.*'] = ['nullable', 'string', 'max:2000'];
            $rules['clinical_assessment.mucosa_observation.kelainan_mukosa'] = ['nullable', 'string', 'max:5000'];
            $rules['clinical_assessment.mucosa_observation.observasi_mulut'] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * Normalize doctor clinical_assessment payload before saving, dropping empty nested values.
     *
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    public static function normalizeDoctorAssessment(?array $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $clean = [];

        if (isset($value['complaint_details']) && is_array($value['complaint_details'])) {
            $details = [];
            foreach ($value['complaint_details'] as $key => $entry) {
                if ($key === 'pain_scale') {
                    if ($entry !== null && $entry !== '') {
                        $details[$key] = (int) $entry;
                    }
                } elseif (is_string($entry) && trim($entry) !== '') {
                    $details[$key] = trim($entry);
                }
            }
            if ($details !== []) {
                $clean['complaint_details'] = $details;
            }
        }

        if (isset($value['dental_pain']) && is_array($value['dental_pain'])) {
            $pain = [];
            foreach ($value['dental_pain'] as $key => $entry) {
                if ($key === 'pain_scale') {
                    if ($entry !== null && $entry !== '') {
                        $pain[$key] = (int) $entry;
                    }
                } elseif ($key === 'triggers' && is_array($entry)) {
                    $triggers = array_values(array_filter($entry, fn (mixed $v): bool => is_string($v) && $v !== ''));
                    if ($triggers !== []) {
                        $pain[$key] = $triggers;
                    }
                } elseif (is_string($entry) && trim($entry) !== '') {
                    $pain[$key] = trim($entry);
                }
            }
            if ($pain !== []) {
                $clean['dental_pain'] = $pain;
            }
        }

        if (isset($value['mucosa_observation']) && is_array($value['mucosa_observation'])) {
            $mucosa = [];
            foreach (array_keys(Screening::ORAL_AREAS) as $site) {
                $status = $value['mucosa_observation'][$site] ?? null;
                if (is_string($status) && trim($status) !== '') {
                    $mucosa[$site] = trim($status);
                }
            }
            if (isset($value['mucosa_observation']['catatan']) && is_array($value['mucosa_observation']['catatan'])) {
                $notes = [];
                foreach ($value['mucosa_observation']['catatan'] as $site => $note) {
                    if (is_string($note) && trim($note) !== '') {
                        $notes[$site] = trim($note);
                    }
                }
                if ($notes !== []) {
                    $mucosa['catatan'] = $notes;
                }
            }
            foreach (['kelainan_mukosa', 'observasi_mulut'] as $textField) {
                $text = $value['mucosa_observation'][$textField] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    $mucosa[$textField] = trim($text);
                }
            }
            if ($mucosa !== []) {
                $clean['mucosa_observation'] = $mucosa;
            }
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * Format doctor clinical_assessment into human-readable key-value rows for RME & print views.
     *
     * @param  array<string, mixed>|null  $assessment
     * @return array<string, string>
     */
    public static function summarizeDoctorAssessment(?array $assessment): array
    {
        if (! is_array($assessment)) {
            return [];
        }

        $rows = [];
        $progressionLabels = [
            'membaik' => 'Membaik',
            'menetap' => 'Menetap',
            'memburuk' => 'Memburuk',
            'belum_diketahui' => 'Belum diketahui',
        ];
        $triggerLabels = [
            'dingin' => 'Dingin',
            'manis' => 'Manis',
            'mengunyah' => 'Mengunyah',
            'panas' => 'Panas',
            'spontan' => 'Spontan',
            'lainnya' => 'Lainnya',
            'tidak_ada' => 'Tidak ada pemicu',
        ];
        $mucosaStatusLabels = [
            'normal' => 'Normal',
            'abnormal' => 'Abnormal',
            'belum_dinilai' => 'Belum dapat dinilai',
        ];

        $complaint = $assessment['complaint_details'] ?? null;
        if (is_array($complaint)) {
            $map = [
                'location' => 'Rincian keluhan (dokter) - Lokasi',
                'character' => 'Rincian keluhan (dokter) - Karakteristik',
                'pattern' => 'Rincian keluhan (dokter) - Pola / frekuensi',
                'triggers' => 'Rincian keluhan (dokter) - Faktor pemicu',
                'relief' => 'Rincian keluhan (dokter) - Faktor pereda',
                'progression_notes' => 'Rincian keluhan (dokter) - Perkembangan',
                'notes' => 'Rincian keluhan (dokter) - Catatan tambahan',
            ];
            foreach ($map as $key => $label) {
                if (filled($complaint[$key] ?? null)) {
                    $rows[$label] = (string) $complaint[$key];
                }
            }
            if (filled($complaint['progression'] ?? null)) {
                $rows['Rincian keluhan (dokter) - Arah perkembangan'] = $progressionLabels[$complaint['progression']] ?? (string) $complaint['progression'];
            }
            if (array_key_exists('pain_scale', $complaint) && $complaint['pain_scale'] !== null && $complaint['pain_scale'] !== '') {
                $rows['Skala nyeri (dokter)'] = $complaint['pain_scale'].' / 10';
            }
        }

        $dentalPain = $assessment['dental_pain'] ?? null;
        if (is_array($dentalPain)) {
            $map = [
                'location' => 'Nyeri gigi (dokter) - Lokasi',
                'duration' => 'Nyeri gigi (dokter) - Pola dan durasi',
                'character' => 'Nyeri gigi (dokter) - Karakter dan penjalaran',
                'relief' => 'Nyeri gigi (dokter) - Faktor pereda',
                'impact' => 'Nyeri gigi (dokter) - Dampak aktivitas/tidur',
                'progression_notes' => 'Nyeri gigi (dokter) - Rincian perkembangan',
            ];
            foreach ($map as $key => $label) {
                if (filled($dentalPain[$key] ?? null)) {
                    $rows[$label] = (string) $dentalPain[$key];
                }
            }
            if (! empty($dentalPain['triggers']) && is_array($dentalPain['triggers'])) {
                $rows['Nyeri gigi (dokter) - Pemicu'] = implode(', ', array_map(
                    fn (string $v): string => $v === 'lainnya' && filled($dentalPain['triggers_other'] ?? null)
                        ? (string) $dentalPain['triggers_other']
                        : ($triggerLabels[$v] ?? $v),
                    $dentalPain['triggers'],
                ));
            }
            if (filled($dentalPain['progression'] ?? null)) {
                $rows['Nyeri gigi (dokter) - Perkembangan'] = $progressionLabels[$dentalPain['progression']] ?? (string) $dentalPain['progression'];
            }
            if (array_key_exists('pain_scale', $dentalPain) && $dentalPain['pain_scale'] !== null && $dentalPain['pain_scale'] !== '') {
                $rows['Skala nyeri gigi (dokter)'] = $dentalPain['pain_scale'].' / 10';
            }
        }

        $mucosa = $assessment['mucosa_observation'] ?? null;
        if (is_array($mucosa)) {
            foreach (Screening::ORAL_AREAS as $site => $label) {
                $status = $mucosa[$site] ?? null;
                $note = $mucosa['catatan'][$site] ?? null;
                if (filled($status) || filled($note)) {
                    $text = $mucosaStatusLabels[$status ?? ''] ?? ($status ?: 'Tercatat');
                    if (filled($note)) {
                        $text .= ' — '.$note;
                    }
                    $rows['Observasi mukosa (dokter) - '.$label] = $text;
                }
            }
            if (filled($mucosa['kelainan_mukosa'] ?? null)) {
                $rows['Observasi mukosa (dokter) - Catatan umum'] = (string) $mucosa['kelainan_mukosa'];
            }
            if (filled($mucosa['observasi_mulut'] ?? null)) {
                $rows['Observasi mulut & rahang (dokter)'] = (string) $mucosa['observasi_mulut'];
            }
        }

        return $rows;
    }
}
