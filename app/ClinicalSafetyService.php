<?php

namespace App;

use App\Models\Obat;
use App\Models\Pasien;
use App\Models\PatientAllergy;
use App\Models\ResepObat;
use App\Models\Screening;

class ClinicalSafetyService
{
    public const PREGNANCY_LABELS = [
        'tidak_hamil' => 'Tidak sedang hamil',
        'hamil' => 'Sedang hamil',
        'kemungkinan_hamil' => 'Kemungkinan hamil / terlambat haid',
        'belum_diketahui' => 'Belum diketahui',
        'tidak_relevan' => 'Tidak relevan',
    ];

    public const LACTATION_LABELS = [
        'tidak' => 'Tidak sedang menyusui',
        'ya' => 'Sedang menyusui (ASI)',
        'belum_diketahui' => 'Belum diketahui',
        'tidak_relevan' => 'Tidak relevan',
    ];

    /**
     * Common therapeutic drug-class cross-sensitivity groups for primary care & dental clinics.
     *
     * @var array<string, list<string>>
     */
    private const DRUG_ALLERGY_FAMILIES = [
        'penicillin' => ['penisilin', 'penicillin', 'amoxicillin', 'amoksisilin', 'ampicillin', 'ampisilin', 'co-amoxiclav', 'amoxiclav', 'cefadroxil', 'sefadroksil'],
        'cephalosporin' => ['sefalosporin', 'cephalosporin', 'cefadroxil', 'sefadroksil', 'cefixime', 'sefiksim', 'ceftriaxone', 'seftriakson'],
        'nsaid' => ['nsaid', 'oains', 'ibuprofen', 'asam mefenamat', 'mefenamic', 'diklofenak', 'diclofenac', 'ketorolac', 'ketorolak', 'meloxicam', 'meloksikam', 'piroxicam', 'piroksikam', 'aspirin', 'asetosal', 'antalgin', 'metamizole', 'metampiron'],
        'sulfa' => ['sulfa', 'sulfonamid', 'sulfonamide', 'cotrimoxazole', 'kotrimoksazol', 'sulfametoksazol', 'sulfamethoxazole', 'trimethoprim'],
        'macrolide' => ['makrolida', 'macrolide', 'azithromycin', 'azitromisin', 'erythromycin', 'eritromisin', 'clarithromycin', 'klaritromisin'],
        'quinolone' => ['kuinolon', 'quinolone', 'ciprofloxacin', 'siprofloksasin', 'levofloxacin', 'levofloksasin', 'ofloxacin'],
        'tetracycline' => ['tetrasiklin', 'tetracycline', 'doxycycline', 'doksisiklin'],
        'paracetamol' => ['paracetamol', 'parasetamol', 'acetaminophen', 'asetaminofen'],
        'local_anesthetic' => ['anestesi lokal', 'lidocaine', 'lidokain', 'articaine', 'artikain', 'mepivacaine', 'mepivakain', 'pehacain'],
    ];

