import { Head, Link, router } from '@inertiajs/react';
import { ClipboardPlus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import { DatePicker } from '@/components/ui/date-picker';

interface Visit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    gender: string | null;
    clinic: string;
    doctor: string | null;
    status: string;
    diagnosisCount: number;
}

export default function ExaminationsIndex({ visits, date: initialDate }: { visits: PaginationData & { data: Visit[] }; date: string }) {
    const [date, setDate] = useState(initialDate);

    function filterVisits(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/pemeriksaan', { tanggal: date }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Pemeriksaan Dokter" />
            <div className="space-y-6">
                <div><p className="text-sm font-medium text-neutral-700">Layanan klinis</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pemeriksaan dokter</h2><p className="mt-1 text-sm text-neutral-500">Tinjau pasien, catat diagnosis, dan lanjutkan rencana pelayanan.</p></div>
                <Card><CardContent className="p-5 sm:p-6"><form className="flex flex-col gap-4 sm:flex-row sm:items-end" onSubmit={filterVisits}><Label className="w-full space-y-1.5 text-sm font-medium text-neutral-700 sm:max-w-xs"><span>Tanggal kunjungan</span><DatePicker onChange={(event) => setDate(event.target.value)}  value={date} /></Label><Button type="submit"><Search className="size-4" />Tampilkan</Button></form></CardContent></Card>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ClipboardPlus className="size-5" /></span><div><CardTitle>Antrean pemeriksaan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} kunjungan untuk tanggal terpilih.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead>Diagnosis</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>
                        {visits.data.length ? visits.data.map((visit) => <TableRow key={visit.id}><TableCell className="font-mono text-xs font-semibold text-neutral-700">{visit.number}</TableCell><TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="font-mono text-xs text-neutral-500">{visit.medicalRecordNumber} · {visit.gender ?? '—'}</p></TableCell><TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor ?? 'Belum ditentukan'}</TableCell><TableCell><StatusBadge status={visit.status} /></TableCell><TableCell>{visit.diagnosisCount}</TableCell><TableCell className="text-right"><Button asChild size="sm"><Link href={`/pelayanan/pemeriksaan/${visit.id}`}>Buka pemeriksaan</Link></Button></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-neutral-500" colSpan={7}>Tidak ada pasien dalam antrean pemeriksaan pada tanggal ini.</TableCell></TableRow>}
                    </TableBody></Table></div><Pagination pagination={visits} /></CardContent>
                </Card>
            </div>
        </>
    );
}
