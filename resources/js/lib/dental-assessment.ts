import { normalizeClinicalAnswer } from '@/lib/clinical-answer';

export function missingCariesDetails(finding: { findingCode: string; surface: string; details?: Record<string, string> | null }, options?: Record<string, Record<string, string>>): string[] {
    if (finding.findingCode !== 'caries') return [];
    const details = finding.details ?? {};
    const missing: string[] = [];
    const hasDetail = (key: string) => options?.[key]
        ? Object.hasOwn(options[key], details[key] ?? '')
        : Boolean(details[key]?.trim());
    if (finding.surface === 'W') missing.push('permukaan spesifik (M/O/D/V/L)');
    for (const [key, label] of Object.entries({ activity: 'aktivitas lesi', site: 'jenis / lokasi lesi', depth: 'kedalaman', pulpal: 'diagnosis pulpa', periapical: 'diagnosis apikal' })) {
        if (!hasDetail(key)) missing.push(label);
    }
    if (details.site === 'root') {
        if (!hasDetail('root_severity')) missing.push('kavitas lesi akar');
        if (details.icdas && details.icdas !== 'na') missing.push('kategori ICDAS akar');
    } else if (details.icdas === 'na') missing.push('skor ICDAS koronal atau kosongkan skor');
    if (!normalizeClinicalAnswer(details.assessment)) missing.push('hasil pemeriksaan dan rencana perawatan');
    return missing;
}
