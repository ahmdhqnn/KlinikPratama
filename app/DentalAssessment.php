<?php

namespace App;

class DentalAssessment
{
    /** @return array<string, array<string, string>> */
    public static function detailOptions(): array
    {
        return [
            'eruption' => ['partial' => 'Erupsi sebagian', 'impacted' => 'Impaksi', 'retained_primary' => 'Persistensi gigi sulung'],
            'position' => ['rotation' => 'Rotasi', 'migration' => 'Migrasi', 'version' => 'Versi / deviasi posisi', 'other' => 'Kelainan posisi lain'],
            'morphology' => ['peg' => 'Peg-shaped', 'microdontia' => 'Mikrodonsia', 'fusion' => 'Fusi', 'other' => 'Anomali bentuk / ukuran lain'],
            'endodontic' => ['non_vital' => 'Non-vital berdasarkan pemeriksaan dokter', 'treated' => 'Riwayat PSA selesai', 'in_progress' => 'PSA dalam perawatan'],
            'prosthesis' => ['crown' => 'Mahkota tiruan', 'pontic' => 'Pontik bridge', 'abutment' => 'Penyangga bridge', 'implant' => 'Implan', 'partial_denture' => 'Gigi tiruan sebagian lepasan', 'complete_denture' => 'Gigi tiruan lengkap'],
            'prosthesis_region' => ['upper' => 'Prostesis rahang atas', 'lower' => 'Prostesis rahang bawah'],
            'material' => ['composite' => 'Komposit', 'amalgam' => 'Amalgam', 'gic' => 'Semen ionomer kaca', 'metal' => 'Logam', 'porcelain' => 'Porselen / keramik', 'pfm' => 'Porselen fusi logam', 'acrylic' => 'Akrilik', 'frame' => 'Kerangka logam', 'other' => 'Material lain', 'unknown' => 'Material belum diketahui'],
            'restoration_state' => ['permanent' => 'Restorasi permanen', 'temporary' => 'Tambalan sementara', 'sealant' => 'Pit & fissure sealant'],
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
        return ['none' => 'Tidak ditemukan setelah ditanyakan / diamati', 'breathing' => 'Sulit bernapas', 'swallowing' => 'Sulit menelan', 'rapid_swelling' => 'Pembengkakan cepat meluas / menutup mata', 'severe_allergy' => 'Dugaan reaksi alergi berat', 'heavy_bleeding' => 'Perdarahan hebat', 'critical_vitals' => 'TTV sangat abnormal menurut penilaian petugas', 'trismus' => 'Sulit membuka mulut', 'fever' => 'Demam / kondisi umum memburuk', 'unknown' => 'Belum dapat dinilai'];
    }

    /** @param array<string, mixed> $finding */
    public static function hasCompleteCariesDetails(array $finding): bool
    {
        return self::missingCariesDetails($finding) === [];
    }

    /** @param array<string, mixed> $finding */
    public static function matchesDiagnosisSource(array $finding, string $source): bool
    {
        if (($finding['finding_code'] ?? null) === $source) {
            return true;
        }
        $details = $finding['details'] ?? [];
        if (in_array($source, ['pulpal', 'periapical'], true)) {
            return filled($details[$source] ?? null);
        }
        $contexts = [
            'partial_erupted' => ['eruption', 'partial'], 'impacted' => ['eruption', 'impacted'], 'retained_primary' => ['eruption', 'retained_primary'],
            'non_vital' => ['endodontic', 'non_vital'], 'root_canal' => ['endodontic', 'treated'], 'crown' => ['prosthesis', 'crown'],
            'bridge_pontic' => ['prosthesis', 'pontic'], 'bridge_abutment' => ['prosthesis', 'abutment'], 'implant' => ['prosthesis', 'implant'],
            'temporary_restoration' => ['restoration_state', 'temporary'], 'sealant' => ['restoration_state', 'sealant'],
        ];
        if (isset($contexts[$source])) {
            [$key, $value] = $contexts[$source];

            return ($details[$key] ?? null) === $value || ($source === 'root_canal' && ($details[$key] ?? null) === 'in_progress');
        }

        return ($source === 'malposition' && filled($details['position'] ?? null))
            || ($source === 'anomaly' && filled($details['morphology'] ?? null))
            || ($source === 'removable_denture' && in_array($details['prosthesis'] ?? '', ['partial_denture', 'complete_denture'], true));
    }

    /** @param array<string, mixed> $finding
     * @return list<string>
     */
    public static function missingCariesDetails(array $finding): array
    {
        if (($finding['finding_code'] ?? null) !== 'caries') {
            return [];
        }
        $details = $finding['details'] ?? [];
        $missing = [];
        if (($finding['surface'] ?? 'W') === 'W') {
            $missing[] = 'permukaan spesifik (M/O/D/V/L)';
        }
        foreach (['activity' => 'aktivitas lesi', 'site' => 'jenis / lokasi lesi', 'depth' => 'kedalaman', 'pulpal' => 'diagnosis pulpa', 'periapical' => 'diagnosis apikal'] as $key => $label) {
            if (! isset(self::detailOptions()[$key][$details[$key] ?? ''])) {
                $missing[] = $label;
            }
        }

        if (($details['site'] ?? null) === 'root') {
            if (! isset(self::detailOptions()['root_severity'][$details['root_severity'] ?? ''])) {
                $missing[] = 'kavitas lesi akar';
            }
            if (filled($details['icdas'] ?? null) && $details['icdas'] !== 'na') {
                $missing[] = 'kategori ICDAS akar';
            }
        } elseif (($details['icdas'] ?? null) === 'na') {
            $missing[] = 'skor ICDAS koronal atau kosongkan skor';
        }
        if (ClinicalAnswer::normalize($details['assessment'] ?? null) === null) {
            $missing[] = 'hasil pemeriksaan dan rencana perawatan';
        }

        return $missing;
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
        if (filled($details['prosthesis_span'] ?? null)) {
            $parts[] = 'Gigi terkait prostesis: '.$details['prosthesis_span'];
        }

        return $parts ? implode(' · ', $parts) : null;
    }
}
