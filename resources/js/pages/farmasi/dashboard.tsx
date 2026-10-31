import { FollowUp, type FollowUpData } from '@/components/dashboard/follow-up';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, ClipboardList, FilePlus2, FileText, PackageSearch, Pill, Timer, UsersRound } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { VisitTable, type DashboardVisit } from '@/components/dashboard/visit-table';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    followUp: FollowUpData;
    stats: { waitingVisits: number; processingVisits: number; completedToday: number; lowStockMedicines: number; totalPharmacyVisits: number; externalToday: number };
    recentVisits: PaginationData & { data: DashboardVisit[] };
}

export default function PharmacyDashboard({ stats, recentVisits, followUp }: Props) {
    useEffect(() => {
        const refresh = () => {
            if (document.visibilityState === 'visible') router.reload({ only: ['stats', 'recentVisits', 'followUp'], async: true });
        };
        const interval = window.setInterval(refresh, 15_000);
        window.addEventListener('focus', refresh);
        return () => { window.clearInterval(interval); window.removeEventListener('focus', refresh); };
    }, []);

    return (
        <>
            <Head title="Dashboard Farmasi" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Operasional farmasi</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan pelayanan obat</h2>
                    <p className="mt-1 text-sm text-neutral-500">Pantau penyerahan obat, resep luar, dan persediaan yang perlu perhatian.</p>
                </div>

                <section aria-label="Statistik farmasi" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4 [&>a>section]:h-full [&>a>section>div]:h-full">
                    <StatCard description="Seluruh kunjungan yang masuk layanan farmasi" href="/pelayanan/farmasi" icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Total kunjungan farmasi" value={stats.totalPharmacyVisits} />
                    <StatCard description={`${stats.processingVisits} sedang diproses hari ini`} href="/pelayanan/farmasi" icon={<Timer className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Perlu dilayani hari ini" value={stats.waitingVisits + stats.processingVisits} />
                    <StatCard description="Resep yang diselesaikan hari ini" href="/pelayanan/farmasi" icon={<CheckCircle2 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Penyerahan obat selesai" value={stats.completedToday} />
                    <StatCard description="Stok layak pakai mencapai batas minimum" href="/stok/persediaan" icon={<PackageSearch className="size-5" />} iconClassName={stats.lowStockMedicines > 0 ? 'bg-amber-50 text-amber-800' : 'bg-neutral-100 text-neutral-700'} label="Obat perlu perhatian" value={stats.lowStockMedicines} />
                </section>
                <FollowUp data={followUp} showUpcoming={false} />

                <Card>
                    <CardHeader>
                        <CardTitle>Akses cepat</CardTitle>
                        <CardDescription>Mulai layanan yang sering digunakan. Ada {stats.externalToday} resep luar dari kunjungan hari ini.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <QuickLink href="/pelayanan/farmasi" icon={<Pill className="size-4" />} label="Penyerahan obat" />
                        <QuickLink href="/farmasi/pemberian-obat" icon={<FilePlus2 className="size-4" />} label="Obat langsung" />
                        <QuickLink href="/farmasi/resep-luar" icon={<FileText className="size-4" />} label="Resep luar" />
                        <QuickLink href="/farmasi/pasien" icon={<UsersRound className="size-4" />} label="Pasien" />
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardList className="size-5" /></span>
                            <div><CardTitle>Siap dilayani hari ini</CardTitle><CardDescription className="mt-1">Resep yang menunggu atau sedang diproses. Yang paling lama menunggu ditampilkan lebih dulu.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <VisitTable visits={recentVisits.data} />
                    <Pagination pagination={recentVisits} />
                    <div className="flex justify-end border-t border-neutral-100 p-4"><Button asChild size="sm" variant="secondary"><Link href="/pelayanan/farmasi">Lihat penyerahan obat</Link></Button></div>
                </Card>
            </div>
        </>
    );
}

function QuickLink({ href, icon, label }: { href: string; icon: ReactNode; label: string }) {
    return <Button asChild className="group h-auto w-full justify-start gap-3 py-3" variant="secondary">
        <Link href={href}>{icon}<span>{label}</span><ArrowRight aria-hidden="true" className="ml-auto size-4 shrink-0 text-neutral-500 transition-transform group-hover:translate-x-0.5" /></Link>
    </Button>;
}
