import { useEffect, useState } from 'react';
import { MapPin, Pencil, Plus, RotateCcw, Wind, ZoomIn } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { ActionButton } from '@/components/ui/action-button';
import { normalizeClinicalAnswer } from '@/lib/clinical-answer';
import { bodyLocations, emptyBodyFinding, hasBodyFindingDraft, organLocations, type BodyFindingDraft, type BodyLocation, type BodyView } from '@/lib/body-examination';
import { HumanBodyModel } from './human-body-model';
import { BodyAnnotationEditor } from './body-annotation';
import { BodyFindingDetails } from './body-examination-record';
import { createRecordedFinding, emptyBodyMap, findingLocation, type BodyMapData, type RecordedBodyFinding } from '@/lib/body-map';

interface Props {
    systems: Record<string, string>;
    onChange: (systems: Record<string, string>) => void;
    bodyMap?: BodyMapData;
    onBodyMapChange?: (data: BodyMapData) => void;
    idPrefix?: string;
    draft: BodyFindingDraft;
    onDraftChange: (draft: BodyFindingDraft) => void;
    onPendingChange?: (pending: boolean) => void;
    errors?: Record<string, string>;
    disabled?: boolean;
    gender?: string | null;
    initialMode?: 'interactive' | 'manual';
    initialCompact?: boolean;
}

