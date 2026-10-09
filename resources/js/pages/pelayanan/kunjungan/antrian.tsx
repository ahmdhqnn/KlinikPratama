import { Head, Link, router } from '@inertiajs/react';
import { Activity, RefreshCw } from 'lucide-react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';

interface Visit {
    id: number;
    number: string;
    patient: string;
    clinic: string;
    doctor: string | null;
    status: string;
}

const queues = [
    { title: 'Menunggu dan skrining', statuses: ['menunggu', 'screening'], color: 'border-amber-200 bg-amber-50' },
    { title: 'Pemeriksaan dokter', statuses: ['pemeriksaan'], color: 'border-neutral-200 bg-neutral-50' },
    { title: 'Farmasi', statuses: ['farmasi'], color: 'border-neutral-200 bg-neutral-50' },
];

export default function VisitQueue({ visits, today }: { visits: Visit[]; today: string }) {
    return (
        <>
            <Head title="Antrean Hari Ini" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-neutral-700">Antrean aktif</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Antrean pasien hari ini</h2><p className="mt-1 text-sm text-neutral-500">{today} · {visits.length} kunjungan aktif</p></div><Button onClick={() => router.reload({ only: ['visits', 'today'] })} variant="secondary"><RefreshCw className="size-4" />Perbarui antrean</Button></div>
                <div className="grid min-w-0 grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">{queues.map((queue) => {
                    const queueVisits = visits.filter((visit) => queue.statuses.includes(visit.status));
                    return <Card className="flex min-w-0 flex-col overflow-hidden" key={queue.title}><CardHeader className={`border-b px-5 py-4 ${queue.color}`}><div className="flex items-center justify-between gap-3"><CardTitle className="text-base leading-6">{queue.title}</CardTitle><span className="shrink-0 rounded-full bg-surface px-2.5 py-1 text-sm font-bold text-neutral-800">{queueVisits.length}</span></div></CardHeader><CardContent className="max-h-[70vh] min-h-56 flex-1 space-y-3 overflow-y-auto p-4">
                        {queueVisits.length ? queueVisits.map((visit) => <article className={`rounded-xl border p-4 ${queue.color}`} key={visit.id}><div className="flex flex-wrap items-start justify-between gap-2"><span className="break-all font-mono text-xs font-semibold text-neutral-700">{visit.number}</span><StatusBadge status={visit.status} /></div><p className="mt-3 break-words font-semibold leading-5 text-neutral-900">{visit.patient}</p><p className="mt-1 break-words text-xs leading-5 text-neutral-600">{visit.clinic}{visit.doctor ? ` · ${visit.doctor}` : ''}</p><div className="mt-4 flex justify-end border-t border-neutral-900/10 pt-3"><Button asChild size="sm" variant="ghost"><Link href={`/pelayanan/kunjungan/${visit.id}`}>Detail kunjungan <span aria-hidden="true">→</span></Link></Button></div></article>) : <div className="flex min-h-48 items-center justify-center"><Empty description="Pasien pada tahap ini akan muncul di antrean." icon={<Activity aria-hidden="true" className="size-5" />} size="compact" title="Tidak ada antrean" /></div>}
                    </CardContent></Card>;
                })}</div>
            </div>
        </>
    );
}
