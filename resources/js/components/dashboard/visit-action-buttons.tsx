import { ActionButton } from '@/components/ui/action-button';
import { Link } from '@inertiajs/react';

type VisitActionType = 'detail' | 'examination' | 'rme';

interface PrimaryAction {
    href: string;
    label: string;
    type: VisitActionType;
    disabled?: boolean;
    title?: string;
}

interface Props {
    patient: string;
    primary?: PrimaryAction;
    recordUrl?: string | null;
    recordLabel?: string;
    letterUrl?: string | null;
    showLetterAction?: boolean;
}

export function VisitActionButtons({ patient, primary, recordUrl, recordLabel, letterUrl, showLetterAction = true }: Props) {
    return (
        <div className="flex items-center justify-center gap-2">
            {primary && (primary.disabled
                ? <ActionButton disabled title={primary.title} action={primary.type} label={primary.label} />
                : <ActionButton asChild title={primary.title} action={primary.type} label={primary.label}><Link href={primary.href} /></ActionButton>)}
            {recordUrl && primary?.href !== recordUrl && <ActionButton asChild action="rme" label={recordLabel ?? `Lihat RME ${patient}`}><Link href={recordUrl} /></ActionButton>}
            {showLetterAction && (letterUrl
                ? <ActionButton asChild action="issue" label={`Terbitkan surat untuk ${patient}`}><Link href={letterUrl} /></ActionButton>
                : <ActionButton action="issue" disabled label={`Surat belum dapat diterbitkan untuk ${patient}`} title="Surat dapat diterbitkan setelah pemeriksaan difinalisasi dan diverifikasi." />)}
        </div>
    );
}
