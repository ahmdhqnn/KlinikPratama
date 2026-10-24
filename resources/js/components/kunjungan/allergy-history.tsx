import { getAllergyStatus } from '@/lib/allergy-status';
import { cn } from '@/lib/utils';

export function AllergyHistory({ value, className }: { value: string | null | undefined; className?: string }) {
    const status = getAllergyStatus([value]);
    const reported = status.state === 'reported';

    return <section aria-label="Riwayat alergi" className={cn('min-w-0 rounded-xl border p-4 text-left', reported ? 'border-red-200 bg-red-50 text-red-900' : 'border-neutral-200 bg-neutral-50 text-neutral-900', className)}>
        <p className={cn('text-xs font-semibold uppercase tracking-wide', reported ? 'text-red-700' : 'text-neutral-600')}>Riwayat alergi</p>
        <p className="mt-1 whitespace-pre-wrap break-words text-sm font-medium [overflow-wrap:anywhere]">{value?.trim() || status.label}</p>
    </section>;
}
