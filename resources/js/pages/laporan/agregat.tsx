import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StatusBadge } from '@/components/dashboard/status-badge';

export default function AggregateReport({ filters, rows, exportUrl }: { filters: { from: string; to: string }; rows: { clinic: string; status: string; total: number }[]; exportUrl: string }) {
    const [period, setPeriod] = useState(filters);
    function filter(event: FormEvent) {
        event.preventDefault();
        router.get('/laporan/kunjungan', { dari: period.from, sampai: period.to }, { preserveState: true, preserveScroll: true });
    }
    return <><Head title="Ringkasan pelayanan" /><div className="space-y-6">
        <div><p className="text-sm font-medium text-neutral-700">Laporan & analitik</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan pelayanan</h2><p className="mt-1 text-sm text-neutral-500">Jumlah kunjungan menurut poliklinik dan tahap pelayanan.</p></div>
        <FilterPanel actions={<Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}><form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={filter}><Field htmlFor="aggregate-period" label="Periode kunjungan"><DateRangePicker id="aggregate-period" from={period.from} to={period.to} onChange={setPeriod} /></Field><FilterSubmit /></form></FilterPanel>
        <Card><CardHeader><CardTitle>Jumlah kunjungan</CardTitle></CardHeader><CardContent><Table><TableHeader><TableRow><TableHead>Poliklinik</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Jumlah</TableHead></TableRow></TableHeader><TableBody>{rows.length ? rows.map((row) => <TableRow key={`${row.clinic}-${row.status}`}><TableCell>{row.clinic}</TableCell><TableCell><StatusBadge status={row.status} /></TableCell><TableCell className="text-right">{row.total.toLocaleString('id-ID')}</TableCell></TableRow>) : <TableRow><TableCell colSpan={3}><Empty size="compact" title="Belum ada kunjungan pada periode ini." /></TableCell></TableRow>}</TableBody></Table></CardContent></Card>
    </div></>;
}
