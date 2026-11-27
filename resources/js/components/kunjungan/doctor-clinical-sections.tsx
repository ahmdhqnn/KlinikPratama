import { ChevronDown } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ChoiceGroup } from '@/components/kunjungan/assessment-choice-group';
import type {
    DoctorComplaintDetails,
    DoctorDentalPainDetails,
    DoctorMucosaObservation,
} from '@/lib/clinical-workflow';

const progressionOptions: Array<[string, string]> = [
    ['membaik', 'Membaik'],
    ['menetap', 'Menetap'],
    ['memburuk', 'Memburuk'],
    ['belum_diketahui', 'Belum diketahui'],
];

const dentalTriggerOptions: Array<[string, string]> = [
    ['dingin', 'Dingin'],
    ['manis', 'Manis'],
    ['mengunyah', 'Mengunyah'],
    ['panas', 'Panas'],
    ['spontan', 'Spontan'],
    ['lainnya', 'Lainnya'],
    ['tidak_ada', 'Tidak ada pemicu'],
];

const mucosaSites = [
    ['lidah', 'Lidah'],
    ['pipi', 'Pipi'],
    ['bibir', 'Bibir'],
    ['palatum', 'Palatum'],
    ['gusi', 'Gusi'],
] as const;

type MucosaSite = typeof mucosaSites[number][0];

const mucosaOptions: Array<[string, string]> = [
    ['normal', 'Normal'],
    ['abnormal', 'Abnormal'],
    ['belum_dinilai', 'Belum dapat dinilai'],
];

const disclosurePill = (
    <span className={buttonVariants({ variant: 'secondary', size: 'sm', className: 'shrink-0' })}>
        <span className="group-open/disclosure:hidden">Buka</span>
        <span className="hidden group-open/disclosure:inline">Tutup</span>
        <ChevronDown aria-hidden="true" className="size-4 transition-transform group-open/disclosure:rotate-180" />
    </span>
);

export function countDoctorAssessmentFields(record: Record<string, unknown> | undefined): number {
    if (!record) return 0;

    return Object.entries(record).filter(([, value]) => {
        if (value === null || value === undefined || value === '') return false;
        if (Array.isArray(value)) return value.length > 0;
        if (typeof value === 'object') {
            return Object.values(value as Record<string, unknown>).some((nested) => Boolean(nested && String(nested).trim()));
        }

        return String(value).trim().length > 0;
    }).length;
}

