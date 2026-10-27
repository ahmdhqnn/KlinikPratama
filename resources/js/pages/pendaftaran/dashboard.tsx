import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Empty } from '@/components/ui/empty';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { ActionButton } from '@/components/ui/action-button';
import { FollowUp, type FollowUpData } from '@/components/dashboard/follow-up';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, CalendarDays, CalendarCheck2, ClipboardList, UserRoundPlus, UsersRound } from 'lucide-react';
import { StatCard } from '@/components/dashboard/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    schedules: { id: number; doctorId: number; doctor: string; clinic: string; start: string; end: string }[];
    visits: PaginationData & { data: { id: number; number: string; patient: string; medicalRecordNumber: string; clinic: string; doctor: string; status: string; examinationStarted: boolean; detailUrl: string; ticketUrl: string }[] };

    today: string;
    followUp: FollowUpData;
    stats: {
        visitsToday: number;
        newPatientsToday: number;
        waitingScreening: number;
        inProgress: number;
    };
}

export default function RegistrationDashboard({ stats, followUp, today, schedules, visits }: Props) {
    return (
        <>
            <Head title="Dashboard Pendaftaran" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-neutral-700">Meja layanan pendaftaran</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan pendaftaran</h2>
                        <p className="mt-1 text-sm text-neutral-500">Pantau pasien dan alur kunjungan hari ini.</p>
                    </div>
                    <Button asChild>
                        <Link href="/pendaftaran/pendaftaran-baru"><UserRoundPlus className="size-4" />Daftarkan pasien</Link>
                    </Button>
                </div>

                <section aria-label="Statistik pendaftaran" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard description="Seluruh pasien yang datang hari ini" href={`/pendaftaran/kunjungan-per-poli?tanggal=${today}`} icon={<CalendarCheck2 className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Kunjungan hari ini" value={stats.visitsToday} />
                    <StatCard description="Pasien yang baru dibuat hari ini" href="/pendaftaran/database-pasien" icon={<UserRoundPlus className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Pasien baru" value={stats.newPatientsToday} />
                    <StatCard description="Menunggu pengkajian awal perawat" href={`/pendaftaran/kunjungan-per-poli?status=menunggu&tanggal=${today}`} icon={<UsersRound className="size-5" />} iconClassName="bg-amber-50 text-amber-700" label="Menunggu pengkajian awal" value={stats.waitingScreening} />
                    <StatCard description="Dalam pengkajian atau pemeriksaan" href={`/pendaftaran/kunjungan-per-poli?tanggal=${today}`} icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Sedang dilayani" value={stats.inProgress} />
                </section>
                <FollowUp data={followUp} />
                <section className="grid items-stretch gap-6 xl:gap-y-0 xl:grid-cols-[minmax(16rem,0.75fr)_minmax(0,1.5fr)]">
                <Card className="flex min-w-0 flex-col overflow-hidden xl:row-span-3 xl:grid xl:grid-rows-subgrid xl:gap-y-0">
                    <CardHeader className="border-b border-neutral-100"><CardTitle>Jadwal praktik hari ini</CardTitle><CardDescription>Dokter yang praktik di seluruh poli.</CardDescription></CardHeader>
                    <CardContent className="flex-1 space-y-3 pt-5 sm:pt-6">
                        <div className="max-h-80 space-y-3 overflow-y-auto">
                            {schedules.length ? schedules.map((schedule) => <div className="rounded-xl border border-neutral-200 p-3" key={`${schedule.doctorId}-${schedule.id}`}><p className="font-medium">{schedule.clinic}</p><p className="mt-1 text-sm text-neutral-600">{schedule.doctor}</p><p className="mt-2 text-sm tabular-nums text-neutral-500">{schedule.start} - {schedule.end}</p></div>) : <Empty size="compact" title="Tidak ada jadwal praktik hari ini" />}
                        </div>
                    </CardContent>
                    <div className="flex justify-end border-t border-neutral-100 p-4"><Button asChild size="sm" variant="secondary"><Link href="/pendaftaran/jadwal-praktik"><CalendarDays className="size-4" />Lihat jadwal</Link></Button></div>
                </Card>
                <Card className="flex min-w-0 flex-col overflow-hidden xl:row-span-3 xl:grid xl:grid-rows-subgrid xl:gap-y-0">
                    <CardHeader className="border-b border-neutral-100"><CardTitle>Kunjungan hari ini</CardTitle><CardDescription>Kunjungan terbaru hari ini di semua poli.</CardDescription></CardHeader>
                    <div className="flex min-w-0 flex-1 flex-col">
                    <CardContent className="flex-1 overflow-x-auto p-0">
                        <Table className="min-w-[900px]"><TableHeader><TableRow><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead>Aksi</TableHead></TableRow></TableHeader>
                            <TableBody>{visits.data.length ? visits.data.map((visit) => <TableRow key={visit.id}>
                                <TableCell className="font-mono text-xs">{visit.number}</TableCell>
                                <TableCell><p className="font-medium">{visit.patient}</p><p className="text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                <TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor}</TableCell>
                                <TableCell><StatusBadge status={visit.status} examinationStarted={visit.examinationStarted} /></TableCell>
                                <TableCell><div className="flex justify-center gap-2"><ActionButton asChild action="detail" label={`Detail ${visit.number}`}><Link href={visit.detailUrl} /></ActionButton><ActionButton asChild action="print" label={`Cetak tiket ${visit.number}`}><a href={visit.ticketUrl} /></ActionButton></div></TableCell>
                            </TableRow>) : <TableRow><TableCell colSpan={6}><Empty size="compact" title="Belum ada kunjungan hari ini" /></TableCell></TableRow>}</TableBody>
                        </Table>
                    </CardContent>
                    <Pagination pagination={visits} />
                    </div>
                    <div className="flex justify-end border-t border-neutral-100 p-4"><Button asChild size="sm" variant="secondary"><Link href={`/pendaftaran/kunjungan-per-poli?tanggal=${today}`}>Lihat semua kunjungan<ArrowUpRight className="size-4" /></Link></Button></div>
                </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Akses cepat</CardTitle>
                        <CardDescription>Pekerjaan pendaftaran yang sering digunakan.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {[
                            { href: '/pendaftaran/pendaftaran-baru', label: 'Pendaftaran pasien', detail: 'Buat data pasien dan kunjungan.' },
                            { href: '/pendaftaran/pendaftaran-baru?tab=registered', label: 'Pasien terdaftar', detail: 'Daftarkan kunjungan pasien terdaftar.' },
                            { href: '/pendaftaran/laporan-kunjungan', label: 'Laporan kunjungan', detail: 'Cari dan tinjau kunjungan pasien.' },
                        ].map((item) => (
                            <Link className="group rounded-xl border border-neutral-200 p-4 transition-colors hover:border-neutral-200 hover:bg-neutral-50/50" href={item.href} key={item.href}>
                                <span className="flex items-center justify-between gap-2 font-medium text-neutral-900">
                                    {item.label}<ArrowUpRight className="size-4 text-neutral-400 transition-colors group-hover:text-neutral-700" />
                                </span>
                                <span className="mt-1 block text-sm text-neutral-500">{item.detail}</span>
                            </Link>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
