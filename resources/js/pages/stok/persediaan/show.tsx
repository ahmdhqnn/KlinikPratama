import { ActionButton } from '@/components/ui/action-button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Boxes, ClipboardList, Package, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableScroll } from '@/components/ui/table';
import { Empty } from '@/components/ui/empty';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { formatNumber } from '@/lib/format';

interface Batch { id: number; number: string; expiry: string | null; stock: number; status: string; source: string; reference: string; expired: boolean; nearExpiry: boolean }
interface Movement { id: number; date: string; type: string; batch: string | null; quantity: number; before: number; after: number; actor: string | null; reason: string | null; canReturn: boolean }
interface Props {
    medicine: { id: number; name: string; code: string; stock: number; unit: string };
    batches: Batch[];
    batchBalance?: number;
    movements: PaginationData & { data: Movement[] };
    filters?: { batch_status?: string; jenis_mutasi?: string; search?: string };
    today: string;
}
type Action = 'receive' | 'reconcile' | 'adjust' | 'return';
const titles: Record<Action, string> = { receive: 'Penerimaan batch', reconcile: 'Verifikasi batch fisik', adjust: 'Stok opname / rusak / pemusnahan', return: 'Retur penyerahan obat' };

export default function StockCard({ medicine, batches, batchBalance, movements, filters: initialFilters }: Props) {
    const [action, setAction] = useState<Action | null>(null);
    const [id, setId] = useState<number | null>(null);
    const [batchStatus, setBatchStatus] = useState(initialFilters?.batch_status ?? '');
    const [movementType, setMovementType] = useState(initialFilters?.jenis_mutasi ?? '');
    const [search, setSearch] = useState(initialFilters?.search ?? '');
    const initialData = { nomor_batch: '', expired_at: '', jumlah: '', sumber: 'distribusi', referensi: '', jenis: 'opname', alasan: '' };
    const form = useForm(initialData);
    const errors = form.errors as Record<string, string | undefined>;

    const usableStock = batches.reduce((sum, b) => sum + (!b.expired && b.status === 'aktif' && b.stock > 0 ? b.stock : 0), 0);
    const alertBatchCount = batches.filter((b) => b.stock > 0 && (b.expired || b.nearExpiry || b.status === 'karantina')).length;

    function applyFilters(event?: FormEvent<HTMLFormElement>) {
        event?.preventDefault();
        router.get(`/stok/persediaan/${medicine.id}`, {
            batch_status: batchStatus,
            jenis_mutasi: movementType,
            search,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function open(next: Action, target?: number) { form.setData(initialData); form.setDefaults(initialData); form.clearErrors(); setId(target ?? null); setAction(next); }
    function openExpiredDisposal(batch: Batch) {
        open('adjust', batch.id);
        form.setData({ ...initialData, jenis: 'pemusnahan', jumlah: String(batch.stock) });
    }
    function save(event: FormEvent) {
        event.preventDefault();
        const url = action === 'receive' ? `/stok/persediaan/${medicine.id}/terima` : action === 'return' ? `/stok/mutasi/${id}/retur` : `/stok/batch/${id}/${action === 'reconcile' ? 'verifikasi' : 'opname'}`;
        form.post(url, { preserveScroll: true, onSuccess: () => setAction(null) });
    }
    return <><Head title={`Batch & kartu stok · ${medicine.name}`} /><div className="space-y-6">
        <PageHeaderWithActions
            actions={<Button onClick={() => open('receive')} size="sm"><Plus className="size-4" />Catat penerimaan</Button>}
            backHref="/stok/persediaan"
            backLabel="Kembali ke persediaan"
            eyebrow="Pengelolaan obat"
            subtitle={`${medicine.name} · ${medicine.code} · Satuan ${medicine.unit}`}
            title="Batch & kartu stok"
        />
        <SummaryStatGrid ariaLabel="Ringkasan batch dan kartu stok" columns={4}>
            <StatCard label="Saldo fisik" value={`${formatNumber(medicine.stock)} ${medicine.unit}`} description="Total saldo tercatat" icon={<Boxes className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
            <StatCard label="Layak pakai" value={`${formatNumber(usableStock)} ${medicine.unit}`} description="Batch aktif & belum ED" icon={<Package className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
            <StatCard label="Batch perhatian" value={alertBatchCount} description="Karantina, ED, atau ≤ 90 hari" icon={<AlertTriangle className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
            <StatCard label="Riwayat mutasi" value={movements.total} description="Catatan keluar-masuk stok" icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
        </SummaryStatGrid>
        {batchBalance !== undefined && Math.abs(medicine.stock - batchBalance) >= 0.005 && (
            <Alert dismissible={false} variant="default">
                <AlertTriangle aria-hidden="true" className="size-4" />
                <AlertTitle>Selisih saldo master dan total batch</AlertTitle>
                <AlertDescription>
                    Saldo master {formatNumber(medicine.stock)} {medicine.unit} berbeda dari total batch {formatNumber(batchBalance)} {medicine.unit}. Verifikasi saldo dan riwayat persediaan sebelum mencatat transaksi.
                </AlertDescription>
            </Alert>
        )}
        <div className="space-y-3">
            <FilterPanel ariaLabel="Filter batch dan kedaluwarsa" filterKeys={['batch_status']}>
                <form className="grid min-w-0 gap-4 sm:grid-cols-2" onSubmit={applyFilters}>
                    <Field htmlFor="stock-card-batch-status" label="Kondisi batch">
                        <Select id="stock-card-batch-status" onChange={(event) => setBatchStatus(event.target.value)} value={batchStatus}>
                            <option value="">Semua kondisi batch</option>
                            <option value="aktif">Aktif / layak pakai</option>
                            <option value="karantina">Karantina</option>
                            <option value="near_expiry">Kedaluwarsa ≤ 90 hari</option>
                            <option value="expired">Kedaluwarsa</option>
                        </Select>
                    </Field>
                    <FilterSubmit />
                </form>
            </FilterPanel>
            <Card className="overflow-hidden">
                <CardHeader className="border-b border-neutral-100">
                    <div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Package className="size-5" /></span><div><CardTitle>Batch & kedaluwarsa</CardTitle><CardDescription className="mt-1">Stok obat dan BHP klinik digunakan dari batch layak pakai yang kedaluwarsa paling awal. Saldo karantina wajib diperiksa secara fisik.</CardDescription></div></div>
                </CardHeader>
                <CardContent className="p-0"><TableScroll className="overflow-x-auto"><Table className="min-w-[950px]"><TableHeader><TableRow><TableHead className="text-center">Batch / sumber</TableHead><TableHead className="text-center">Kedaluwarsa</TableHead><TableHead className="text-center">Saldo</TableHead><TableHead className="text-center">Kondisi</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{batches.length ? batches.map((batch) => <TableRow key={batch.id}><TableCell className="text-center"><p className="font-sans tabular-nums text-xs font-semibold">{batch.number}</p><p className="mt-1 text-xs text-neutral-500">{batch.source} · {batch.reference}</p></TableCell><TableCell className="text-center">{batch.expiry ?? 'Belum diketahui'}</TableCell><TableCell className="text-center">{formatNumber(batch.stock)} {medicine.unit}</TableCell><TableCell className="text-center"><Badge variant={batch.expired && batch.stock > 0 ? 'cancelled' : batch.status === 'karantina' || batch.nearExpiry ? 'warning' : 'default'}>{batch.stock <= 0 ? 'Saldo habis' : batch.expired ? 'Kedaluwarsa' : batch.status === 'karantina' ? 'Karantina' : batch.nearExpiry ? '≤ 90 hari' : !batch.expiry ? 'Kedaluwarsa belum diketahui' : 'Layak pakai'}</Badge></TableCell><TableCell className="text-center"><div className="flex flex-wrap items-center justify-center gap-2"><ActionButton disabledReason="Verifikasi hanya tersedia untuk batch karantina dengan stok positif." disabled={!(batch.status === 'karantina' && batch.stock > 0)} onClick={() => open('reconcile', batch.id)} action="verify" label="Verifikasi" /><ActionButton onClick={() => open('adjust', batch.id)} action="adjust" label="Opname / kondisi" />{batch.expired && batch.stock > 0 && <Button type="button" variant="secondary" size="sm" onClick={() => openExpiredDisposal(batch)}>Catat pemusnahan</Button>}</div></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell className="text-center" colSpan={5}><Empty title="Belum ada batch sesuai filter" description="Catat penerimaan pertama dengan nomor batch dan kedaluwarsa." size="compact" /></TableCell></TableRow>}</TableBody></Table></TableScroll></CardContent>
            </Card>
        </div>
        <div className="space-y-3">
            <FilterPanel ariaLabel="Filter kartu stok" filterKeys={['search', 'jenis_mutasi']}>
                <form className="grid min-w-0 gap-4 sm:grid-cols-2" onSubmit={applyFilters}>
                    <Field htmlFor="stock-card-search" label="Cari batch / alasan">
                        <Input id="stock-card-search" onChange={(event) => setSearch(event.target.value)} placeholder="Nomor batch, referensi, atau alasan mutasi" value={search} />
                    </Field>
                    <Field htmlFor="stock-card-movement-type" label="Jenis mutasi">
                        <Select id="stock-card-movement-type" onChange={(event) => setMovementType(event.target.value)} value={movementType}>
                            <option value="">Semua mutasi</option>
                            <option value="penerimaan">Penerimaan</option>
                            <option value="penyerahan">Penyerahan</option>
                            <option value="retur">Retur</option>
                            <option value="opname">Opname</option>
                            <option value="rusak">Rusak</option>
                            <option value="pemusnahan">Pemusnahan</option>
                        </Select>
                    </Field>
                    <FilterSubmit />
                </form>
            </FilterPanel>
            <Card className="overflow-hidden">
                <CardHeader className="border-b border-neutral-100">
                    <div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardList className="size-5" /></span><div><CardTitle>Kartu stok & jejak petugas</CardTitle><CardDescription className="mt-1">Setiap perubahan memiliki saldo sebelum/sesudah, batch, alasan, dan petugas.</CardDescription></div></div>
                </CardHeader>
                <CardContent className="p-0"><TableScroll className="overflow-x-auto"><Table className="min-w-[1000px]"><TableHeader><TableRow><TableHead className="text-center">Waktu / petugas</TableHead><TableHead className="text-center">Jenis</TableHead><TableHead className="text-center">Batch</TableHead><TableHead className="text-center">Jumlah</TableHead><TableHead className="text-center">Total sebelum → sesudah</TableHead><TableHead className="text-center">Referensi / alasan</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{movements.data.length ? movements.data.map((movement) => <TableRow key={movement.id}><TableCell className="text-center text-xs">{movement.date}<p className="mt-1 text-neutral-500">{movement.actor ?? 'Data historis'}</p></TableCell><TableCell className="text-center">{movement.type.replaceAll('_', ' ')}</TableCell><TableCell className="text-center font-sans tabular-nums text-xs">{movement.batch ?? 'Historis'}</TableCell><TableCell className="text-center">{formatNumber(movement.quantity)}</TableCell><TableCell className="text-center font-sans tabular-nums text-xs">{formatNumber(movement.before)} → {formatNumber(movement.after)}</TableCell><TableCell className="max-w-64 text-center text-xs">{movement.reason ?? 'Belum dicatat'}</TableCell><TableCell className="text-center"><ActionButton disabledReason="Mutasi ini tidak memenuhi syarat retur." disabled={!movement.canReturn} onClick={() => open('return', movement.id)} action="return" label="Retur" /></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell className="text-center" colSpan={7}><Empty title="Belum ada mutasi stok sesuai filter" size="compact" /></TableCell></TableRow>}</TableBody></Table></TableScroll><Pagination pagination={movements} /></CardContent>
            </Card>
        </div>
    </div>
    <Dialog open={action !== null} onOpenChange={(open) => { if (!open) setAction(null); }}><DialogContent className="max-h-[90vh] overflow-y-auto"><DialogHeader><DialogTitle>{action ? titles[action] : ''}</DialogTitle><DialogDescription>{medicine.name} · Satuan {medicine.unit}. {form.data.jenis === 'pemusnahan' && action === 'adjust' ? 'Isi jumlah yang benar-benar dimusnahkan dan cantumkan alasan atau referensinya. ' : ''}Semua perubahan tercatat pada kartu stok.</DialogDescription></DialogHeader><form onSubmit={save} className="space-y-4">
        {action === 'adjust' && <Field label="Jenis pencatatan" htmlFor="adjust-type"><Select id="adjust-type" value={form.data.jenis} onChange={(event) => form.setData('jenis', event.target.value)}><option value="opname">Stok opname — saldo fisik baru</option><option value="rusak">Barang rusak — jumlah dikeluarkan</option><option value="pemusnahan">Pemusnahan — jumlah dikeluarkan</option></Select></Field>}
        <Field label={action === 'adjust' && form.data.jenis === 'opname' ? 'Saldo batch hasil hitung fisik' : 'Jumlah'} htmlFor="stock-quantity" error={errors.jumlah} required><Input placeholder={`Jumlah dalam ${medicine.unit}`} id="stock-quantity" type="number" min={action === 'return' ? 1 : action === 'adjust' ? 0 : 0.01} step={action === 'return' ? '1' : '0.01'} value={form.data.jumlah} onChange={(event) => form.setData('jumlah', event.target.value)} required /></Field>
        {['receive', 'reconcile'].includes(action ?? '') && <><Field label="Nomor batch fisik" htmlFor="stock-batch" error={errors.nomor_batch} required><Input placeholder="Nomor batch pada kemasan" id="stock-batch" value={form.data.nomor_batch} onChange={(event) => form.setData('nomor_batch', event.target.value)} required /></Field><Field label="Tanggal kedaluwarsa" htmlFor="stock-expiry" error={errors.expired_at} required><DatePicker id="stock-expiry" value={form.data.expired_at} onChange={(event) => form.setData('expired_at', event.target.value)} required /></Field><Field label="Referensi penerimaan / bukti verifikasi" htmlFor="stock-reference" error={errors.referensi} required><Input placeholder="Nomor dokumen penerimaan atau verifikasi" id="stock-reference" value={form.data.referensi} onChange={(event) => form.setData('referensi', event.target.value)} required /></Field></>}
        {action === 'receive' && <Field label="Sumber penerimaan" htmlFor="stock-source"><Select id="stock-source" value={form.data.sumber} onChange={(event) => form.setData('sumber', event.target.value)}><option value="distribusi">Distribusi instansi</option><option value="hibah">Hibah</option><option value="saldo_awal">Saldo awal</option><option value="lainnya">Lainnya</option></Select></Field>}
        {['adjust', 'return'].includes(action ?? '') && <Field label="Alasan / bukti pencatatan" htmlFor="stock-reason" error={errors.alasan} required><Textarea placeholder="Alasan perubahan stok dan bukti pendukung" id="stock-reason" value={form.data.alasan} onChange={(event) => form.setData('alasan', event.target.value)} required /></Field>}
        {errors.items && <p className="text-sm text-red-700" role="alert">{errors.items}</p>}
        <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setAction(null)}>Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan pencatatan'}</Button></div>
    </form></DialogContent></Dialog></>;
}
