import { Head } from '@inertiajs/react';
import { CalendarClock, ClipboardCheck, Stethoscope } from 'lucide-react';
import { StatCard } from '@/components/dashboard/stat-card';
import { VisitTable, type DashboardVisit } from '@/components/dashboard/visit-table';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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
                    <p className="text-sm font-medium text-blue-700">Praktik dokter</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Ringkasan pelayanan</h2>
                    <p className="mt-1 text-sm text-slate-500">Jadwal praktik dan kunjungan pasien yang ditugaskan kepada Anda.</p>
                </div>
                <section aria-label="Statistik praktik dokter" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatCard description="Seluruh kunjungan yang ditugaskan hari ini" icon={<CalendarClock className="size-5" />} iconClassName="bg-blue-50 text-blue-700" label="Kunjungan hari ini" value={stats.visitsToday} />
                    <StatCard description="Menunggu pemeriksaan dokter" icon={<Stethoscope className="size-5" />} iconClassName="bg-amber-50 text-amber-700" label="Menunggu pemeriksaan" value={stats.waitingExaminations} />
                    <StatCard description="Diteruskan ke farmasi, kasir, atau selesai" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-emerald-50 text-emerald-700" label="Selesai ditangani" value={stats.completedToday} />
                </section>
                <section className="grid gap-6 xl:grid-cols-[minmax(16rem,0.75fr)_minmax(0,1.5fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Jadwal praktik hari ini</CardTitle>
                            <CardDescription>Poliklinik dan jam praktik aktif.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {schedule.length > 0 ? schedule.map((item) => (
                                <div className="flex items-center justify-between gap-4 rounded-xl border border-slate-100 p-3" key={item.id}>
                                    <div><p className="font-medium text-slate-900">{item.clinic}</p><p className="mt-1 text-xs text-slate-500">Poliklinik</p></div>
                                    <p className="shrink-0 rounded-lg bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-700">{item.start}–{item.end}</p>
                                </div>
                            )) : <p className="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Tidak ada jadwal praktik aktif hari ini.</p>}
                        </CardContent>
                    </Card>
                    <Card className="overflow-hidden">
                        <CardHeader className="border-b border-slate-100">
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
