import { useState, type ReactNode } from 'react';
import { MapPin, Pencil } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { BodyAnnotationView } from './body-annotation';
import { HumanBodyModel } from './human-body-model';
import { examinationAnnotationNote, findingLocation, hasBodyMapContent, legacyBodyFindings, recordedAnnotationNote, type BodyMapData, type RecordedBodyFinding } from '@/lib/body-map';

export function BodyFindingDetails({ finding, annotationInPrintOnly = false, hideGeneratedAnnotation = false, hideLocation = false }: { finding: RecordedBodyFinding; annotationInPrintOnly?: boolean; hideGeneratedAnnotation?: boolean; hideLocation?: boolean }) {
    const location = findingLocation(finding);
    const duplicateAnnotation = finding.annotationNote === examinationAnnotationNote(location?.label ?? finding.locationLabel ?? '', finding.finding);
    return <div className="min-w-0 space-y-2 text-sm [overflow-wrap:anywhere]">
        <div className="flex flex-wrap items-center gap-2">{!hideLocation && <span className="font-semibold text-neutral-900">{finding.locationId === 'general' ? 'Pemeriksaan fisik' : location?.label ?? finding.locationLabel ?? 'Lokasi tubuh'}</span>}{finding.locationId !== 'general' && <Badge>{finding.category}</Badge>}{finding.side && !location?.label.toLowerCase().includes(finding.side) && <Badge>{finding.side}</Badge>}</div>
        {finding.detail && <p className="whitespace-pre-wrap break-words text-neutral-600">Lokasi rinci: {finding.detail}</p>}
        {(finding.method || finding.pain !== null) && <p className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-500">{finding.method && <span>{finding.method}{finding.methodDetail ? ': ' + finding.methodDetail : ''}</span>}{finding.pain !== null && <span>Nyeri: {finding.pain}/10</span>}</p>}
        {finding.symptom && <p className="whitespace-pre-wrap break-words text-neutral-700"><strong>Keluhan lokal:</strong> {finding.symptom}</p>}
        <p className="whitespace-pre-wrap break-words [overflow-wrap:anywhere] text-neutral-800">{!finding.id.startsWith('legacy-') && <strong>Temuan: </strong>}{finding.finding}</p>
        {(!hideGeneratedAnnotation || !duplicateAnnotation) && finding.annotationNote?.trim() && <p className={`whitespace-pre-wrap break-words text-neutral-700 ${annotationInPrintOnly ? 'hidden print:block' : ''}`}><strong>{hideGeneratedAnnotation ? 'Catatan gambar' : 'Keterangan anotasi'}:</strong> {finding.annotationNote}</p>}
    </div>;
}

const summaryColumns = 'min-w-0 items-start gap-4 md:grid-cols-2';

export function BodyExaminationRecord({ data, systems = {}, gender, title, diagnosisSummary = false }: { diagnosisSummary?: boolean; data?: BodyMapData | null; systems?: Record<string, string>; gender?: string | null; title?: string }) {
    const findings = [...(data?.findings ?? []), ...legacyBodyFindings(systems, gender === 'P' ? 'female' : 'male')];
    const [summaryMode, setSummaryMode] = useState('interactive');
    const [activeGroup, setActiveGroup] = useState('');
    const [selectedId, setSelectedId] = useState('');
    if (!findings.length && !hasBodyMapContent(data)) return null;
    const groups = new Map<string, RecordedBodyFinding[]>();
    const annotationNote = recordedAnnotationNote(data);
    const strokes = data?.annotation.strokes ?? [];
    const manualFindings = findings.filter(finding => finding.entryMode === 'manual');
    const hasManual = Boolean(strokes.length || annotationNote || manualFindings.length);
    const hasInteractive = findings.some(finding => finding.entryMode !== 'manual');
    const activeMode = hasInteractive && hasManual ? summaryMode : hasManual ? 'manual' : 'interactive';
    const textFindings: RecordedBodyFinding[] = [];
    for (const finding of findings) {
        if (diagnosisSummary && finding.entryMode === 'manual') continue;
        if (!findingLocation(finding) || (finding.entryMode === 'manual' && strokes.length > 0)) {
            textFindings.push(finding);
            continue;
        }
        const key = finding.mode + '-' + finding.view + '-' + finding.model;
        groups.set(key, [...(groups.get(key) ?? []), finding]);
    }
    const heading = title ?? (groups.size || strokes.length ? 'Peta tubuh & temuan pemeriksaan' : 'Temuan pemeriksaan');
    const hasText = Boolean(textFindings.length || annotationNote);
    const activeKey = groups.has(activeGroup) ? activeGroup : groups.keys().next().value;
    const label = (finding: RecordedBodyFinding) => (finding.mode === 'organs' ? 'Organ' : finding.view === 'front' ? 'Tubuh depan' : 'Tubuh belakang') + ' - ' + (finding.model === 'female' ? 'perempuan' : 'laki-laki');
    const interactiveMaps = <>
        {groups.size > 1 && <div className="flex flex-wrap gap-2 print:hidden" role="group" aria-label="Tampilan peta tubuh tercatat">{[...groups].map(([key, items]) => <Button key={key} type="button" size="sm" variant={activeKey === key ? 'default' : 'secondary'} aria-pressed={activeKey === key} onClick={() => setActiveGroup(key)}>{label(items[0])}</Button>)}</div>}
        {[...groups].map(([key, items]) => {
            const selected = items.find((item) => item.id === selectedId) ?? items[0];
            const markerLocations = [...new Set(items.map((item) => item.locationId))];
            const markers = markerLocations.map((locationId) => ({ locationId, label: items.flatMap((item, index) => item.locationId === locationId ? [String(index + 1)] : []).join(',') }));
            return <div key={key} className={(activeKey === key ? 'grid' : 'hidden') + ' body-record-group print:grid ' + (diagnosisSummary ? summaryColumns : 'min-w-0 gap-4 md:grid-cols-[minmax(12rem,0.75fr)_minmax(0,1.75fr)]')}>
                <div className="min-w-0 rounded-xl bg-neutral-50 p-3"><p className="mb-2 text-center text-xs font-medium text-neutral-600">{label(items[0])}</p><div className={diagnosisSummary ? 'mx-auto max-w-[175px]' : 'mx-auto max-w-[245px]'}><HumanBodyModel mode={selected.mode} view={selected.view} model={selected.model} selectedId={selected.locationId} highlightedIds={items.map((item) => item.locationId)} markers={markers} onSelect={(location) => { const match = items.find((item) => item.locationId === location.id); if (match && selected.locationId !== location.id) setSelectedId(match.id); }} /></div><p className="text-center text-xs text-neutral-500">Kanan/kiri mengikuti sisi pasien. Nomor pada tubuh sesuai nomor temuan.</p></div>
                <div className="min-w-0 space-y-3">{items.map((finding, index) => <BodyFindingCard
                    key={finding.id}
                    finding={finding}
                    label={`Temuan ${index + 1}`}
                    annotationInPrintOnly={!diagnosisSummary}
                    hideGeneratedAnnotation={diagnosisSummary}
                    action={<Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-6 shrink-0 aria-pressed:bg-neutral-100 print:hidden"
                        title="Tampilkan lokasi pada peta"
                        aria-label={'Tampilkan lokasi ' + (findingLocation(finding)?.label ?? '')}
                        aria-pressed={selected.id === finding.id}
                        onClick={() => setSelectedId(finding.id)}
                    ><MapPin className="size-4" /></Button>}
                />)}</div>
                {!diagnosisSummary && <div className="rounded-lg border border-neutral-200 p-3 md:col-span-2 print:hidden" aria-live="polite">
                    <h4 className="text-xs font-medium">Keterangan anotasi</h4>
                    <p className="mt-2 whitespace-pre-wrap break-words text-sm">{selected.annotationNote?.trim() || 'Belum ada keterangan anotasi untuk temuan ini.'}</p>
                </div>}
            </div>;
        })}
    </>;

    if (diagnosisSummary) {
        const panelClass = 'min-w-0 space-y-4';
        const interactiveContent = <>{interactiveMaps}<UnlocatedFindings findings={textFindings} hideGeneratedAnnotation /></>;
        const manualContent = <div className={strokes.length ? 'grid ' + summaryColumns : 'space-y-3'}>
            {strokes.length > 0 && <div className="min-w-0 rounded-xl bg-neutral-50 p-3"><div className="mx-auto max-w-[280px]"><BodyAnnotationView value={{ strokes, note: '' }} /></div></div>}
            <div className="min-w-0 space-y-3 [overflow-wrap:anywhere]">
                <UnlocatedFindings findings={manualFindings} hideGeneratedAnnotation />
                {annotationNote && <p className="whitespace-pre-wrap text-sm text-neutral-700">{annotationNote}</p>}
            </div>
        </div>;
        return <section className="min-w-0 space-y-4 rounded-xl border border-neutral-200 p-4 sm:p-5" aria-label="Temuan pemeriksaan">
            {hasInteractive && hasManual ? <Tabs value={activeMode} onValueChange={setSummaryMode} className="space-y-4">
                <TabsList aria-label="Cara pencatatan temuan pemeriksaan">
                    <TabsTrigger value="interactive"><MapPin className="size-4" />Peta interaktif</TabsTrigger>
                    <TabsTrigger value="manual"><Pencil className="size-4" />Gambar & anotasi</TabsTrigger>
                </TabsList>
                <TabsContent value="interactive" className={panelClass}>{interactiveContent}</TabsContent>
                <TabsContent value="manual" className={panelClass}>{manualContent}</TabsContent>
            </Tabs> : <>
                <h3 className="flex min-h-11 items-center border-b border-neutral-300 text-sm font-semibold">{activeMode === 'manual' && strokes.length ? 'Gambar & anotasi' : heading}</h3>
                <div className={panelClass}>{activeMode === 'manual' ? manualContent : interactiveContent}</div>
            </>}
        </section>;
    }

    return <section className="min-w-0 space-y-4 rounded-xl border border-neutral-200 p-4 sm:p-5" aria-label={heading}>
        <div className="flex flex-wrap items-center justify-between gap-2"><h3 className="flex items-center gap-2 text-sm font-semibold text-neutral-900"><MapPin className="size-4" />{heading}</h3><div className="flex gap-2">{!!findings.length && <Badge>{findings.length} temuan pemeriksaan</Badge>}{!!data?.annotation.strokes.length && <Badge>{data.annotation.strokes.length} anotasi</Badge>}</div></div>
        {interactiveMaps}
        {(textFindings.length > 0 || strokes.length > 0 || annotationNote) && <div className={strokes.length && hasText ? 'grid min-w-0 items-start gap-4 md:grid-cols-[minmax(12rem,0.8fr)_minmax(0,1.2fr)] print:grid-cols-2' : 'space-y-3'}>
            {strokes.length > 0 && <div className="min-w-0 rounded-lg bg-neutral-50 p-2"><h4 className="mb-2 text-xs font-semibold text-neutral-700">Gambar dan anotasi pemeriksaan</h4><div className="mx-auto max-w-[280px]"><BodyAnnotationView value={{ strokes, note: '' }} /></div></div>}
            {hasText && <div className="min-w-0 space-y-3"><UnlocatedFindings findings={textFindings} />{annotationNote && <div className="rounded-lg border border-neutral-200 p-3"><h4 className="text-xs font-medium">Keterangan anotasi</h4>{findings.length > 0 && <p className="mt-1 text-xs text-neutral-500">Catatan untuk seluruh peta.</p>}<p className="mt-2 whitespace-pre-wrap break-words text-sm">{annotationNote}</p></div>}</div>}
        </div>}
    </section>;
}

function UnlocatedFindings({ findings, hideGeneratedAnnotation }: { findings: RecordedBodyFinding[]; hideGeneratedAnnotation?: boolean }) {
    if (!findings.length) return null;

    return <div className="min-w-0 space-y-3">{findings.map((finding) => <BodyFindingCard
        key={finding.id}
        finding={finding}
        label={finding.entryMode === 'quick' ? 'Isian ringkas' : finding.entryMode === 'manual' ? 'Gambar & anotasi' : 'Lokasi lainnya'}
        hideGeneratedAnnotation={hideGeneratedAnnotation}
    />)}</div>;
}

function BodyFindingCard({ label, action, ...details }: Parameters<typeof BodyFindingDetails>[0] & { label: string; action?: ReactNode }) {
    return <article className="min-w-0 space-y-2 rounded-lg border border-neutral-200 p-3 print:break-inside-avoid">
        <div className="flex min-h-6 items-center justify-between gap-2"><Badge>{label}</Badge>{action}</div>
        <BodyFindingDetails {...details} />
    </article>;
}
