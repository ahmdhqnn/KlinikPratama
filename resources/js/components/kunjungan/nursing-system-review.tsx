import { nursingSystems, rosSystems, type SystemAnswers } from '@/components/kunjungan/general-assessment';
import { ChoiceGroup } from '@/components/kunjungan/assessment-choice-group';
import { Field } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';

const statuses: Record<string, string> = { positive: 'Positif / abnormal', negative: 'Negatif setelah dikaji', not_assessed: 'Belum dikaji', not_relevant: 'Tidak relevan pada kunjungan ini' };
export function NursingSystemReview({ source, value, onChange, errors = {}, prefix = 'review_of_systems' }: { source: SystemAnswers; value: SystemAnswers; onChange: (value: SystemAnswers) => void; errors?: Record<string, string>; prefix?: string }) {
    const systems = { ...nursingSystems, ...Object.fromEntries(Object.entries(rosSystems).filter(([key]) => key in value && !(key in nursingSystems))) };
    return <section className="space-y-4" aria-label="Verifikasi pengkajian sistem perawat">
        <div><h4 className="text-sm font-semibold">Verifikasi pengkajian sistem perawat</h4><p className="mt-1 text-xs leading-5 text-neutral-500">Hasil perawat diteruskan untuk ditinjau. Buka sistem yang perlu dilengkapi atau dikoreksi berdasarkan evaluasi dokter. Sistem yang belum dikaji tetap berstatus belum dikaji.</p></div>
        <div className="divide-y divide-neutral-200 rounded-xl border border-neutral-200">{Object.entries(systems).map(([key, label]) => {
            const nurse = source[key];
            const answer = value[key] ?? { status: 'not_assessed', notes: '' };
            const changed = nurse && (answer.status !== nurse.status || (answer.notes ?? '') !== (nurse.notes ?? ''));
            const update = (patch: Partial<typeof answer>) => onChange({ ...value, [key]: { ...answer, ...patch } });
            const error = errors[`review_of_systems.${key}.status`] || errors[`review_of_systems.${key}.notes`];
            return <details key={key} open={Boolean(error)} className="min-w-0 p-4">
                <summary className="cursor-pointer text-sm"><span className="font-medium">{label}</span><span className="ml-2 text-neutral-600">{statuses[answer.status] ?? 'Belum dikaji'}{changed ? ' · diperbarui dokter' : ''}</span>{answer.notes && <p className="mt-1 whitespace-pre-wrap break-words text-sm text-neutral-700">{answer.notes}</p>}</summary>
                <div className="mt-4 space-y-4">
                    <p className="whitespace-pre-wrap text-xs text-neutral-500">Hasil perawat: {statuses[nurse?.status] ?? 'Belum dikaji'}{nurse?.notes ? ` — ${nurse.notes}` : ''}</p>
                    <ChoiceGroup name={`${prefix}-${key}`} label="Hasil evaluasi dokter" options={Object.entries(statuses)} single compact selected={[answer.status]} error={errors[`review_of_systems.${key}.status`]} onChange={items => update({ status: items[0] ?? 'not_assessed' })} />
                    <Field htmlFor={`${prefix}-${key}-notes`} label="Klarifikasi / temuan tambahan dokter" required={answer.status === 'positive'} error={errors[`review_of_systems.${key}.notes`]}><Textarea id={`${prefix}-${key}-notes`} rows={2} maxLength={3000} placeholder="Lengkapi gejala, lokasi, atau hasil klarifikasi yang relevan" value={answer.notes ?? ''} onChange={event => update({ notes: event.target.value })} /></Field>
                </div>
            </details>;
        })}</div>
    </section>;
}
