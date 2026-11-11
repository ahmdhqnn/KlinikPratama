import { FollowUp, type FollowUpData } from '@/components/dashboard/follow-up';
import { StatCard } from '@/components/dashboard/stat-card';
import { visitStatusFilterLabels } from '@/components/dashboard/status-badge';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    CalendarCheck2,
    CalendarPlus2,
    ChartNoAxesCombined,
    ClipboardPlus,
    ClipboardList,
    HeartPulse,
    Pill,
    Stethoscope,
    UsersRound,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';

interface DashboardProps {
    followUp: FollowUpData;
    stats: {
        totalPatients: number;
        visitsToday: number;
        visitsThisMonth: number;
        prescriptionsToday: number;
    };
    canRegister: boolean;
    visitStatuses: Record<string, number>;
    visitsByDay: Array<{ tanggal: string; jumlah: number }>;
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

function formatWeekday(date: string): string {
    const [year, month, day] = date.split('-').map(Number);
    const localDate = new Date(year, month - 1, day);

    return new Intl.DateTimeFormat('id-ID', { weekday: 'short' }).format(localDate);
}

const visitBarTones = [
    'bg-neutral-950',
    'bg-neutral-950/90',
    'bg-neutral-950/80',
    'bg-neutral-950/70',
    'bg-neutral-950/60',
    'bg-neutral-950/[0.52]',
    'bg-neutral-950/[0.46]',
] as const;

export default function DashboardPage({ stats, visitStatuses, visitsByDay, canRegister, followUp }: DashboardProps) {
    const activeStatuses = Object.entries(visitStatusFilterLabels).filter(([status]) => (visitStatuses[status] ?? 0) > 0);
    const peakVisits = Math.max(1, ...visitsByDay.map((day) => day.jumlah));
    const today = visitsByDay.at(-1)?.tanggal ?? '';
    const todayReportUrl = `/laporan/kunjungan?dari=${today}&sampai=${today}`;
    const reportStatusUrl = (status: string) => `/laporan/kunjungan?dari=${today}&sampai=${today}&status=${status}`;
    const visitStatusUrls: Record<string, string> = canRegister ? {
        menunggu: `/pendaftaran/kunjungan-per-poli?tanggal=${today}&status=menunggu`,
        screening: `/pelayanan/screening?tanggal=${today}`,
        pemeriksaan: `/pelayanan/pemeriksaan?tanggal=${today}&status=pemeriksaan`,
        tindakan: `/pelayanan/tindakan-perawat?tanggal=${today}&status=pending`,
        farmasi: `/pelayanan/farmasi?tanggal=${today}`,
        kasir: reportStatusUrl('kasir'),
        selesai: reportStatusUrl('selesai'),
        batal: reportStatusUrl('batal'),
    } : Object.fromEntries(Object.keys(visitStatusFilterLabels).map((status) => [status, todayReportUrl]));

    return (
        <>
            <Head title="Dasbor" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-neutral-700">Ikhtisar operasional</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Selamat datang di Klinik Pratama</h2>
                        <p className="mt-1 text-sm text-neutral-500">Pantau aktivitas pelayanan klinik hari ini.</p>
                    </div>
                    {canRegister && <Button asChild>
                        <Link href="/pendaftaran/pendaftaran-baru">
                            <ClipboardPlus className="size-4" />
                            Buat kunjungan
                        </Link>
                    </Button>}
                </div>

                <section aria-label="Ringkasan klinik" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        iconClassName="bg-neutral-100 text-neutral-700"
                        description="Data pasien terdaftar"
                        icon={<UsersRound className="size-5" />}
                        label="Total pasien"
                        href={canRegister ? '/pelayanan/pasien' : '/laporan/kunjungan'}
                        value={stats.totalPatients}
                    />
                    <StatCard
                        iconClassName="bg-neutral-100 text-neutral-700"
                        description="Seluruh kunjungan tercatat hari ini"
                        icon={<CalendarCheck2 className="size-5" />}
                        label="Kunjungan hari ini"
                        href={canRegister ? `/pendaftaran/kunjungan-per-poli?tanggal=${today}` : todayReportUrl}
                        value={stats.visitsToday}
                    />
                    <StatCard
                        iconClassName="bg-neutral-100 text-neutral-700"
                        description="Akumulasi bulan berjalan"
                        icon={<ClipboardList className="size-5" />}
                        label="Kunjungan bulan ini"
                        href={`/laporan/kunjungan?dari=${today.slice(0, 7)}-01&sampai=${today}`}
                        value={stats.visitsThisMonth}
                    />
                    <StatCard
                        iconClassName="bg-neutral-100 text-neutral-700"
                        description="Obat telah diserahkan hari ini"
                        icon={<Pill className="size-5" />}
                        label="Resep diserahkan"
                        href={todayReportUrl}
                        value={stats.prescriptionsToday}
                    />
                </section>
                <FollowUp data={followUp} />

                <section className="grid min-w-0 gap-4 lg:grid-cols-[minmax(16rem,1fr)_minmax(0,1.6fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status kunjungan hari ini</CardTitle>
                            <CardDescription>Pergerakan pasien di setiap tahap pelayanan.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex h-72 flex-col gap-3">
                            <div className="min-h-0 flex-1 space-y-2 overflow-y-auto">
                            {activeStatuses.length === 0 && <Empty size="compact" title="Belum ada kunjungan hari ini" />}
                            {activeStatuses.map(([status, label]) => (
                                <Link aria-label={`${label}: ${formatNumber(visitStatuses[status] ?? 0)}. Lihat daftar terkait`} className="flex items-center justify-between gap-3 rounded-lg p-1 transition-colors hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500" href={visitStatusUrls[status]} key={status}>
                                    <div className="flex items-center gap-3">
                                        <span className={`size-2 rounded-full ${status === 'batal' ? 'bg-red-500' : 'bg-neutral-500'}`} />
                                        <span className="text-sm text-neutral-600">{label}</span>
                                    </div>
                                    <span className="min-w-8 rounded-full bg-neutral-100 px-2 py-1 text-center text-xs font-semibold text-neutral-700">
                                        {formatNumber(visitStatuses[status] ?? 0)}
                                    </span>
                                </Link>
                            ))}
                            </div>
                            <div className="pt-2">
                                <Button asChild className="w-full" variant="secondary">
                                    <Link href={canRegister ? '/pelayanan/antrian' : '/laporan/kunjungan'}>
                                        {canRegister ? 'Buka antrean' : 'Laporan kunjungan'}
                                        <ArrowUpRight className="size-4" />
                                    </Link>
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
                        <CardContent className="flex h-72 min-w-0 flex-col justify-between gap-4">
                            {visitsByDay.length > 0 ? (
                                <div className="grid min-h-0 flex-1 grid-cols-7 items-end gap-1 border-b border-neutral-100 pb-2 sm:gap-3">
                                    {visitsByDay.map((day, index) => (
                                        <Link aria-label={`${formatNumber(day.jumlah)} kunjungan pada ${day.tanggal}. Lihat laporan`} className="group flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2 rounded-t-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500" href={`/laporan/kunjungan?dari=${day.tanggal}&sampai=${day.tanggal}`} key={day.tanggal}>
                                            <span className="text-xs font-medium text-neutral-500">{formatNumber(day.jumlah)}</span>
                                            <div className="flex min-h-0 w-full flex-1 items-end justify-center">
                                                <div
                                                    aria-label={`${formatNumber(day.jumlah)} kunjungan`}
                                                    className={`w-full max-w-12 rounded-lg transition-colors group-hover:bg-neutral-950 group-focus-visible:bg-neutral-950 ${visitBarTones[index % visitBarTones.length]}`}
                                                    style={{ height: `${(day.jumlah / peakVisits) * 100}%` }}
                                                />
                                            </div>
                                            <span className="text-xs text-neutral-500">{formatWeekday(day.tanggal)}</span>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <Empty className="h-44 rounded-xl bg-neutral-50 px-4 py-4" description="Data kunjungan harian akan tampil di sini." size="compact" title="Belum ada data kunjungan dalam periode ini." />
                            )}
                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-neutral-500">
                                <span>Data kunjungan harian</span>
                                <Link className="inline-flex items-center gap-1 font-medium text-neutral-700 hover:text-neutral-800" href="/laporan/kunjungan">
                                    Lihat laporan <ArrowUpRight className="size-3.5" />
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                {canRegister && <Card>
                    <CardHeader>
                        <CardTitle>Akses cepat</CardTitle>
                        <CardDescription>Buka layanan, persediaan, dan laporan yang sering digunakan.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        { label: 'Pendaftaran pasien', href: '/pendaftaran/pendaftaran-baru', icon: UsersRound },
                        { label: 'Kunjungan poli', href: '/pendaftaran/kunjungan-per-poli', icon: ClipboardPlus },
                        { label: 'Pengkajian awal', href: '/pelayanan/screening', icon: HeartPulse },
                        { label: 'Pemeriksaan dokter', href: '/pelayanan/pemeriksaan', icon: Stethoscope },
                        { label: 'Tindakan perawat', href: '/pelayanan/tindakan-perawat', icon: ClipboardList },
                        { label: 'Penyerahan obat', href: '/pelayanan/farmasi', icon: Pill },
                        { label: 'Obat langsung', href: '/farmasi/pemberian-obat', icon: Pill },
                        { label: 'Persediaan & batch', href: '/stok/persediaan', icon: Pill },
                        { label: 'Pusat laporan', href: '/admin/laporan', icon: ChartNoAxesCombined },
                    ].map(({ href, icon: Icon, label }) => (
                        <Button asChild key={href} className="group h-auto w-full justify-start gap-3 py-3" variant="secondary">
                            <Link href={href}><Icon className="size-4 shrink-0" /><span>{label}</span><ArrowRight aria-hidden="true" className="ml-auto size-4 shrink-0 text-neutral-500 transition-transform group-hover:translate-x-0.5" /></Link>
                        </Button>
                    ))}
                    </CardContent>
                </Card>}
            </div>
        </>
    );
}
