import { Head } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { DateRangeFilter } from '@/components/reports/date-range-filter';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Result {
    id: number;
    date: string | null;
    visitNumber: string;
    patient: string;
    test: string;
    staff: string | null;
    status: string;
}

interface Props {
    filters: { from: string; to: string };
    results: PaginationData & { data: Result[] };
}

export default function LaboratoryReport({ filters, results }: Props) {
    return (
        <>
            <Head title="Laporan Laboratorium" />
            <div className="space-y-6"><div><p className="text-sm font-medium text-violet-700">Hasil penunjang medis</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Laporan laboratorium</h2><p className="mt-1 text-sm text-slate-500">Tinjau pemeriksaan lab dan petugas pelaksana berdasarkan periode.</p></div>
                <DateRangeFilter from={filters.from} to={filters.to} route="/laporan/laboratorium" />
                <Card className="overflow-hidden"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>Tanggal</TableHead><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Pemeriksaan lab</TableHead><TableHead>Petugas lab</TableHead><TableHead>Status</TableHead></tr></TableHeader><TableBody>{results.data.length ? results.data.map((result) => <TableRow key={result.id}><TableCell className="font-mono">{result.date ?? '—'}</TableCell><TableCell className="font-mono text-xs font-semibold">{result.visitNumber}</TableCell><TableCell className="font-medium text-slate-900">{result.patient}</TableCell><TableCell><span className="flex items-center gap-2"><FlaskConical className="size-4 text-violet-600" />{result.test}</span></TableCell><TableCell>{result.staff ?? '—'}</TableCell><TableCell><StatusBadge status={result.status} /></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-slate-500" colSpan={6}>Belum ada pemeriksaan laboratorium pada periode ini.</TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={results} /></Card>
            </div>
        </>
    );
}
