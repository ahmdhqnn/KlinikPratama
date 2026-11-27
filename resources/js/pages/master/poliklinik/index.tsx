import { MasterExportButton } from '@/components/admin/master-export-button';
import { ActionButton } from '@/components/ui/action-button';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, CalendarDays, CheckCircle2, DoorOpen } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Select } from '@/components/ui/select';
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
    stats?: { total: number; active: number; totalRooms: number; activeRooms: number };
    clinics: PaginationData & { data: Clinic[] };
    filters: { search: string; jenis?: string; status?: string };
}

export default function ClinicIndex({ stats, clinics, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [jenis, setJenis] = useState(filters.jenis ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    function applyFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/master/poliklinik', { search, jenis, status }, { preserveState: true, preserveScroll: true, replace: true });
    }

    const totalClinics = stats?.total ?? clinics.total;
    const activeClinics = stats?.active ?? clinics.data.filter((c) => c.active).length;

    return (
        <>
            <Head title="Poliklinik & ruang periksa" />
            <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Konfigurasi layanan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Poliklinik & ruang periksa</h2><p className="mt-1 text-sm text-neutral-500">Kelola unit Poli Umum dan Poli Gigi beserta ruang pemeriksaan aktif untuk penugasan layanan dan kesiapan pemetaan lokasi SATUSEHAT.</p></div></div>
                <SummaryStatGrid ariaLabel="Ringkasan poliklinik dan ruang periksa" columns={4}>
                    <StatCard label="Total poliklinik" value={totalClinics} description="Unit pelayanan klinik" icon={<Building2 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Poliklinik aktif" value={activeClinics} description="Tersedia untuk layanan" icon={<CheckCircle2 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Total ruang periksa" value={stats?.totalRooms ?? 0} description="Ruang terdaftar di poli" icon={<DoorOpen className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Ruang aktif" value={stats?.activeRooms ?? 0} description="Siap digunakan praktik" icon={<CheckCircle2 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </SummaryStatGrid>
                <FilterPanel actions={<><Button asChild size="sm" variant="secondary"><Link href="/pendaftaran/jadwal-praktik"><CalendarDays className="size-4" />Jadwal praktik</Link></Button><MasterExportButton catalog="poliklinik" /></>}><form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" onSubmit={applyFilter}><Field htmlFor="clinic-search" label="Cari nama atau kode poli"><Input id="clinic-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode poli" value={search} /></Field>
                    <Field htmlFor="clinic-type" label="Jenis unit"><Select id="clinic-type" value={jenis} onChange={(event) => setJenis(event.target.value)}>
                        <option value="">Semua jenis</option><option value="umum">Poli Umum</option><option value="gigi">Poli Gigi</option>
                    </Select></Field>
                    <Field htmlFor="clinic-status" label="Status poliklinik"><Select id="clinic-status" value={status} onChange={(event) => setStatus(event.target.value)}>
                        <option value="">Semua status</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option>
                    </Select></Field><FilterSubmit /></form></FilterPanel>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Building2 className="size-5" /></span><div><CardTitle>Daftar poliklinik</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(clinics.total)} poliklinik sesuai filter.</CardDescription></div></div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><TableRow><TableHead>Kode</TableHead><TableHead>Nama poliklinik</TableHead><TableHead>Jenis unit</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{clinics.data.length ? clinics.data.map((clinic) => <TableRow key={clinic.id}><TableCell className="font-sans tabular-nums text-xs font-semibold">{clinic.code}</TableCell><TableCell className="font-medium text-neutral-900">{clinic.name}</TableCell><TableCell className="capitalize">{clinic.type}</TableCell><TableCell><Badge>{clinic.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell><TableCell><div className="flex flex-wrap items-center justify-center gap-2"><ActionButton asChild action="rooms" label="Ruang poli"><Link href={`/master/poliklinik/${clinic.id}`} /></ActionButton></div></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Belum ada poliklinik yang sesuai." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={clinics} /></CardContent></Card>
            </div>
        </>
    );
}