function NurseReferenceBlock({ items }: { items?: Array<[string, string]> }) {
    if (!items || items.length === 0) return null;

    return (
        <div className="space-y-2 rounded-lg border border-neutral-200 bg-neutral-50/80 p-3.5">
            <p className="text-xs font-semibold text-neutral-700">Acuan dari pengkajian awal perawat</p>
            <dl className="grid gap-x-4 gap-y-2 sm:grid-cols-2">
                {items.map(([label, val]) => (
                    <div className="min-w-0" key={label}>
                        <dt className="text-[11px] font-medium text-neutral-500">{label}</dt>
                        <dd className="whitespace-pre-wrap break-words text-xs text-neutral-800">{val}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

export function NurseReferenceCallout({
    title,
    description,
    items,
}: {
    title: string;
    description?: string;
    items: Array<[string, string]>;
}) {
    if (items.length === 0) return null;

    return (
        <section
            aria-label={title}
            className="space-y-3 rounded-xl border border-neutral-200 bg-neutral-50/80 p-4"
        >
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <h4 className="text-sm font-semibold text-neutral-900">{title}</h4>
                    <Badge variant="default">Acuan perawat</Badge>
                </div>
                {description && <p className="mt-0.5 text-xs text-neutral-500">{description}</p>}
            </div>
            <dl className="grid gap-x-5 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-3">
                {items.map(([label, val]) => (
                    <div className="min-w-0" key={label}>
                        <dt className="text-xs font-medium text-neutral-500">{label}</dt>
                        <dd className="mt-0.5 whitespace-pre-wrap break-words text-sm text-neutral-800">{val}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

export function DoctorComplaintDetailsSection({
    value,
    onChange,
    errors = {},
    disabled = false,
    defaultOpen = false,
    nurseShared = false,
    nurseReference,
    idPrefix = 'doctor-',
}: {
    value: DoctorComplaintDetails;
    onChange: (next: DoctorComplaintDetails) => void;
    errors?: Record<string, string>;
    disabled?: boolean;
    defaultOpen?: boolean;
    nurseShared?: boolean;
    nurseReference?: Array<[string, string]>;
    idPrefix?: string;
}) {
    const filledCount = countDoctorAssessmentFields(value as Record<string, unknown>);
    const hasError = Object.keys(errors).some((key) => key.startsWith('clinical_assessment.complaint_details'));
    const update = <K extends keyof DoctorComplaintDetails>(key: K, nextValue: DoctorComplaintDetails[K]) => {
        onChange({ ...value, [key]: nextValue });
    };

    return (
        <details
            className="group/disclosure min-w-0 overflow-hidden rounded-xl border border-neutral-200 bg-neutral-50/60"
            open={defaultOpen || filledCount > 0 || hasError}
        >
            <summary className="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] items-start gap-3 p-4 [&::-webkit-details-marker]:hidden">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-sm font-semibold text-neutral-900">Rincian keluhan & skala nyeri</h4>
                        {filledCount > 0 ? <Badge variant="complete">{filledCount} terisi</Badge> : <Badge variant="default">{nurseShared ? 'Perawat & Dokter' : 'Diisi dokter'}</Badge>}
                    </div>
                    <p className="text-xs leading-5 text-neutral-500">
                        {nurseShared
                            ? 'Lengkapi atau klarifikasi lokasi, karakteristik, pemicu, pereda, perkembangan keluhan, dan skala nyeri pasien.'
                            : 'Catat lokasi, karakteristik, pola, faktor pemicu/pereda, perkembangan keluhan, dan skala nyeri secara terstruktur.'}
                    </p>
                </div>
                {disclosurePill}
            </summary>
            <div className="space-y-4 border-t border-neutral-200 bg-surface p-4 sm:p-5">
                <NurseReferenceBlock items={nurseReference} />
                <div className="grid gap-4 md:grid-cols-2">
                    <Field error={errors['clinical_assessment.complaint_details.location']} htmlFor={`${idPrefix}complaint-location`} label="Lokasi keluhan">
                        <Input disabled={disabled} id={`${idPrefix}complaint-location`} maxLength={2000} onChange={(e) => update('location', e.target.value)} placeholder="Contoh: epigastrium, dada kiri, tenggorokan" value={value.location ?? ''} />
                    </Field>
                    <Field error={errors['clinical_assessment.complaint_details.character']} htmlFor={`${idPrefix}complaint-character`} label="Karakteristik keluhan">
                        <Input disabled={disabled} id={`${idPrefix}complaint-character`} maxLength={2000} onChange={(e) => update('character', e.target.value)} placeholder="Contoh: nyeri tumpul, menusuk, berdenyut, batuk berdahak" value={value.character ?? ''} />
                    </Field>
                    <Field error={errors['clinical_assessment.complaint_details.pattern']} htmlFor={`${idPrefix}complaint-pattern`} label="Pola / frekuensi">
                        <Input disabled={disabled} id={`${idPrefix}complaint-pattern`} maxLength={2000} onChange={(e) => update('pattern', e.target.value)} placeholder="Contoh: hilang timbul, memberat malam hari" value={value.pattern ?? ''} />
                    </Field>
                    <Field error={errors['clinical_assessment.complaint_details.pain_scale']} htmlFor={`${idPrefix}complaint-pain-scale`} label="Skala nyeri (0–10)">
                        <Select disabled={disabled} id={`${idPrefix}complaint-pain-scale`} onChange={(e) => update('pain_scale', e.target.value === '' ? null : Number(e.target.value))} value={value.pain_scale != null ? String(value.pain_scale) : ''}>
                            <option value="">Belum dikaji</option>
                            {Array.from({ length: 11 }, (_, score) => (
                                <option key={score} value={score}>{score}{score === 0 ? ' · Tidak nyeri' : ''}</option>
                            ))}
                        </Select>
                    </Field>
                    <Field error={errors['clinical_assessment.complaint_details.triggers']} htmlFor={`${idPrefix}complaint-triggers`} label="Faktor pemicu">
                        <Input disabled={disabled} id={`${idPrefix}complaint-triggers`} maxLength={2000} onChange={(e) => update('triggers', e.target.value)} placeholder="Aktivitas, makanan, atau paparan yang memperberat" value={value.triggers ?? ''} />
                    </Field>
                    <Field error={errors['clinical_assessment.complaint_details.relief']} htmlFor={`${idPrefix}complaint-relief`} label="Faktor pereda">
                        <Input disabled={disabled} id={`${idPrefix}complaint-relief`} maxLength={2000} onChange={(e) => update('relief', e.target.value)} placeholder="Istirahat, posisi, atau obat yang meringankan" value={value.relief ?? ''} />
                    </Field>
                </div>
                <ChoiceGroup
                    compact
                    error={errors['clinical_assessment.complaint_details.progression']}
                    label="Perkembangan keluhan"
                    name={`${idPrefix}complaint-progression`}
                    onChange={(selected) => update('progression', selected[0] ?? '')}
                    options={progressionOptions}
                    selected={value.progression ? [value.progression] : []}
                    single
                />
                <div className="grid gap-4 md:grid-cols-2">
                    <Field error={errors['clinical_assessment.complaint_details.progression_notes']} htmlFor={`${idPrefix}complaint-progression-notes`} label="Rincian perkembangan keluhan">
                        <Textarea disabled={disabled} id={`${idPrefix}complaint-progression-notes`} maxLength={2000} onChange={(e) => update('progression_notes', e.target.value)} placeholder="Uraikan perubahan gejala sejak awal timbul" rows={2} value={value.progression_notes ?? ''} />
                    </Field>
                    <Field error={errors['clinical_assessment.complaint_details.notes']} htmlFor={`${idPrefix}complaint-notes`} label="Catatan rincian keluhan">
                        <Textarea disabled={disabled} id={`${idPrefix}complaint-notes`} maxLength={2000} onChange={(e) => update('notes', e.target.value)} placeholder="Keterangan tambahan yang relevan dengan keluhan" rows={2} value={value.notes ?? ''} />
                    </Field>
                </div>
            </div>
        </details>
    );
}

export function DoctorDentalPainSection({
    value,
    onChange,
    errors = {},
    disabled = false,
    defaultOpen = false,
    nurseShared = false,
    nurseReference,
    idPrefix = 'doctor-',
}: {
    value: DoctorDentalPainDetails;
    onChange: (next: DoctorDentalPainDetails) => void;
    errors?: Record<string, string>;
    disabled?: boolean;
    defaultOpen?: boolean;
    nurseShared?: boolean;
    nurseReference?: Array<[string, string]>;
    idPrefix?: string;
}) {
    const filledCount = countDoctorAssessmentFields(value as Record<string, unknown>);
    const hasError = Object.keys(errors).some((key) => key.startsWith('clinical_assessment.dental_pain'));
    const triggers = value.triggers ?? [];
    const update = <K extends keyof DoctorDentalPainDetails>(key: K, nextValue: DoctorDentalPainDetails[K]) => {
        onChange({ ...value, [key]: nextValue });
    };

    return (
        <details
            className="group/disclosure min-w-0 overflow-hidden rounded-xl border border-neutral-200 bg-neutral-50/60"
            open={defaultOpen || filledCount > 0 || hasError}
        >
            <summary className="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] items-start gap-3 p-4 [&::-webkit-details-marker]:hidden">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-sm font-semibold text-neutral-900">Rincian nyeri gigi & perkembangan keluhan</h4>
                        {filledCount > 0 ? <Badge variant="complete">{filledCount} terisi</Badge> : <Badge variant="default">{nurseShared ? 'Perawat & Dokter' : 'Diisi dokter'}</Badge>}
                    </div>
                    <p className="text-xs leading-5 text-neutral-500">Catat lokasi nyeri gigi/gusi, skala nyeri, pola durasi, pemicu, dan dampak pada aktivitas pasien.</p>
                </div>
                {disclosurePill}
            </summary>
            <div className="space-y-4 border-t border-neutral-200 bg-surface p-4 sm:p-5">
                <NurseReferenceBlock items={nurseReference} />
                <div className="grid gap-4 md:grid-cols-3">
                    <Field error={errors['clinical_assessment.dental_pain.location']} htmlFor={`${idPrefix}dental-location`} label="Lokasi nyeri gigi / gusi">
                        <Input disabled={disabled} id={`${idPrefix}dental-location`} maxLength={2000} onChange={(e) => update('location', e.target.value)} placeholder="Contoh: geraham bawah kanan" value={value.location ?? ''} />
                    </Field>
                    <Field error={errors['clinical_assessment.dental_pain.pain_scale']} htmlFor={`${idPrefix}dental-pain-scale`} label="Skala nyeri (0–10)">
                        <Select disabled={disabled} id={`${idPrefix}dental-pain-scale`} onChange={(e) => update('pain_scale', e.target.value === '' ? null : Number(e.target.value))} value={value.pain_scale != null ? String(value.pain_scale) : ''}>
                            <option value="">Belum dikaji</option>
                            {Array.from({ length: 11 }, (_, score) => (
                                <option key={score} value={score}>{score}{score === 0 ? ' · Tidak nyeri' : ''}</option>
                            ))}
                        </Select>
                    </Field>
                    <Field error={errors['clinical_assessment.dental_pain.duration']} htmlFor={`${idPrefix}dental-duration`} label="Pola dan durasi episode nyeri">
                        <Input disabled={disabled} id={`${idPrefix}dental-duration`} maxLength={2000} onChange={(e) => update('duration', e.target.value)} placeholder="Contoh: berdenyut 10–15 menit" value={value.duration ?? ''} />
                    </Field>
                </div>
                <ChoiceGroup
                    error={errors['clinical_assessment.dental_pain.triggers']}
                    label="Pemicu nyeri gigi"
                    name={`${idPrefix}dental-triggers`}
                    onChange={(selected) => update('triggers', selected)}
                    options={dentalTriggerOptions}
                    selected={triggers}
                />
                {triggers.includes('lainnya') && (
                    <Field error={errors['clinical_assessment.dental_pain.triggers_other']} htmlFor={`${idPrefix}dental-triggers-other`} label="Keterangan pemicu lainnya">
                        <Input disabled={disabled} id={`${idPrefix}dental-triggers-other`} maxLength={2000} onChange={(e) => update('triggers_other', e.target.value)} placeholder="Sebutkan pemicu lainnya" value={value.triggers_other ?? ''} />
                    </Field>
                )}
                <div className="grid gap-4 md:grid-cols-3">
                    <Field error={errors['clinical_assessment.dental_pain.character']} htmlFor={`${idPrefix}dental-character`} label="Karakter & penjalaran nyeri">
                        <Textarea disabled={disabled} id={`${idPrefix}dental-character`} maxLength={2000} onChange={(e) => update('character', e.target.value)} placeholder="Berdenyut, ngilu tajam, menjalar ke telinga/rahang" rows={2} value={value.character ?? ''} />
                    </Field>
                    <Field error={errors['clinical_assessment.dental_pain.relief']} htmlFor={`${idPrefix}dental-relief`} label="Faktor pereda nyeri">
                        <Textarea disabled={disabled} id={`${idPrefix}dental-relief`} maxLength={2000} onChange={(e) => update('relief', e.target.value)} placeholder="Analgesik, kumur air hangat, istirahat" rows={2} value={value.relief ?? ''} />
                    </Field>
                    <Field error={errors['clinical_assessment.dental_pain.impact']} htmlFor={`${idPrefix}dental-impact`} label="Dampak pada aktivitas / tidur">
                        <Textarea disabled={disabled} id={`${idPrefix}dental-impact`} maxLength={2000} onChange={(e) => update('impact', e.target.value)} placeholder="Mengganggu makan atau tidur malam" rows={2} value={value.impact ?? ''} />
                    </Field>
                </div>
                <ChoiceGroup
                    compact
                    error={errors['clinical_assessment.dental_pain.progression']}
                    label="Perkembangan keluhan"
                    name={`${idPrefix}dental-progression`}
                    onChange={(selected) => update('progression', selected[0] ?? '')}
                    options={progressionOptions}
                    selected={value.progression ? [value.progression] : []}
                    single
                />
                <Field error={errors['clinical_assessment.dental_pain.progression_notes']} htmlFor={`${idPrefix}dental-progression-notes`} label="Rincian perkembangan keluhan">
                    <Textarea disabled={disabled} id={`${idPrefix}dental-progression-notes`} maxLength={2000} onChange={(e) => update('progression_notes', e.target.value)} placeholder="Catatan perkembangan nyeri atau pembengkakan" rows={2} value={value.progression_notes ?? ''} />
                </Field>
            </div>
        </details>
    );
}

export function DoctorMucosaObservationSection({
    value,
    onChange,
    errors = {},
    disabled = false,
    defaultOpen = false,
    nurseShared = false,
    nurseReference,
    idPrefix = 'doctor-',
}: {
    value: DoctorMucosaObservation;
    onChange: (next: DoctorMucosaObservation) => void;
    errors?: Record<string, string>;
    disabled?: boolean;
    defaultOpen?: boolean;
    nurseShared?: boolean;
    nurseReference?: Array<[string, string]>;
    idPrefix?: string;
}) {
    const filledCount = countDoctorAssessmentFields(value as Record<string, unknown>);
    const hasError = Object.keys(errors).some((key) => key.startsWith('clinical_assessment.mucosa_observation'));
    const allNormal = mucosaSites.every(([site]) => value[site] === 'normal');
    const hasProtected = Boolean(value.kelainan_mukosa?.trim()) || mucosaSites.some(([site]) => (
        ['abnormal', 'belum_dinilai'].includes(value[site] ?? '') || Boolean(value.catatan?.[site]?.trim())
    ));

    const toggleAllNormal = (checked: boolean) => {
        if (hasProtected) return;
        const patch = Object.fromEntries(mucosaSites.map(([site]) => [site, checked ? 'normal' : ''])) as Partial<Record<MucosaSite, string>>;
        onChange({ ...value, ...patch });
    };

    return (
        <details
            className="group/disclosure min-w-0 overflow-hidden rounded-xl border border-neutral-200 bg-neutral-50/60"
            open={defaultOpen || filledCount > 0 || hasError}
        >
            <summary className="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] items-start gap-3 p-4 [&::-webkit-details-marker]:hidden">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-sm font-semibold text-neutral-900">Observasi mukosa mulut</h4>
                        {filledCount > 0 ? <Badge variant="complete">{filledCount} terisi</Badge> : <Badge variant="default">{nurseShared ? 'Perawat & Dokter' : 'Diisi dokter'}</Badge>}
                    </div>
                    <p className="text-xs leading-5 text-neutral-500">Dokumentasikan kondisi mukosa pada lidah, pipi, bibir, palatum, dan gusi beserta temuan kelainan bila ada.</p>
                </div>
                {disclosurePill}
            </summary>
            <div className="space-y-4 border-t border-neutral-200 bg-surface p-4 sm:p-5">
                <NurseReferenceBlock items={nurseReference} />
                <div className="grid min-w-0 items-start gap-4 md:grid-cols-2">
                    {mucosaSites.map(([site, label]) => {
                        const status = value[site] ?? '';
                        const note = value.catatan?.[site] ?? '';
                        const abnormal = status === 'abnormal';
                        return (
                            <section className="min-w-0 space-y-3 rounded-lg border border-neutral-200 p-4" key={site}>
                                <ChoiceGroup
                                    compact
                                    error={errors[`clinical_assessment.mucosa_observation.${site}`]}
                                    label={label}
                                    name={`${idPrefix}mucosa-${site}`}
                                    onChange={(items) => onChange({ ...value, [site]: items[0] ?? '' })}
                                    options={mucosaOptions}
                                    selected={status ? [status] : []}
                                    single
                                />
                                <details className="space-y-2" open={abnormal || Boolean(note)}>
                                    <summary className="cursor-pointer text-xs font-medium text-neutral-600">{abnormal ? 'Uraian temuan' : 'Catatan tambahan (bila diperlukan)'}</summary>
                                    <Field error={errors[`clinical_assessment.mucosa_observation.catatan.${site}`]} htmlFor={`${idPrefix}mucosa-${site}-notes`} label={`Catatan ${label}`}>
                                        <Textarea disabled={disabled} id={`${idPrefix}mucosa-${site}-notes`} maxLength={2000} onChange={(e) => onChange({ ...value, catatan: { ...(value.catatan ?? {}), [site]: e.target.value } })} rows={2} value={note} />
                                    </Field>
                                </details>
                            </section>
                        );
                    })}
                </div>
                <label className="flex items-start gap-2 text-sm leading-6">
                    <Checkbox checked={allNormal} className="mt-1 shrink-0" disabled={disabled || hasProtected} id={`${idPrefix}mucosa-quick-normal`} onCheckedChange={(checked) => toggleAllNormal(checked === true)} />
                    <span>Semua lokasi mukosa normal setelah diamati</span>
                </label>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field error={errors['clinical_assessment.mucosa_observation.kelainan_mukosa']} htmlFor={`${idPrefix}kelainan-mukosa`} label="Catatan umum mukosa">
                        <Textarea disabled={disabled} id={`${idPrefix}kelainan-mukosa`} maxLength={5000} onChange={(e) => onChange({ ...value, kelainan_mukosa: e.target.value })} placeholder="Lokasi, tampilan, dan kelainan mukosa yang terlihat" rows={2} value={value.kelainan_mukosa ?? ''} />
                    </Field>
                    <Field error={errors['clinical_assessment.mucosa_observation.observasi_mulut']} htmlFor={`${idPrefix}observasi-mulut`} label="Observasi mulut dan rahang">
                        <Textarea disabled={disabled} id={`${idPrefix}observasi-mulut`} maxLength={5000} onChange={(e) => onChange({ ...value, observasi_mulut: e.target.value })} placeholder="Catatan tambahan terkait jaringan lunak atau rahang" rows={2} value={value.observasi_mulut ?? ''} />
                    </Field>
                </div>
            </div>
        </details>
    );
}
