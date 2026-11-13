import { Fragment, useState, type ReactNode } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Field } from '@/components/ui/field';
import { ChoiceGroup } from '@/components/kunjungan/assessment-choice-group';
import { Textarea } from '@/components/ui/textarea';

export type SystemAnswer = { status: string; notes: string };
export type SystemAnswers = Record<string, SystemAnswer>;
export type GeneralNursingData = { systems?: SystemAnswers } & Record<string, string | SystemAnswers | undefined>;

export function GeneralAssessmentNarrativeField({ field, label, generated, value, onChange, error }: {
    field: 'complaint_notes' | 'systems_notes'; label: string; generated: string; value: string; onChange: (value: string) => void; error?: string;
}) {
    const lead = generated ? `Ringkasan otomatis. ${generated}\n` : '';
    return <Field htmlFor={`general-${field}`} label={label} error={error}>
        <Textarea id={`general-${field}`} rows={4} maxLength={2000} placeholder="Tambahkan keterangan yang belum tercakup pada pilihan di atas" value={lead + value}
            onFocus={event => {
                const input = event.currentTarget;
                if (input.selectionStart < lead.length && input.selectionStart === input.selectionEnd) input.setSelectionRange(lead.length, lead.length);
            }}
            onChange={event => {
                const next = event.target.value;
                if (next.startsWith(lead)) onChange(next.slice(lead.length));
                else if (!next) onChange('');
            }} />
    </Field>;
}

export const nursingSystems = {
    cardiovascular: 'Kardiovaskular', respiratory: 'Respirasi', digestive: 'Pencernaan',
    neurological: 'Neurologis', musculoskeletal: 'Muskuloskeletal', genitourinary: 'Genitourinaria',
    skin: 'Integumen', psychological: 'Psikologis dan status fungsional',
};
export const rosSystems = { constitutional: 'Konstitusional', head_neck: 'Kepala dan leher', ...nursingSystems };
const systemStatuses: Record<string, string> = { positive: 'Positif / abnormal', negative: 'Negatif setelah dikaji', not_assessed: 'Belum dikaji', not_relevant: 'Tidak relevan pada kunjungan ini' };

export function allSystemsNegative(value: SystemAnswers, systems = nursingSystems): boolean {
    return Object.keys(systems).every(key => value[key]?.status === 'negative');
}

export function hasProtectedSystemAnswer(value: SystemAnswers, systems = nursingSystems): boolean {
    return Object.keys(systems).some(key => ['positive', 'not_relevant'].includes(value[key]?.status) || Boolean(value[key]?.notes?.trim()));
}

export function normalSystemsPatch(value: SystemAnswers, checked: boolean, systems = nursingSystems): SystemAnswers {
    if (hasProtectedSystemAnswer(value, systems)) return value;
    return { ...value, ...Object.fromEntries(Object.keys(systems).map(key => [key, { ...value[key], status: checked ? 'negative' : 'not_assessed', notes: value[key]?.notes ?? '' }])) };
}

