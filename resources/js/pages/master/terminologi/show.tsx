import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Database, FileText, Tag, Trash2 } from 'lucide-react';
import { handleBackNavigation } from '@/lib/navigation';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Field } from '@/components/ui/field';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableScroll } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface Catalog {
    system: string;
    label: string;
    release: string;
    releaseValue: string;
    count: number;
    importedAt: string;
}

interface Code { code: string; display: string; type: string | null; active: boolean; }
interface Props { catalog: Catalog; codes: PaginationData & { data: Code[] }; search: string; status?: string; }

export default function ClinicalTerminologyShow({ catalog, codes, search: initialSearch, status: initialStatus = '' }: Props) {
    const [search, setSearch] = useState(initialSearch);
    const [status, setStatus] = useState(initialStatus);

    function applySearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/master/terminologi-klinis/detail', { system: catalog.system, release: catalog.releaseValue, search, status }, { preserveState: true, preserveScroll: true });
    }

    function removeCatalog() {
        confirmAction(`Hapus seluruh ${new Intl.NumberFormat('id-ID').format(catalog.count)} kode dari ${catalog.label}, rilis ${catalog.release}? Penghapusan ini tidak dapat dibatalkan.`, () => {
            router.delete('/master/terminologi-klinis', { preserveScroll: 'errors', data: { system: catalog.system, release: catalog.releaseValue } });
        }, 'Hapus katalog');
    }

    return (
        <>
            <Head title={`Detail ${catalog.label}`} />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <Button asChild className="mb-4" size="sm" variant="secondary"><Link href="/master/terminologi-klinis" onClick={(event) => handleBackNavigation(event, '/master/terminologi-klinis')}><ArrowLeft className="size-4" />Kembali ke katalog</Link></Button>
                        <p className="text-sm font-medium text-neutral-700">Detail katalog terminologi</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{catalog.label}</h2>
                        <p className="mt-1 text-sm text-neutral-500">Rilis {catalog.release} · Impor terakhir {catalog.importedAt ?? 'Belum diisi'}</p>
                    </div>
                    <Button onClick={removeCatalog} variant="destructive"><Trash2 className="size-4" />Hapus katalog</Button>
                </div>
                <SummaryStatGrid ariaLabel="Ringkasan detail katalog terminologi" columns={3}>
                    <StatCard label="Total kode katalog" value={catalog.count} description="Seluruh kode dalam rilis ini" icon={<Database className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Sesuai filter" value={codes.total} description="Kode yang tampil saat ini" icon={<FileText className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Versi rilis" value={catalog.release} description={catalog.label} icon={<Tag className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </SummaryStatGrid>
                <FilterPanel contextKeys={['system', 'release']}><form className="grid min-w-0 gap-4 sm:grid-cols-2" onSubmit={applySearch}>
                            <Field htmlFor="terminology-code-search" label="Cari kode atau uraian"><Input id="terminology-code-search" maxLength={100} onChange={(event) => setSearch(event.target.value)} placeholder="Cari kode atau uraian" value={search} /></Field>
                            <Field htmlFor="terminology-code-status" label="Status"><Select id="terminology-code-status" onChange={(event) => setStatus(event.target.value)} value={status}><option value="">Semua status</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></Select></Field>
                            <FilterSubmit />
                        </form></FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <CardTitle>Daftar kode</CardTitle>
                        <CardDescription>Kode dan uraian asli dari sistem terminologi terpilih.</CardDescription>

                    </CardHeader>
                    <CardContent className="p-0">
                        <TableScroll className="overflow-x-auto">
                            <Table className="min-w-[760px]">
                                <TableHeader><TableRow><TableHead>Kode</TableHead><TableHead>Uraian</TableHead><TableHead>Jenis</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                                <TableBody>{codes.data.length ? codes.data.map((code) => <TableRow key={code.code}><TableCell className="font-sans tabular-nums text-xs font-semibold text-neutral-900">{code.code}</TableCell><TableCell className="max-w-3xl whitespace-normal">{code.display}</TableCell><TableCell className="capitalize">{code.type ?? 'Belum diisi'}</TableCell><TableCell><Badge variant={code.active ? 'complete' : 'default'}>{code.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={4}><Empty description="Coba kata kunci lain atau periksa katalog terpilih." size="compact" title="Kode tidak ditemukan" /></TableCell></TableRow>}</TableBody>
                            </Table>
                        </TableScroll>
                        <Pagination pagination={codes} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
