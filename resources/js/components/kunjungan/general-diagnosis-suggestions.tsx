import { describeGeneralFinding, generalFindingSearch, generalFindingSearches } from '@/lib/general-diagnosis';
import { useState, type ReactNode } from 'react';
import { ClipboardPlus, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { findingLocation, type BodyMapData } from '@/lib/body-map';
import { normalizeClinicalAnswer } from '@/lib/clinical-answer';

interface Props {
    bodyMap?: BodyMapData | null;
    physicalExam?: string | null;
    physicalSystems?: Record<string, string>;
    disabled: boolean;
    adult?: boolean;
    codeSystem?: string;
    headerAction?: ReactNode;
    onSearch: (term: string, source: string) => void;
}

export function generalDiagnosisSources({
    bodyMap,
    physicalExam,
    physicalSystems = {},
}: Pick<Props, 'bodyMap' | 'physicalExam' | 'physicalSystems'>) {
    return [
        ...bodyMap?.findings.map(finding => ({
            id: 'map-' + finding.id,
            label: findingLocation(finding)?.label ?? finding.locationLabel ?? 'Pemeriksaan fisik',
            value: finding.finding,
            context: describeGeneralFinding(finding),
        })) ?? [],
        ...Object.entries(physicalSystems).map(([label, value]) => ({ id: 'system-' + label, label, value })),
        { id: 'summary', label: 'Ringkasan pemeriksaan fisik', value: physicalExam },
    ].flatMap(finding => {
        const value = normalizeClinicalAnswer(finding.value);
        return value ? [{ ...finding, value }] : [];
    });
}

export function GeneralDiagnosisSuggestions({ bodyMap, physicalExam, physicalSystems = {}, disabled, adult, codeSystem, headerAction, onSearch }: Props) {
    const findings = generalDiagnosisSources({ bodyMap, physicalExam, physicalSystems });
    const [selection, setSelection] = useState<{ id: string; term: string } | null>(null);
    const selected = findings.find(finding => finding.id === selection?.id);
    const context = selected && ('context' in selected ? selected.context : `${selected.label}: ${selected.value}`);
    const term = selected ? selection?.term ?? '' : '';

    return <section aria-labelledby="general-diagnosis-suggestions-title" className="min-w-0 space-y-3 rounded-xl border border-neutral-200 p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <h4 id="general-diagnosis-suggestions-title" className="flex items-center gap-2 text-sm font-semibold"><ClipboardPlus aria-hidden="true" className="size-4 shrink-0" />Usulan kode berdasarkan temuan</h4>
            {headerAction}
        </div>
        {findings.length === 0 ? <p className="text-sm text-neutral-600">Simpan temuan pemeriksaan fisik untuk memulai pencarian dari temuan.</p> : <>
            <p className="text-sm text-neutral-600">Pilih temuan tersimpan, lalu gunakan istilah yang sesuai dengan hasil pemeriksaan. Tinjau konteks dan hasil katalog sebelum memilih kode.</p>
            <Field htmlFor="general-diagnosis-source" label="Temuan pemeriksaan">
                <Select id="general-diagnosis-source" value={selected?.id ?? ''} disabled={disabled} onChange={event => {
                    const finding = findings.find(item => item.id === event.target.value);
                    setSelection(finding ? { id: finding.id, term: generalFindingSearch(finding.value) } : null);
                }}>
                    <option value="">Pilih temuan tersimpan</option>
                    {findings.map(finding => <option key={finding.id} value={finding.id}>{finding.label} · {finding.value.replace(/\s+/g, ' ').slice(0, 100)}</option>)}
                </Select>
            </Field>
            {selected && <>
                <p className="whitespace-pre-wrap rounded-lg bg-neutral-50 p-3 text-sm [overflow-wrap:anywhere]">{context}</p>
                <div className="flex flex-wrap gap-2">{generalFindingSearches(selected.value).map(query => <Button key={query} type="button" size="sm" variant="secondary" disabled={disabled} onClick={() => setSelection({ id: selected.id, term: query })}>{query}</Button>)}</div>
                <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <Field className="min-w-0" htmlFor="general-diagnosis-term" label="Istilah pencarian kode"><Input id="general-diagnosis-term" maxLength={100} value={term} disabled={disabled} onChange={event => setSelection({ id: selected.id, term: event.target.value })} /></Field>
                    <Button className="h-10" type="button" variant="secondary" disabled={disabled || term.trim().length < 2} onClick={() => onSearch(term.trim(), context ?? '')}><Search className="size-4" />Cari usulan kode</Button>
                </div>
            </>}
        </>}
        {adult && codeSystem === 'icd10_cm' && <div className="space-y-2 border-t border-neutral-200 pt-3">
            <p className="text-sm font-semibold">Pemeriksaan kesehatan umum dewasa</p>
            <p className="text-sm text-neutral-600">Gunakan bila tujuan kunjungan adalah pemeriksaan rutin. Pilih sesuai ada atau tidaknya temuan abnormal.</p>
            <div className="flex flex-wrap gap-2">
                <Button type="button" size="sm" variant="secondary" disabled={disabled} onClick={() => onSearch('Z00.00', physicalExam?.trim() ?? '')}>Z00.00 · Tanpa temuan abnormal</Button>
                <Button type="button" size="sm" variant="secondary" disabled={disabled} onClick={() => onSearch('Z00.01', physicalExam?.trim() ?? '')}>Z00.01 · Dengan temuan abnormal</Button>
            </div>
        </div>}
    </section>;
}
