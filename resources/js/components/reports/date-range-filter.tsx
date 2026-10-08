import { router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { DateRangePicker } from '@/components/ui/date-range-picker';

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
        <Card><CardContent className="flex flex-wrap items-end gap-4 p-5 sm:p-6"><form className="flex min-w-0 flex-1 flex-wrap items-end gap-4" onSubmit={filterReport}>
            <div className="min-w-[min(100%,18rem)] flex-1"><Field htmlFor="report-period" label={periodLabel} required><DateRangePicker id="report-period" from={from} onChange={(range) => { setFrom(range.from); setTo(range.to); }} required to={to} /></Field></div>
            <Button type="submit"><Search className="size-4" />Filter data</Button>
        </form>{exportUrl && <Button asChild variant="secondary"><a href={exportUrl}><Download className="size-4" />Export Excel</a></Button>}</CardContent></Card>
    );
}
