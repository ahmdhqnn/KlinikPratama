import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Search, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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
interface Props { catalog: Catalog; codes: PaginationData & { data: Code[] }; search: string; }

export default function ClinicalTerminologyShow({ catalog, codes, search: initialSearch }: Props) {
    const [search, setSearch] = useState(initialSearch);

    function applySearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/master/terminologi-klinis/detail', { system: catalog.system, release: catalog.releaseValue, search }, { preserveState: true, preserveScroll: true });
    }

    function removeCatalog() {
        confirmAction(`Hapus seluruh ${new Intl.NumberFormat('id-ID').format(catalog.count)} kode dari ${catalog.label}, rilis ${catalog.release}? Penghapusan ini tidak dapat dibatalkan.`, () => {
            router.delete('/master/terminologi-klinis', { data: { system: catalog.system, release: catalog.releaseValue } });
        }, 'Hapus katalog');
    }

    return (
        <>
            <Head title={`Detail ${catalog.label}`} />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <Button asChild className="mb-4" size="sm" variant="secondary"><Link href="/master/terminologi-klinis"><ArrowLeft className="size-4" />Kembali ke katalog</Link></Button>
                        <p className="text-sm font-medium text-neutral-700">Detail katalog terminologi</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{catalog.label}</h2>
                        <p className="mt-1 text-sm text-neutral-500">Rilis {catalog.release} · {new Intl.NumberFormat('id-ID').format(catalog.count)} kode · Impor terakhir {catalog.importedAt ?? 'Belum diisi'}</p>
                    </div>
                    <Button onClick={removeCatalog} variant="destructive"><Trash2 className="size-4" />Hapus katalog</Button>
                </div>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <CardTitle>Daftar kode</CardTitle>
                        <CardDescription>Kode dan uraian asli dari sistem terminologi terpilih.</CardDescription>
                        <form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_auto]" onSubmit={applySearch}>
                            <Input aria-label="Cari kode atau uraian" onChange={(event) => setSearch(event.target.value)} placeholder="Cari kode atau uraian…" value={search} />
                            <Button type="submit"><Search className="size-4" />Cari</Button>
                        </form>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[760px]">
                                <TableHeader><tr><TableHead>Kode</TableHead><TableHead>Uraian</TableHead><TableHead>Jenis</TableHead><TableHead>Status</TableHead></tr></TableHeader>
                                <TableBody>{codes.data.length ? codes.data.map((code) => <TableRow key={code.code}><TableCell className="font-mono text-xs font-semibold text-neutral-900">{code.code}</TableCell><TableCell className="max-w-3xl whitespace-normal">{code.display}</TableCell><TableCell className="capitalize">{code.type ?? 'Belum diisi'}</TableCell><TableCell><Badge variant={code.active ? 'complete' : 'default'}>{code.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell></TableRow>) : <TableRow><TableCell colSpan={4}><Empty description="Coba kata kunci lain atau periksa katalog terpilih." size="compact" title="Kode tidak ditemukan" /></TableCell></TableRow>}</TableBody>
                            </Table>
                        </div>
                        <Pagination pagination={codes} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