export function PhysicalExaminationWorkspace({ systems, onChange, bodyMap = emptyBodyMap(), onBodyMapChange, idPrefix = '', draft, onDraftChange, onPendingChange, errors = {}, disabled = false, gender, initialMode = 'interactive', initialCompact = false }: Props) {
    const [tab, setTab] = useState<'interactive' | 'manual'>(initialMode);
    const [compact, setCompact] = useState(initialCompact);
    const [mode, setMode] = useState<'organs' | 'body'>('organs');
    const [view, setView] = useState<BodyView>('front');
    const [model, setModel] = useState<'male' | 'female'>(gender === 'P' ? 'female' : 'male');
    const [error, setError] = useState('');
    const [expanded, setExpanded] = useState(false);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [legacySystem, setLegacySystem] = useState<string | null>(null);
    const inputId = (key: string) => idPrefix + key;
    const selected = findingLocation({ locationId: draft.locationId, mode, view });
    const preview = createRecordedFinding(draft, mode, view, model, editingId ?? '', compact ? 'quick' : tab);
    const existingFinding = bodyMap.findings.find((finding) => finding.id === editingId);
    const findingChanged = Boolean(preview.finding && (!existingFinding || Object.entries(preview.finding).some(([key, value]) => value !== (key === 'entryMode' ? existingFinding.entryMode ?? 'interactive' : existingFinding[key as keyof RecordedBodyFinding] ?? ''))));
    const pending = editingId ? findingChanged || !preview.finding : hasBodyFindingDraft(draft);
    const canApplyFinding = !disabled && Boolean(onBodyMapChange) && findingChanged && (!editingId || Boolean(existingFinding)) && (Boolean(editingId) || bodyMap.findings.length < 60);
    useEffect(() => { onPendingChange?.(pending); }, [pending, onPendingChange]);
    const oldNotes = Object.entries(systems).filter(([, value]) => normalizeClinicalAnswer(value));
    useEffect(() => { if (!draft.locationId && !hasBodyFindingDraft(draft)) { setEditingId(null); setLegacySystem(null); } }, [draft]);

    function update(field: keyof BodyFindingDraft, value: string): void {
        setError('');
        onDraftChange({ ...draft, [field]: value });
    }
    function resetDraft(): void {
        onDraftChange({ ...emptyBodyFinding }); setEditingId(null); setLegacySystem(null); setError('');
    }
    function changeTab(next: 'interactive' | 'manual'): void {
        if (next === tab) return;
        if (compact || disabled) { setTab(next); return; }
        const apply = () => { resetDraft(); setTab(next); };
        if (pending) confirmAction('Kosongkan isian temuan yang belum ditambahkan dan pindah cara pemeriksaan?', apply, 'Pindah pemeriksaan');
        else apply();
    }
    function changeCompact(next: boolean): void {
        if (disabled || next === compact) return;
        const apply = () => {
            setCompact(next); setError('');
            if (next) onDraftChange({ ...draft, side: selected?.side ?? '', detail: '', method: '', methodDetail: '', symptom: '', pain: '' });
            else if (draft.locationId === 'general') onDraftChange({ ...draft, locationId: '', locationLabel: '' });
        };
        const hasDetails = Boolean(draft.detail || draft.method || draft.methodDetail || draft.symptom || (draft.side && draft.side !== selected?.side) || draft.pain !== '');
        if (next && hasDetails) confirmAction('Gunakan isian ringkas dan kosongkan rincian lokasi, metode, keluhan lokal, serta nyeri pada isian ini? Lokasi pada peta dan teks temuan tetap tersedia.', apply, 'Gunakan isian ringkas');
        else apply();
    }
    function chooseLocation(location: BodyLocation | null, nextMode = mode, nextView = view, toggle = false): void {
        if (disabled) return;
        const next = toggle && location?.id === draft.locationId ? null : location;
        const apply = () => {
            setMode(nextMode); setView(nextView); setError('');
            onDraftChange({ ...draft, locationId: next?.id ?? '', locationLabel: next?.label ?? '', category: next?.category ?? 'Lainnya', side: next?.side ?? '', view: nextView === 'back' ? 'belakang' : 'depan' });
        };
        if (pending && next && draft.locationId && next.id !== draft.locationId) confirmAction('Pindahkan isian temuan ini ke lokasi yang dipilih?', apply, 'Ganti lokasi');
        else apply();
    }
    function locationChanged(id: string): void {
        if (id === 'other') {
            chooseLocation({ id: 'other', label: '', category: 'Lainnya', x: 0, y: 0, width: 0, height: 0 }, mode, view);
            return;
        }
        if (!id) { chooseLocation(null); return; }
        const organ = organLocations.find((item) => item.id === id);
        const nextView = id.startsWith('back-') ? 'back' : 'front';
        const location = organ ?? bodyLocations(nextView).find((item) => item.id === id);
        if (location) chooseLocation(location, organ ? 'organs' : 'body', nextView);
    }
    function applyFinding(): void {
        if (disabled) return;
        if (editingId && !bodyMap.findings.some((finding) => finding.id === editingId)) { setError('Temuan ini sudah tidak tersedia. Salin isian sebelum membuka atau menambahkan temuan lain.'); return; }
        if (!canApplyFinding || !onBodyMapChange || !preview.finding) return;
        const finding = { ...preview.finding, id: editingId ?? crypto.randomUUID() };
        const label = editingId || legacySystem ? 'Perbarui temuan' : 'Tambahkan temuan';
        confirmAction(`${label} ini ke draf pemeriksaan? Simpan formulir setelah semua temuan ditambahkan.`, () => {
            onBodyMapChange({ ...bodyMap, findings: editingId ? bodyMap.findings.map((item) => item.id === editingId ? finding : item) : [...bodyMap.findings, finding] });
            if (legacySystem) { const remaining = { ...systems }; delete remaining[legacySystem]; onChange(remaining); }
            resetDraft();
        }, label, label);
    }
    function editFinding(finding: RecordedBodyFinding): void {
        if (disabled) return;
        const apply = () => {
            const location = findingLocation(finding);
            setCompact(finding.entryMode === 'quick'); setTab(finding.entryMode === 'quick' ? (location ? 'interactive' : tab) : finding.entryMode === 'manual' ? 'manual' : 'interactive'); setMode(finding.mode); setView(finding.view); setModel(finding.model); setEditingId(finding.id); setLegacySystem(null); setError('');
            onDraftChange({ locationId: finding.locationId, locationLabel: location?.label ?? finding.locationLabel ?? '', category: finding.category, view: finding.view === 'back' ? 'belakang' : 'depan', side: finding.side, detail: finding.detail, method: finding.method, methodDetail: finding.methodDetail ?? '', symptom: finding.symptom, finding: finding.finding, pain: finding.pain === null ? '' : String(finding.pain) });
        };
        if (pending) confirmAction('Kosongkan isian yang belum ditambahkan dan buka temuan ini?', apply, 'Buka temuan'); else apply();
    }
    function renderLocationPicker() {
        const option = (location: BodyLocation) => <option key={location.id} value={location.id}>{location.label}</option>;
        return <Field htmlFor={inputId('body-location')} label="Lokasi pemeriksaan">
            <div className={draft.locationId === 'other' ? 'grid gap-2 sm:grid-cols-2' : ''}>
                <Select id={inputId('body-location')} value={draft.locationId} disabled={disabled} onChange={(event) => locationChanged(event.target.value)}>
                    <option value="">Pilih lokasi pemeriksaan</option>
                    {tab === 'interactive' ? (mode === 'organs' ? organLocations : bodyLocations(view)).map(option) : [
                        { label: 'Organ', locations: organLocations },
                        { label: 'Tubuh depan', locations: bodyLocations('front') },
                        { label: 'Tubuh belakang', locations: bodyLocations('back') },
                    ].flatMap((group) => group.locations.map((location) => <option key={location.id} value={location.id}>{group.label} - {location.label}</option>))}
                    <option value="other">Lainnya</option>
                </Select>
                {draft.locationId === 'other' && <Input id={inputId('body-other-location')} aria-label="Lokasi pemeriksaan lainnya" disabled={disabled} maxLength={150} value={draft.locationLabel} onChange={(event) => update('locationLabel', event.target.value)} placeholder="Tuliskan lokasi pemeriksaan" />}
            </div>
        </Field>;
    }
    function renderFindingForm() {
        return <section className="min-w-0 space-y-4 rounded-xl border border-neutral-200 p-4 sm:p-5" aria-label={compact ? 'Input pemeriksaan ringkas' : 'Input temuan lokasi tubuh'}>
            <div className="flex items-center gap-3"><span aria-label={selected?.id === 'lungs' ? 'Sistem Respirasi' : 'Lokasi pemeriksaan'} role="img" className="rounded-xl bg-neutral-100 p-3 text-neutral-700">{selected?.id === 'lungs' ? <Wind className="size-5" /> : <MapPin className="size-5" />}</span><div className="min-w-0"><h3 className="font-semibold text-neutral-950">{selected?.label || draft.locationLabel || (compact ? 'Pemeriksaan fisik' : 'Pilih lokasi pemeriksaan')}</h3><p className="text-sm text-neutral-500">Catat temuan sesuai hasil pemeriksaan.</p></div></div>
            {!compact && renderLocationPicker()}
            {!compact && <>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field htmlFor={inputId('body-side')} label="Sisi pemeriksaan"><Select id={inputId('body-side')} value={draft.side} disabled={disabled || Boolean(selected?.side)} onChange={(event) => update('side', event.target.value)}><option value="">Belum ditentukan</option><option value="kanan">Kanan</option><option value="kiri">Kiri</option><option value="bilateral">Bilateral</option><option value="garis tengah">Garis tengah</option></Select></Field>
                    <Field htmlFor={inputId('body-location-detail')} label="Lokasi rinci"><Input id={inputId('body-location-detail')} maxLength={250} disabled={disabled} value={draft.detail} onChange={(event) => update('detail', event.target.value)} placeholder="Contoh: 2 cm di bawah siku kanan" /></Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field htmlFor={inputId('body-finding-method')} label="Metode pemeriksaan"><Select id={inputId('body-finding-method')} disabled={disabled} value={draft.method} onChange={(event) => { setError(''); onDraftChange({ ...draft, method: event.target.value, methodDetail: '' }); }}><option value="">Pilih metode</option>{['Inspeksi', 'Palpasi', 'Perkusi', 'Auskultasi', 'Rentang gerak', 'Pemeriksaan neurologis', 'Lainnya'].map((method) => <option key={method}>{method}</option>)}</Select></Field>
                    <Field htmlFor={inputId('body-pain')} label="Skala nyeri (0-10)"><Input id={inputId('body-pain')} type="number" min="0" max="10" step="1" disabled={disabled} value={draft.pain} onChange={(event) => update('pain', event.target.value)} placeholder="Jika dinilai" /></Field>
                </div>
                {draft.method === 'Lainnya' && <Field htmlFor={inputId('body-other-method')} label="Metode pemeriksaan lainnya"><Input id={inputId('body-other-method')} maxLength={250} disabled={disabled} value={draft.methodDetail ?? ''} onChange={(event) => update('methodDetail', event.target.value)} placeholder="Tuliskan metode yang digunakan" /></Field>}
                <Field htmlFor={inputId('body-local-complaint')} label="Keluhan lokal pasien"><Input id={inputId('body-local-complaint')} disabled={disabled} maxLength={500} value={draft.symptom} onChange={(event) => update('symptom', event.target.value)} placeholder="Contoh: nyeri saat digerakkan" /></Field>
            </>}
            {!compact && tab === 'manual' && <Field htmlFor={inputId('body-annotation-note')} label="Keterangan anotasi"><Textarea id={inputId('body-annotation-note')} rows={3} maxLength={2000} disabled={disabled} value={bodyMap.annotation.note} onChange={(event) => onBodyMapChange?.({ ...bodyMap, annotation: { ...bodyMap.annotation, note: event.target.value } })} placeholder="Jelaskan tanda pada gambar, sisi tubuh, atau ukuran." /></Field>}
            {compact && tab === 'manual' && bodyMap.annotation.note && <div className="space-y-1 rounded-lg bg-neutral-50 p-3"><p className="text-xs font-medium text-neutral-500">Keterangan anotasi</p><p className="whitespace-pre-wrap break-words text-sm text-neutral-700">{bodyMap.annotation.note}</p></div>}
            <Field htmlFor={inputId('body-objective-finding')} label="Temuan pemeriksaan" error={error || (pending ? preview.error : undefined)}><Textarea id={inputId('body-objective-finding')} disabled={disabled} maxLength={3000} rows={compact ? 5 : 4} value={draft.finding} onChange={(event) => update('finding', event.target.value)} placeholder="Tuliskan hasil pemeriksaan yang dinilai." /></Field>
            <div className="flex flex-wrap items-center justify-end gap-x-4 gap-y-3 border-t border-neutral-200 pt-4">
                {!editingId && bodyMap.findings.length >= 60 && <p role="alert" className="text-sm text-red-600">Maksimal 60 temuan lokasi per pemeriksaan.</p>}
                <Button disabled={disabled || (!pending && !draft.locationId)} type="button" variant="secondary" onClick={() => pending ? confirmAction('Kosongkan isian temuan yang belum ditambahkan?', resetDraft, 'Kosongkan isian') : resetDraft()}><RotateCcw className="size-4" />Kosongkan</Button>
                <label className="flex cursor-pointer items-center gap-2 text-sm font-medium text-neutral-800" htmlFor={inputId('body-compact')}><Checkbox id={inputId('body-compact')} checked={compact} disabled={disabled} onCheckedChange={(checked) => changeCompact(checked === true)} />Isian ringkas</label>
                <Button disabled={!canApplyFinding} type="button" onClick={applyFinding}><Plus className="size-4" />{editingId || legacySystem ? 'Perbarui temuan' : 'Tambahkan temuan'}</Button>
            </div>
        </section>;
    }

    return <Card>
        <CardHeader><CardTitle>Diagram Anatomi & Lokasi Keluhan Tubuh (Body Map)</CardTitle><CardDescription>Pilih cara pencatatan pemeriksaan sesuai kebutuhan.</CardDescription></CardHeader>
        <CardContent className="space-y-5">
            {errors.body_map && <p role="alert" className="text-sm text-red-600">{errors.body_map}</p>}
            <Tabs value={tab} onValueChange={(value) => changeTab(value as 'interactive' | 'manual')}>
                <TabsList aria-label="Cara pemeriksaan fisik"><TabsTrigger value="interactive"><MapPin className="size-4" />Peta interaktif</TabsTrigger><TabsTrigger value="manual"><Pencil className="size-4" />Gambar & anotasi</TabsTrigger></TabsList>
                {pending && <p role="status" className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">Temuan belum ditambahkan. Tambahkan atau kosongkan isian sebelum menyimpan pemeriksaan.</p>}
                <TabsContent value="interactive" className="pt-5">
                    {tab === 'interactive' && <div className="grid min-w-0 items-start gap-5 xl:grid-cols-2">
                        <section className="min-w-0 space-y-4 rounded-xl border border-neutral-200 bg-neutral-50/50 p-4 sm:p-5" aria-label="Diagram anatomi tubuh">
                            <div className="flex flex-wrap items-center justify-between gap-2"><div className="flex gap-2" role="group" aria-label="Tampilan anatomi"><Button type="button" size="sm" disabled={disabled} variant={mode === 'organs' ? 'default' : 'secondary'} aria-pressed={mode === 'organs'} onClick={() => { if (mode !== 'organs') chooseLocation(null, 'organs', 'front'); }}>Organ</Button><Button type="button" size="sm" disabled={disabled} variant={mode === 'body' ? 'default' : 'secondary'} aria-pressed={mode === 'body'} onClick={() => { if (mode !== 'body') chooseLocation(null, 'body', view); }}>Seluruh tubuh</Button></div><Button type="button" size="sm" variant="ghost" disabled={!draft.locationId || disabled} onClick={() => chooseLocation(null)}>Lepas pilihan</Button></div>
                            <div className="grid grid-cols-2 gap-3"><Field htmlFor={inputId('body-view')} label="Sudut pandang"><Select id={inputId('body-view')} value={view} disabled={disabled || mode === 'organs'} onChange={(event) => chooseLocation(null, mode, event.target.value as BodyView)}><option value="front">Depan</option><option value="back">Belakang</option></Select></Field><Field htmlFor={inputId('body-model')} label="Model ilustrasi"><Select id={inputId('body-model')} disabled={disabled} value={model} onChange={(event) => setModel(event.target.value as 'male' | 'female')}><option value="male">Laki-laki</option><option value="female">Perempuan</option></Select></Field></div>
                            <HumanBodyModel mode={mode} model={model} view={view} selectedId={draft.locationId} disabled={disabled} onSelect={(location) => chooseLocation(location, mode, view, true)} />
                            <p className="text-xs leading-5 text-neutral-500">Kanan/kiri mengikuti sisi pasien. {compact ? 'Lokasi dapat dipilih bila diperlukan. ' : ''}Klik kembali lokasi terpilih untuk melepas pilihan.</p>
                        </section>
                        {renderFindingForm()}
                    </div>}
                </TabsContent>
                <TabsContent value="manual" className="pt-5">
                    {tab === 'manual' && <div className="grid min-w-0 items-start gap-5 xl:grid-cols-2">
                        <section className="min-w-0 space-y-4 rounded-xl border border-neutral-200 p-4 sm:p-5" aria-label="Gambar dan alat anotasi">
                            <div className="flex items-start justify-between gap-3"><div className="flex items-center gap-3"><span className="rounded-xl bg-neutral-100 p-3 text-neutral-700"><Pencil className="size-5" /></span><div className="min-w-0"><h3 className="font-semibold text-neutral-950">Gambar tubuh</h3><p className="text-sm text-neutral-500">Tandai lokasi temuan pada gambar.</p></div></div><Button size="icon" type="button" variant="secondary" title="Perbesar gambar" aria-label="Perbesar gambar" onClick={() => setExpanded(true)}><ZoomIn className="size-4" /></Button></div>
                            <BodyAnnotationEditor value={bodyMap.annotation} showNote={false} disabled={disabled} onChange={(annotation) => onBodyMapChange?.({ ...bodyMap, annotation })} />
                        </section>
                        {renderFindingForm()}
                    </div>}
                </TabsContent>
            </Tabs>
            {bodyMap.findings.length > 0 && <section className="space-y-3 border-t border-neutral-200 pt-5" aria-label="Temuan lokasi tercatat"><h3 className="text-sm font-semibold text-neutral-950">Temuan pemeriksaan ({bodyMap.findings.length})</h3><div className="grid min-w-0 gap-3 lg:grid-cols-2">{bodyMap.findings.map((finding) => <article className="min-w-0 space-y-3 rounded-xl border border-neutral-200 p-4" key={finding.id}><BodyFindingDetails finding={finding} /><div className="flex items-center justify-between gap-2"><Badge>{finding.entryMode === 'quick' ? 'Isian ringkas' : finding.entryMode === 'manual' ? 'Gambar & anotasi' : 'Peta interaktif'}</Badge>{!disabled && <div className="flex gap-2"><ActionButton action="edit" label="Ubah temuan lokasi" type="button" onClick={() => editFinding(finding)} /><ActionButton action="delete" label="Hapus temuan lokasi" type="button" onClick={() => confirmAction('Hapus temuan pemeriksaan ini?', () => { onBodyMapChange?.({ ...bodyMap, findings: bodyMap.findings.filter((item) => item.id !== finding.id) }); if (editingId === finding.id) resetDraft(); })} /></div>}</div></article>)}</div></section>}
            {oldNotes.length > 0 && <section className="space-y-3 border-t border-neutral-200 pt-5" aria-label="Catatan pemeriksaan sebelumnya"><h3 className="text-sm font-semibold text-neutral-950">Catatan pemeriksaan sebelumnya</h3><div className="grid gap-3 lg:grid-cols-2">{oldNotes.map(([system, value]) => <article className="min-w-0 rounded-xl border border-neutral-200 p-4" key={system}><div className="flex items-center justify-between gap-2"><h4 className="text-sm font-semibold text-neutral-900">{system}</h4>{!disabled && <ActionButton action="edit" label={'Ubah temuan ' + system} onClick={() => {
                const apply = () => { setCompact(false); setTab('manual'); setLegacySystem(system); setEditingId(null); setMode('body'); setError(''); onDraftChange({ ...emptyBodyFinding, locationId: 'other', locationLabel: system, category: ['Kepala dan leher', 'Thorax', 'Abdomen', 'Ekstremitas', 'Neurologis', 'Kulit'].includes(system) ? system : 'Lainnya', finding: value }); };
                if (pending) confirmAction('Kosongkan isian yang belum ditambahkan dan buka catatan ini?', apply, 'Buka catatan'); else apply();
            }} />}</div><p className="mt-2 whitespace-pre-wrap break-words text-sm text-neutral-700">{value}</p></article>)}</div></section>}
            {Object.entries(errors).filter(([key]) => key.startsWith('pemeriksaan_fisik_terstruktur.')).map(([key, message]) => <p role="alert" className="text-sm text-red-600" key={key}>{message}</p>)}
            <Dialog open={expanded} onOpenChange={setExpanded}><DialogContent className="max-w-4xl"><DialogHeader><DialogTitle>Anotasi lokasi tubuh</DialogTitle><DialogDescription>Goresan dan keterangan disimpan bersama pemeriksaan fisik.</DialogDescription></DialogHeader><div className="max-h-[75vh] overflow-auto p-1"><BodyAnnotationEditor value={bodyMap.annotation} showNote={false} disabled={disabled} onChange={(annotation) => onBodyMapChange?.({ ...bodyMap, annotation })} /></div></DialogContent></Dialog>
        </CardContent>
    </Card>;
}