    /**
     * Evaluate numeric vital sign Early Warning thresholds for automated triage escalation.
     *
     * @param  array<string, mixed>  $data
     * @return array{emergency: bool, priority: bool, dentalHypertensionAlert: bool, alerts: list<string>}
     */
    public function evaluateNumericTriage(array $data, bool $isDental): array
    {
        $systolic = isset($data['td_sistole']) && is_numeric($data['td_sistole']) ? (int) $data['td_sistole'] : null;
        $diastolic = isset($data['td_diastole']) && is_numeric($data['td_diastole']) ? (int) $data['td_diastole'] : null;
        $pulse = isset($data['nadi']) && is_numeric($data['nadi']) ? (int) $data['nadi'] : null;
        $temp = isset($data['suhu']) && is_numeric($data['suhu']) ? (float) $data['suhu'] : null;
        $resp = isset($data['respirasi']) && is_numeric($data['respirasi']) ? (int) $data['respirasi'] : null;
        $spo2 = isset($data['spo2']) && is_numeric($data['spo2']) ? (int) $data['spo2'] : null;
        $pain = isset($data['skala_nyeri']) && is_numeric($data['skala_nyeri']) ? (int) $data['skala_nyeri'] : null;

        $emergency = false;
        $priority = false;
        $dentalHypertensionAlert = false;
        $alerts = [];

        if ($systolic !== null && ($systolic >= 220 || $systolic < 80)) {
            $emergency = true;
            $alerts[] = "Tekanan sistolik kritis ({$systolic} mmHg)";
        } elseif ($systolic !== null && ($systolic >= 180 || $systolic < 90)) {
            $priority = true;
            $alerts[] = "Tekanan sistolik perlu evaluasi segera ({$systolic} mmHg)";
        }

        if ($diastolic !== null && $diastolic >= 130) {
            $emergency = true;
            $alerts[] = "Tekanan diastolik kritis ({$diastolic} mmHg)";
        } elseif ($diastolic !== null && $diastolic >= 110) {
            $priority = true;
            $alerts[] = "Tekanan diastolik perlu evaluasi segera ({$diastolic} mmHg)";
        }

        if ($isDental && (($systolic !== null && $systolic >= 180) || ($diastolic !== null && $diastolic >= 110))) {
            $dentalHypertensionAlert = true;
            $priority = true;
            $alerts[] = 'Hipertensi >= 180/110 mmHg: tunda tindakan gigi invasif/ekstraksi & hindari anestesi lokal ber-adrenalin sebelum stabilisasi medis';
        }

        if ($spo2 !== null && $spo2 < 90) {
            $emergency = true;
            $alerts[] = "Hipoksemia berat (SpO₂ {$spo2}%)";
        } elseif ($spo2 !== null && $spo2 <= 93) {
            $priority = true;
            $alerts[] = "Saturasi oksigen menurun (SpO₂ {$spo2}%)";
        }

        if ($pulse !== null && ($pulse >= 140 || $pulse < 40)) {
            $emergency = true;
            $alerts[] = "Denyut nadi kritis ({$pulse} kali/menit)";
        } elseif ($pulse !== null && ($pulse >= 115 || $pulse <= 49)) {
            $priority = true;
            $alerts[] = "Denyut nadi abnormal ({$pulse} kali/menit)";
        }

        if ($resp !== null && ($resp >= 30 || $resp < 8)) {
            $emergency = true;
            $alerts[] = "Frekuensi napas kritis ({$resp} kali/menit)";
        } elseif ($resp !== null && ($resp >= 24 || $resp <= 10)) {
            $priority = true;
            $alerts[] = "Frekuensi napas abnormal ({$resp} kali/menit)";
        }

        if ($temp !== null && ($temp >= 40.0 || $temp < 35.0)) {
            $emergency = true;
            $alerts[] = "Suhu tubuh kritis ({$temp} °C)";
        } elseif ($temp !== null && $temp >= 38.5) {
            $priority = true;
            $alerts[] = "Demam tinggi ({$temp} °C)";
        }

        if ($pain !== null && $pain >= 7) {
            $priority = true;
            $alerts[] = "Nyeri berat (skala {$pain}/10)";
        }

        return [
            'emergency' => $emergency,
            'priority' => $priority,
            'dentalHypertensionAlert' => $dentalHypertensionAlert,
            'alerts' => array_values(array_unique($alerts)),
        ];
    }

    /**
     * Kemenkes RI / WHO Asia-Pacific BMI classification.
     */
    public function imtCategory(?float $imt, ?int $ageYears = null): ?string
    {
        if ($imt === null || $imt <= 0) {
            return null;
        }
        if ($ageYears !== null && $ageYears < 18) {
            return 'IMT anak/remaja (rujuk kurva IMT/U Kemenkes)';
        }

        return match (true) {
            $imt < 17.0 => 'Sangat kurus (Kekurangan BB berat)',
            $imt < 18.5 => 'Kurus (Kekurangan BB ringan)',
            $imt <= 22.9 => 'Normal (Ideal Asia-Pasifik)',
            $imt <= 24.9 => 'Berat badan lebih (Berisiko)',
            $imt <= 29.9 => 'Obesitas tingkat I',
            default => 'Obesitas tingkat II',
        };
    }

    public static function isReproductiveAgeFemale(?Pasien $patient): bool
    {
        if (! $patient || $patient->jenis_kelamin !== 'P') {
            return false;
        }
        $age = $patient->umur;

        return $age === null || ($age >= 10 && $age <= 55);
    }

