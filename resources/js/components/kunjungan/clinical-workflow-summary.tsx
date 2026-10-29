import type { ReactNode } from 'react';
import { BodyExaminationRecord } from '@/components/anatomy/body-examination-record';
import { hasBodyMapContent, type BodyMapData } from '@/lib/body-map';
import { ClipboardPlus, Stethoscope } from 'lucide-react';
import { ActionButton } from '@/components/ui/action-button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { normalizeClinicalAnswer } from '@/lib/clinical-answer';
import { optionalAnswers } from '@/lib/optional-answers';

interface SummaryProps {
    hasUnsavedChanges: boolean;
    onReview: () => void;
}

interface DentalExaminationSummaryProps extends SummaryProps {
    anamnesis: string | null | undefined;
    intraoralExam: string | null | undefined;
    extraoralExam: string | null | undefined;
    findings: Array<{ toothFdi: string; surface: string; findingCode: string; notes: string | null }>;
    options: { surfaces: Record<string, string>; findings: Record<string, string> };
}

interface DiagnosisSummaryProps extends SummaryProps {
    id?: string;
    diagnoses: Array<{ id: number; code: string | null; codeSystem?: string; name: string; note?: string | null; type: string }>;
    differentialDiagnosis: string | null | undefined;
    codeSystems: Array<{ value: string; label: string }>;
}

export function DentalExaminationSummary({ anamnesis, intraoralExam, extraoralExam, findings, options, hasUnsavedChanges, onReview }: DentalExaminationSummaryProps) {
    return (
        <WorkflowSummary
            counts={<><Badge>{new Set(findings.map((finding) => finding.toothFdi)).size} gigi</Badge><Badge>{findings.length} temuan</Badge></>}
            description="Hasil pemeriksaan tersimpan pada kunjungan ini sebagai acuan penetapan diagnosis."
            hasUnsavedChanges={hasUnsavedChanges}
            icon={Stethoscope}
            id="dental-examination-summary"
            onReview={onReview}
            reviewLabel="Tinjau pemeriksaan dan odontogram"
            title="Ringkasan pemeriksaan & odontogram"
            unsavedMessage="Ada isian anamnesis atau pemeriksaan gigi yang belum disimpan. Simpan di tab Anamnesis atau Pemeriksaan & odontogram agar masuk ke ringkasan ini."
        >
            {(normalizeClinicalAnswer(anamnesis) || normalizeClinicalAnswer(intraoralExam) || normalizeClinicalAnswer(extraoralExam)) && <dl className="grid gap-4 sm:grid-cols-2">
                {normalizeClinicalAnswer(anamnesis) && <SummaryValue className="sm:col-span-2" label="Keluhan utama" value={anamnesis} />}
                {normalizeClinicalAnswer(intraoralExam) && <SummaryValue label="Pemeriksaan intraoral" value={intraoralExam} />}
                {normalizeClinicalAnswer(extraoralExam) && <SummaryValue label="Pemeriksaan ekstraoral" value={extraoralExam} />}
            </dl>}
            {findings.length > 0 ? (
                <div className="overflow-x-auto rounded-xl border border-neutral-200">
                    <Table aria-label="Ringkasan temuan odontogram" className="min-w-[36rem] table-fixed">
                        <TableHeader><TableRow>
                            <TableHead className="w-20 px-4 py-3 text-left">Gigi FDI</TableHead>
                            <TableHead className="w-36 px-4 py-3 text-left">Permukaan</TableHead>
                            <TableHead className="w-40 px-4 py-3 text-left">Temuan</TableHead>
                            <TableHead className="px-4 py-3 text-left">Catatan</TableHead>
                        </TableRow></TableHeader>
                        <TableBody>{findings.map((finding) => (
                            <TableRow key={finding.toothFdi + '-' + finding.surface}>
                                <TableCell className="px-4 py-3 text-left align-top font-mono font-semibold">{finding.toothFdi}</TableCell>
                                <TableCell className="break-words px-4 py-3 text-left align-top">{options.surfaces[finding.surface] ?? finding.surface}</TableCell>
                                <TableCell className="break-words px-4 py-3 text-left align-top">{options.findings[finding.findingCode] ?? finding.findingCode}</TableCell>
                                <TableCell className="whitespace-pre-wrap break-words px-4 py-3 text-left align-top">{normalizeClinicalAnswer(finding.notes) ?? optionalAnswers.notRecorded}</TableCell>
                            </TableRow>
                        ))}</TableBody>
                    </Table>
                </div>
            ) : <Empty size="compact" title="Belum ada temuan odontogram tersimpan" description="Lengkapi temuan pada tab Pemeriksaan & odontogram sebagai acuan diagnosis." />}
        </WorkflowSummary>
    );
}

