<?php

namespace App;

use Illuminate\Validation\Rule;

class GeneralAssessment
{
    public const SYSTEMS = [
        'cardiovascular' => 'Kardiovaskular', 'respiratory' => 'Respirasi',
        'digestive' => 'Pencernaan', 'neurological' => 'Neurologis',
        'musculoskeletal' => 'Muskuloskeletal', 'genitourinary' => 'Genitourinaria',
        'skin' => 'Integumen', 'psychological' => 'Psikologis dan status fungsional',
    ];

    public const ROS = [
        'constitutional' => 'Konstitusional', 'head_neck' => 'Kepala dan leher',
        ...self::SYSTEMS,
    ];

    public const FIELDS = [
        'hospitalization_status' => 'Riwayat rawat inap', 'surgery_status' => 'Riwayat operasi', 'special_needs_status' => 'Status kebutuhan khusus', 'needs_status' => 'Status masalah perawat', 'initial_actions_status' => 'Status tindakan awal',
        'visit_purpose' => 'Tujuan kunjungan (catatan sebelumnya)', 'companion' => 'Pendamping pasien',
        'arrival' => 'Kondisi kedatangan', 'general_condition' => 'Kondisi umum',
        'complaint_notes' => 'Catatan rincian keluhan', 'systems_notes' => 'Catatan pengkajian sistem tubuh',
        'airway' => 'Jalan napas', 'circulation' => 'Sirkulasi',
        'red_flags' => 'Tanda bahaya / hasil penilaian',
        'onset' => 'Awitan keluhan (catatan sebelumnya)', 'location' => 'Lokasi keluhan',
        'character' => 'Karakteristik keluhan', 'severity' => 'Keparahan (catatan sebelumnya)',
        'pattern' => 'Pola dan frekuensi', 'triggers' => 'Faktor pemicu',
        'relief' => 'Faktor pereda', 'associated' => 'Gejala penyerta (catatan sebelumnya)',
        'progression' => 'Perkembangan keluhan', 'progression_notes' => 'Rincian perkembangan keluhan', 'chronic' => 'Penyakit kronis (catatan sebelumnya)',
        'hospitalization' => 'Rincian rawat inap (catatan sebelumnya)', 'surgery' => 'Rincian operasi (catatan sebelumnya)',
        'mobility' => 'Status mobilitas', 'special_needs' => 'Kebutuhan khusus pasien',
        'summary' => 'Ringkasan temuan (catatan sebelumnya)', 'needs' => 'Masalah / kebutuhan teridentifikasi',
        'initial_actions' => 'Tindakan awal sesuai kewenangan', 'doctor_notes' => 'Catatan untuk dokter (catatan sebelumnya)',
        'handover_follow_up' => 'Tindak lanjut serah terima',
    ];

    public const DENTAL_FIELDS = ['general_condition', 'progression', 'progression_notes'];

    public const HANDOVER_FIELDS = ['needs_status', 'needs', 'initial_actions_status', 'initial_actions', 'handover_follow_up'];

    public static function currentIllnessNarrative(array $assessment, ?string $complaint, ?string $duration, ?string $associated, ?int $pain = null): ?string
    {
        $sentences = [];
        $append = static function (string $prefix, mixed $value) use (&$sentences): void {
            if (filled($value)) {
                $sentences[] = $prefix.rtrim(trim((string) $value), '.').'.';
            }
        };
        $append('Pasien mengeluhkan ', $complaint);
        $append(preg_match('/^(sejak|selama)\b/i', trim($duration ?? '')) ? 'Keluhan dirasakan ' : 'Keluhan berlangsung selama ', $duration);
        foreach (['location' => 'Keluhan berlokasi di ', 'character' => 'Karakter keluhan berupa ', 'pattern' => 'Pola / frekuensi keluhan: ', 'triggers' => 'Keluhan dipicu oleh ', 'relief' => 'Keluhan berkurang dengan '] as $key => $prefix) {
            $value = $assessment[$key] ?? null;
            if ($key === 'triggers' && is_string($value) && preg_match('/^tidak ada(?: pemicu)?\.?$/i', trim($value))) {
                $append('', 'Tidak ada pemicu keluhan yang dilaporkan');
            } else {
                $append($prefix, $value);
            }
        }
        $progression = ['membaik' => 'Keluhan membaik', 'menetap' => 'Keluhan menetap', 'memburuk' => 'Keluhan memburuk', 'belum_diketahui' => 'Perkembangan keluhan belum diketahui'];
        $append('', $progression[$assessment['progression'] ?? ''] ?? null);
        $append('Rincian perkembangan: ', $assessment['progression_notes'] ?? null);
        $append('Gejala penyerta: ', $associated);
        if ($pain !== null) {
            $sentences[] = 'Skala nyeri '.$pain.' dari 10.';
        }

        return $sentences ? implode(' ', $sentences) : null;
    }