    /**
     * @return array{applicable: bool, pregnancyStatus: ?string, pregnancyLabel: ?string, gestationalWeeks: ?int, trimester: ?int, hpht: ?string, lactationStatus: ?string, lactationLabel: ?string, hasRisk: bool, summaryText: ?string}
     */
    public function maternalSummary(?Screening $screening, ?Pasien $patient = null): array
    {
        $applicable = self::isReproductiveAgeFemale($patient);
        $pregnancyStatus = $screening?->status_kehamilan;
        $weeks = $screening?->usia_kehamilan_minggu !== null ? (int) $screening->usia_kehamilan_minggu : null;
        $trimester = $weeks !== null ? ($weeks <= 13 ? 1 : ($weeks <= 27 ? 2 : 3)) : null;
        $hpht = $screening?->hpht?->format('d/m/Y');
        $lactationStatus = $screening?->status_menyusui;

        $pregnancyLabel = $pregnancyStatus ? (self::PREGNANCY_LABELS[$pregnancyStatus] ?? $pregnancyStatus) : null;
        if ($pregnancyStatus === 'hamil' && $weeks !== null) {
            $pregnancyLabel .= " ({$weeks} minggu · Trimester {$trimester})";
        }
        if ($pregnancyStatus === 'hamil' && $hpht !== null) {
            $pregnancyLabel .= " · HPHT {$hpht}";
        }
        $lactationLabel = $lactationStatus ? (self::LACTATION_LABELS[$lactationStatus] ?? $lactationStatus) : null;

        $hasRisk = in_array($pregnancyStatus, ['hamil', 'kemungkinan_hamil'], true) || $lactationStatus === 'ya';
        $parts = [];
        if ($pregnancyLabel && $pregnancyStatus !== 'tidak_relevan') {
            $parts[] = 'Kehamilan: '.$pregnancyLabel;
        }
        if ($lactationLabel && $lactationStatus !== 'tidak_relevan') {
            $parts[] = 'Menyusui: '.$lactationLabel;
        }

        return [
            'applicable' => $applicable,
            'pregnancyStatus' => $pregnancyStatus,
            'pregnancyLabel' => $pregnancyLabel,
            'gestationalWeeks' => $weeks,
            'trimester' => $trimester,
            'hpht' => $hpht,
            'lactationStatus' => $lactationStatus,
            'lactationLabel' => $lactationLabel,
            'hasRisk' => $hasRisk,
            'summaryText' => $parts !== [] ? implode(' · ', $parts) : null,
        ];
    }

    public function isNegativeAllergyText(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return true;
        }
        $normalized = mb_strtolower(trim($text));

