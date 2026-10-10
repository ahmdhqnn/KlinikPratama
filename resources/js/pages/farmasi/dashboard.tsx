import { Head, Link } from '@inertiajs/react';
import { Boxes, CheckCircle2, ClipboardList, PackageSearch, Pill, ShoppingBag, Timer } from 'lucide-react';
import type { ReactNode } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { VisitTable, type DashboardVisit } from '@/components/dashboard/visit-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    stats: { waitingVisits: number; processingVisits: number; completedToday: number; lowStockMedicines: number };
    recentVisits: DashboardVisit[];
}

export default function PharmacyDashboard({ stats, recentVisits }: Props) {
    return (
        <>
            <Head title="Dashboard Farmasi" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Operasional farmasi</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan pelayanan obat</h2>
                    <p className="mt-1 text-sm text-neutral-500">Pantau resep, proses penyerahan obat, dan ketersediaan stok layak pakai hari ini.</p>
                </div>

                <section aria-label="Statistik farmasi" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard description="Menunggu penyiapan obat" icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Menunggu farmasi" value={stats.waitingVisits} />
                    <StatCard description="Sedang disiapkan atau diserahkan" icon={<Timer className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Sedang diproses" value={stats.processingVisits} />
                    <StatCard description="Resep yang diselesaikan hari ini" icon={<CheckCircle2 className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Selesai hari ini" value={stats.completedToday} />
                    <StatCard description="Stok layak pakai mencapai batas minimum" icon={<PackageSearch className="size-5" />} iconClassName={stats.lowStockMedicines > 0 ? 'bg-amber-50 text-amber-800' : 'bg-neutral-50 text-neutral-700'} label="Obat perlu perhatian" value={stats.lowStockMedicines} />
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Akses cepat</CardTitle>
                        <CardDescription>Buka antrean resep, kartu persediaan, dan proses pengadaan.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <QuickLink href="/pelayanan/farmasi" icon={<Pill className="size-4" />} label="Antrean farmasi" />
                        <QuickLink href="/stok/persediaan" icon={<Boxes className="size-4" />} label="Persediaan & batch" />
                        <QuickLink href="/stok/purchase-order" icon={<ShoppingBag className="size-4" />} label="Pengadaan" />
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <CardTitle>Antrean resep hari ini</CardTitle>
                        <CardDescription>Urutan kunjungan tertua ditampilkan lebih dahulu untuk membantu pelayanan.</CardDescription>
                    </CardHeader>
                    <VisitTable visits={recentVisits} />
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
