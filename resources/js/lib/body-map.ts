import { annotationNarrative, emptyBodyFinding, bodyLocations, organLocations, physicalExamSystems, type BodyFindingDraft } from './body-examination';
import { normalizeClinicalAnswer } from './clinical-answer';

export type BodyModel = 'male' | 'female';
export type BodyMode = 'organs' | 'body';
export type ExaminationEntryMode = 'interactive' | 'manual' | 'quick';
export interface RecordedBodyFinding {
    id: string;
    locationId: string;
    locationLabel?: string;
    entryMode?: ExaminationEntryMode;
    mode: BodyMode;
    view: 'front' | 'back';
    model: BodyModel;
    category: string;
    side: string;
    detail: string;
    method: string;
    methodDetail?: string;
    symptom: string;
    finding: string;
    annotationNote?: string;
    pain: number | null;
}
export type AnnotationTool = 'pen' | 'line' | 'arrow' | 'ellipse';
export interface AnnotationPoint { x: number; y: number }
export interface AnnotationStroke { id: string; tool: AnnotationTool; color: string; width: number; points: AnnotationPoint[] }
export interface BodyAnnotation { strokes: AnnotationStroke[]; note: string }
export interface BodyMapData { version: 1; findings: RecordedBodyFinding[]; annotation: BodyAnnotation }
export const annotationColors = ['#dc2626', '#2563eb', '#16a34a', '#9333ea', '#171717'];
export const annotationLimits = { strokes: 100, pointsPerStroke: 400, totalPoints: 8000 };
export const emptyBodyMap = (): BodyMapData => ({ version: 1, findings: [], annotation: { strokes: [], note: '' } });
export function findingLocation(finding: Pick<RecordedBodyFinding, 'locationId' | 'mode' | 'view'>) {
    return (finding.mode === 'organs' ? organLocations : bodyLocations(finding.view)).find((location) => location.id === finding.locationId);
}
export function createRecordedFinding(draft: BodyFindingDraft, mode: BodyMode, view: 'front' | 'back', model: BodyModel, id: string, entryMode: ExaminationEntryMode = 'interactive'): { finding?: RecordedBodyFinding; error?: string } {
    const location = findingLocation({ locationId: draft.locationId, mode, view });
    const isOther = draft.locationId === 'other';
    const isGeneral = entryMode === 'quick' && (!draft.locationId || draft.locationId === 'general');
    if ((!location && !isOther && !isGeneral) || (isOther && !normalizeClinicalAnswer(draft.locationLabel)) || (!isGeneral && !physicalExamSystems.some((system) => system === draft.category))) return { error: 'Pilih lokasi dan sistem pemeriksaan.' };
    if (entryMode !== 'quick' && draft.method === 'Lainnya' && !draft.methodDetail?.trim()) return { error: 'Tuliskan metode pemeriksaan lainnya.' };
    if (draft.finding.length > 3000 || draft.locationLabel.length > 150) return { error: 'Lokasi maksimal 150 karakter dan temuan maksimal 3.000 karakter.' };
    const annotationNote = draft.annotationNote?.trim();
    if (annotationNote && annotationNote.length > 2000) return { error: 'Keterangan anotasi maksimal 2.000 karakter.' };
    if (!normalizeClinicalAnswer(draft.finding)) return { error: 'Isi temuan pemeriksaan yang benar-benar dinilai.' };
    if (entryMode !== 'quick' && draft.pain !== '' && !/^([0-9]|10)$/.test(draft.pain)) return { error: 'Skala nyeri harus berupa angka bulat 0 sampai 10.' };
    return { finding: { id, entryMode, locationId: isGeneral ? 'general' : location?.id ?? 'other', ...(isOther ? { locationLabel: draft.locationLabel.trim() } : {}), mode: isGeneral ? 'body' : mode, view: isGeneral || mode === 'organs' ? 'front' : view, model, category: isGeneral ? 'Lainnya' : draft.category, side: isGeneral ? '' : location?.side ?? (entryMode === 'quick' ? '' : draft.side), detail: entryMode === 'quick' ? '' : draft.detail.trim(), method: entryMode === 'quick' ? '' : draft.method, methodDetail: entryMode !== 'quick' && draft.method === 'Lainnya' ? (draft.methodDetail ?? '').trim() : '', symptom: entryMode === 'quick' ? '' : draft.symptom.trim(), finding: draft.finding.trim(), ...(annotationNote ? { annotationNote } : {}), pain: entryMode === 'quick' || draft.pain === '' ? null : Number(draft.pain) } };
}
export function hasBodyMapContent(data?: BodyMapData | null): boolean {
    return Boolean(data?.findings.length || data?.annotation.strokes.length || data?.annotation.note?.trim());
}
export function recordedAnnotationNote(data?: BodyMapData | null): string {
    const note = data?.annotation.note?.trim().replace(/\r\n/g, '\n') ?? '';
    if (!note || !data?.findings.length) return note;

    // Old drafts could leave either a complete copy or only the first lines of a finding.
    const generated = data.findings.map(finding => annotationNarrative({
        ...emptyBodyFinding,
        locationLabel: findingLocation(finding)?.label ?? finding.locationLabel ?? 'Pemeriksaan umum',
        side: finding.side,
        detail: finding.detail,
        symptom: finding.symptom,
        finding: finding.finding,
    }).replace(/\r\n/g, '\n'));
    if (note === generated.join('\n\n')) return '';

    return note.split(/\n[ \t]*\n/).filter(block => {
        if (generated.some(text => text === block || text.startsWith(block + '\n'))) return false;
        return !data.findings.some(finding => finding.detail.trim() && block === `Lokasi pemeriksaan: ${finding.detail.trim()}.`);
    }).join('\n\n');
}

// Older notes contain explicit location labels. Only recover those exact labels;
// free-text symptoms are never used to infer a body selection.
export function legacyBodyFindings(systems: Record<string, string>, model: BodyModel): RecordedBodyFinding[] {
    const findings: RecordedBodyFinding[] = [];
    for (const [category, text] of Object.entries(systems)) {
        if (typeof text !== 'string') continue;
        for (const match of text.matchAll(/(?:^|\n)- \[([^\]]+)\] ([^\n]+)/g)) {
            const [label, ...context] = match[1].split('; ');
            const organ = organLocations.find((location) => location.label === label);
            const view = context.includes('belakang') ? 'back' : 'front';
            const location = organ ?? bodyLocations(view).find((region) => region.label === label);
            if (!location) continue;
            findings.push({ id: 'legacy-' + findings.length, locationId: location.id, mode: organ ? 'organs' : 'body', view, model, category, side: location.side ?? '', detail: '', method: '', symptom: '', finding: match[2], pain: null });
        }
    }
    return findings;
}
export function annotationPoint(clientX: number, clientY: number, bounds: { left: number; top: number; width: number; height: number }): AnnotationPoint {
    return { x: Math.round(Math.max(0, Math.min(592, (clientX - bounds.left) / Math.max(1, bounds.width) * 592)) * 10) / 10, y: Math.round(Math.max(0, Math.min(558, (clientY - bounds.top) / Math.max(1, bounds.height) * 558)) * 10) / 10 };
}