        return (bool) preg_match('/^(tidak ada|pasien menyangkal|menyangkal|belum diketahui|belum dapat|tidak diketahui|none|nkda|nihil|-)\b/u', $normalized);
    }

    /**
     * Cross-check prescribed medicine name/ingredients against patient's recorded allergies.
     *
     * @param  iterable<PatientAllergy>  $structuredAllergies
     */
    public function detectDrugAllergyConflict(?string $allergyText, iterable $structuredAllergies, ?Obat $obat, ?string $externalDrugName = null): ?string
    {
        $drugCorpus = mb_strtolower(trim(implode(' ', array_filter([
            $obat?->nama,
            $obat?->kandungan,
            $obat?->nama_kfa,
            $externalDrugName,
        ]))));

        if ($drugCorpus === '') {
            return null;
        }

        $rawTerms = [];
        if (! $this->isNegativeAllergyText($allergyText)) {
            foreach (preg_split('/[,;\n\/]+/u', (string) $allergyText, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $chunk) {
                $clean = trim((string) preg_replace('/\b(alergi|obat|riwayat|terhadap|reaksi|ruam|gatal|bengkak|sesak)\b/iu', ' ', $chunk));
                $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));
                if (mb_strlen($clean) >= 4 && ! $this->isNegativeAllergyText($clean)) {
                    $rawTerms[] = $clean;
                }
            }
        }

        foreach ($structuredAllergies as $allergy) {
            if (($allergy->clinical_status ?? 'active') === 'active' && ($allergy->verification_status ?? 'confirmed') !== 'refuted') {
                $display = trim((string) $allergy->allergen_display);
                if (mb_strlen($display) >= 3 && ! $this->isNegativeAllergyText($display)) {
                    $rawTerms[] = $display;
                }
            }
        }

        foreach (array_unique($rawTerms) as $term) {
            $lowerTerm = mb_strtolower($term);
            if (str_contains($drugCorpus, $lowerTerm)) {
                return $term;
            }

            foreach (preg_split('/[\s()-]+/u', $lowerTerm, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                if (mb_strlen($token) >= 5 && str_contains($drugCorpus, $token)) {
                    return $term;
                }
            }

            foreach (self::DRUG_ALLERGY_FAMILIES as $members) {
                $allergyInFamily = collect($members)->contains(fn (string $member): bool => str_contains($lowerTerm, $member));
                if ($allergyInFamily && collect($members)->contains(fn (string $member): bool => str_contains($drugCorpus, $member))) {
                    return $term;
                }
            }
        }

        return null;
    }

    /**
     * Detect duplicate active ingredients with existing active prescription items in the same visit.
     *
     * @param  iterable<ResepObat>  $existingItems
     */
    public function detectDuplicateIngredient(?Obat $obat, iterable $existingItems): ?string
    {
        if (! $obat || blank($obat->kandungan)) {
            return null;
        }
        $targetTokens = $this->ingredientTokens($obat->kandungan);
        if ($targetTokens === []) {
            return null;
        }

        foreach ($existingItems as $item) {
            if ($item->voided_at !== null) {
                continue;
            }
            if ((int) $item->obat_id === (int) $obat->id) {
                return $item->nama_obat;
            }
            $otherTokens = $this->ingredientTokens($item->obat?->kandungan);
            if (array_intersect($targetTokens, $otherTokens) !== []) {
                return $item->nama_obat;
            }
        }

        return null;
    }

    /**
     * Compose backward-compatible Indonesian sig string when structured FHIR dosage fields are supplied.
     *
     * @param  array<string, mixed>  $data
     */
    public function composeDosageInstruction(array $data): ?string
    {
        if (filled($data['aturan_pakai'] ?? null)) {
            return trim((string) $data['aturan_pakai']);
        }
        $freq = isset($data['frekuensi']) && is_numeric($data['frekuensi']) ? (int) $data['frekuensi'] : null;
        $dose = isset($data['dosis_sekali_pakai']) && is_numeric($data['dosis_sekali_pakai']) ? rtrim(rtrim(number_format((float) $data['dosis_sekali_pakai'], 2, '.', ''), '0'), '.') : null;
        if ($freq === null && $dose === null) {
            return null;
        }
        $timingLabels = [
            'sesudah_makan' => 'sesudah makan',
            'sebelum_makan' => 'sebelum makan',
            'bersama_makan' => 'bersama makan',
            'bila_perlu' => 'bila perlu',
            'sebelum_tidur' => 'sebelum tidur',
        ];
        $parts = [];
        if ($freq !== null && $dose !== null) {
            $parts[] = "{$freq} x {$dose}".(filled($data['dosis_unit'] ?? null) ? ' '.trim((string) $data['dosis_unit']) : '');
        } elseif ($freq !== null) {
            $parts[] = "{$freq} kali sehari";
        } elseif ($dose !== null) {
            $parts[] = "Dosis {$dose}".(filled($data['dosis_unit'] ?? null) ? ' '.trim((string) $data['dosis_unit']) : '');
        }
        if (! empty($data['waktu_pakai']) && isset($timingLabels[$data['waktu_pakai']])) {
            $parts[] = $timingLabels[$data['waktu_pakai']];
        }
        if (! empty($data['durasi_hari']) && is_numeric($data['durasi_hari'])) {
            $parts[] = 'selama '.(int) $data['durasi_hari'].' hari';
        }

        return implode(' ', $parts);
    }

    /**
     * @return list<string>
     */
    private function ingredientTokens(?string $ingredients): array
    {
        if (blank($ingredients)) {
            return [];
        }

        return collect(preg_split('/[,;+\/\n]+/u', mb_strtolower((string) $ingredients), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $part): string => trim((string) preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:mg|ml|mcg|gr|g|iu|%)\b/iu', '', $part)))
            ->map(fn (string $part): string => trim((string) preg_replace('/\s+/u', ' ', $part)))
            ->filter(fn (string $part): bool => mb_strlen($part) >= 4)
            ->values()
            ->all();
    }
}
