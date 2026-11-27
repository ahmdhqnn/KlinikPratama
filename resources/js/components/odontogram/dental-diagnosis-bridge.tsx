import { useState, type ReactNode } from 'react';
import { ClipboardPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/dashboard/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dentalSurfaceLabel, isDentalDiagnosisRecorded, type RecordedDentalDiagnosis, type DentalDiagnosisCandidate, type DentalDiagnosisGroup } from '@/lib/dental-diagnosis';

export function DentalDiagnosisBridge({ groups, findingLabels, disabled, diagnoses = [], headerAction, onImport }: { groups: DentalDiagnosisGroup[]; diagnoses?: RecordedDentalDiagnosis[]; findingLabels: Record<string, string>; disabled: boolean; headerAction?: ReactNode; onImport: (group: DentalDiagnosisGroup, candidate: DentalDiagnosisCandidate) => void }) {
    const [page, setPage] = useState(1);
    if (!groups.length) return null;
    const lastPage = Math.max(1, Math.ceil(groups.length / 4));
    const currentPage = Math.min(page, lastPage);
    const start = (currentPage - 1) * 4;
    return <section aria-label="Usulan kode diagnosis per gigi" className="space-y-3 rounded-xl border border-neutral-200 p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <h4 className="flex items-center gap-2 text-sm font-semibold"><ClipboardPlus className="size-4" />Usulan kode berdasarkan temuan</h4>
            {headerAction}
        </div>
        <p className="text-sm text-neutral-600">Usulan berasal dari temuan gigi yang sudah disimpan. Tinjau kesesuaiannya dengan pemeriksaan sebelum menyimpan diagnosis. Kondisi jaringan mulut atau ekstraoral tetap dapat dicatat melalui pencarian katalog atau diagnosis manual.</p>
        <div className="overflow-x-auto"><Table className="min-w-[42rem] table-fixed"><TableHeader><TableRow><TableHead className="w-[20%] text-center">Gigi</TableHead><TableHead className="w-[30%] text-left">Temuan</TableHead><TableHead className="w-[50%] text-center">Susun diagnosis</TableHead></TableRow></TableHeader><TableBody>{groups.slice(start, start + 4).map(group => <TableRow key={group.toothFdi}><TableCell className="text-center font-semibold">Gigi {group.toothFdi} ({dentalSurfaceLabel(group.surfaces)})</TableCell><TableCell className="whitespace-normal text-left">{[...new Set(group.findings.map(finding => findingLabels[finding.findingCode] ?? finding.findingCode))].join(', ')}{group.warnings.map(warning => <p key={warning} className="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-2 text-left text-xs text-amber-950" role="status">{warning}</p>)}</TableCell><TableCell><div className="flex flex-wrap justify-center gap-2"><Button type="button" size="sm" variant="secondary" disabled={disabled || isDentalDiagnosisRecorded(group, group.candidates[0], diagnoses)} onClick={() => { const candidate = group.candidates[0]; if (candidate) onImport(group, candidate); }}>{isDentalDiagnosisRecorded(group, group.candidates[0], diagnoses) ? 'Diagnosis sudah ditambahkan' : 'Susun diagnosis'}</Button>{group.candidates.map(candidate => <Button key={candidate.label} type="button" size="sm" variant="secondary" disabled={disabled || isDentalDiagnosisRecorded(group, candidate, diagnoses)} title={isDentalDiagnosisRecorded(group, candidate, diagnoses) ? 'Diagnosis sudah tercatat untuk gigi dan permukaan ini' : 'Susun draf untuk ditinjau dokter'} onClick={() => onImport(group, candidate)}>{isDentalDiagnosisRecorded(group, candidate, diagnoses) ? `${candidate.label} · sudah ditambahkan` : `${candidate.label}${candidate.exact ? ` · ${candidate.code}` : ' · cari kode'}`}</Button>)}</div></TableCell></TableRow>)}</TableBody></Table></div>
        <p className="text-xs text-neutral-600">Kode dalam katalog tidak menggantikan penilaian klinis. Usulan belum menjadi diagnosis sampai disimpan dokter.</p>
        <Pagination pagination={{ currentPage, lastPage, total: groups.length, from: start + 1, to: Math.min(start + 4, groups.length), previousUrl: null, nextUrl: null }} onPageChange={setPage} />
    </section>;
}