export function SystemAssessment({ value, onChange, errors = {}, prefix, doctor = false }: {
    value: SystemAnswers; onChange: (value: SystemAnswers) => void; errors?: Record<string, string>; prefix: string; doctor?: boolean;
}) {
    const systems = doctor ? rosSystems : nursingSystems;
    return <div className="space-y-4">
        <p className="text-sm text-neutral-600">Pilih hasil setelah dikaji. Bagian kosong ditampilkan sebagai belum dikaji. Jelaskan temuan positif, lokasi, dan gejala terkait.</p>
        <div className="grid min-w-0 gap-4 lg:grid-cols-2">{Object.entries(systems).map(([key, label]) => {
            const answer = value[key] ?? { status: 'not_assessed', notes: '' };
            const update = (patch: Partial<SystemAnswer>) => onChange({ ...value, [key]: { ...answer, ...patch } });
            return <section key={key} className="min-w-0 space-y-3 rounded-lg border border-neutral-200 p-4">
                <ChoiceGroup label={label} name={`${prefix}-${key}`} options={Object.entries(systemStatuses)} selected={[answer.status]} single compact error={errors[`${prefix}.${key}.status`]} onChange={values => update({ status: values[0] ?? 'not_assessed' })} />
                <details open={answer.status === 'positive' || Boolean(answer.notes)} className="space-y-2"><summary className="cursor-pointer text-xs font-medium text-neutral-600">{answer.status === 'positive' ? 'Uraian temuan' : 'Catatan tambahan (bila diperlukan)'}</summary><Field htmlFor={`${prefix}-${key}-notes`} label="Uraian temuan" required={answer.status === 'positive'} error={errors[`${prefix}.${key}.notes`]}>
                    <Textarea id={`${prefix}-${key}-notes`} rows={2} maxLength={3000} value={answer.notes ?? ''} onChange={event => update({ notes: event.target.value })} />
                </Field></details>
            </section>;
        })}</div>
        {!doctor && <label className="flex cursor-pointer items-start gap-2 text-sm leading-6">
            <Checkbox id={`${prefix}-quick-normal`} className="mt-1 shrink-0" checked={allSystemsNegative(value)} disabled={hasProtectedSystemAnswer(value)} onCheckedChange={checked => onChange(normalSystemsPatch(value, checked === true))} />
            <span>Semua sistem tubuh tidak ditemukan kelainan setelah dikaji</span>
        </label>}
        {!doctor && hasProtectedSystemAnswer(value) && <p className="text-xs text-neutral-500">Pilihan cepat tidak tersedia saat ada temuan abnormal, catatan, atau sistem yang tidak relevan. Tinjau jawaban masing-masing sistem.</p>}
    </div>;
}

function SystemSummary({ value, notes }: { value: SystemAnswers; notes: string }) {
    const systems = Object.entries(nursingSystems);
    const findings = systems.filter(([key]) => value[key]?.status === 'positive' || Boolean(value[key]?.notes?.trim()));
    const unassessed = systems.filter(([key]) => !value[key]?.status || value[key].status === 'not_assessed');

    return <section className="space-y-2 rounded-lg border border-neutral-200 p-4" aria-label="Ringkasan otomatis sistem tubuh">
        <h4 className="text-sm font-semibold">Ringkasan sistem tubuh</h4>
        {findings.map(([key, label]) => <p key={key} className="wrap-break-word whitespace-pre-wrap text-sm"><strong>{label}:</strong> {value[key]?.notes?.trim() || 'Uraian temuan belum diisi'}</p>)}
        {notes.trim() && <p className="wrap-break-word whitespace-pre-wrap text-sm"><strong>Catatan pengkajian:</strong> {notes}</p>}
        <p className="text-xs text-neutral-500">Belum dikaji: {unassessed.map(([, label]) => label).join(', ') || 'Tidak ada'}</p>
    </section>;
}

