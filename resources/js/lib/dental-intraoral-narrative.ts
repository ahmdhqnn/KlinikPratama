import { normalizeClinicalAnswer } from '@/lib/clinical-answer';
import type { DentalDiagnosisFinding } from '@/lib/dental-diagnosis';

export const intraoralNarrativeLimit = 10000;

export function syncIntraoralNarrative(current: string, previous: string, generated: string): string {
    if (current.trim() && current !== previous) return current;

    // Do not cut a finding or leave an outdated generated note in the draft.
    return generated.length <= intraoralNarrativeLimit ? generated : '';
}

export function dentalIntraoralNarrative(
    findings: DentalDiagnosisFinding[],
    summary: string | null | undefined,
    labels: { surfaces: Record<string, string>; findings: Record<string, string> },
): string {
    const teeth = new Map<string, DentalDiagnosisFinding[]>();
    for (const finding of findings) {
        const records = teeth.get(finding.toothFdi) ?? [];
        records.push(finding);
        teeth.set(finding.toothFdi, records);
    }

    const paragraphs = [...teeth].sort(([first], [second]) => first.localeCompare(second)).map(([tooth, records]) => {
        // Multi-surface entries can share the same stored observation. Merge only
        // identical observations and keep differences attached to their surfaces.
        const observations = new Map<string, { label: string; details: string | null; notes: string | null; surfaces: Set<string> }>();
        for (const { findingCode, surface, detailSummary, notes } of records) {
            const details = normalizeClinicalAnswer(detailSummary);
            const note = normalizeClinicalAnswer(notes);
            const key = JSON.stringify([findingCode, details, note, surface === 'W']);
            const observation = observations.get(key) ?? {
                label: labels.findings[findingCode] ?? `Temuan tercatat (${findingCode})`,
                details,
                notes: note,
                surfaces: new Set<string>(),
            };
            observation.surfaces.add(surface);
            observations.set(key, observation);
        }

        const sentences = [...observations.values()].map(({ label, details, notes, surfaces }) => {
            const location = [...surfaces].map(surface => {
                const name = labels.surfaces[surface];
                if (surface === 'W') return name ?? 'Seluruh gigi';
                return name ? `${name} (${surface})` : surface;
            }).join(', ');
            return [
                `${label} pada ${surfaces.has('W') ? location.toLocaleLowerCase('id') : `permukaan ${location}`}`,
                details,
                notes && notes !== details ? `Catatan: ${notes}` : null,
            ].flatMap(sentence => sentence ? [/[.!?]$/.test(sentence) ? sentence : `${sentence}.`] : []).join(' ');
        });

        return `Gigi ${tooth}: ${sentences.join(' ')}`;
    });

    const recordedSummary = normalizeClinicalAnswer(summary);
    if (recordedSummary) paragraphs.push(`Ringkasan odontogram: ${recordedSummary}`);

    return paragraphs.join('\n\n');
}
