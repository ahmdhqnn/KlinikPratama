import { router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface Props {
    from: string;
    to: string;
    route: string;
    exportUrl?: string;
    additionalFilters?: Record<string, string | number>;
}

export function DateRangeFilter({ from: initialFrom, to: initialTo, route, exportUrl, additionalFilters }: Props) {
    const [from, setFrom] = useState(initialFrom);
    const [to, setTo] = useState(initialTo);

    function filterReport(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(route, { ...additionalFilters, dari: from, sampai: to }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <Card><CardContent className="flex flex-wrap items-end gap-4 p-5 sm:p-6"><form className="flex flex-1 flex-wrap items-end gap-4" onSubmit={filterReport}>
            <label className="space-y-1.5 text-sm font-medium text-slate-700"><span>Dari tanggal</span><Input onChange={(event) => setFrom(event.target.value)} required type="date" value={from} /></label>
            <label className="space-y-1.5 text-sm font-medium text-slate-700"><span>Sampai tanggal</span><Input min={from} onChange={(event) => setTo(event.target.value)} required type="date" value={to} /></label>
            <Button type="submit"><Search className="size-4" />Filter data</Button>
        </form>{exportUrl && <Button asChild variant="secondary"><a href={exportUrl}><Download className="size-4" />Export Excel</a></Button>}</CardContent></Card>
    );
}
