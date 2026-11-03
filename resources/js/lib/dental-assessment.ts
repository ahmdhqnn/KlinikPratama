import { normalizeClinicalAnswer } from '@/lib/clinical-answer';

export function missingCariesDetails(finding: { findingCode: string; surface: string; details?: Record<string, string> | null }): string[] {
    if (finding.findingCode !== 'caries') return [];
    const details = finding.details ?? {};
    const missing: string[] = [];
    if (finding.surface === 'W') missing.push('permukaan spesifik (M/O/D/V/L)');
    for (const [key, label] of Object.entries({ activity: 'aktivitas lesi', site: 'jenis / lokasi lesi', depth: 'kedalaman', pulpal: 'diagnosis pulpa', periapical: 'diagnosis apikal' })) {
        if (!details[key]) missing.push(label);
    }
    if (details.site === 'root') {
        if (!details.root_severity) missing.push('kavitas lesi akar');
        if (details.icdas && details.icdas !== 'na') missing.push('kategori ICDAS akar');
    } else if (details.icdas === 'na') missing.push('skor ICDAS koronal atau kosongkan skor');
    if (!normalizeClinicalAnswer(details.assessment)) missing.push('hasil pemeriksaan dan rencana perawatan');
    return missing;
}
