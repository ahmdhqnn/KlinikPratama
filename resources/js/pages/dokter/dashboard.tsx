import { Head, Link } from '@inertiajs/react';
import { CalendarClock, ChartNoAxesCombined, ClipboardCheck, ClipboardList, Pill, Stethoscope, UsersRound } from 'lucide-react';
import type { ReactNode } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { VisitTable, type DashboardVisit } from '@/components/dashboard/visit-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';

interface Props {
    stats: { visitsToday: number; waitingExaminations: number; completedToday: number };
    schedule: Array<{ id: number; clinic: string; start: string; end: string }>;
    recentVisits: DashboardVisit[];
}

export default function DoctorDashboard({ stats, schedule, recentVisits }: Props) {
    return (
        <>
            <Head title="Dashboard Dokter" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Praktik dokter</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan pelayanan</h2>
                    <p className="mt-1 text-sm text-neutral-500">Jadwal praktik dan kunjungan pasien yang ditugaskan kepada Anda.</p>
                </div>
                <section aria-label="Statistik praktik dokter" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatCard description="Seluruh kunjungan yang ditugaskan hari ini" icon={<CalendarClock className="size-5" />} iconClassName="bg-neutral-900 text-neutral-50" label="Kunjungan hari ini" value={stats.visitsToday} />
                    <StatCard description="Menunggu pemeriksaan dokter" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-900 text-neutral-50" label="Menunggu pemeriksaan" value={stats.waitingExaminations} />
                    <StatCard description="Diteruskan ke farmasi atau kunjungan selesai" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-900 text-neutral-50" label="Selesai ditangani" value={stats.completedToday} />
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle>Akses cepat</CardTitle>
                        <CardDescription>Buka antrean, data pasien, dan informasi klinis yang sering digunakan.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <QuickLink href="/pelayanan/pemeriksaan" icon={<Stethoscope className="size-4" />} label="Antrean pemeriksaan" />
                        <QuickLink href="/dokter/kunjungan" icon={<ClipboardList className="size-4" />} label="Kunjungan saya" />
                        <QuickLink href="/dokter/pasien" icon={<UsersRound className="size-4" />} label="Daftar pasien" />
                        <QuickLink href="/dokter/stok-obat" icon={<Pill className="size-4" />} label="Ketersediaan obat" />
                        <QuickLink href="/dokter/laporan-top-diagnosa" icon={<ChartNoAxesCombined className="size-4" />} label="Top diagnosis" />
                    </CardContent>
                </Card>
                <section className="grid gap-6 xl:grid-cols-[minmax(16rem,0.75fr)_minmax(0,1.5fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Jadwal praktik hari ini</CardTitle>
                            <CardDescription>Poliklinik dan jam praktik aktif.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {schedule.length > 0 ? schedule.map((item) => (
                                <div className="flex items-center justify-between gap-4 rounded-xl border border-neutral-100 p-3" key={item.id}>
                                    <div><p className="font-medium text-neutral-900">{item.clinic}</p><p className="mt-1 text-xs text-neutral-500">Poliklinik</p></div>
                                    <p className="shrink-0 rounded-lg bg-neutral-50 px-2.5 py-1.5 text-xs font-semibold text-neutral-700">{item.start}–{item.end}</p>
                                </div>
                            )) : <Empty description="Jadwal dokter yang aktif akan muncul di sini." size="compact" title="Tidak ada jadwal praktik aktif hari ini." />}
                        </CardContent>
                    </Card>
                    <Card className="overflow-hidden">
                        <CardHeader className="border-b border-neutral-100">
                            <CardTitle>Kunjungan terkini</CardTitle>
                            <CardDescription>Daftar kunjungan pasien hari ini.</CardDescription>
                        </CardHeader>
                        <VisitTable visits={recentVisits} />
                    </Card>
                </section>
            </div>
        </>
    );
}

function QuickLink({ href, icon, label }: { href: string; icon: ReactNode; label: string }) {
    return <Button asChild className="h-auto justify-start gap-3 py-3" variant="secondary">
        <Link href={href}>{icon}<span>{label}</span></Link>
    </Button>;
}
