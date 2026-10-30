import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { ActionButton } from '@/components/ui/action-button';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, ArrowLeft, CalendarDays, Check, ClipboardCheck, Download, Printer, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface ProcedureRow {
    id: number;
    date: string;
    visitNumber: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    doctor: string;
    name: string;
    code: string | null;
    quantity: number;
    tooth: string | null;
    instructions: string | null;
    status: 'pending' | 'completed';
    result: string | null;
    performedBy: string | null;
    performedAt: string | null;
    visitUrl: string | null;
}

interface Props {
    title: string;
    clinicName: string;
    filters: { dari: string; sampai: string; search: string; status: string };
    stats: { total: number; visits: number; pending: number; completed: number };
    procedures: PaginationData & { data: ProcedureRow[] };
    exportUrl: string | null;
    printUrl: string | null;
    printMode: boolean;
}

export default function NursingProcedureReport({ title, clinicName, filters: initialFilters, stats, procedures, exportUrl, printUrl, printMode }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/perawat/laporan-tindakan', filters, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <>
        <Head title={title} />
        <div className="space-y-6">
            {printMode ? <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <Button asChild variant="secondary"><Link href={`/perawat/laporan-tindakan${queryString(filters)}`}><ArrowLeft className="size-4" />Kembali ke laporan tindakan</Link></Button>
                <Button onClick={() => window.print()} type="button"><Printer className="size-4" />Cetak laporan</Button>
            </div> : <div>
                <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan · {clinicName}</p>
                <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{title}</h2>
                <p className="mt-1 text-sm text-neutral-500">Ringkasan tindakan perawat yang disiapkan dokter, berdasarkan tanggal kunjungan dan filter yang dipilih.</p>
            </div>}

            <section aria-label="Ringkasan laporan tindakan" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 print:grid-cols-4">
                <StatCard label="Total tindakan" value={stats.total} description="Sesuai periode dan filter" icon={<Activity className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Kunjungan" value={stats.visits} description="Memiliki tindakan perawat" icon={<CalendarDays className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Menunggu tindakan" value={stats.pending} description="Belum dilaksanakan" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Tindakan selesai" value={stats.completed} description="Hasil pelaksanaan sudah dicatat" icon={<Check className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
            </section>

            {!printMode && <FilterPanel actions={<div className="flex flex-wrap gap-2">
                {printUrl && <Button asChild size="sm" variant="secondary"><a href={printUrl} target="_blank" rel="noreferrer"><Printer className="size-4" />Cetak laporan</a></Button>}
                {exportUrl && <Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}
            </div>}>
                <form className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 xl:items-end" onSubmit={applyFilters}>
                    <Field htmlFor="nursing-procedure-report-search" label="Cari pasien, kunjungan, atau tindakan"><Input id="nursing-procedure-report-search" placeholder="Nama, No. RM, No. kunjungan, atau tindakan" value={filters.search} onChange={(event) => setFilters({ ...filters, search: event.target.value })} /></Field>
                    <Field htmlFor="nursing-procedure-report-period" label="Periode tanggal kunjungan"><DateRangePicker id="nursing-procedure-report-period" from={filters.dari} onChange={(range) => setFilters({ ...filters, dari: range.from, sampai: range.to })} to={filters.sampai} /></Field>
                    <Field htmlFor="nursing-procedure-report-status" label="Status tindakan"><Select id="nursing-procedure-report-status" value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}><option value="">Semua status</option><option value="pending">Menunggu tindakan</option><option value="completed">Selesai</option></Select></Field>
                    <div className="flex gap-2"><Button type="submit"><Search className="size-4" />Terapkan filter</Button><Button asChild variant="secondary"><Link href="/perawat/laporan-tindakan">Reset</Link></Button></div>
                </form>
            </FilterPanel>}

            <Card className="overflow-hidden print:rounded-none print:border-0 print:shadow-none">
                <CardHeader className="border-b border-neutral-100 print:px-0 print:pt-0">
                    <div className="flex items-start gap-3">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700 print:hidden"><ClipboardCheck className="size-5" /></span>
                        <div><CardTitle>{printMode ? 'Laporan tindakan perawat' : 'Daftar tindakan'}</CardTitle><CardDescription className="mt-1">{clinicName} · {new Intl.NumberFormat('id-ID').format(procedures.total)} tindakan sesuai filter.</CardDescription></div>
                    </div>
                </CardHeader>
                <CardContent className="p-0 print:px-0">
                    <div className="overflow-x-auto print:overflow-visible">
                        <Table className={printMode ? 'min-w-[1120px] print:min-w-0' : 'min-w-[980px]'}>
                            <TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Tindakan</TableHead>{printMode && <TableHead>Instruksi dan hasil</TableHead>}<TableHead>Pelaksana</TableHead><TableHead>Status</TableHead>{!printMode && <TableHead className="print:hidden">Aksi</TableHead>}</tr></TableHeader>
                            <TableBody>{procedures.data.length ? procedures.data.map((procedure) => <TableRow className="align-top" key={procedure.id}>
                                <TableCell className="whitespace-nowrap font-mono text-xs text-neutral-600">{procedure.visitNumber}</TableCell>
                                <TableCell className="whitespace-nowrap text-neutral-600">{procedure.date}</TableCell>
                                <TableCell className="min-w-40"><p className="font-medium text-neutral-900">{procedure.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{procedure.medicalRecordNumber}</p><p className="mt-1 text-xs text-neutral-500">{procedure.clinic} · {procedure.doctor}</p></TableCell>
                                <TableCell className={printMode ? 'min-w-44' : 'max-w-64'}><p className={printMode ? 'font-medium text-neutral-900' : 'truncate font-medium text-neutral-900'} title={procedure.name}>{procedure.name}</p>{procedure.code && <p className="mt-1 text-xs text-neutral-500">ICD-9-CM {procedure.code}</p>}<p className="mt-1 text-xs text-neutral-500">Jumlah: {procedure.quantity}{procedure.tooth ? ` · Gigi ${procedure.tooth}` : ''}</p></TableCell>
                                {printMode && <TableCell className="min-w-56"><p className="whitespace-pre-wrap text-neutral-700">{procedure.instructions || 'Tidak ada instruksi tambahan'}</p>{procedure.status === 'completed' && <p className="mt-2 whitespace-pre-wrap border-t border-neutral-100 pt-2 text-xs text-neutral-600">Hasil: {procedure.result || 'Belum ada catatan hasil'}</p>}</TableCell>}
                                <TableCell className="min-w-36">{procedure.status === 'completed' ? <><p className="font-medium text-neutral-800">{procedure.performedBy || 'Belum dicatat'}</p><p className="mt-0.5 text-xs text-neutral-500">{procedure.performedAt || 'Waktu belum dicatat'}</p></> : <span className="text-neutral-500">Belum dilaksanakan</span>}</TableCell>
                                <TableCell><Badge>{procedure.status === 'completed' ? 'Selesai' : 'Menunggu tindakan'}</Badge></TableCell>
                                {!printMode && <TableCell className="print:hidden"><div className="flex justify-center">{procedure.visitUrl && <ActionButton asChild action="detail" label="Lihat pelaksanaan"><Link href={procedure.visitUrl} /></ActionButton>}</div></TableCell>}
                            </TableRow>) : <TableRow><TableCell colSpan={printMode ? 7 : 8}><Empty size="compact" title="Belum ada tindakan sesuai filter." description="Ubah periode atau kata pencarian untuk melihat catatan tindakan lainnya." /></TableCell></TableRow>}</TableBody>
                        </Table>
                    </div>
                    {!printMode && <Pagination pagination={procedures} />}
                </CardContent>
            </Card>
        </div>
    </>;
}

function queryString(filters: Props['filters']): string {
    const query = new URLSearchParams(Object.fromEntries(Object.entries(filters).filter(([, value]) => value)));
    return query.size > 0 ? `?${query.toString()}` : '';
}
