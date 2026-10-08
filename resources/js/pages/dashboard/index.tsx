import { Head } from '@inertiajs/react';
import {
    ArrowUpRight,
    CalendarCheck2,
    CalendarPlus2,
    CircleDollarSign,
    ClipboardPlus,
    ClipboardList,
    Pill,
    UsersRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface DashboardProps {
    stats: {
        totalPatients: number;
        visitsToday: number;
        visitsThisMonth: number;
        revenueThisMonth: number;
    };
    visitStatuses: Record<string, number>;
    visitsByDay: Array<{ tanggal: string; jumlah: number }>;
    recentVisits: Array<{
        id: number;
        number: string;
        patient: string;
        medicalRecordNumber: string;
        clinic: string;
        doctor: string | null;
        status: string;
    }>;
}

const statusLabels: Record<string, string> = {
    menunggu: 'Menunggu',
    screening: 'Skrining',
    pemeriksaan: 'Pemeriksaan',
    farmasi: 'Farmasi',
    kasir: 'Kasir',
    selesai: 'Selesai',
    batal: 'Dibatalkan',
};

const statusVariants: Record<string, 'waiting' | 'screening' | 'examination' | 'pharmacy' | 'cashier' | 'complete' | 'cancelled' | 'default'> = {
    menunggu: 'waiting',
    screening: 'screening',
    pemeriksaan: 'examination',
    farmasi: 'pharmacy',
    kasir: 'cashier',
    selesai: 'complete',
    batal: 'cancelled',
};

function formatNumber(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatWeekday(date: string): string {
    const [year, month, day] = date.split('-').map(Number);
    const localDate = new Date(year, month - 1, day);

    return new Intl.DateTimeFormat('id-ID', { weekday: 'short' }).format(localDate);
}

function StatCard({
    label,
    value,
    detail,
    icon,
    accent,
}: {
    label: string;
    value: string;
    detail: string;
    icon: ReactNode;
    accent: string;
}) {
    return (
        <Card className="min-w-0">
            <CardContent className="flex min-h-32 items-center justify-between gap-4 p-5 sm:p-6">
                <div className="min-w-0">
                    <p className="text-sm font-medium text-neutral-500">{label}</p>
                    <p className="mt-3 truncate text-2xl font-semibold tracking-tight text-neutral-950 sm:text-3xl">{value}</p>
                    <p className="mt-2 text-xs text-neutral-500">{detail}</p>
                </div>
                <span className={`flex size-12 shrink-0 items-center justify-center rounded-xl ${accent}`}>
                    {icon}
                </span>
            </CardContent>
        </Card>
    );
}

export default function DashboardPage({ stats, visitStatuses, visitsByDay, recentVisits }: DashboardProps) {
    const peakVisits = Math.max(1, ...visitsByDay.map((day) => day.jumlah));

    return (
        <>
            <Head title="Dashboard" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-neutral-700">Ikhtisar operasional</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Selamat datang di Klinik Pratama</h2>
                        <p className="mt-1 text-sm text-neutral-500">Pantau aktivitas pelayanan klinik hari ini.</p>
                    </div>
                    <Button asChild>
                        <a href="/pelayanan/kunjungan/create">
                            <ClipboardPlus className="size-4" />
                            Buat kunjungan
                        </a>
                    </Button>
                </div>

                <section aria-label="Ringkasan klinik" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        accent="bg-neutral-900 text-neutral-50"
                        detail="Data pasien terdaftar"
                        icon={<UsersRound className="size-5" />}
                        label="Total pasien"
                        value={formatNumber(stats.totalPatients)}
                    />
                    <StatCard
                        accent="bg-neutral-900 text-neutral-50"
                        detail="Kunjungan aktif hari ini"
                        icon={<CalendarCheck2 className="size-5" />}
                        label="Kunjungan hari ini"
                        value={formatNumber(stats.visitsToday)}
                    />
                    <StatCard
                        accent="bg-neutral-900 text-neutral-50"
                        detail="Akumulasi bulan berjalan"
                        icon={<ClipboardList className="size-5" />}
                        label="Kunjungan bulan ini"
                        value={formatNumber(stats.visitsThisMonth)}
                    />
                    <StatCard
                        accent="bg-neutral-900 text-neutral-50"
                        detail="Transaksi yang telah lunas"
                        icon={<CircleDollarSign className="size-5" />}
                        label="Pendapatan bulan ini"
                        value={formatCurrency(stats.revenueThisMonth)}
                    />
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(18rem,0.8fr)_minmax(0,1.7fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status kunjungan hari ini</CardTitle>
                            <CardDescription>Pergerakan pasien di setiap tahap pelayanan.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {Object.entries(statusLabels).map(([status, label]) => (
                                <div className="flex items-center justify-between gap-3" key={status}>
                                    <div className="flex items-center gap-3">
                                        <span className={`size-2 rounded-full ${status === 'selesai' ? 'bg-emerald-500' : status === 'batal' ? 'bg-red-500' : 'bg-neutral-500'}`} />
                                        <span className="text-sm text-neutral-600">{label}</span>
                                    </div>
                                    <span className="min-w-8 rounded-full bg-neutral-100 px-2 py-1 text-center text-xs font-semibold text-neutral-700">
                                        {formatNumber(visitStatuses[status] ?? 0)}
                                    </span>
                                </div>
                            ))}
                            <div className="pt-2">
                                <Button asChild className="w-full" variant="secondary">
                                    <a href="/pelayanan/antrian">
                                        Buka antrian
                                        <ArrowUpRight className="size-4" />
                                    </a>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-3">
                            <div className="space-y-1.5">
                                <CardTitle>Tren kunjungan</CardTitle>
                                <CardDescription>Pergerakan kunjungan selama tujuh hari terakhir.</CardDescription>
                            </div>
                            <span className="rounded-lg bg-neutral-50 p-2 text-neutral-700"><CalendarPlus2 className="size-4" /></span>
                        </CardHeader>
                        <CardContent>
                            {visitsByDay.length > 0 ? (
                                <div className="flex h-44 items-end gap-3 border-b border-neutral-100 pb-2">
                                    {visitsByDay.map((day) => (
                                        <div className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2" key={day.tanggal}>
                                            <span className="text-xs font-medium text-neutral-500">{formatNumber(day.jumlah)}</span>
                                            <div className="flex h-28 w-full items-end justify-center">
                                                <div
                                                    aria-label={`${formatNumber(day.jumlah)} kunjungan`}
                                                    className="w-full max-w-10 rounded-t-md bg-neutral-950 transition-all hover:bg-neutral-800 "
                                                    style={{ height: `${Math.max(8, (day.jumlah / peakVisits) * 100)}%` }}
                                                />
                                            </div>
                                            <span className="text-xs text-neutral-500">{formatWeekday(day.tanggal)}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <Empty className="h-44 rounded-xl bg-neutral-50 px-4 py-4" description="Data kunjungan harian akan tampil di sini." size="compact" title="Belum ada data kunjungan dalam periode ini." />
                            )}
                            <div className="mt-4 flex items-center justify-between text-xs text-neutral-500">
                                <span>Data kunjungan harian</span>
                                <a className="inline-flex items-center gap-1 font-medium text-neutral-700 hover:text-neutral-800" href="/laporan/kunjungan">
                                    Lihat laporan <ArrowUpRight className="size-3.5" />
                                </a>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <Card className="overflow-hidden">
                    <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-neutral-100">
                        <div className="space-y-1.5">
                            <CardTitle>Kunjungan terkini</CardTitle>
                            <CardDescription>Daftar kunjungan pasien hari ini.</CardDescription>
                        </div>
                        <Button asChild size="sm" variant="ghost">
                            <a href="/pelayanan/kunjungan">
                                Semua kunjungan <ArrowUpRight className="size-4" />
                            </a>
                        </Button>
                    </CardHeader>
                    <div className="overflow-x-auto">
                        <Table className="min-w-[760px]">
                            <TableHeader><tr>
                                <TableHead>No. kunjungan</TableHead>
                                <TableHead>Pasien</TableHead>
                                <TableHead>Poliklinik</TableHead>
                                <TableHead>Dokter</TableHead>
                                <TableHead>Status</TableHead>
                            </tr></TableHeader>
                            <TableBody>
                                {recentVisits.length > 0 ? recentVisits.map((visit) => (
                                    <TableRow key={visit.id}>
                                        <TableCell className="whitespace-nowrap font-mono text-xs text-neutral-600">{visit.number}</TableCell>
                                        <TableCell>
                                            <p className="font-medium text-neutral-900">{visit.patient}</p>
                                            <p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p>
                                        </TableCell>
                                        <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                                        <TableCell className="text-neutral-600">{visit.doctor ?? 'Belum ditentukan'}</TableCell>
                                        <TableCell>
                                            <Badge variant={statusVariants[visit.status] ?? 'default'}>
                                                {statusLabels[visit.status] ?? visit.status}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                )) : (
                                    <TableRow><TableCell colSpan={5}><Empty description="Kunjungan pasien yang terdaftar hari ini akan muncul di sini." size="compact" title="Belum ada kunjungan hari ini" /></TableCell></TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </Card>

                <section aria-label="Aksi cepat" className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        { label: 'Daftarkan pasien', href: '/pelayanan/pasien/create', icon: UsersRound },
                        { label: 'Buka kunjungan', href: '/pelayanan/kunjungan/create', icon: ClipboardPlus },
                        { label: 'Lihat antrian', href: '/pelayanan/antrian', icon: ClipboardList },
                        { label: 'Stok obat', href: '/master/obat', icon: Pill },
                    ].map(({ href, icon: Icon, label }) => (
                        <a className="group flex items-center justify-between rounded-xl border border-neutral-200 bg-surface p-4 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-200 hover:bg-neutral-50/50 hover:text-neutral-800" href={href} key={href}>
                            <span className="flex items-center gap-3">
                                <span className="flex size-9 items-center justify-center rounded-lg bg-neutral-100 text-neutral-600 transition group-hover:bg-neutral-100 group-hover:text-neutral-700">
                                    <Icon className="size-4" />
                                </span>
                                {label}
                            </span>
                            <ArrowUpRight className="size-4 text-neutral-400 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-neutral-700" />
                        </a>
                    ))}
                </section>
            </div>
        </>
    );
}
