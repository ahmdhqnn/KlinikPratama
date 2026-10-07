import { Head, Link, router } from '@inertiajs/react';
import { Activity, RefreshCw } from 'lucide-react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
    { title: 'Kasir', statuses: ['kasir'], color: 'border-neutral-200 bg-neutral-50' },
];

export default function VisitQueue({ visits, today }: { visits: Visit[]; today: string }) {
    return (
        <>
            <Head title="Antrean Hari Ini" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-neutral-700">Antrean aktif</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Antrean pasien hari ini</h2><p className="mt-1 text-sm text-neutral-500">{today} · {visits.length} kunjungan aktif</p></div><Button onClick={() => router.reload({ only: ['visits', 'today'] })} variant="secondary"><RefreshCw className="size-4" />Perbarui antrean</Button></div>
                <div className="grid gap-5 sm:grid-cols-2 2xl:grid-cols-4">{queues.map((queue) => {
                    const queueVisits = visits.filter((visit) => queue.statuses.includes(visit.status));
                    return <Card className="overflow-hidden" key={queue.title}><CardHeader className={`border-b ${queue.color}`}><div className="flex items-center justify-between"><CardTitle>{queue.title}</CardTitle><span className="rounded-full bg-surface px-2.5 py-1 text-sm font-bold text-neutral-800">{queueVisits.length}</span></div></CardHeader><CardContent className="max-h-[70vh] space-y-3 overflow-y-auto p-3">
                        {queueVisits.length ? queueVisits.map((visit) => <article className={`rounded-xl border p-3 ${queue.color}`} key={visit.id}><div className="flex items-center justify-between gap-2"><span className="font-mono text-xs font-semibold text-neutral-700">{visit.number}</span><StatusBadge status={visit.status} /></div><p className="mt-2 font-semibold text-neutral-900">{visit.patient}</p><p className="text-xs text-neutral-600">{visit.clinic}{visit.doctor ? ` · ${visit.doctor}` : ''}</p><div className="mt-3 flex justify-end border-t border-inverse/5 pt-2"><Link className="text-xs font-semibold text-neutral-700 hover:underline" href={visit.status === 'menunggu' || visit.status === 'screening' ? `/pelayanan/screening/${visit.id}` : visit.status === 'pemeriksaan' ? `/pelayanan/pemeriksaan/${visit.id}` : visit.status === 'farmasi' ? `/pelayanan/farmasi/${visit.id}` : `/pelayanan/kasir/${visit.id}`}>Buka layanan →</Link></div></article>) : <div className="flex flex-col items-center gap-2 py-10 text-center text-sm text-neutral-400"><Activity className="size-5" />Tidak ada antrean</div>}
                    </CardContent></Card>;
                })}</div>
            </div>
        </>
    );
}
