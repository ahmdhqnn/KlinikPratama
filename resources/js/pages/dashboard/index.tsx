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
            <CardContent className="flex items-start justify-between gap-4 p-5 sm:p-6">
                <div className="min-w-0">
                    <p className="text-sm font-medium text-slate-500">{label}</p>
                    <p className="mt-3 truncate text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">{value}</p>
                    <p className="mt-2 text-xs text-slate-500">{detail}</p>
                </div>
                <span className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${accent}`}>
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
                        <p className="text-sm font-medium text-blue-700">Ikhtisar operasional</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Selamat datang di Klinik Pratama</h2>
                        <p className="mt-1 text-sm text-slate-500">Pantau aktivitas pelayanan klinik hari ini.</p>
                    </div>
                    <Button asChild variant="secondary">
                        <a href="/pelayanan/kunjungan/create">
                            <ClipboardPlus className="size-4" />
                            Buat kunjungan
                        </a>
                    </Button>
                </div>

                <section aria-label="Ringkasan klinik" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        accent="bg-blue-50 text-blue-700"
                        detail="Data pasien terdaftar"
                        icon={<UsersRound className="size-5" />}
                        label="Total pasien"
                        value={formatNumber(stats.totalPatients)}
                    />
                    <StatCard
                        accent="bg-emerald-50 text-emerald-700"
                        detail="Kunjungan aktif hari ini"
                        icon={<CalendarCheck2 className="size-5" />}
                        label="Kunjungan hari ini"
                        value={formatNumber(stats.visitsToday)}
                    />
                    <StatCard
                        accent="bg-violet-50 text-violet-700"
                        detail="Akumulasi bulan berjalan"
                        icon={<ClipboardList className="size-5" />}
                        label="Kunjungan bulan ini"
                        value={formatNumber(stats.visitsThisMonth)}
                    />
                    <StatCard
                        accent="bg-amber-50 text-amber-700"
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
                                        <span className={`size-2 rounded-full ${status === 'selesai' ? 'bg-emerald-500' : status === 'batal' ? 'bg-red-500' : 'bg-blue-500'}`} />
                                        <span className="text-sm text-slate-600">{label}</span>
                                    </div>
                                    <span className="min-w-8 rounded-full bg-slate-100 px-2 py-1 text-center text-xs font-semibold text-slate-700">
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
                            <span className="rounded-lg bg-blue-50 p-2 text-blue-700"><CalendarPlus2 className="size-4" /></span>
                        </CardHeader>
                        <CardContent>
                            {visitsByDay.length > 0 ? (
                                <div className="flex h-44 items-end gap-3 border-b border-slate-100 pb-2">
                                    {visitsByDay.map((day) => (
                                        <div className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2" key={day.tanggal}>
                                            <span className="text-xs font-medium text-slate-500">{formatNumber(day.jumlah)}</span>
                                            <div className="flex h-28 w-full items-end justify-center">
                                                <div
                                                    aria-label={`${formatNumber(day.jumlah)} kunjungan`}
                                                    className="w-full max-w-10 rounded-t-md bg-blue-500 transition-all hover:bg-blue-600"
                                                    style={{ height: `${Math.max(8, (day.jumlah / peakVisits) * 100)}%` }}
                                                />
                                            </div>
                                            <span className="text-xs text-slate-500">{formatWeekday(day.tanggal)}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="flex h-44 items-center justify-center rounded-xl bg-slate-50 text-sm text-slate-500">
                                    Belum ada data kunjungan dalam periode ini.
                                </div>
                            )}
                            <div className="mt-4 flex items-center justify-between text-xs text-slate-500">
                                <span>Data kunjungan harian</span>
                                <a className="inline-flex items-center gap-1 font-medium text-blue-700 hover:text-blue-800" href="/laporan/kunjungan">
                                    Lihat laporan <ArrowUpRight className="size-3.5" />
                                </a>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <Card className="overflow-hidden">
                    <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-slate-100">
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
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-5 py-3.5">No. kunjungan</th>
                                    <th className="px-5 py-3.5">Pasien</th>
                                    <th className="px-5 py-3.5">Poliklinik</th>
                                    <th className="px-5 py-3.5">Dokter</th>
                                    <th className="px-5 py-3.5">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recentVisits.length > 0 ? recentVisits.map((visit) => (
                                    <tr className="transition-colors hover:bg-slate-50/80" key={visit.id}>
                                        <td className="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{visit.number}</td>
                                        <td className="px-5 py-4">
                                            <p className="font-medium text-slate-900">{visit.patient}</p>
                                            <p className="mt-0.5 text-xs text-slate-500">{visit.medicalRecordNumber}</p>
                                        </td>
                                        <td className="px-5 py-4 text-slate-600">{visit.clinic}</td>
                                        <td className="px-5 py-4 text-slate-600">{visit.doctor ?? 'Belum ditentukan'}</td>
                                        <td className="px-5 py-4">
                                            <Badge variant={statusVariants[visit.status] ?? 'default'}>
                                                {statusLabels[visit.status] ?? visit.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td className="px-5 py-12 text-center text-sm text-slate-500" colSpan={5}>
                                            Belum ada kunjungan yang tercatat hari ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <section aria-label="Aksi cepat" className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        { label: 'Daftarkan pasien', href: '/pelayanan/pasien/create', icon: UsersRound },
                        { label: 'Buka kunjungan', href: '/pelayanan/kunjungan/create', icon: ClipboardPlus },
                        { label: 'Lihat antrian', href: '/pelayanan/antrian', icon: ClipboardList },
                        { label: 'Stok obat', href: '/master/obat', icon: Pill },
                    ].map(({ href, icon: Icon, label }) => (
                        <a className="group flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 text-sm font-medium text-slate-700 shadow-sm transition hover:border-blue-200 hover:bg-blue-50/50 hover:text-blue-800" href={href} key={href}>
                            <span className="flex items-center gap-3">
                                <span className="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600 transition group-hover:bg-blue-100 group-hover:text-blue-700">
                                    <Icon className="size-4" />
                                </span>
                                {label}
                            </span>
                            <ArrowUpRight className="size-4 text-slate-400 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-blue-700" />
                        </a>
                    ))}
                </section>
            </div>
        </>
    );
}
