import { VisitActionButtons } from '@/components/dashboard/visit-action-buttons';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CalendarDays, ClipboardCheck, ClipboardPlus, Clock3, Search, Stethoscope } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

interface Visit {
    id: number;
    date: string;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    gender: string | null;
    clinic: string;
    doctor: string | null;
    status: string;
    examinationStarted: boolean;
    canExamine: boolean;
    diagnosisCount: number;
    recordUrl: string | null;
    letterUrl: string | null;
}

interface Props {
    canIssueLetters: boolean;
    visits: PaginationData & { data: Visit[] };
    todayVisits: PaginationData & { data: Visit[] };
    date: string;
    search: string;
    stats: { total: number; waiting: number; inProgress: number; completed: number };
}

export default function ExaminationsIndex({ visits, todayVisits, date: initialDate, search: initialSearch, stats, canIssueLetters }: Props) {
    const [date, setDate] = useState(initialDate);
    const [search, setSearch] = useState(initialSearch);

    function filterVisits(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/pemeriksaan', { search, tanggal: date }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Pemeriksaan Dokter" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Layanan klinis</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pemeriksaan dokter</h2>
                    <p className="mt-1 text-sm text-neutral-500">Pasien yang menunggu pemeriksaan hari ini tampil lebih dulu. Cari kunjungan lain untuk meninjau catatan sebelumnya.</p>
                </div>
                <section aria-label="Ringkasan pemeriksaan hari ini" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Kunjungan dokter hari ini" value={stats.total} description="Kunjungan yang sudah memasuki tahap dokter atau layanan selanjutnya" icon={<Activity className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Menunggu pemeriksaan" value={stats.waiting} description="Belum mulai diperiksa dokter" icon={<Clock3 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Sedang diperiksa" value={stats.inProgress} description="Pemeriksaan dokter belum difinalisasi" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Pemeriksaan selesai hari ini" value={stats.completed} description="Sudah diteruskan ke tahap layanan berikutnya" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </section>
                <VisitList
                    description="Kunjungan yang menunggu atau sedang diperiksa dokter hari ini, dari waktu tunggu terlama."
                    emptyDescription="Pasien akan muncul di sini setelah pengkajian awal selesai."
                    emptyTitle="Belum ada pasien dalam antrean pemeriksaan hari ini."
                    icon={<Clock3 className="size-5" />}
                    title="Pemeriksaan hari ini"
                    canIssueLetters={canIssueLetters}
                    visits={todayVisits}
                />
                <FilterPanel>
                    <form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={filterVisits}>
                        <Field htmlFor="examination-visit-search" label="Cari pasien">
                            <Input id="examination-visit-search" onChange={(event) => setSearch(event.target.value)} placeholder="Nama atau No. RM" value={search} />
                        </Field>
                        <Field htmlFor="examination-visit-date" label="Tanggal kunjungan">
                            <DatePicker id="examination-visit-date" onChange={(event) => setDate(event.target.value)} value={date} />
                        </Field>
                        <Button type="submit"><Search className="size-4" />Terapkan filter</Button>
                    </form>
                </FilterPanel>
                <VisitList
                    description={`${new Intl.NumberFormat('id-ID').format(visits.total)} kunjungan sesuai filter. Tinjau kembali catatan pemeriksaan dan rekam medis pasien.`}
                    emptyDescription="Ubah tanggal atau kata pencarian untuk melihat kunjungan lain."
                    emptyTitle="Belum ada kunjungan yang sesuai."
                    icon={<CalendarDays className="size-5" />}
                    title="Seluruh kunjungan pasien"
                    canIssueLetters={canIssueLetters}
                    visits={visits}
                />
            </div>
        </>
    );
}

function VisitList({ title, description, icon, visits, emptyTitle, emptyDescription, canIssueLetters }: {
    title: string;
    description: string;
    icon: ReactNode;
    visits: PaginationData & { data: Visit[] };
    emptyTitle: string;
    emptyDescription: string;
    canIssueLetters: boolean;
}) {
    return (
        <Card className="overflow-hidden">
            <CardHeader className="border-b border-neutral-100">
                <div className="flex items-start gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">{icon}</span>
                    <div><CardTitle>{title}</CardTitle><CardDescription className="mt-1">{description}</CardDescription></div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto">
                    <Table className="min-w-[980px]">
                        <TableHeader><TableRow>
                            <TableHead className="text-center">No. kunjungan</TableHead>
                            <TableHead className="text-center">Tanggal</TableHead>
                            <TableHead className="text-center">Pasien</TableHead>
                            <TableHead className="text-center">Poliklinik</TableHead>
                            <TableHead className="text-center">Dokter</TableHead>
                            <TableHead className="text-center">Status</TableHead>
                            <TableHead className="text-center">Diagnosis</TableHead>
                            <TableHead className="text-center">Aksi</TableHead>
                        </TableRow></TableHeader>
                        <TableBody>
                            {visits.data.length ? visits.data.map((visit) => (
                                <TableRow key={visit.id}>
                                    <TableCell className="text-center whitespace-nowrap font-mono text-xs font-semibold text-neutral-700">{visit.number}</TableCell>
                                    <TableCell className="text-center whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                                    <TableCell className="text-center"><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 font-mono text-xs text-neutral-500">{visit.medicalRecordNumber} · {visit.gender ?? 'Belum diisi'}</p></TableCell>
                                    <TableCell className="text-center text-neutral-600">{visit.clinic}</TableCell>
                                    <TableCell className="text-center text-neutral-600">{visit.doctor ?? 'Belum ditentukan'}</TableCell>
                                    <TableCell className="text-center"><StatusBadge examinationStarted={visit.examinationStarted} status={visit.status} /></TableCell>
                                    <TableCell className="text-center tabular-nums">{visit.diagnosisCount}</TableCell>
                                    <TableCell className="text-center"><VisitActionButtons letterUrl={visit.letterUrl} patient={visit.patient} primary={{ href: `/pelayanan/pemeriksaan/${visit.id}`, label: visit.canExamine ? (visit.status === 'pemeriksaan' ? (visit.examinationStarted ? 'Lanjutkan pemeriksaan' : 'Mulai pemeriksaan') : 'Tinjau pemeriksaan') : 'Menunggu pengkajian perawat', disabled: !visit.canExamine, title: visit.canExamine ? undefined : 'Pemeriksaan dokter dapat dimulai setelah pengkajian perawat dikirim.', type: 'examination' }} recordLabel="Rekam medis" recordUrl={visit.recordUrl} showLetterAction={canIssueLetters} /></TableCell>
                                </TableRow>
                            )) : <TableRow className="hover:bg-transparent"><TableCell className="text-center" colSpan={8}><Empty description={emptyDescription} size="compact" title={emptyTitle} /></TableCell></TableRow>}
                        </TableBody>
                    </Table>
                </div>
                <Pagination pagination={visits} />
            </CardContent>
        </Card>
    );
}
