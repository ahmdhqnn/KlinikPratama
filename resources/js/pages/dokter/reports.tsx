import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { VisitActionButtons } from '@/components/dashboard/visit-action-buttons';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, Download, FileText, Pill, Search, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type ReportType = 'tindakan' | 'obat';

interface ReportRow {
    date: string;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    statusLabel: string;
    name: string;
    quantity: number;
    executionRole?: string | null;
    instruction?: string | null;
    external?: boolean;
    detailUrl: string;
    letterUrl: string | null;
    recordUrl: string | null;
}

interface Props {
    type: ReportType;
    filters: { dari: string; sampai: string; search: string };
    stats: { total: number; secondary: number; tertiary: number; related: number };
    rows: PaginationData & { data: ReportRow[] };
    exportUrl: string;
}

const reportDetails: Record<ReportType, { title: string; description: string; icon: typeof ClipboardCheck; empty: string }> = {
    tindakan: { title: 'Laporan tindakan', description: 'Tindakan yang dicatat pada pemeriksaan Anda.', icon: ClipboardCheck, empty: 'Belum ada tindakan pada periode ini.' },
    obat: { title: 'Laporan obat', description: 'Obat yang diresepkan pada pemeriksaan Anda.', icon: Pill, empty: 'Belum ada obat yang diresepkan pada periode ini.' },
};

export default function DoctorReports({ type, filters, stats, rows, exportUrl }: Props) {
    const [values, setValues] = useState(filters);
    const detail = reportDetails[type];
    const Icon = detail.icon;

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(`/dokter/laporan/${type}`, values, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title={detail.title} />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Laporan pelayanan dokter</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{detail.title}</h2>
                    <p className="mt-1 text-sm text-neutral-500">{detail.description} Atur periode untuk melihat catatan yang sesuai.</p>
                </div>
                <section aria-label="Ringkasan laporan" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label={type === 'tindakan' ? 'Total tindakan' : 'Total obat'} value={stats.total} description="Sesuai filter laporan" icon={<Icon className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label={type === 'tindakan' ? 'Menunggu tindakan' : 'Resep klinik'} value={stats.secondary} description={type === 'tindakan' ? 'Belum selesai dilaksanakan' : 'Obat untuk pelayanan farmasi'} icon={<FileText className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label={type === 'tindakan' ? 'Tindakan selesai' : 'Resep luar'} value={stats.tertiary} description={type === 'tindakan' ? 'Sudah dilaksanakan' : 'Obat ditebus di luar klinik'} icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label={type === 'tindakan' ? 'Kunjungan terkait' : 'Resep terkait'} value={stats.related} description="Sesuai filter laporan" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </section>
                <FilterPanel actions={<Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}>
                    <form className="flex flex-col gap-4 sm:flex-row sm:items-end" onSubmit={applyFilters}>
                        <Field htmlFor="doctor-report-search" label="Cari pasien atau catatan"><Input id="doctor-report-search" onChange={(event) => setValues({ ...values, search: event.target.value })} placeholder="Nama, No. RM, atau nama catatan" value={values.search} /></Field>
                        <Field htmlFor="doctor-report-period" label="Periode kunjungan"><DateRangePicker from={values.dari} id="doctor-report-period" onChange={(range) => setValues({ ...values, dari: range.from, sampai: range.to })} to={values.sampai} /></Field>
                        <Button type="submit"><Search className="size-4" />Terapkan filter</Button>
                        <Button asChild variant="secondary"><Link href={`/dokter/laporan/${type}`}>Reset</Link></Button>
                    </form>
                </FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Icon className="size-5" /></span><div><CardTitle>{detail.title}</CardTitle><CardDescription className="mt-1">{rows.total.toLocaleString('id-ID')} data sesuai pilihan.</CardDescription></div></div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto"><Table className="min-w-[1000px]">
                            <TableHeader><TableRow>
                                <TableHead className="text-center">Tanggal</TableHead><TableHead className="text-center">No. kunjungan</TableHead><TableHead className="text-center">Pasien</TableHead><TableHead className="text-center">Poliklinik</TableHead>
                                <TableHead className="text-center">{type === 'tindakan' ? 'Tindakan' : 'Obat'}</TableHead><TableHead className="text-center">Jumlah</TableHead>{type === 'tindakan' ? <><TableHead className="text-center">Pelaksana</TableHead><TableHead className="text-center">Status</TableHead></> : <><TableHead className="text-center">Aturan pakai</TableHead><TableHead className="text-center">Status resep</TableHead></>}
                                <TableHead className="text-center">Aksi</TableHead>
                            </TableRow></TableHeader>
                            <TableBody>
                                {rows.data.length ? rows.data.map((row, index) => <TableRow key={`${row.number}-${index}`}>
                                    <TableCell className="whitespace-nowrap text-center">{row.date}</TableCell><TableCell className="text-center font-mono text-xs">{row.number}</TableCell><TableCell className="text-center"><p className="font-medium">{row.patient}</p><p className="text-xs text-neutral-500">{row.medicalRecordNumber}</p></TableCell><TableCell className="text-center">{row.clinic}</TableCell>
                                    <TableCell className="max-w-56 text-center"><span className="line-clamp-2">{row.name}</span>{row.external && <span className="mt-1 block text-xs text-neutral-500">Resep luar</span>}</TableCell><TableCell className="text-center tabular-nums">{row.quantity}</TableCell>{type === 'tindakan' ? <><TableCell className="text-center">{row.executionRole === 'perawat' ? 'Perawat' : 'Dokter'}</TableCell><TableCell className="text-center"><Badge className="whitespace-nowrap">{row.statusLabel}</Badge></TableCell></> : <><TableCell className="max-w-48 text-center"><span className="block truncate" title={row.instruction || 'Belum dicatat'}>{row.instruction || 'Belum dicatat'}</span></TableCell><TableCell className="text-center"><Badge className="whitespace-nowrap">{row.statusLabel}</Badge></TableCell></>}
                                    <TableCell className="text-center"><VisitActionButtons letterUrl={row.letterUrl} patient={row.patient} primary={{ href: row.detailUrl, label: `Lihat pemeriksaan ${row.number}`, type: 'detail' }} recordUrl={row.recordUrl} /></TableCell>
                                </TableRow>) : <TableRow><TableCell className="text-center" colSpan={9}><Empty size="compact" title={detail.empty} /></TableCell></TableRow>}
                            </TableBody>
                        </Table></div>
                        <Pagination pagination={rows} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

