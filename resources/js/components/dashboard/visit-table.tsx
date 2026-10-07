import { StatusBadge } from '@/components/dashboard/status-badge';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export interface DashboardVisit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    status: string;
    triage?: string | null;
    priority?: string | null;
}

export function VisitTable({ visits }: { visits: DashboardVisit[] }) {
    return (
        <div className="overflow-x-auto">
            <Table className="min-w-[680px]">
                <TableHeader><tr>
                    <TableHead>No. kunjungan</TableHead>
                    <TableHead>Pasien</TableHead>
                    <TableHead>Poliklinik</TableHead>
                    <TableHead>Status</TableHead>
                </tr></TableHeader>
                <TableBody>
                    {visits.length > 0 ? visits.map((visit) => (
                        <tr className="transition-colors hover:bg-neutral-50/80" key={visit.id}>
                            <TableCell className="whitespace-nowrap font-mono text-xs text-neutral-600">{visit.number}</TableCell>
                            <TableCell>
                                <p className="font-medium text-neutral-900">{visit.patient}</p>
                                <p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p>
                            </TableCell>
                            <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                            <TableCell>
                                <div className="flex flex-col items-start gap-1.5">
                                    <StatusBadge status={visit.status} />
                                    {visit.triage && (
                                        <span className={`text-xs font-medium capitalize ${visit.triage === 'merah' ? 'text-red-700' : 'text-neutral-500'}`}>
                                            Triase {visit.triage}{visit.priority ? ` · ${visit.priority}` : ''}
                                        </span>
                                    )}
                                </div>
                            </TableCell>
                        </tr>
                    )) : (
                        <tr>
                            <TableCell colSpan={4}><Empty className="min-h-0 rounded-none border-0 bg-transparent py-6" description="Kunjungan yang sesuai akan tampil di daftar ini." title="Belum ada kunjungan" /></TableCell>
                        </tr>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
