import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, router } from '@inertiajs/react';
import { ClipboardCheck, Download, Printer } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { type PaginationData } from '@/components/dashboard/pagination';
import { ReportTableCard } from '@/components/dashboard/report-table-card';
import { Button } from '@/components/ui/button';
import { Empty } from '@/components/ui/empty';
import { DateRangePicker } from '@/components/ui/date-range-picker';
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
    subject?: string | null;
}

interface Props {
    activityLog?: boolean;
    exportUrl?: string;
    printUrl?: string;
    filters: { from: string; to: string; action: string; search: string };
    actions: Array<{ key: string; label: string }>;
    events: PaginationData & { data: AuditEvent[] };
}

export default function AssistanceAuditPage({ filters, actions, events, activityLog = false, exportUrl, printUrl }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [action, setAction] = useState(filters.action);
    const [search, setSearch] = useState(filters.search);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(activityLog ? '/admin/log-aktivitas' : '/admin/audit-bantuan', { dari: from, sampai: to, aksi: action, q: search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <>
        <Head title={activityLog ? 'Log aktivitas' : 'Audit bantuan layanan'} />
        <div className="space-y-6">
            <div>
                <p className="text-sm font-medium text-neutral-700">Administrasi dan akuntabilitas</p>
                <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{activityLog ? 'Log aktivitas' : 'Audit bantuan layanan'}</h2>
                <p className="mt-1 text-sm text-neutral-500">{activityLog ? 'Jejak akses, ekspor, bantuan layanan, dan pengelolaan akun yang dicatat aplikasi. Aktivitas sebelum pencatatan tersedia tidak direkonstruksi.' : 'Riwayat bantuan layanan oleh admin dan peninjauan draf oleh dokter.'}</p>
            </div>

            <FilterPanel actions={activityLog && <div className="flex flex-wrap gap-2">{exportUrl && <Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}{printUrl && <Button asChild size="sm" variant="secondary"><a href={printUrl}><Printer className="size-4" />Cetak / PDF</a></Button>}</div>}><form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" onSubmit={applyFilters}>
                <Field htmlFor="audit-period" label="Periode kegiatan"><DateRangePicker id="audit-period" from={from} to={to} onChange={(range) => { setFrom(range.from); setTo(range.to); }} /></Field>
                <Field htmlFor="audit-action" label="Jenis kegiatan"><Select id="audit-action" onChange={(event) => setAction(event.target.value)} value={action}><option value="">Semua kegiatan</option>{actions.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</Select></Field>
                <Field htmlFor="audit-search" label={activityLog ? 'Operator / No. kunjungan' : 'No. kunjungan'}><Input id="audit-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nomor kunjungan" value={search} /></Field>
                <FilterSubmit />
            </form></FilterPanel>

            <ReportTableCard title={activityLog ? 'Aktivitas tercatat' : 'Daftar kegiatan bantuan'} description={`${new Intl.NumberFormat('id-ID').format(events.total)} kegiatan tercatat.`} icon={<ClipboardCheck className="size-5" />} pagination={events}>
                <Table className="min-w-[1050px]">
                    <TableHeader><TableRow><TableHead>Waktu</TableHead><TableHead>Kegiatan</TableHead>{activityLog && <TableHead>Objek</TableHead>}<TableHead>Hasil</TableHead><TableHead>Operator</TableHead><TableHead>No. kunjungan</TableHead><TableHead>Poliklinik</TableHead>{!activityLog && <TableHead>Alasan bantuan</TableHead>}</TableRow></TableHeader>
                    <TableBody>{events.data.length ? events.data.map((event) => <TableRow key={event.id}>
                        <TableCell className="whitespace-nowrap">{new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(event.occurredAt))}</TableCell>
                        <TableCell className="font-medium">{event.label}</TableCell>{activityLog && <TableCell>{event.subject ?? '—'}</TableCell>}
                        <TableCell>{event.result ?? 'Berhasil'}</TableCell>
                        <TableCell>{event.actor}</TableCell>
                        <TableCell className="font-sans tabular-nums text-xs">{event.visitNumber ?? 'Tidak terhubung'}</TableCell>
                        <TableCell>{event.clinic ?? '—'}</TableCell>
                        {!activityLog && <TableCell className="max-w-xs whitespace-normal">{event.reason ?? 'Rincian dibatasi sesuai kewenangan'}</TableCell>}
                    </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty description="Coba ubah rentang tanggal atau jenis kegiatan." icon={<ClipboardCheck className="size-5" />} size="compact" title="Belum ada aktivitas pada filter ini." /></TableCell></TableRow>}</TableBody>
                </Table>
            </ReportTableCard>
        </div>
    </>;
}
