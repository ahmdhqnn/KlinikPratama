import { Head, Link } from '@inertiajs/react';
import { Activity, ArrowUpRight, BarChart3, ChartNoAxesCombined, FolderKanban, Pill } from 'lucide-react';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface ReportLink {
    title: string;
    description: string;
    href: string;
}

interface ReportGroup {
    title: string;
    description: string;
    reports: ReportLink[];
}

export default function AdminReportsPage({
    groups,
    stats,
}: {
    groups: ReportGroup[];
    stats?: { totalReports: number; categories: number; visitsThisMonth: number; activeMedicines: number };
}) {
    const totalReports = stats?.totalReports ?? groups.reduce((sum, g) => sum + g.reports.length, 0);

    return <>
        <Head title="Pusat laporan" />
        <div className="space-y-6">
            <div>
                <p className="text-sm font-medium text-neutral-700">Administrasi klinik</p>
                <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pusat laporan</h2>
                <p className="mt-1 text-sm text-neutral-500">Buka laporan pelayanan klinik, farmasi, atau persediaan.</p>
            </div>
            <SummaryStatGrid ariaLabel="Ringkasan pusat laporan" columns={4}>
                <StatCard label="Laporan tersedia" value={totalReports} description="Modul rekap siap unduh" icon={<BarChart3 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Kelompok laporan" value={stats?.categories ?? groups.length} description="Kategori analitik klinik" icon={<FolderKanban className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Kunjungan bulan ini" value={stats?.visitsThisMonth ?? 0} description="Akumulasi layanan bulan berjalan" icon={<Activity className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Obat & BHP aktif" value={stats?.activeMedicines ?? 0} description="Item aktif dalam katalog" icon={<Pill className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
            </SummaryStatGrid>

            {groups.map((group) => <section aria-label={group.title} className="space-y-3" key={group.title}>
                <div>
                    <h3 className="text-lg font-semibold tracking-tight text-neutral-900">{group.title}</h3>
                    <p className="mt-1 text-sm text-neutral-500">{group.description}</p>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {group.reports.map((report) => <Card className="min-w-0 transition-colors hover:border-neutral-300" key={report.href}>
                        <CardContent className="flex h-full flex-col items-start gap-3 p-5 pt-5 sm:p-6 sm:pt-6">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ChartNoAxesCombined className="size-5" /></span>
                            <div className="min-w-0 flex-1">
                                <h4 className="font-semibold text-neutral-900">{report.title}</h4>
                                <p className="mt-1 text-sm leading-6 text-neutral-500">{report.description}</p>
                            </div>
                            <Button asChild size="sm" variant="secondary">
                                <Link aria-label={`Buka laporan ${report.title}`} href={report.href}>Buka laporan <ArrowUpRight className="size-4" /></Link>
                            </Button>
                        </CardContent>
                    </Card>)}
                </div>
            </section>)}
        </div>
    </>;
}
