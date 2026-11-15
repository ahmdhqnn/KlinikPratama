import { examinationAnnotationNote, findingLocation, type RecordedBodyFinding } from './body-map';

export function describeGeneralFinding(finding: RecordedBodyFinding): string {
    const location = findingLocation(finding)?.label ?? finding.locationLabel ?? 'Pemeriksaan fisik';
    const generatedNote = examinationAnnotationNote(location, finding.finding);
    return [
        `Lokasi: ${location}`,
        finding.locationId !== 'general' && `Sistem: ${finding.category}`,
        finding.side && `Sisi: ${finding.side}`,
        finding.detail && `Lokasi rinci: ${finding.detail}`,
        finding.method && `Metode: ${[finding.method, finding.methodDetail].filter(Boolean).join(' · ')}`,
        finding.symptom && `Keluhan lokal: ${finding.symptom}`,
        finding.pain != null && `Nyeri: ${finding.pain}/10`,
        `Temuan: ${finding.finding}`,
        finding.annotationNote && finding.annotationNote !== generatedNote && `Catatan gambar: ${finding.annotationNote}`,
    ].filter(Boolean).join('\n');
}

const catalogTerms = [
    [/\b(?:wheezing|mengi)\b/i, 'Wheezing'],
    [/\b(?:nyeri dada|chest pain)\b/i, 'Chest pain'],
    [/\b(?:nyeri perut|abdominal pain)\b/i, 'Abdominal pain'],
    [/\b(?:sesak napas|shortness of breath)\b/i, 'Shortness of breath'],
    [/\b(?:batuk|cough)\b/i, 'Cough'],
    [/\b(?:demam|fever)\b/i, 'Fever'],
] as const;

export function generalFindingSearches(value: string): string[] {
    return catalogTerms.filter(([pattern]) => pattern.test(value)).map(([, term]) => term);
}

export function generalFindingSearch(value: string): string {
    const term = value.trim().replace(/\s+/g, ' ');
    return term.length <= 100 ? term : '';
}
