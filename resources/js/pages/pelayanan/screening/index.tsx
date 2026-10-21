import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, HeartPulse } from 'lucide-react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';

interface Clinic {
    id: number;
    name: string;
}

interface Visit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    hasScreening: boolean;
    status: string;
    screeningUrl: string;
}

interface Props {
    filters: { search: string; tanggal: string; poliklinikId: number | '' };
    clinics: Clinic[];
    canSelectClinic: boolean;
    visits: PaginationData & { data: Visit[] };
}

export default function ScreeningQueue({ filters: initialFilters, visits }: Props) {
    return (
        <>
            <Head title="Antrean Skrining" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Antrean skrining dan tanda vital</h2>
                    <p className="mt-1 text-sm text-neutral-500">Pasien yang menunggu atau sedang menjalani pemeriksaan awal.</p>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-surface p-4">
                    <p className="text-sm text-neutral-600">Antrean hari ini · {initialFilters.tanggal} · tanpa filter</p>
                    <Button asChild variant="secondary"><Link href="/pelayanan/screening/riwayat">Semua riwayat skrining</Link></Button>
                </div>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><HeartPulse className="size-5" /></span>
                            <div><CardTitle>Daftar pasien</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} pasien pada antrean ini.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[780px]">
                                <TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Status skrining</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader>
                                <TableBody>
                                    {visits.data.length > 0 ? visits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-mono text-xs text-neutral-600">{visit.number}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                                            <TableCell><StatusBadge status={visit.status} /></TableCell>
                                            <TableCell className="text-center"><Button asChild size="sm"><a href={visit.screeningUrl}><ClipboardCheck className="size-4" />{visit.hasScreening ? 'Lanjutkan asesmen' : 'Mulai skrining'}</a></Button></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Tidak ada pasien menunggu skrining hari ini." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                    <Pagination pagination={visits} />
                </Card>
            </div>
        </>
    );
}
