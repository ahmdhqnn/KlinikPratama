import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Field } from '@/components/ui/field';
import { ChoiceGroup } from '@/components/kunjungan/assessment-choice-group';
import { Textarea } from '@/components/ui/textarea';

export type SystemAnswer = { status: string; notes: string };
export type SystemAnswers = Record<string, SystemAnswer>;
export type GeneralNursingData = { systems?: SystemAnswers } & Record<string, string | SystemAnswers | undefined>;

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
    complaint: [['location', 'Lokasi keluhan'], ['character', 'Karakteristik'], ['pattern', 'Pola / frekuensi'], ['triggers', 'Faktor pemicu'], ['relief', 'Faktor pereda'], ['progression', 'Perkembangan keluhan', [['membaik', 'Membaik'], ['menetap', 'Menetap'], ['memburuk', 'Memburuk'], ['belum_diketahui', 'Belum diketahui']]], ['progression_notes', 'Rincian perkembangan keluhan']],
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
const legacyFields: Partial<Record<NursingSection, Array<[string, string]>>> = {
    visit: [['visit_purpose', 'Tujuan kunjungan sebelumnya']],
    complaint: [['onset', 'Awitan sebelumnya'], ['severity', 'Keparahan sebelumnya'], ['associated', 'Gejala penyerta sebelumnya']],
    history: [['chronic', 'Penyakit kronis sebelumnya'], ['hospitalization', 'Riwayat rawat inap sebelumnya'], ['surgery', 'Riwayat operasi sebelumnya']],
    triage: [['red_flags', 'Catatan tanda bahaya sebelumnya']],
    conclusion: [['summary', 'Ringkasan sebelumnya'], ['doctor_notes', 'Catatan dokter sebelumnya']],
};

export function GeneralNursingAssessment({ value, onChange, complaint, errors = {}, section, informationSource, dental = false }: {
    value: GeneralNursingData; onChange: (value: GeneralNursingData) => void; complaint: string; errors?: Record<string, string>; section?: NursingSection; informationSource?: string; dental?: boolean;
}) {
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
    return <div className="space-y-4" aria-label={dental ? "Rincian pengkajian gigi dan mulut" : "Rincian pengkajian Poli Umum"}>{parts.map(part => <div key={part} className="space-y-4">
        {part === 'complaint' && relevant.length > 0 && <aside aria-label="Pertanyaan lanjutan sesuai keluhan" className="space-y-1 rounded-lg border border-neutral-200 p-3"><p className="text-sm font-medium">Pertanyaan lanjutan sesuai keluhan</p>{relevant.map(([, prompt]) => <p className="text-xs leading-5 text-neutral-600" key={prompt}>{prompt}</p>)}</aside>}
        {part === 'conclusion' && !dental && <div className="space-y-2 rounded-lg border border-neutral-200 p-3" aria-label="Ringkasan otomatis sistem tubuh"><p className="text-sm font-medium">Ringkasan sistem tubuh</p>{Object.entries(nursingSystems).filter(([key]) => value.systems?.[key]?.status === 'positive').map(([key, label]) => <p key={key} className="whitespace-pre-wrap text-sm"><strong>{label}:</strong> {value.systems?.[key]?.notes || 'Uraian temuan belum diisi'}</p>)}<p className="text-xs text-neutral-500">Belum dikaji: {Object.entries(nursingSystems).filter(([key]) => !value.systems?.[key] || value.systems[key].status === 'not_assessed').map(([, label]) => label).join(', ') || 'Tidak ada'}</p></div>}
        {part === 'systems' ? <SystemAssessment prefix="general_assessment.systems" value={value.systems ?? {}} errors={errors} onChange={systems => onChange({ ...value, systems })} /> : <div className="grid min-w-0 gap-4 md:grid-cols-2">{groups[part].map(([key, label, options]) => {
            if (dental && part === 'complaint' && !['progression', 'progression_notes'].includes(key)) return null;
            if (key === 'progression_notes' && (!read('progression') || read('progression') === 'membaik') && !read(key)) return null;
            if (key === 'companion' && (!informationSource || informationSource === 'pasien')) return null;
            const condition = { special_needs: 'special_needs_status', needs: 'needs_status', initial_actions: 'initial_actions_status' }[key];
            if (condition && read(condition) !== 'ada' && !read(key)) return null;
            return options ? <div key={key} className="min-w-0 md:col-span-2"><ChoiceGroup label={label} name={`general-${key}`} options={options} single compact selected={options.some(([option]) => option === read(key)) ? [read(key)] : []} error={errors[`general_assessment.${key}`]} onChange={items => set(key, items[0] ?? '')} />{read(key) && !options.some(([option]) => option === read(key)) && <p className="mt-2 text-xs text-neutral-600">Catatan sebelumnya: {read(key)}</p>}</div> : <Field className={condition || ['handover_follow_up', 'progression_notes'].includes(key) ? 'md:col-span-2' : undefined} key={key} htmlFor={`general-${key}`} label={label} error={errors[`general_assessment.${key}`]}>{['companion', 'location', 'pattern', 'triggers', 'relief'].includes(key) && !read(key).includes('\n') ? <Input id={`general-${key}`} maxLength={2000} placeholder={`Catat ${label.toLocaleLowerCase('id')}`} value={read(key)} onChange={event => set(key, event.target.value)} /> : <Textarea id={`general-${key}`} rows={2} maxLength={2000} placeholder={`Catat ${label.toLocaleLowerCase('id')}`} value={read(key)} onChange={event => set(key, event.target.value)} />}</Field>;
        })}</div>}
        {(legacyFields[part] ?? []).some(([key]) => read(key)) && <details className="rounded-lg border border-neutral-200 p-3"><summary className="cursor-pointer text-xs font-medium">Catatan sebelumnya</summary><dl className="mt-3 grid gap-3 sm:grid-cols-2">{(legacyFields[part] ?? []).filter(([key]) => read(key)).map(([key, label]) => <div key={key}><dt className="text-xs text-neutral-500">{label}</dt><dd className="whitespace-pre-wrap text-sm">{read(key)}</dd></div>)}</dl></details>}
    </div>)}</div>;
}
