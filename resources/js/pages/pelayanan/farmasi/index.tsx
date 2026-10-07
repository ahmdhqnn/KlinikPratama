import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Visit {
    id: number;
    prescriptionNumber: string | null;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    doctor: string | null;
    itemCount: number;
    pharmacyStatus: string;
}

interface Props {
    visits: PaginationData & { data: Visit[] };
    date: string;
    search: string;
}

export default function PharmacyQueue({ visits, date: initialDate, search: initialSearch }: Props) {
    const [date, setDate] = useState(initialDate);
    const [search, setSearch] = useState(initialSearch);

    function filterQueue(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/farmasi', { tanggal: date, search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Antrean Farmasi" />
            <div className="space-y-6">
                <div><p className="text-sm font-medium text-orange-700">Pelayanan obat</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Antrean farmasi</h2><p className="mt-1 text-sm text-slate-500">Siapkan resep, verifikasi jumlah, lalu serahkan obat kepada pasien.</p></div>
                <Card><CardContent className="p-5 sm:p-6"><form className="grid gap-4 md:grid-cols-[minmax(0,1fr)_14rem_auto] md:items-end" onSubmit={filterQueue}><label className="space-y-1.5 text-sm font-medium text-slate-700"><span>Cari pasien atau No. RM</span><Input onChange={(event) => setSearch(event.target.value)} placeholder="Nama pasien atau No. RM" value={search} /></label><label className="space-y-1.5 text-sm font-medium text-slate-700"><span>Tanggal resep</span><Input onChange={(event) => setDate(event.target.value)} type="date" value={date} /></label><Button type="submit"><Search className="size-4" />Filter antrean</Button></form></CardContent></Card>
                <Card className="overflow-hidden"><CardHeader className="border-b border-slate-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-orange-50 text-orange-700"><ClipboardList className="size-5" /></span><div><CardTitle>Resep menunggu dispensing</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} kunjungan ditemukan.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>No. resep</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Jumlah item</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{visits.data.length ? visits.data.map((visit) => <TableRow key={visit.id}><TableCell className="font-mono text-xs font-semibold text-slate-700">{visit.prescriptionNumber ?? '—'}</TableCell><TableCell><p className="font-medium text-slate-900">{visit.patient}</p><p className="font-mono text-xs text-slate-500">{visit.medicalRecordNumber}</p></TableCell><TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor ?? '—'}</TableCell><TableCell>{visit.itemCount} item</TableCell><TableCell><StatusBadge status={visit.pharmacyStatus === 'selesai' ? 'selesai' : 'farmasi'} /></TableCell><TableCell className="text-right"><Button asChild size="sm"><Link href={`/pelayanan/farmasi/${visit.id}`}>Proses resep</Link></Button></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-slate-500" colSpan={7}>Tidak ada resep menunggu penyiapan pada filter ini.</TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={visits} /></CardContent>
                </Card>
            </div>
        </>
    );
}