    public static function nursingRules(bool $general): array
    {
        $fields = $general ? self::FIELDS : array_intersect_key(self::FIELDS, array_flip([...self::HANDOVER_FIELDS, ...self::DENTAL_FIELDS]));
        $rules = ['general_assessment' => ['nullable', 'array:'.implode(',', [...array_keys($fields), ...($general ? ['systems'] : [])])]];
        foreach ($fields as $key => $label) {
            $rules['general_assessment.'.$key] = ['nullable', 'string', 'max:2000'];
        }

        foreach (['hospitalization_status', 'surgery_status', 'special_needs_status', 'needs_status', 'initial_actions_status'] as $field) {
            if (array_key_exists($field, $fields)) {
                $rules['general_assessment.'.$field] = ['nullable', Rule::in(['ada', 'tidak_ada', 'belum_diketahui'])];
            }
        }
        foreach (['special_needs' => 'special_needs_status', 'needs' => 'needs_status', 'initial_actions' => 'initial_actions_status'] as $detail => $status) {
            if (array_key_exists($detail, $fields)) {
                $rules['general_assessment.'.$detail][] = 'required_if:general_assessment.'.$status.',ada';
            }
        }

        return [...$rules, ...($general ? self::systemRules('general_assessment.systems', self::SYSTEMS) : [])];
    }

    public static function handoverSummary(?array $values): array
    {
        $labels = array_intersect_key(self::FIELDS, array_flip([...self::HANDOVER_FIELDS, ...self::DENTAL_FIELDS]));

        return array_intersect_key(self::nursingSummary($values), array_flip($labels));
    }

    public static function systemRules(string $prefix, array $systems): array
    {
        return [
            $prefix => ['nullable', 'array:'.implode(',', array_keys($systems))],
            $prefix.'.*' => ['array:status,notes'],
            $prefix.'.*.status' => ['required', Rule::in(['positive', 'negative', 'not_assessed', 'not_relevant'])],
            $prefix.'.*.notes' => ['nullable', 'string', 'max:3000', 'required_if:'.$prefix.'.*.status,positive'],
        ];
    }

    public static function systemSummary(?array $values, array $systems, bool $includeUnassessed = true): array
    {
        $statuses = ['positive' => 'Positif / abnormal', 'negative' => 'Negatif setelah dikaji', 'not_assessed' => 'Belum dikaji', 'not_relevant' => 'Tidak relevan pada kunjungan ini'];

        return collect($systems)
            ->filter(fn (string $label, string $key): bool => $includeUnassessed
                || in_array($values[$key]['status'] ?? null, ['positive', 'negative', 'not_relevant'], true)
                || filled($values[$key]['notes'] ?? null))
            ->mapWithKeys(fn (string $label, string $key): array => [
                $label => ($statuses[$values[$key]['status'] ?? 'not_assessed'] ?? 'Belum dikaji').(filled($values[$key]['notes'] ?? null) ? ' — '.$values[$key]['notes'] : ''),
            ])->all();
    }

    public static function nursingSummary(?array $values, bool $includeUnassessed = true): array
    {
        $choices = ['ada' => 'Ada', 'tidak_ada' => 'Tidak ada', 'belum_diketahui' => 'Belum diketahui', 'mandiri' => 'Mandiri', 'dibantu' => 'Dibantu', 'kursi_roda' => 'Kursi roda', 'lainnya' => 'Lainnya', 'baik' => 'Baik', 'cukup' => 'Cukup', 'lemah' => 'Lemah', 'belum_dinilai' => 'Belum dinilai', 'bebas' => 'Bebas', 'perlu_penilaian' => 'Perlu penilaian lanjut', 'tanpa_kelainan' => 'Tidak ditemukan kelainan setelah dikaji', 'terbatas' => 'Terbatas', 'membaik' => 'Membaik', 'menetap' => 'Menetap', 'memburuk' => 'Memburuk'];
        $coded = ['hospitalization_status', 'surgery_status', 'special_needs_status', 'needs_status', 'initial_actions_status', 'arrival', 'general_condition', 'airway', 'circulation', 'mobility', 'progression'];

        return [
            ...collect(self::FIELDS)->mapWithKeys(fn (string $label, string $key): array => [$label => in_array($key, $coded, true) ? ($choices[$values[$key] ?? ''] ?? ($values[$key] ?? null)) : ($values[$key] ?? null)])->all(),
            ...collect(self::systemSummary($values['systems'] ?? null, self::SYSTEMS, $includeUnassessed))->mapWithKeys(fn (string $value, string $label): array => ['Pengkajian sistem - '.$label => $value])->all(),
        ];
    }
}
