<?php

namespace App;

class DentalAssessment
{
    /** @return array<string, array<string, string>> */
    public static function detailOptions(): array
    {
        return [
            'icdas' => ['na' => 'Gunakan kategori akar (bukan skor koronal)', '1' => '1 · Perubahan awal setelah dikeringkan', '2' => '2 · Perubahan jelas pada email', '3' => '3 · Kerusakan email lokal tanpa dentin terlihat', '4' => '4 · Bayangan gelap dentin', '5' => '5 · Kavitas jelas dengan dentin terlihat', '6' => '6 · Kavitas luas dengan dentin terlihat'],
            'activity' => ['active' => 'Aktif', 'inactive' => 'Tidak aktif', 'uncertain' => 'Belum dapat ditentukan'],
            'site' => ['primary' => 'Primer', 'secondary' => 'Sekunder / terkait restorasi', 'root' => 'Permukaan akar'],
            'depth' => ['enamel' => 'Email', 'dentin' => 'Dentin', 'deep_dentin' => 'Dentin dalam', 'uncertain' => 'Belum dapat ditentukan'],
            'root_severity' => ['noncavitated' => 'Akar kode 1 - lesi tanpa kavitas', 'cavitated' => 'Akar kode 2 - lesi berkavitas', 'uncertain' => 'Lesi akar belum dapat ditentukan'],
            'black_class' => ['I' => 'Kelas I', 'II' => 'Kelas II', 'III' => 'Kelas III', 'IV' => 'Kelas IV', 'V' => 'Kelas V', 'VI' => 'Kelas VI', 'na' => 'Tidak diterapkan'],
            'pulpal' => ['normal' => 'Pulpa normal', 'reversible' => 'Pulpitis reversibel', 'irreversible_symptomatic' => 'Pulpitis ireversibel simptomatik', 'irreversible_asymptomatic' => 'Pulpitis ireversibel asimptomatik', 'necrosis' => 'Nekrosis pulpa', 'previously_treated' => 'Pernah dirawat endodontik', 'uncertain' => 'Belum dapat ditentukan'],
            'periapical' => ['normal' => 'Jaringan apikal normal', 'symptomatic' => 'Periodontitis apikal simptomatik', 'asymptomatic' => 'Periodontitis apikal asimptomatik', 'acute_abscess' => 'Abses apikal akut', 'chronic_abscess' => 'Abses apikal kronis', 'uncertain' => 'Belum dapat ditentukan'],
        ];
    }

    /** @return array<string, string> */
    public static function redFlagLabels(): array
    {
        return ['none' => 'Tidak ditemukan setelah ditanyakan / diamati', 'breathing' => 'Sulit bernapas', 'swallowing' => 'Sulit menelan', 'rapid_swelling' => 'Pembengkakan cepat meluas / menutup mata', 'trismus' => 'Sulit membuka mulut', 'fever' => 'Demam / kondisi umum memburuk', 'unknown' => 'Belum dapat dinilai'];
    }

    /** @param array<string, mixed> $finding */
    public static function hasCompleteCariesDetails(array $finding): bool
    {
        if (($finding['finding_code'] ?? null) !== 'caries') {
            return true;
        }
        $details = $finding['details'] ?? [];

        if (($details['site'] ?? null) === 'root') {
            if (($details['icdas'] ?? null) !== 'na' || ! isset(self::detailOptions()['root_severity'][$details['root_severity'] ?? ''])) {
                return false;
            }
        } elseif (($details['icdas'] ?? null) === 'na') {
            return false;
        }

        return collect(['icdas', 'activity', 'site', 'depth', 'pulpal', 'periapical'])
            ->every(fn (string $key): bool => isset(self::detailOptions()[$key][$details[$key] ?? '']))
            && ClinicalAnswer::normalize($details['assessment'] ?? null) !== null
            && ($finding['surface'] ?? 'W') !== 'W';
    }

    /** @param array<string, mixed>|null $details */
    public static function detailSummary(?array $details): ?string
    {
        $parts = [];
        foreach (self::detailOptions() as $key => $options) {
            if (isset($options[$details[$key] ?? ''])) {
                $parts[] = ($key === 'icdas' ? 'ICDAS ' : '').$options[$details[$key]];
            }
        }
        if (filled($details['assessment'] ?? null)) {
            $parts[] = $details['assessment'];
        }

        return $parts ? implode(' · ', $parts) : null;
    }
}
