import { FollowUp, type FollowUpData } from '@/components/dashboard/follow-up';
import { Head, Link } from '@inertiajs/react';
import { Activity, AlertTriangle, CalendarDays, ClipboardCheck, HeartPulse, Stethoscope, UsersRound } from 'lucide-react';
import type { ReactNode } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { VisitTable, type DashboardVisit } from '@/components/dashboard/visit-table';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';

interface Props {
    followUp: FollowUpData;
    stats: {
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

export default function NurseDashboard({ stats, visits, followUp }: Props) {
    return (
        <>
            <Head title="Dashboard Perawat" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan pengkajian awal</h2>
                    <p className="mt-1 text-sm text-neutral-500">Ringkasan menunjukkan seluruh kunjungan poli; antrean dan kebutuhan respons menampilkan kondisi hari ini.</p>
                </div>
                <section aria-label="Statistik keperawatan" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4 [&>a>section]:h-full [&>a>section>div]:h-full">
                    <StatCard description={`Menunggu atau dalam pengkajian${todayCountDescription(stats.menunggu_ttv_hari_ini)}`} href="/pelayanan/screening" icon={<HeartPulse className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Pengkajian awal" value={stats.menunggu_ttv} />
                    <StatCard description={`Seluruh kunjungan poli${todayCountDescription(stats.total_kunjungan_hari_ini)}`} href="/pendaftaran/kunjungan-per-poli" icon={<Activity className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Total kunjungan poli" value={stats.total_kunjungan} />
                    <StatCard description={`Menunggu / sedang diperiksa${todayCountDescription(stats.siap_dokter_hari_ini)}`} href="/pendaftaran/kunjungan-per-poli?status=pemeriksaan" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Pemeriksaan dokter" value={stats.siap_dokter} />
                    <StatCard description="Memerlukan respons segera hari ini" href="/pelayanan/screening/riwayat" icon={<AlertTriangle className="size-5" />} iconClassName="bg-red-50 text-red-700" label="Perlu respons segera" value={stats.triase_darurat} />
                </section>
                <FollowUp data={followUp} />
                <Card>
                    <CardHeader>
                        <CardTitle>Akses cepat</CardTitle>
                        <CardDescription>Buka antrean pengkajian awal dan informasi layanan yang sering digunakan.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <QuickLink href="/pelayanan/screening" icon={<ClipboardCheck className="size-4" />} label="Antrean pengkajian awal" />
                        <QuickLink href="/pendaftaran/kunjungan-per-poli" icon={<Activity className="size-4" />} label="Kunjungan poli" />
                        <QuickLink href="/pendaftaran/database-pasien" icon={<UsersRound className="size-4" />} label="Database pasien" />
                        <QuickLink href="/pendaftaran/jadwal-praktik" icon={<CalendarDays className="size-4" />} label="Jadwal praktik" />
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ClipboardCheck className="size-5" /></span>
                            <div><CardTitle>Antrean pelayanan hari ini</CardTitle><CardDescription className="mt-1">Pasien dengan waktu tunggu terlama ditampilkan lebih dahulu.</CardDescription></div>
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
