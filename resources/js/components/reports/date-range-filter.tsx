import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Field } from '@/components/ui/field';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Props {
    from: string;
    to: string;
    route: string;
    exportUrl?: string;
    additionalFilters?: Record<string, string | number>;
    periodLabel?: string;
}

export function DateRangeFilter({ from: initialFrom, to: initialTo, route, exportUrl, additionalFilters, periodLabel = 'Periode laporan' }: Props) {
    const [from, setFrom] = useState(initialFrom);
    const [to, setTo] = useState(initialTo);

    function filterReport(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(route, { ...additionalFilters, dari: from, sampai: to }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <FilterPanel actions={exportUrl && <Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}>
            <form className="grid min-w-0 gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" onSubmit={filterReport}>
            <Field htmlFor="report-period" label={periodLabel}><DateRangePicker id="report-period" from={from} onChange={(range) => { setFrom(range.from); setTo(range.to); }} to={to} /></Field>
            <FilterSubmit />
        </form>
        </FilterPanel>
    );
}
