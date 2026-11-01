import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, router } from '@inertiajs/react';
import { ClipboardCheck, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface AuditEvent {
    id: number;
    label: string;
    actor: string;
    occurredAt: string;
    visitNumber: string | null;
    clinic: string | null;
    reason: string | null;
    result: string | null;
}

interface Props {
    filters: { from: string; to: string; action: string; search: string };
    actions: Array<{ key: string; label: string }>;
    events: PaginationData & { data: AuditEvent[] };
}

export default function AssistanceAuditPage({ filters, actions, events }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [action, setAction] = useState(filters.action);
    const [search, setSearch] = useState(filters.search);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/admin/audit-bantuan', { dari: from, sampai: to, aksi: action, q: search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <>
        <Head title="Audit bantuan layanan" />
        <div className="space-y-6">
            <div>
                <p className="text-sm font-medium text-neutral-700">Administrasi dan akuntabilitas</p>
                <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Audit bantuan layanan</h2>
                <p className="mt-1 text-sm text-neutral-500">Riwayat tindakan bantuan yang dicatat oleh akun admin. Isi klinis dan data pasien tidak ditampilkan di halaman ini.</p>
            </div>

            <FilterPanel><form className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" onSubmit={applyFilters}>
                <Field htmlFor="audit-from" label="Dari tanggal"><Input id="audit-from" onChange={(event) => setFrom(event.target.value)} type="date" value={from} /></Field>
                <Field htmlFor="audit-to" label="Sampai tanggal"><Input id="audit-to" onChange={(event) => setTo(event.target.value)} type="date" value={to} /></Field>
                <Field htmlFor="audit-action" label="Jenis kegiatan"><Select id="audit-action" onChange={(event) => setAction(event.target.value)} value={action}><option value="">Semua kegiatan bantuan</option>{actions.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</Select></Field>
                <Field htmlFor="audit-search" label="No. kunjungan"><Input id="audit-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nomor kunjungan" value={search} /></Field>
                <Button className="sm:col-span-2 xl:col-span-4" type="submit"><Search className="size-4" />Terapkan filter</Button>
            </form></FilterPanel>

            <Card className="overflow-hidden">
                <div className="overflow-x-auto">
                    <Table className="min-w-[1050px]">
                        <TableHeader><tr><TableHead>Waktu</TableHead><TableHead>Kegiatan</TableHead><TableHead>Hasil</TableHead><TableHead>Operator</TableHead><TableHead>No. kunjungan</TableHead><TableHead>Poliklinik</TableHead><TableHead>Alasan bantuan</TableHead></tr></TableHeader>
                        <TableBody>{events.data.length ? events.data.map((event) => <TableRow key={event.id}>
                            <TableCell className="whitespace-nowrap">{new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(event.occurredAt))}</TableCell>
                            <TableCell className="font-medium">{event.label}</TableCell>
                            <TableCell>{event.result ?? 'Berhasil'}</TableCell>
                            <TableCell>{event.actor}</TableCell>
                            <TableCell className="font-mono text-xs">{event.visitNumber ?? 'Tidak terhubung'}</TableCell>
                            <TableCell>{event.clinic ?? '—'}</TableCell>
                            <TableCell className="max-w-xs whitespace-normal">{event.reason ?? 'Tidak dicatat pada event ini'}</TableCell>
                        </TableRow>) : <TableRow><TableCell colSpan={7}><Empty description="Coba ubah rentang tanggal atau jenis kegiatan." icon={<ClipboardCheck className="size-5" />} size="compact" title="Belum ada riwayat bantuan pada filter ini." /></TableCell></TableRow>}</TableBody>
                    </Table>
                </div>
                <Pagination pagination={events} />
            </Card>
        </div>
    </>;
}