type NursingSection = 'visit' | 'triage' | 'complaint' | 'history' | 'systems' | 'additional' | 'conclusion';
const statusOptions: Array<[string, string]> = [['ada', 'Ada'], ['tidak_ada', 'Tidak ada'], ['belum_diketahui', 'Belum diketahui']];
const groups: Record<Exclude<NursingSection, 'systems'>, Array<[string, string, Array<[string, string]>?]>> = {
    visit: [
        ['arrival', 'Kondisi kedatangan', [['mandiri', 'Berjalan sendiri'], ['dibantu', 'Dibantu'], ['kursi_roda', 'Kursi roda'], ['lainnya', 'Lainnya']]],
        ['companion', 'Nama / hubungan pendamping (bila ada)'],
    ],
    triage: [
        ['general_condition', 'Keadaan umum', [['baik', 'Baik'], ['cukup', 'Cukup'], ['lemah', 'Lemah'], ['belum_dinilai', 'Belum dinilai']]],
        ['airway', 'Jalan napas', [['bebas', 'Bebas'], ['perlu_penilaian', 'Perlu penilaian lanjut'], ['belum_dinilai', 'Belum dinilai']]],
        ['circulation', 'Sirkulasi', [['tanpa_kelainan', 'Tidak ditemukan kelainan setelah dikaji'], ['perlu_penilaian', 'Perlu penilaian lanjut'], ['belum_dinilai', 'Belum dinilai']]],
    ],
    complaint: [['progression', 'Perkembangan keluhan', [['membaik', 'Membaik'], ['menetap', 'Menetap'], ['memburuk', 'Memburuk'], ['belum_diketahui', 'Belum diketahui']]], ['progression_notes', 'Rincian perkembangan keluhan']],
    history: [['hospitalization_status', 'Pernah dirawat inap', statusOptions], ['surgery_status', 'Pernah menjalani operasi', statusOptions]],
    additional: [
        ['mobility', 'Mobilitas', [['mandiri', 'Mandiri'], ['dibantu', 'Perlu bantuan'], ['terbatas', 'Terbatas'], ['belum_dinilai', 'Belum dinilai']]],
        ['special_needs_status', 'Kebutuhan khusus', statusOptions], ['special_needs', 'Rincian kebutuhan khusus'],
    ],
    conclusion: [
        ['needs_status', 'Masalah / kebutuhan yang teridentifikasi', statusOptions], ['needs', 'Rincian masalah / kebutuhan'],
        ['initial_actions_status', 'Tindakan awal yang sudah dilakukan', [['ada', 'Ada tindakan'], ['tidak_ada', 'Belum ada tindakan']]], ['initial_actions', 'Rincian tindakan dan respons pasien'],
        ['handover_follow_up', 'Komunikasi / tindak lanjut serah terima (bila diperlukan)'],
    ],
};
const dentalHandoverPhrases = [
    'Pasien sudah berada di dental chair.',
    'TTV stabil berdasarkan penilaian petugas.',
    'Pengkajian awal lengkap telah diserahkan kepada dokter gigi.',
    'Pemeriksaan fisik intraoral oleh dokter gigi sebagai tindak lanjut.',
    'Tindak lanjut dengan spesialis konservasi gigi telah direncanakan.',
];
const generalHandoverPhrases = [
    'Pasien sudah berada di ruang pemeriksaan.',
    'TTV stabil berdasarkan penilaian petugas.',
    'Pengkajian awal lengkap telah diserahkan kepada dokter.',
    'Pemeriksaan fisik oleh dokter sebagai tindak lanjut.',
];

const dentalHandoverOptions = [
    ['Posisi di Dental Chair', 'Pasien sudah berada di Dental Chair'],
    ['TTV stabil', 'TTV stabil'],
    ['Pengkajian dikirim ke dokter gigi', 'pengkajian awal lengkap dikirim ke dokter gigi'],
    ['Pemeriksaan fisik intraoral', 'pemeriksaan fisik intraoral'],
    ['Tindak lanjut konservasi gigi', 'penanganan oleh spesialis konservasi gigi'],
] as const;
const generalHandoverOptions = [
    ['Posisi di ruang pemeriksaan', 'Pasien sudah berada di ruang pemeriksaan'],
    ['TTV stabil', 'TTV stabil'],
    ['Pengkajian dikirim ke dokter', 'pengkajian awal lengkap dikirim ke dokter'],
    ['Pemeriksaan fisik', 'pemeriksaan fisik oleh dokter'],
] as const;

function handoverSentence(selected: number[], options: ReadonlyArray<readonly [string, string]>) {
    const actions = selected.filter(index => index >= 3).map(index => options[index][1]).join(' dan ');
    const clauses = selected.filter(index => index < 3).map(index => options[index][1]);
    if (actions) {
        if (selected.includes(2)) clauses[clauses.length - 1] += ` untuk ${actions}`;
        else clauses.push(`tindak lanjut yang direncanakan mencakup ${actions}`);
    }
    const sentence = clauses.join(', ');
    return sentence ? `${sentence[0].toLocaleUpperCase('id')}${sentence.slice(1)}.` : '';
}

