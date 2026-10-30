import { ActionButton } from '@/components/ui/action-button';
import { DataScope } from '@/components/dashboard/data-scope';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DatePicker } from '@/components/ui/date-picker';

interface Visit {
    id: number;
    date: string;
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
            <Head title="Resep Farmasi" />
            <div className="space-y-6">
                <div><p className="text-sm font-medium text-neutral-700">Pelayanan obat</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Resep farmasi</h2><p className="mt-1 text-sm text-neutral-500">Siapkan resep, verifikasi jumlah, lalu serahkan obat kepada pasien.</p></div>
                <DataScope todayLabel="Resep hari ini" />
                <FilterPanel><form className="grid gap-4 md:grid-cols-2 md:items-end" onSubmit={filterQueue}><Field htmlFor="pharmacy-search" label="Cari pasien atau No. RM"><Input id="pharmacy-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau No. RM" value={search} /></Field><Field htmlFor="pharmacy-prescription-date" label="Tanggal resep"><DatePicker id="pharmacy-prescription-date" onChange={(event) => setDate(event.target.value)} value={date} /></Field><Button type="submit"><Search className="size-4" />Filter antrean</Button></form></FilterPanel>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardList className="size-5" /></span><div><CardTitle>Daftar resep farmasi</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} resep ditemukan.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>No. resep</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Jumlah item</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader><TableBody>{visits.data.length ? visits.data.map((visit) => {
                        const isComplete = visit.pharmacyStatus === 'selesai';

                        return <TableRow key={visit.id}><TableCell className="font-mono text-xs font-semibold text-neutral-700">{visit.prescriptionNumber ?? 'Belum diisi'}<p className="mt-1 text-xs text-neutral-500">{visit.date}</p></TableCell><TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="font-mono text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell><TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor ?? 'Belum diisi'}</TableCell><TableCell>{visit.itemCount} item</TableCell><TableCell><StatusBadge context="pharmacy" status={visit.pharmacyStatus} /></TableCell><TableCell className="text-center"><ActionButton asChild action={isComplete ? 'detail' : 'pharmacy'} label={isComplete ? 'Lihat detail resep' : 'Proses resep'}><Link href={`/pelayanan/farmasi/${visit.id}`} /></ActionButton></TableCell></TableRow>;
                    }) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Tidak ada resep pada tanggal dan filter ini." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={visits} /></CardContent>
                </Card>
            </div>
        </>
    );
}
