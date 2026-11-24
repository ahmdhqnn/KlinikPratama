import { ActionButton } from '@/components/ui/action-button';
import { Badge } from '@/components/ui/badge';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Clinic {
    id: number;
    code: string;
    name: string;
    type: string;
    active: boolean;
}

interface Props {
    clinics: PaginationData & { data: Clinic[] };
    filters: { search: string };
}

export default function ClinicIndex({ clinics, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    function applyFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/master/poliklinik', { search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Poliklinik" />
            <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Konfigurasi layanan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Poliklinik</h2><p className="mt-1 text-sm text-neutral-500">Buka Poli Umum atau Poli Gigi untuk mengelola ruang periksa.</p></div></div>
                <FilterPanel><form className="grid gap-4 sm:grid-cols-2" onSubmit={applyFilter}><Field htmlFor="clinic-search" label="Cari nama atau kode poli"><Input id="clinic-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode poli" value={search} /></Field><FilterSubmit /></form></FilterPanel>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Building2 className="size-5" /></span><div><CardTitle>Daftar poliklinik</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(clinics.total)} poliklinik sesuai filter.</CardDescription></div></div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><TableRow><TableHead>Kode</TableHead><TableHead>Nama poliklinik</TableHead><TableHead>Jenis unit</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{clinics.data.length ? clinics.data.map((clinic) => <TableRow key={clinic.id}><TableCell className="font-sans tabular-nums text-xs font-semibold">{clinic.code}</TableCell><TableCell className="font-medium text-neutral-900">{clinic.name}</TableCell><TableCell className="capitalize">{clinic.type}</TableCell><TableCell><Badge>{clinic.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell><TableCell><div className="flex flex-wrap items-center justify-center gap-2"><ActionButton asChild action="rooms" label="Ruang poli"><Link href={`/master/poliklinik/${clinic.id}`} /></ActionButton></div></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Belum ada poliklinik yang sesuai." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={clinics} /></CardContent></Card>
            </div>
        </>
    );
}
