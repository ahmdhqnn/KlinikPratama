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
        <Card><CardContent className="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-6"><form className="grid min-w-0 gap-4 sm:grid-cols-[minmax(18rem,1fr)_auto] sm:items-start" onSubmit={filterReport}>
            <Field htmlFor="report-period" label={periodLabel} required><DateRangePicker id="report-period" from={from} onChange={(range) => { setFrom(range.from); setTo(range.to); }} required to={to} /></Field>
            <Button className="sm:mt-7" type="submit"><Search className="size-4" />Filter data</Button>
        </form>{exportUrl && <Button asChild className="sm:mt-7" variant="secondary"><a href={exportUrl}><Download className="size-4" />Export Excel</a></Button>}</CardContent></Card>
    );
}
