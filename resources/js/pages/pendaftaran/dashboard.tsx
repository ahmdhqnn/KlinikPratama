import { Head } from '@inertiajs/react';
import { ArrowUpRight, CalendarCheck2, ClipboardList, UserRoundPlus, UsersRound } from 'lucide-react';
import { StatCard } from '@/components/dashboard/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    stats: {
        visitsToday: number;
        newPatientsToday: number;
        waitingScreening: number;
        inProgress: number;
    };
}

export default function RegistrationDashboard({ stats }: Props) {
    return (
        <>
            <Head title="Dashboard Pendaftaran" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-neutral-700">Meja layanan pendaftaran</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan pendaftaran</h2>
                        <p className="mt-1 text-sm text-neutral-500">Pantau pasien dan alur kunjungan hari ini.</p>
                    </div>
                    <Button asChild>
                        <a href="/pendaftaran/pendaftaran-baru"><UserRoundPlus className="size-4" />Daftarkan pasien</a>
                    </Button>
                </div>

                <section aria-label="Statistik pendaftaran" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard description="Seluruh pasien yang datang hari ini" icon={<CalendarCheck2 className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Kunjungan hari ini" value={stats.visitsToday} />
                    <StatCard description="Pasien yang baru dibuat hari ini" icon={<UserRoundPlus className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Pasien baru" value={stats.newPatientsToday} />
                    <StatCard description="Menunggu pemeriksaan awal perawat" icon={<UsersRound className="size-5" />} iconClassName="bg-amber-50 text-amber-700" label="Menunggu skrining" value={stats.waitingScreening} />
                    <StatCard description="Skrining atau pemeriksaan berlangsung" icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Sedang dilayani" value={stats.inProgress} />
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Akses cepat</CardTitle>
                        <CardDescription>Pekerjaan pendaftaran yang sering digunakan.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {[
                            { href: '/pendaftaran/pendaftaran-baru', label: 'Pendaftaran pasien baru', detail: 'Buat data pasien dan kunjungan.' },
                            { href: '/pendaftaran/pendaftaran-lama', label: 'Pendaftaran pasien lama', detail: 'Daftarkan kunjungan pasien terdaftar.' },
                            { href: '/pendaftaran/laporan-kunjungan', label: 'Laporan kunjungan', detail: 'Cari dan tinjau kunjungan pasien.' },
                        ].map((item) => (
                            <a className="group rounded-xl border border-neutral-200 p-4 transition-colors hover:border-neutral-200 hover:bg-neutral-50/50" href={item.href} key={item.href}>
                                <span className="flex items-center justify-between gap-2 font-medium text-neutral-900">
                                    {item.label}<ArrowUpRight className="size-4 text-neutral-400 transition-colors group-hover:text-neutral-700" />
                                </span>
                                <span className="mt-1 block text-sm text-neutral-500">{item.detail}</span>
                            </a>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
