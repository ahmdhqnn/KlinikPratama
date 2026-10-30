import { StatCard } from '@/components/dashboard/stat-card';
import { Input } from '@/components/ui/input';
import { VisitActionButtons } from '@/components/dashboard/visit-action-buttons';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CalendarDays, ClipboardCheck, Clock3, Download, FileText, Search, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { PriorityBadge } from '@/components/dashboard/priority-badge';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';

interface Visit {
    id: number;
    date: string;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    status: string;
    examinationStarted?: boolean;
    isReservation?: boolean;
    triage?: string | null;
    priority?: string | null;
    examinationUrl: string;
    recordUrl: string;
    letterUrl: string | null;
    diagnoses: string;
    finalizedAt: string | null;
}

interface Props {
    filters: { dari: string; sampai: string; search: string };
    isReport: boolean;
    exportUrl: string | null;
    stats: { total: number; today?: number; waiting?: number; completed?: number; draft?: number; final?: number; patients?: number };
    visits: PaginationData & { data: Visit[] };
}

export default function DoctorAppointments({ filters, visits, isReport, exportUrl, stats }: Props) {
    const [dates, setDates] = useState(filters);
    const pageUrl = isReport ? '/dokter/laporan-pemeriksaan' : '/dokter/kunjungan';
    const title = isReport ? 'Laporan pemeriksaan' : 'Daftar kunjungan pasien';

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(pageUrl, dates, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title={title} />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Praktik dokter</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{title}</h2>
                    <p className="mt-1 text-sm text-neutral-500">{isReport ? 'Pemeriksaan pasien yang Anda tangani, sesuai layanan dan periode yang dipilih.' : 'Kunjungan pasien yang terdaftar untuk pelayanan Anda.'}</p>
                </div>
                <section aria-label={isReport ? 'Ringkasan laporan pemeriksaan' : 'Ringkasan kunjungan dokter'} className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4 [&>section]:h-full [&>section>div]:h-full">
                    <StatCard label={isReport ? 'Total pemeriksaan' : 'Total kunjungan'} value={stats.total} description={isReport ? 'Sesuai filter laporan' : 'Seluruh kunjungan Anda'} icon={<Activity className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label={isReport ? 'Draf pemeriksaan' : 'Kunjungan hari ini'} value={(isReport ? stats.draft : stats.today) ?? 0} description={isReport ? 'Belum difinalisasi' : 'Terdaftar untuk layanan Anda'} icon={isReport ? <FileText className="size-5" /> : <CalendarDays className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label={isReport ? 'Pemeriksaan final' : 'Pengkajian awal'} value={(isReport ? stats.final : stats.waiting) ?? 0} description={isReport ? 'Sudah difinalisasi' : 'Menunggu / sedang dikaji hari ini'} icon={isReport ? <ClipboardCheck className="size-5" /> : <Clock3 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label={isReport ? 'Pasien ditangani' : 'Pemeriksaan selesai'} value={(isReport ? stats.patients : stats.completed) ?? 0} description={isReport ? 'Pasien unik sesuai filter' : 'Kunjungan hari ini sudah final'} icon={isReport ? <UsersRound className="size-5" /> : <ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </section>
                <FilterPanel actions={exportUrl && <Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}>
                    <form className="flex flex-col gap-4 sm:flex-row sm:items-end" onSubmit={applyFilters}>
                        {isReport && <Field htmlFor="doctor-report-search" label="Cari pasien"><Input id="doctor-report-search" placeholder="Cari nama atau No. RM" value={dates.search} onChange={(event) => setDates({ ...dates, search: event.target.value })} /></Field>}
                        <Field htmlFor="doctor-appointments-period" label="Periode kunjungan">
                            <DateRangePicker id="doctor-appointments-period" from={dates.dari} onChange={(range) => setDates({ ...dates, dari: range.from, sampai: range.to })} to={dates.sampai} />
                        </Field>
                        <Button type="submit"><Search className="size-4" />Terapkan filter</Button><Button asChild variant="secondary"><Link href={pageUrl}>Reset</Link></Button>
                    </form>
                </FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><CalendarDays className="size-5" /></span>
                            <div><CardTitle>{isReport ? 'Daftar pemeriksaan' : 'Daftar kunjungan'}</CardTitle><CardDescription className="mt-1">{visits.total} kunjungan dalam hasil pencarian.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[850px]">
                                <TableHeader>
                                    <tr>
                                        <TableHead>No. kunjungan</TableHead>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead>Pasien</TableHead>
                                        <TableHead>Poliklinik</TableHead>
                                        <TableHead className="text-center">Status</TableHead>
                                        <TableHead className="text-center">{isReport ? 'Diagnosis' : 'Prioritas'}</TableHead>
                                        <TableHead className="text-center">Aksi</TableHead>
                                    </tr>
                                </TableHeader>
                                <TableBody>
                                    {visits.data.length > 0 ? visits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-mono text-xs font-semibold text-neutral-700">{visit.number}</TableCell>
                                            <TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                                            <TableCell className="text-center">{isReport ? <div><span className="font-medium">{visit.finalizedAt ? 'Final' : 'Draf'}</span>{visit.finalizedAt && <p className="mt-1 text-xs text-neutral-500">{visit.finalizedAt}</p>}</div> : <StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} status={visit.status} />}</TableCell>
                                            <TableCell className="text-center">{isReport ? <p className="max-w-xs whitespace-normal">{visit.diagnoses || 'Belum dicatat'}</p> : <PriorityBadge triage={visit.triage} priority={visit.priority} />}</TableCell>
                                            <TableCell className="text-center"><VisitActionButtons letterUrl={visit.letterUrl} patient={visit.patient} primary={{ href: visit.examinationUrl, label: visit.status === 'pemeriksaan' ? (visit.examinationStarted ? 'Lanjutkan pemeriksaan' : 'Mulai pemeriksaan') : 'Tinjau pemeriksaan', type: 'examination' }} recordLabel="Rekam medis" recordUrl={visit.recordUrl} /></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Belum ada kunjungan pada rentang tanggal ini." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination pagination={visits} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
