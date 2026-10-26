import { useState } from 'react';
import { MapPin } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { BodyAnnotationView } from './body-annotation';
import { HumanBodyModel } from './human-body-model';
import { findingLocation, hasBodyMapContent, legacyBodyFindings, type BodyMapData, type RecordedBodyFinding } from '@/lib/body-map';

export function BodyFindingDetails({ finding }: { finding: RecordedBodyFinding }) {
    const location = findingLocation(finding);
    return <div className="min-w-0 space-y-2 text-sm">
        <div className="flex flex-wrap items-center gap-2"><span className="font-semibold text-neutral-900">{finding.locationId === 'general' ? 'Pemeriksaan fisik' : location?.label ?? finding.locationLabel ?? 'Lokasi tubuh'}</span>{finding.locationId !== 'general' && <Badge>{finding.category}</Badge>}{finding.side && !location?.label.toLowerCase().includes(finding.side) && <Badge>{finding.side}</Badge>}</div>
        {finding.detail && <p className="whitespace-pre-wrap break-words text-neutral-600">Lokasi rinci: {finding.detail}</p>}
        {(finding.method || finding.pain !== null) && <p className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-500">{finding.method && <span>{finding.method}{finding.methodDetail ? ': ' + finding.methodDetail : ''}</span>}{finding.pain !== null && <span>Nyeri: {finding.pain}/10</span>}</p>}
        {finding.symptom && <p className="whitespace-pre-wrap break-words text-neutral-700"><strong>Keluhan lokal:</strong> {finding.symptom}</p>}
        <p className="whitespace-pre-wrap break-words [overflow-wrap:anywhere] text-neutral-800">{!finding.id.startsWith('legacy-') && <strong>Temuan: </strong>}{finding.finding}</p>
    </div>;
}

export function BodyExaminationRecord({ data, systems = {}, gender, title = 'Peta tubuh & temuan pemeriksaan' }: { data?: BodyMapData | null; systems?: Record<string, string>; gender?: string | null; title?: string }) {
    const findings = [...(data?.findings ?? []), ...legacyBodyFindings(systems, gender === 'P' ? 'female' : 'male')];
    const [activeGroup, setActiveGroup] = useState('');
    const [selectedId, setSelectedId] = useState('');
    if (!findings.length && !hasBodyMapContent(data)) return null;
    const groups = new Map<string, RecordedBodyFinding[]>();
    const unlocated = findings.filter((finding) => !findingLocation(finding));
    for (const finding of findings.filter((finding) => findingLocation(finding))) {
        const key = finding.mode + '-' + finding.view + '-' + finding.model;
        groups.set(key, [...(groups.get(key) ?? []), finding]);
    }
    const activeKey = groups.has(activeGroup) ? activeGroup : groups.keys().next().value;
    const label = (finding: RecordedBodyFinding) => (finding.mode === 'organs' ? 'Organ' : finding.view === 'front' ? 'Tubuh depan' : 'Tubuh belakang') + ' - ' + (finding.model === 'female' ? 'perempuan' : 'laki-laki');
    return <section className="min-w-0 space-y-4 rounded-xl border border-neutral-200 p-4 sm:p-5" aria-label={title}>
        <div className="flex flex-wrap items-center justify-between gap-2"><h3 className="flex items-center gap-2 text-sm font-semibold text-neutral-900"><MapPin className="size-4" />{title}</h3><div className="flex gap-2">{!!findings.length && <Badge>{findings.length} temuan pemeriksaan</Badge>}{!!data?.annotation.strokes.length && <Badge>{data.annotation.strokes.length} anotasi</Badge>}</div></div>
        {groups.size > 1 && <div className="flex flex-wrap gap-2 print:hidden" role="group" aria-label="Tampilan peta tubuh tercatat">{[...groups].map(([key, items]) => <Button key={key} type="button" size="sm" variant={activeKey === key ? 'default' : 'secondary'} aria-pressed={activeKey === key} onClick={() => setActiveGroup(key)}>{label(items[0])}</Button>)}</div>}
        {[...groups].map(([key, items]) => {
            const selected = items.find((item) => item.id === selectedId) ?? items[0];
            return <div key={key} className={(activeKey === key ? 'grid' : 'hidden') + ' min-w-0 gap-4 md:grid-cols-[minmax(12rem,0.75fr)_minmax(0,1.75fr)] print:grid print:break-inside-avoid'}>
                <div className="min-w-0 rounded-xl bg-neutral-50 p-3"><p className="mb-2 text-center text-xs font-medium text-neutral-600">{label(items[0])}</p><div className="mx-auto max-w-[245px]"><HumanBodyModel mode={selected.mode} view={selected.view} model={selected.model} selectedId={selected.locationId} highlightedIds={items.map((item) => item.locationId)} onSelect={(location) => { const match = items.find((item) => item.locationId === location.id); if (match) setSelectedId(match.id); }} /></div><p className="text-center text-xs text-neutral-500">Kanan/kiri mengikuti sisi pasien.</p></div>
                <div className="min-w-0 space-y-3">{items.map((finding) => <article key={finding.id} className={'min-w-0 rounded-xl border p-4 print:break-inside-avoid ' + (selected.id === finding.id ? 'border-neutral-400 bg-neutral-50' : 'border-neutral-200')}><div className="mb-3 flex items-center justify-between gap-2"><span className="text-xs text-neutral-500">{finding.entryMode === 'quick' ? 'Isian ringkas' : finding.entryMode === 'manual' ? 'Gambar & anotasi' : finding.view === 'back' ? 'Tampak belakang' : 'Tampak depan'}</span><Button type="button" size="icon" variant="ghost" className="print:hidden" title="Tampilkan lokasi pada peta" aria-label={'Tampilkan lokasi ' + (findingLocation(finding)?.label ?? '')} aria-pressed={selected.id === finding.id} onClick={() => setSelectedId(finding.id)}><MapPin className="size-4" /></Button></div><BodyFindingDetails finding={finding} /></article>)}</div>
            </div>;
        })}
        {unlocated.length > 0 && <div className="grid min-w-0 gap-3 md:grid-cols-2">{unlocated.map((finding) => <article key={finding.id} className="min-w-0 space-y-3 rounded-xl border border-neutral-200 p-4 print:break-inside-avoid"><Badge>{finding.entryMode === 'quick' ? 'Isian ringkas' : finding.entryMode === 'manual' ? 'Gambar & anotasi' : 'Lokasi lainnya'}</Badge><BodyFindingDetails finding={finding} /></article>)}</div>}
        {data && (data.annotation.strokes.length > 0 || data.annotation.note) && <div className={(findings.length ? 'border-t border-neutral-200 pt-4 ' : '') + 'space-y-3 print:break-inside-avoid'}><h4 className="text-sm font-semibold text-neutral-900">Anotasi pemeriksaan tubuh</h4><div className="mx-auto max-w-2xl"><BodyAnnotationView value={data.annotation} /></div></div>}
    </section>;
}
