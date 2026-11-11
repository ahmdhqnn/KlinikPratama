import { Field } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';

interface Props {
    id: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    disabled?: boolean;
}

export function AssistanceReasonField({ id, value, onChange, error, disabled }: Props) {
    return (
        <Field className="w-full" error={error} htmlFor={id} label="Alasan bantuan admin" required>
            <Textarea aria-describedby={`${id}-help`} className="min-h-24" disabled={disabled} id={id} minLength={10} maxLength={1000} onChange={(event) => onChange(event.target.value)} placeholder="Jelaskan alasan Anda membantu layanan ini sebagai operator admin." required rows={3} value={value} />
            <p className="mt-2 text-xs leading-5 text-neutral-500" id={`${id}-help`}>Isi 10–1.000 karakter. Alasan ini dicatat bersama akun operator pada jejak audit.</p>
        </Field>
    );
}
