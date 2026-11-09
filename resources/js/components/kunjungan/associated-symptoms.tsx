import { useState } from 'react';
import { ChoiceGroup } from '@/components/kunjungan/assessment-choice-group';
import { Field } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';

export const symptomOptions: Array<[string, string]> = [['mual', 'Mual'], ['muntah', 'Muntah'], ['demam', 'Demam'], ['pusing', 'Pusing'], ['lemas', 'Lemas'], ['bengkak', 'Bengkak'], ['perdarahan', 'Perdarahan'], ['tidak_ada', 'Tidak ada gejala penyerta'], ['lainnya', 'Lainnya']];
export function parseSymptoms(value: string) {
    const parts = value.split('; ').filter(Boolean);
    const known = symptomOptions.filter(([key, label]) => key !== 'lainnya' && parts.includes(label)).map(([key]) => key);
    const other = parts.filter(part => !symptomOptions.some(([key, label]) => key !== 'lainnya' && label === part)).join('; ');
    return { selected: [...known, ...(other ? ['lainnya'] : [])], other };
}
export function formatSymptoms(selected: string[], other: string): string {
    return [...symptomOptions.filter(([key]) => key !== 'lainnya' && selected.includes(key)).map(([, label]) => label), ...(selected.includes('lainnya') && other ? [other] : [])].join('; ');
}
export function AssociatedSymptoms({ value, onChange, error }: { value: string; onChange: (value: string) => void; error?: string }) {
    const parsed = parseSymptoms(value);
    // Keep an empty “Lainnya” choice visible while its detail is being entered.
    const [otherOpen, setOtherOpen] = useState(parsed.selected.includes('lainnya'));
    const selected = [...parsed.selected.filter(key => key !== 'lainnya'), ...(otherOpen || parsed.other ? ['lainnya'] : [])];
    return <div className="space-y-4">
        <ChoiceGroup name="associated-symptoms" label="Gejala penyerta" options={symptomOptions} selected={selected} error={error} onChange={items => { setOtherOpen(items.includes('lainnya')); onChange(formatSymptoms(items, parsed.other)); }} />
        {selected.includes('lainnya') && <Field htmlFor="keluhan_tambahan" label="Gejala penyerta lainnya" error={error}>
            <Textarea id="keluhan_tambahan" maxLength={4000} placeholder="Catat gejala lain yang menyertai keluhan utama, sejak kapan, dan frekuensinya" value={parsed.other} onChange={event => onChange(formatSymptoms(selected, event.target.value))} />
        </Field>}
    </div>;
}
