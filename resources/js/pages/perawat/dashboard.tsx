import { FollowUp, type FollowUpData } from '@/components/dashboard/follow-up';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, AlertTriangle, CalendarDays, ClipboardCheck, HeartPulse, Stethoscope, UsersRound } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { VisitTable, type DashboardVisit } from '@/components/dashboard/visit-table';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';

interface Props {
    clinicName: string;
    schedules: { id: number; doctorId: number; doctor: string; clinic: string; start: string; end: string }[];
    followUp: FollowUpData;
    stats: {
        menunggu_tindakan: number;
        total_kunjungan: number;
        total_kunjungan_hari_ini: number;
        menunggu_ttv: number;
        menunggu_ttv_hari_ini: number;
        siap_dokter: number;
        siap_dokter_hari_ini: number;
        triase_darurat: number;
    };
    visits: PaginationData & { data: Array<DashboardVisit & { triage: string | null; priority: string | null }> };
}

export default function NurseDashboard({ stats, visits, followUp, schedules, clinicName }: Props) {
    useEffect(() => {
        const refresh = () => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['stats', 'visits', 'schedules'], async: true });
            }
        };
        const interval = window.setInterval(refresh, 15_000);
        window.addEventListener('focus', refresh);

        return () => { window.clearInterval(interval); window.removeEventListener('focus', refresh); };
    }, []);

    return (
        <>
            <Head title="Dashboard Perawat" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan pelayanan keperawatan</h2>
                    <p className="mt-1 text-sm text-neutral-500">Ringkasan menunjukkan pengkajian dan tindakan poli; antrean dan kebutuhan respons menampilkan kondisi hari ini.</p>
                </div>
                <section aria-label="Statistik keperawatan" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4 [&>a>section]:h-full [&>a>section>div]:h-full">
                    <StatCard description={`Seluruh kunjungan poli Anda${todayCountDescription(stats.total_kunjungan_hari_ini)}`} href="/pendaftaran/kunjungan-per-poli" icon={<Activity className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Total kunjungan" value={stats.total_kunjungan} />
                    <StatCard description="Pengkajian yang masih terbuka hari ini" href="/pelayanan/screening" icon={<HeartPulse className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Pengkajian awal" value={stats.menunggu_ttv} />
                    <StatCard description={`Menunggu / sedang diperiksa${todayCountDescription(stats.siap_dokter_hari_ini)}`} href="/pendaftaran/kunjungan-per-poli?status=pemeriksaan" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Pemeriksaan dokter" value={stats.siap_dokter} />
                    <StatCard description="Kunjungan hari ini dengan triase merah yang belum selesai" icon={<AlertTriangle className="size-5" />} iconClassName="bg-red-50 text-red-700" label="Triase merah aktif" value={stats.triase_darurat} />
                </section>
                <section aria-label="Jadwal dan tindakan perawat" className="grid items-stretch gap-4 lg:grid-cols-2 [&>a>section]:h-full [&>a>section>div]:h-full">
                    <Link aria-label={`Jadwal praktik hari ini di ${clinicName}. Lihat jadwal`} className="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 focus-visible:ring-offset-2" href="/pendaftaran/jadwal-praktik">
                        <Card className="min-w-0 transition-colors group-hover:border-neutral-300">
                            <CardContent className="flex min-h-32 items-center justify-between gap-4 p-5 sm:p-6">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-neutral-500">Jadwal praktik hari ini · {clinicName}</p>
                                    {schedules.length ? <div className="mt-2 space-y-1">
                                        {schedules.slice(0, 2).map((schedule) => <p className="flex flex-wrap items-baseline gap-x-2 text-sm" key={`${schedule.doctorId}-${schedule.id}`}><span className="font-medium text-neutral-900">{schedule.doctor}</span><span className="tabular-nums text-neutral-500">{schedule.start}–{schedule.end}</span></p>)}
                                        {schedules.length > 2 && <p className="text-xs text-neutral-500">+{schedules.length - 2} jadwal lainnya</p>}
                                    </div> : <p className="mt-2 text-sm text-neutral-500">Tidak ada dokter terjadwal hari ini.</p>}
                                    <p className="mt-2 text-xs font-medium text-neutral-600">Lihat jadwal</p>
                                </div>
                                <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><CalendarDays className="size-5" /></span>
                            </CardContent>
                        </Card>
                    </Link>
                    <StatCard description="Instruksi dokter yang menunggu pelaksanaan, termasuk kunjungan sebelumnya" href="/pelayanan/tindakan-perawat" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Tindakan perawat" value={stats.menunggu_tindakan} />
                </section>
                <FollowUp data={followUp} />
                <Card>
                    <CardHeader>
                        <CardTitle>Akses cepat</CardTitle>
                        <CardDescription>Buka pengkajian, tindakan, dan informasi layanan yang sering digunakan.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <QuickLink href="/pelayanan/screening" icon={<HeartPulse className="size-4" />} label="Antrean pengkajian awal" />
                        <QuickLink href="/pelayanan/tindakan-perawat" icon={<ClipboardCheck className="size-4" />} label="Tindakan perawat" />
                        <QuickLink href="/pendaftaran/kunjungan-per-poli" icon={<Activity className="size-4" />} label="Kunjungan poli" />
                        <QuickLink href="/pendaftaran/database-pasien" icon={<UsersRound className="size-4" />} label="Database pasien" />
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ClipboardCheck className="size-5" /></span>
                            <div><CardTitle>Siap dilayani hari ini</CardTitle><CardDescription className="mt-1">Pengkajian dan tindakan yang dapat dikerjakan perawat; pasien dengan waktu tunggu terlama ditampilkan lebih dahulu.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <VisitTable visits={visits.data} />
                    <Pagination pagination={visits} />
                    <div className="flex justify-end border-t border-neutral-100 p-4"><Button asChild size="sm" variant="secondary"><Link href="/pelayanan/screening">Lihat antrean pengkajian</Link></Button></div>
                </Card>
            </div>
        </>
    );
}

function QuickLink({ href, icon, label }: { href: string; icon: ReactNode; label: string }) {
    return <Button asChild className="h-auto justify-start gap-3 py-3" variant="secondary">
        <Link href={href}>{icon}<span>{label}</span></Link>
    </Button>;
}

function todayCountDescription(value: number): string {
    return value > 0 ? `; hari ini: ${new Intl.NumberFormat('id-ID').format(value)}` : '';
}
