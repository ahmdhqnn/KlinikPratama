import { FollowUp, type FollowUpData } from '@/components/dashboard/follow-up';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { VisitTable, type DashboardVisit } from '@/components/dashboard/visit-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CalendarClock, ClipboardCheck, ClipboardList, Stethoscope, Syringe, UsersRound } from 'lucide-react';
import type { ReactNode } from 'react';

interface Props {
    followUp: FollowUpData;
    stats: { visitsToday: number; totalVisits: number; waitingExaminations: number; completedToday: number };
    schedule: Array<{ id: number; clinic: string; start: string; end: string }>;
    recentVisits: PaginationData & { data: DashboardVisit[] };
}

export default function DoctorDashboard({ stats, schedule, recentVisits, followUp }: Props) {
    const { auth } = usePage<{ auth?: { user?: { clinicType?: string | null } | null } }>().props;
    const practiceLabel = auth?.user?.clinicType === 'gigi' ? 'Praktik dokter gigi' : auth?.user?.clinicType === 'umum' ? 'Praktik dokter umum' : 'Praktik dokter';

    return (
        <>
            <Head title="Dashboard Dokter" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">{practiceLabel}</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan pelayanan</h2>
                    <p className="mt-1 text-sm text-neutral-500">Jadwal praktik dan kunjungan pasien yang ditugaskan kepada Anda.</p>
                </div>
                <section aria-label="Statistik praktik dokter" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard description="Seluruh kunjungan yang ditugaskan kepada Anda" href="/dokter/kunjungan" icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Total kunjungan" value={stats.totalVisits} />
                    <StatCard description="Kunjungan yang ditugaskan kepada Anda hari ini" href="/dokter/kunjungan" icon={<CalendarClock className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Kunjungan saya hari ini" value={stats.visitsToday} />
                    <StatCard description="Menunggu atau sedang diperiksa dokter" href="/pelayanan/pemeriksaan" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Antrean pemeriksaan dokter" value={stats.waitingExaminations} />
                    <StatCard description="Pemeriksaan dokter selesai dan diteruskan ke tahap layanan berikutnya" href="/dokter/kunjungan" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Pemeriksaan selesai" value={stats.completedToday} />
                </section>
                <FollowUp data={followUp} />
                <Card>
                    <CardHeader>
                        <CardTitle>Akses cepat</CardTitle>
                        <CardDescription>Buka antrean pemeriksaan, tindakan, dan data pasien yang sering digunakan.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <QuickLink href="/pelayanan/pemeriksaan" icon={<Stethoscope className="size-4" />} label="Antrean pemeriksaan" />
                        <QuickLink href="/dokter/kunjungan" icon={<ClipboardList className="size-4" />} label="Kunjungan saya" />
                        <QuickLink href="/dokter/pasien" icon={<UsersRound className="size-4" />} label="Daftar pasien" />
                        <QuickLink href="/dokter/tindakan" icon={<Syringe className="size-4" />} label="Tindakan" />
                    </CardContent>
                </Card>
                <section className="grid items-stretch gap-6 xl:gap-y-0 xl:grid-cols-[minmax(16rem,0.75fr)_minmax(0,1.5fr)]">
                    <Card className="flex min-w-0 flex-col overflow-hidden xl:row-span-3 xl:grid xl:grid-rows-subgrid xl:gap-y-0">
                        <CardHeader className="border-b border-neutral-100">
                            <CardTitle>Jadwal praktik hari ini</CardTitle>
                            <CardDescription>Poliklinik dan jam praktik aktif.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex-1 space-y-3 pt-5 sm:pt-6">
                            {schedule.length > 0 ? schedule.map((item) => (
                                <div className="flex items-center justify-between gap-4 rounded-xl border border-neutral-100 p-3" key={item.id}>
                                    <div><p className="font-medium text-neutral-900">{item.clinic}</p><p className="mt-1 text-xs text-neutral-500">Poliklinik</p></div>
                                    <p className="shrink-0 rounded-lg bg-neutral-50 px-2.5 py-1.5 text-xs font-semibold text-neutral-700">{item.start}–{item.end}</p>
                                </div>
                            )) : <Empty description="Jadwal dokter yang aktif akan muncul di sini." size="compact" title="Tidak ada jadwal praktik aktif hari ini." />}
                        </CardContent>
                        <div className="flex justify-end border-t border-neutral-100 p-4"><Button asChild size="sm" variant="secondary"><Link href="/pendaftaran/jadwal-praktik">Lihat jadwal</Link></Button></div>
                    </Card>
                    <Card className="flex min-w-0 flex-col overflow-hidden xl:row-span-3 xl:grid xl:grid-rows-subgrid xl:gap-y-0">
                        <CardHeader className="border-b border-neutral-100">
                            <CardTitle>Kunjungan saya hari ini</CardTitle>
                            <CardDescription>Pasien yang ditugaskan kepada Anda hari ini.</CardDescription>
                        </CardHeader>
                        <div className="flex min-w-0 flex-1 flex-col">
                            <div className="flex-1"><VisitTable visits={recentVisits.data} /></div>
                            <Pagination pagination={recentVisits} />
                        </div>
                        <div className="flex justify-end border-t border-neutral-100 p-4"><Button asChild size="sm" variant="secondary"><Link href="/dokter/kunjungan">Lihat semua kunjungan</Link></Button></div>
                    </Card>
                </section>
            </div>
        </>
    );
}

function QuickLink({ href, icon, label }: { href: string; icon: ReactNode; label: string }) {
    return (
        <Button asChild className="group h-auto w-full justify-start gap-3 py-3" variant="secondary">
            <Link href={href}>{icon}<span>{label}</span><ArrowRight aria-hidden="true" className="ml-auto size-4 shrink-0 text-neutral-500 transition-transform group-hover:translate-x-0.5" /></Link>
        </Button>
    );
}
