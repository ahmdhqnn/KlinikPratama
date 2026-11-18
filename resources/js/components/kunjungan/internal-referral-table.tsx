import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { InternalReferralStatus } from '@/components/kunjungan/internal-referral-status';
import { ActionButton } from '@/components/ui/action-button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link } from '@inertiajs/react';
import { ArrowRightLeft } from 'lucide-react';

interface InternalReferralRow {
    id: number;
    number: string | null;
    date: string | null;
    patient: string;
    visitNumber: string;
    fromClinic: string;
    toClinic: string;
    doctor: string;
    status: string;
    destinationVisit: string | null;
    destinationDate: string | null;
    destinationUrl: string | null;
    registrationUrl: string | null;
    printUrl: string | null;
}

export type InternalReferralPagination = PaginationData & { data: InternalReferralRow[] };

export function InternalReferralTable({ referrals, canManage, registration = false }: {
    referrals: InternalReferralPagination;
    canManage: boolean;
    registration?: boolean;
}) {
    return (
        <Card id="rujukan-internal" className="overflow-hidden">
            <CardHeader className="border-b border-neutral-100">
                <div className="flex items-start gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ArrowRightLeft className="size-5" /></span>
                    <div className="min-w-0">
                        <CardTitle>{registration ? 'Daftar rujukan internal' : 'Rujukan internal'}</CardTitle>
                        <CardDescription className="mt-1">{registration
                            ? 'Daftarkan pasien ke poli tujuan dan pantau status kunjungannya.'
                            : 'Pantau rujukan antarpoliklinik dari penerbitan hingga pelayanan di poli tujuan.'}</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto">
                    <Table aria-label="Daftar rujukan internal" className="min-w-[56rem] table-fixed">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-[18%]">Nomor / tanggal</TableHead>
                                <TableHead>Pasien</TableHead>
                                <TableHead className="w-[21%]">Poli asal / tujuan</TableHead>
                                <TableHead className="w-[15%]">Status</TableHead>
                                <TableHead className="w-[16%]">Kunjungan tujuan</TableHead>
                                <TableHead className={canManage ? 'w-36' : 'w-24'}>Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {referrals.data.length ? referrals.data.map((referral) => (
                                <TableRow key={referral.id}>
                                    <TableCell className="wrap-anywhere tabular-nums">
                                        <span className="font-medium">{referral.number ?? 'Nomor belum tercatat'}</span>
                                        <p className="mt-1 text-xs text-neutral-500">{referral.date ?? 'Tanggal belum tercatat'}</p>
                                    </TableCell>
                                    <TableCell className="wrap-anywhere">
                                        <span className="font-medium">{referral.patient}</span>
                                        <p className="mt-1 text-xs text-neutral-500">{referral.visitNumber}</p>
                                    </TableCell>
                                    <TableCell className="wrap-anywhere">
                                        <p className="text-xs text-neutral-500">Asal · {referral.fromClinic}</p>
                                        <p className="font-medium">Tujuan · {referral.toClinic}</p>
                                        <p className="mt-1 text-xs text-neutral-500">{referral.doctor}</p>
                                    </TableCell>
                                    <TableCell><InternalReferralStatus status={referral.status} /></TableCell>
                                    <TableCell className="wrap-anywhere tabular-nums">
                                        {referral.destinationVisit ?? 'Belum terdaftar'}
                                        {referral.destinationDate && <p className="mt-1 text-xs text-neutral-500">{referral.destinationDate}</p>}
                                    </TableCell>
                                    <TableCell className="px-3">
                                        <div className="flex flex-wrap items-center justify-center gap-2">
                                            {canManage && <>
                                                <ActionButton asChild action="detail" disabled={!referral.destinationUrl} disabledReason="Kunjungan tujuan belum terdaftar." label="Detail kunjungan tujuan">
                                                    <Link href={referral.destinationUrl ?? ''} />
                                                </ActionButton>
                                                <ActionButton asChild action="activate" disabled={!referral.registrationUrl} disabledReason="Rujukan sudah ditangani atau tidak dapat didaftarkan." label="Daftarkan ke poli tujuan">
                                                    <Link href={referral.registrationUrl ?? ''} />
                                                </ActionButton>
                                            </>}
                                            {referral.printUrl && <ActionButton asChild action="print" label="Cetak rujukan internal"><a href={referral.printUrl} /></ActionButton>}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={6}><Empty size="compact" title="Tidak ada rujukan internal yang ditemukan." /></TableCell></TableRow>}
                        </TableBody>
                    </Table>
                </div>
                <Pagination pagination={referrals} />
            </CardContent>
        </Card>
    );
}