interface GeneralExaminationSummaryProps extends SummaryProps {
    anamnesis: string | null | undefined;
    physicalExam: string | null | undefined;
    physicalSystems: Record<string, string>;
    bodyMap?: BodyMapData | null;
}

export function GeneralExaminationSummary({ anamnesis, physicalExam, physicalSystems, bodyMap, hasUnsavedChanges, onReview }: GeneralExaminationSummaryProps) {
    const recordedSystems = Object.entries(physicalSystems)
        .map(([system, value]) => ({ system, value: normalizeClinicalAnswer(value) }))
        .filter(({ value }) => value !== null);
    const findingCount = (bodyMap?.findings.length ?? 0) + recordedSystems.length;
    const hasAnnotation = Boolean(bodyMap?.annotation.strokes.length || bodyMap?.annotation.note?.trim());

    return (
        <WorkflowSummary
            counts={findingCount || hasAnnotation ? <>{findingCount > 0 && <Badge>{findingCount} temuan dicatat</Badge>}{hasAnnotation && <Badge>Anotasi tersimpan</Badge>}</> : null}
            description="Hasil pemeriksaan tersimpan pada kunjungan ini sebagai acuan penetapan diagnosis."
            hasUnsavedChanges={hasUnsavedChanges}
            icon={Stethoscope}
            id="general-examination-summary"
            onReview={onReview}
            reviewLabel="Tinjau pemeriksaan fisik"
            title="Ringkasan pemeriksaan fisik"
            unsavedMessage="Ada isian anamnesis atau pemeriksaan fisik yang belum disimpan. Simpan di tab Anamnesis atau Pemeriksaan fisik agar masuk ke ringkasan ini."
        >
            {(normalizeClinicalAnswer(anamnesis) || normalizeClinicalAnswer(physicalExam)) && <dl className="grid gap-4 sm:grid-cols-2">
                {normalizeClinicalAnswer(anamnesis) && <SummaryValue label="Keluhan utama" value={anamnesis} />}
                {normalizeClinicalAnswer(physicalExam) && <SummaryValue label="Ringkasan pemeriksaan fisik" value={physicalExam} />}
            </dl>}
            {hasBodyMapContent(bodyMap) && <BodyExaminationRecord data={bodyMap} />}
            {recordedSystems.length > 0 && <div className="space-y-3 border-t border-neutral-200 pt-4">
                <h3 className="text-sm font-semibold text-neutral-900">Temuan per sistem</h3>
                <dl className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {recordedSystems.map(({ system, value }) => <SummaryValue key={system} label={system} value={value} />)}
                </dl>
            </div>}
            {!normalizeClinicalAnswer(physicalExam) && !hasBodyMapContent(bodyMap) && recordedSystems.length === 0 && <Empty size="compact" title="Belum ada temuan fisik tersimpan" description="Catat temuan pada tab Pemeriksaan fisik." />}
        </WorkflowSummary>
    );
}