const complaintColumns: Array<typeof groups.complaint> = [
    [['location', 'Lokasi keluhan'], ['triggers', 'Faktor pemicu'], ['relief', 'Faktor pereda']],
    [['character', 'Karakteristik'], ['pattern', 'Pola / frekuensi']],
];

const legacyFields: Partial<Record<NursingSection, Array<[string, string]>>> = {
    visit: [['visit_purpose', 'Tujuan kunjungan sebelumnya']],
    complaint: [['onset', 'Awitan sebelumnya'], ['severity', 'Keparahan sebelumnya'], ['associated', 'Gejala penyerta sebelumnya']],
    history: [['chronic', 'Penyakit kronis sebelumnya'], ['hospitalization', 'Riwayat rawat inap sebelumnya'], ['surgery', 'Riwayat operasi sebelumnya']],
    triage: [['red_flags', 'Catatan tanda bahaya sebelumnya']],
    conclusion: [['summary', 'Ringkasan sebelumnya'], ['doctor_notes', 'Catatan dokter sebelumnya']],
};

export function GeneralNursingAssessment({ value, onChange, complaint, errors = {}, section, informationSource, nutritionField, dental = false, complaintNarrative = '', systemsNarrative = '', includeComplaintNote = true }: {
    value: GeneralNursingData; onChange: (value: GeneralNursingData) => void; complaint: string; errors?: Record<string, string>; section?: NursingSection; informationSource?: string; nutritionField?: ReactNode; dental?: boolean; complaintNarrative?: string; systemsNarrative?: string; includeComplaintNote?: boolean;
}) {
    const [showOtherHandover, setShowOtherHandover] = useState(false);
    const parts: NursingSection[] = section ? [section] : ['visit', 'triage', 'complaint', 'history', 'systems', 'additional', 'conclusion'];
    const symptom = complaint.toLocaleLowerCase('id');
    const prompts: Array<[RegExp, string]> = [
        [/batuk|sesak|napas/, 'Respirasi: tanyakan awitan sesak, batuk, dahak, serta kaitannya dengan aktivitas.'],
        [/dada|berdebar/, 'Tanyakan lokasi, penjalaran, lama keluhan dan gejala penyerta.'],
        [/perut|mual|muntah|diare|\bbab\b/, 'Tanyakan pola makan, muntah, dan perubahan buang air besar.'],
        [/pusing|kepala|kebas|lemah/, 'Tanyakan awitan, perubahan kesadaran, kelemahan, dan dampak aktivitas.'],
        [/kencing|berkemih|\bbak\b/, 'Tanyakan nyeri, frekuensi, dan perubahan buang air kecil.'],
        [/kulit|gatal|ruam/, 'Tanyakan lokasi, penyebaran, paparan, dan riwayat alergi.'],
        [/nyeri|sakit/, 'Lengkapi lokasi, karakter, pemicu, pereda, skala nyeri dan dampak aktivitas.'],
    ];
    const relevant = prompts.filter(([pattern]) => pattern.test(symptom));
    const read = (key: string) => typeof value[key] === 'string' ? value[key] as string : '';
    const set = (key: string, text: string) => { const detail = { special_needs_status: 'special_needs', needs_status: 'needs', initial_actions_status: 'initial_actions' }[key]; onChange({ ...value, [key]: text, ...(text === 'tidak_ada' && detail ? { [detail]: '' } : {}) }); };
    const hasCompanionField = Boolean(informationSource && informationSource !== 'pasien');
    const hasSpecialNeedsDetail = read('special_needs_status') === 'ada' || Boolean(read('special_needs'));
    const renderField = ([key, label, options]: (typeof groups.complaint)[number]) => {
        if (key === 'handover_follow_up') {
            const note = read(key);
            const [firstLine, ...rest] = note.split(/\r?\n/);
            const options = dental ? dentalHandoverOptions : generalHandoverOptions;
            const legacy = dental ? dentalHandoverPhrases : generalHandoverPhrases;
            const candidates = options.flatMap(([, phrase], index) =>
                firstLine.toLocaleLowerCase('id').includes(phrase.toLocaleLowerCase('id')) ? [index] : []);
            const generated = Boolean(firstLine && firstLine === handoverSentence(candidates, options));
            const remaining = generated ? rest : [firstLine, ...rest];
            const selected = options.flatMap((_, index) =>
                (generated && candidates.includes(index)) || remaining.includes(legacy[index]) ? [index] : []);
            const otherPrefix = 'Lainnya: ';
            const other = remaining.find(line => line.startsWith(otherPrefix))?.slice(otherPrefix.length) ?? '';
            const showOther = showOtherHandover || Boolean(other);
            const manual = remaining.filter(line => !legacy.includes(line) && !line.startsWith(otherPrefix)).join('\n');
            const compose = (items: number[], detail: string, additional: string) =>
                [handoverSentence(items, options), detail.trim() ? otherPrefix + detail : '', additional].filter(Boolean).join('\n');

            return <Field key={key} className="md:col-span-2" htmlFor={`general-${key}`} label={label} error={errors[`general_assessment.${key}`]}>
                <div className="space-y-3">
                    <Textarea id={`general-${key}`} rows={5} maxLength={2000} value={note}
                        placeholder="Catat penerima, waktu, dan rincian tindak lanjut" onChange={event => set(key, event.target.value)} />
                    <div className="flex flex-wrap items-start justify-start gap-x-4 gap-y-2" role="group" aria-label="Pilihan cepat serah terima">
                        {options.map(([optionLabel], index) => {
                            const checked = selected.includes(index);
                            const next = checked ? selected.filter(item => item !== index) : [...selected, index].sort((a, b) => a - b);
                            const startsNewRow = dental && index === dentalHandoverOptions.length - 1;
                            return <Fragment key={optionLabel}>
                                {startsNewRow && <div className="basis-full" aria-hidden="true" />}
                                <label className="flex items-start gap-2 text-sm leading-5">
                                    <Checkbox checked={checked} className="mt-0.5 shrink-0"
                                        disabled={!checked && compose(next, other, manual).length > 2000}
                                        onCheckedChange={() => set(key, compose(next, other, manual))} />
                                    <span>{optionLabel}</span>
                                </label>
                            </Fragment>;
                        })}
                        <label className="flex items-start gap-2 text-sm leading-5">
                            <Checkbox checked={showOther} className="mt-0.5 shrink-0" onCheckedChange={checked => {
                                setShowOtherHandover(checked === true);
                                if (checked !== true && other) set(key, compose(selected, '', manual));
                            }} />
                            <span>Lainnya</span>
                        </label>
                        {showOther && <div className="w-full"><Input className="w-full" aria-label="Rincian tindak lanjut lainnya" placeholder="Rincian tindak lanjut lainnya" value={other}
                            maxLength={Math.max(0, 2000 - compose(selected, '', manual).length - otherPrefix.length - 1)}
                            onChange={event => set(key, compose(selected, event.target.value, manual))} /></div>}
                    </div>
                </div>
            </Field>;
        }
        const tall = key === 'character' || key === 'pattern';
        if (key === 'progression_notes' && (!read('progression') || read('progression') === 'membaik') && !read(key)) return null;
        if (key === 'companion' && !hasCompanionField) return null;
        const condition = { special_needs: 'special_needs_status', needs: 'needs_status', initial_actions: 'initial_actions_status' }[key];
        if (condition && read(condition) !== 'ada' && !read(key)) return null;
        const paired = ['general_condition', 'airway', 'hospitalization_status', 'surgery_status'].includes(key)
            || (key === 'mobility' && Boolean(nutritionField))
            || (key === 'arrival' && hasCompanionField)
            || (key === 'special_needs_status' && hasSpecialNeedsDetail);
        return options ? <div key={key} className={paired ? 'min-w-0' : 'min-w-0 md:col-span-2'}><ChoiceGroup label={label} name={`general-${key}`} options={options} single compact selected={options.some(([option]) => option === read(key)) ? [read(key)] : []} error={errors[`general_assessment.${key}`]} onChange={items => set(key, items[0] ?? '')} />{read(key) && !options.some(([option]) => option === read(key)) && <p className="mt-2 text-xs text-neutral-600">Catatan sebelumnya: {read(key)}</p>}</div> : <Field className={(condition && key !== 'special_needs') || key === 'progression_notes' ? 'md:col-span-2' : tall ? 'md:grid-rows-[auto_minmax(0,1fr)]' : undefined} key={key} htmlFor={`general-${key}`} label={label} error={errors[`general_assessment.${key}`]}>{['companion', 'location', 'triggers', 'relief'].includes(key) && !read(key).includes('\n') ? <Input id={`general-${key}`} maxLength={2000} placeholder={`Catat ${label.toLocaleLowerCase('id')}`} value={read(key)} onChange={event => set(key, event.target.value)} /> : <Textarea id={`general-${key}`} className={tall ? 'min-h-16 md:h-full' : undefined} rows={2} maxLength={2000} placeholder={`Catat ${label.toLocaleLowerCase('id')}`} value={read(key)} onChange={event => set(key, event.target.value)} />}</Field>;
    };
    return <div className="space-y-4" aria-label={dental ? "Rincian pengkajian gigi dan mulut" : "Rincian pengkajian Poli Umum"}>{parts.map(part => <div key={part} className="space-y-4">
        {part === 'complaint' && relevant.length > 0 && <aside aria-label="Pertanyaan lanjutan sesuai keluhan" className="space-y-1 rounded-lg border border-neutral-200 p-3"><p className="text-sm font-medium">Pertanyaan lanjutan sesuai keluhan</p>{relevant.map(([, prompt]) => <p className="text-xs leading-5 text-neutral-600" key={prompt}>{prompt}</p>)}</aside>}
        {part === 'conclusion' && !dental && <SystemSummary value={value.systems ?? {}} notes={read('systems_notes')} />}
        {part === 'systems' ? <>
            <SystemAssessment prefix="general_assessment.systems" value={value.systems ?? {}} errors={errors} onChange={systems => onChange({ ...value, systems })} />
            {!dental && <GeneralAssessmentNarrativeField field="systems_notes" label="Catatan pengkajian sistem tubuh" generated={systemsNarrative} value={read('systems_notes')} onChange={text => set('systems_notes', text)} error={errors['general_assessment.systems_notes']} />}
        </> : <div className="grid min-w-0 gap-4 md:grid-cols-2">
            {part === 'additional' && nutritionField}
            {part === 'complaint' && !dental && complaintColumns.map((column, index) =>
                <div key={column[0][0]} className={index === 0 ? 'grid min-w-0 content-start gap-4' : 'grid min-w-0 gap-4 md:auto-rows-fr'}>{column.map(renderField)}</div>,
            )}
            {groups[part].map(renderField)}
        </div>}
        {part === 'complaint' && !dental && includeComplaintNote && <GeneralAssessmentNarrativeField field="complaint_notes" label="Catatan rincian keluhan" generated={complaintNarrative} value={read('complaint_notes')} onChange={text => set('complaint_notes', text)} error={errors['general_assessment.complaint_notes']} />}
        {(legacyFields[part] ?? []).some(([key]) => read(key)) && <details className="rounded-lg border border-neutral-200 p-3"><summary className="cursor-pointer text-xs font-medium">Catatan sebelumnya</summary><dl className="mt-3 grid gap-3 sm:grid-cols-2">{(legacyFields[part] ?? []).filter(([key]) => read(key)).map(([key, label]) => <div key={key}><dt className="text-xs text-neutral-500">{label}</dt><dd className="whitespace-pre-wrap text-sm">{read(key)}</dd></div>)}</dl></details>}
    </div>)}</div>;
}
