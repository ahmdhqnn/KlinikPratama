import { Head, Link } from '@inertiajs/react';
import { Activity, AlertTriangle, CalendarDays, ClipboardCheck, HeartPulse, Stethoscope, UsersRound } from 'lucide-react';
import type { ReactNode } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { VisitTable, type DashboardVisit } from '@/components/dashboard/visit-table';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

interface Props {
    stats: { menunggu_ttv: number; siap_dokter: number; triase_darurat: number };
    visits: Array<DashboardVisit & { triage: string | null; priority: string | null }>;
}

export default function NurseDashboard({ stats, visits }: Props) {
    return (
        <>
            <Head title="Dashboard Perawat" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan skrining pasien</h2>
                    <p className="mt-1 text-sm text-neutral-500">Pantau antrean, pemeriksaan tanda vital, dan prioritas triase hari ini.</p>
                </div>
                <section aria-label="Statistik keperawatan" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatCard description="Menunggu atau sedang menjalani skrining" icon={<HeartPulse className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Menunggu tanda vital" value={stats.menunggu_ttv} />
                    <StatCard description="Siap mendapat pemeriksaan dokter" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Siap ke dokter" value={stats.siap_dokter} />
                    <StatCard description="Triase merah yang perlu perhatian" icon={<AlertTriangle className="size-5" />} iconClassName="bg-red-50 text-red-700" label="Triase darurat" value={stats.triase_darurat} />
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle>Akses cepat</CardTitle>
                        <CardDescription>Buka antrean skrining dan informasi layanan yang sering digunakan.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <QuickLink href="/pelayanan/screening" icon={<ClipboardCheck className="size-4" />} label="Antrean skrining" />
                        <QuickLink href="/pendaftaran/kunjungan-per-poli" icon={<Activity className="size-4" />} label="Kunjungan per poli" />
                        <QuickLink href="/pendaftaran/database-pasien" icon={<UsersRound className="size-4" />} label="Database pasien" />
                        <QuickLink href="/pendaftaran/jadwal-praktik" icon={<CalendarDays className="size-4" />} label="Jadwal praktik" />
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ClipboardCheck className="size-5" /></span>
                            <div><CardTitle>Antrean pelayanan hari ini</CardTitle><CardDescription className="mt-1">Kunjungan terlama ditampilkan lebih dahulu.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <VisitTable visits={visits} />
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