export function DiagnosisSummary({ id = 'diagnosis-summary', diagnoses, differentialDiagnosis, codeSystems, hasUnsavedChanges, onReview }: DiagnosisSummaryProps) {
    const primaryCount = diagnoses.filter((diagnosis) => diagnosis.type === 'utama').length;
    const orderedDiagnoses = [...diagnoses].sort((first, second) => Number(second.type === 'utama') - Number(first.type === 'utama'));

    return (
        <WorkflowSummary
            counts={<><Badge>{primaryCount} utama</Badge><Badge>{diagnoses.filter((diagnosis) => diagnosis.type !== 'utama').length} tambahan</Badge></>}
            description="Diagnosis tersimpan pada kunjungan ini sebagai acuan pencatatan tindakan."
            hasUnsavedChanges={hasUnsavedChanges}
            icon={ClipboardPlus}
            id={id}
            onReview={onReview}
            reviewLabel="Tinjau diagnosis"
            title="Ringkasan diagnosis"
            unsavedMessage="Ada isian diagnosis yang belum disimpan. Simpan di tab Diagnosis agar masuk ke ringkasan ini."
        >
            {orderedDiagnoses.length > 0 ? (
                <div className="overflow-x-auto rounded-xl border border-neutral-200">
                    <Table aria-label="Ringkasan diagnosis tercatat" className="min-w-[36rem] table-fixed">
                        <TableHeader><TableRow>
                            <TableHead className="w-24 px-4 py-3 text-center">Kode</TableHead>
                            <TableHead className="w-36 px-4 py-3 text-center">Sistem kode</TableHead>
                            <TableHead className="px-4 py-3 text-center">Diagnosis</TableHead>
                            <TableHead className="w-28 px-4 py-3 text-center">Jenis</TableHead>
                        </TableRow></TableHeader>
                        <TableBody>{orderedDiagnoses.map((diagnosis) => (
                            <TableRow key={diagnosis.id}>
                                <TableCell className="break-words px-4 py-3 text-center align-middle font-mono font-semibold">{diagnosis.code || 'Manual'}</TableCell>
                                <TableCell className="break-words px-4 py-3 text-center align-middle">{diagnosis.codeSystem === 'manual' || !diagnosis.code ? 'Manual' : codeSystems.find((system) => system.value === diagnosis.codeSystem)?.label ?? diagnosis.codeSystem ?? optionalAnswers.notRecorded}</TableCell>
                                <TableCell className="whitespace-pre-wrap break-words px-4 py-3 text-center align-middle font-medium"><p>{diagnosis.name}</p>{diagnosis.note && <p className="mt-1 font-normal text-neutral-600">{diagnosis.note}</p>}</TableCell>
                                <TableCell className="px-4 py-3 text-center align-middle"><Badge variant={diagnosis.type === 'utama' ? 'examination' : 'default'}>{diagnosis.type === 'utama' ? 'Utama' : 'Tambahan'}</Badge></TableCell>
                            </TableRow>
                        ))}</TableBody>
                    </Table>
                </div>
            ) : <Empty size="compact" title="Belum ada diagnosis tersimpan" description="Catat diagnosis pada tab Diagnosis sebelum melanjutkan pencatatan tindakan." />}
            {primaryCount !== 1 && diagnoses.length > 0 && <p className="text-sm text-amber-700" role="status">Pastikan tepat satu diagnosis utama tercatat sebelum finalisasi pemeriksaan.</p>}
            <dl><SummaryValue label="Diagnosis banding" value={differentialDiagnosis} /></dl>
        </WorkflowSummary>
    );
}

function WorkflowSummary({ id, title, description, icon: Icon, counts, reviewLabel, onReview, hasUnsavedChanges, unsavedMessage, children }: SummaryProps & {
    id: string;
    title: string;
    description: string;
    icon: typeof Stethoscope;
    counts: ReactNode;
    reviewLabel: string;
    unsavedMessage: string;
    children: ReactNode;
}) {
    return (
        <Card aria-labelledby={id + '-title'} id={id}>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 items-start gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600"><Icon aria-hidden="true" className="size-5" /></span>
                        <div className="min-w-0 space-y-1.5">
                            <CardTitle id={id + '-title'}>{title}</CardTitle>
                            <CardDescription>{description}</CardDescription>
                        </div>
                    </div>
                    <ActionButton action="detail" className="shrink-0" label={reviewLabel} onClick={onReview} />
                </div>
                {(counts || hasUnsavedChanges) && <div className="flex flex-wrap gap-2 pt-2">{counts}{hasUnsavedChanges && <Badge variant="waiting">Ada perubahan belum disimpan</Badge>}</div>}
            </CardHeader>
            <CardContent className="space-y-4">
                {hasUnsavedChanges && <p className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700" role="status">{unsavedMessage}</p>}
                {children}
            </CardContent>
        </Card>
    );
}

function SummaryValue({ label, value, className }: { label: string; value: string | null | undefined; className?: string }) {
    const answer = normalizeClinicalAnswer(value);

    return (
        <div className={'min-w-0 ' + (className ?? '')}>
            <dt className="text-xs font-medium text-neutral-500">{label}</dt>
            <dd className={'mt-1 whitespace-pre-wrap break-words text-sm leading-6 ' + (answer ? 'text-neutral-900' : 'text-neutral-500')}>{answer ?? optionalAnswers.notRecorded}</dd>
        </div>
    );
}
