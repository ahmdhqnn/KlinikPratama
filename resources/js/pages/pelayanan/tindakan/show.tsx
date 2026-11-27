import { AssistanceReasonField } from '@/components/kunjungan/assistance-reason-field';
import { handleBackNavigation } from '@/lib/navigation';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Ban, ClipboardCheck, Download, Play, Printer, Save, ShieldAlert, ShieldCheck, Syringe, UserRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { AllergyHistory } from '@/components/kunjungan/allergy-history';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FormActionFooter } from '@/components/ui/form-action-footer';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

interface ConsentSummary {
    decision: string;
    currentId: number | null;
    recordedAt: string | null;
    planMatches: boolean;
    requiredReview: boolean;
    signaturePending?: boolean;
    effectiveMode?: string;
    effectiveModeLabel?: string;
    signerName?: string | null;
    signerRelationship?: string | null;
    signerRelationshipLabel?: string | null;
    witnessName?: string | null;
    recordedBy?: string | null;
    recordedByRole?: string | null;
    reason?: string | null;
    evidenceUrl?: string | null;
    followupRequired?: boolean;
    generalConsentActive?: boolean;
    canPerform?: boolean;
    blockReason?: string | null;
    url: string;
}

interface Treatment {
    id: number;
    name: string;
    code: string | null;
    quantity: number;
    toothFdi: string | null;
    instructions: string | null;
    status: string;
    result: string | null;
    performedBy: string | null;
    performedAt: string | null;
    consent?: ConsentSummary;
    completeUrl: string;
    refuseUrl?: string | null;
    emergencyUrl?: string | null;
}

const consentAllowsExecution = (consent: Treatment['consent']) => !consent || (
    consent.canPerform !== undefined
        ? consent.canPerform
        : (!consent.signaturePending
            && ((!consent.requiredReview && consent.decision === 'unreviewed')
                || (consent.planMatches && ['accepted', 'oral', 'not_required', 'emergency'].includes(consent.decision))))
);

const decisionHeadline = (consent: ConsentSummary) => {
    if (consent.signaturePending) return 'Menunggu tanda tangan pasien atau konfirmasi saksi';
    switch (consent.decision) {
        case 'accepted':
            return 'Informed Consent tertulis disetujui';
        case 'oral':
            return 'Persetujuan lisan tercatat';
        case 'not_required':
            return 'Tindakan rutin — mengacu pada General Consent';
        case 'emergency':
            return 'Mode Darurat / Penjelasan Menyusul aktif';
        case 'refused':
            return 'Pasien / wali menolak tindakan';
        case 'withdrawn':
            return 'Persetujuan dicabut oleh pasien / wali';
        default:
            return 'Persetujuan belum ditinjau';
    }
};

const decisionBadgeVariant = (consent: ConsentSummary): 'complete' | 'warning' | 'cancelled' | 'default' => {
    if (consent.signaturePending) return 'warning';
    if (['refused', 'withdrawn'].includes(consent.decision)) return 'cancelled';
    if (consent.decision === 'emergency') return 'warning';
    if (!consentAllowsExecution(consent)) return 'warning';
    if (['accepted', 'oral', 'not_required'].includes(consent.decision)) return 'complete';
    return 'default';
};

interface Props {
    visit: {
        id: number;
        number: string;
        patient: string;
        medicalRecordNumber: string;
        clinic: string;
        doctor: string | null;
        status: string;
        treatmentStartedAt: string | null;
        startUrl: string;
        allergies: string | null;
        education: string | null;
        nonPharmacological: string | null;
        diagnoses: string[];
        hasMedication: boolean;
        recordUrl: string | null;
        adminAssistance?: boolean;
        isEmergencyVisit?: boolean;
        delegation?: {
            doctor: string;
            signedAt: string | null;
            verified: boolean;
        };
        generalConsent?: {
            status: string;
            active?: boolean;
            recordedAt: string | null;
            signerName?: string | null;
            versionCurrent: boolean;
            required: boolean;
            url: string;
        };
        treatments: Treatment[];
    };
}

export default function NursingProcedureDetail({ visit }: Props) {
    const startForm = useForm({ assistance_reason: '' });
    const startConsentError = (startForm.errors as Record<string, string>).procedure_consent;
    const pendingTreatments = visit.treatments.filter((item) => item.status === 'pending');
    const performablePending = pendingTreatments.filter((item) => consentAllowsExecution(item.consent));
    const blockedNonRefused = pendingTreatments.filter((item) => !consentAllowsExecution(item.consent) && !['refused', 'withdrawn'].includes(item.consent?.decision ?? ''));
    const blockedTreatments = pendingTreatments.filter((item) => !consentAllowsExecution(item.consent));
    const canStart = visit.status === 'tindakan' && !visit.treatmentStartedAt && performablePending.length > 0 && blockedNonRefused.length === 0;
    const canComplete = visit.status === 'tindakan' && Boolean(visit.treatmentStartedAt);
    const generalConsentActive = visit.generalConsent ? (visit.generalConsent.active ?? (visit.generalConsent.status === 'accepted' && visit.generalConsent.versionCurrent)) : null;

    function start() {
        confirmAction('Mulai pelaksanaan tindakan perawat sesuai instruksi dan delegasi dokter?', () => startForm.post(visit.startUrl, { preserveScroll: true }), 'Mulai tindakan', 'Mulai tindakan perawat');
    }

    return <>
        <Head title="Pelaksanaan tindakan" />
        <div className="space-y-6">
            <header className="flex flex-wrap items-end justify-between gap-4">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-neutral-700">{visit.clinic}</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pelaksanaan tindakan</h2>
                    <p className="mt-1 max-w-3xl text-sm text-neutral-500">Verifikasi instruksi delegasi dokter dan status persetujuan pasien sebelum memulai tindakan, lalu dokumentasikan hasil serta respons pasien.</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild size="sm" variant="secondary">
                        <Link href="/pelayanan/tindakan-perawat" onClick={(event) => handleBackNavigation(event, '/pelayanan/tindakan-perawat')}>
                            <ArrowLeft className="size-4" />Kembali
                        </Link>
                    </Button>
                    {!visit.adminAssistance && (
                        <Button asChild size="sm" variant="secondary">
                            <a href={`/perawat/laporan-tindakan/cetak?search=${encodeURIComponent(visit.number)}`} target="_blank" rel="noreferrer">
                                <Printer className="size-4" />Cetak tindakan
                            </a>
                        </Button>
                    )}
                </div>
            </header>

            {visit.adminAssistance && canStart && (
                <Card>
                    <CardHeader><CardTitle>Bantuan tindakan admin</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-neutral-600">Bantuan admin lintas poli. Akun Anda dicatat sebagai pelaku pelaksanaan; pastikan tindakan benar-benar sudah dilakukan sesuai instruksi dokter.</p>
                        <AssistanceReasonField error={startForm.errors.assistance_reason} id="start-assistance" value={startForm.data.assistance_reason} onChange={(value) => startForm.setData('assistance_reason', value)} disabled={startForm.processing} />
                    </CardContent>
                </Card>
            )}

            <Card className="overflow-hidden">
                <CardContent className="p-0 sm:p-0">
                    <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                            <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600">
                                <UserRound aria-hidden="true" className="size-6" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-xs font-medium text-neutral-500">Pasien</p>
                                <h3 className="mt-1 break-words text-lg font-semibold text-neutral-950">{visit.patient}</h3>
                                <p className="mt-1 text-sm text-neutral-600">No. RM {visit.medicalRecordNumber} · {visit.number}</p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 self-start sm:self-center">
                            {visit.isEmergencyVisit && <Badge variant="cancelled">Triase darurat</Badge>}
                            <StatusBadge context="nurse" status={visit.status} treatmentStarted={Boolean(visit.treatmentStartedAt)} />
                        </div>
                    </div>
                    <dl className="grid gap-4 border-t border-neutral-100 bg-neutral-50/60 px-5 py-4 sm:grid-cols-3 sm:px-6">
                        <div><dt className="text-xs text-neutral-500">Poliklinik</dt><dd className="mt-1 text-sm font-medium text-neutral-900">{visit.clinic}</dd></div>
                        <div><dt className="text-xs text-neutral-500">Dokter pemberi instruksi</dt><dd className="mt-1 text-sm font-medium text-neutral-900">{visit.delegation?.doctor || visit.doctor || 'Belum dicatat'}</dd></div>
                        <div><dt className="text-xs text-neutral-500">Mulai tindakan</dt><dd className="mt-1 text-sm font-medium text-neutral-900">{visit.treatmentStartedAt || 'Belum dimulai'}</dd></div>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardCheck className="size-5" /></span>
                        <div>
                            <CardTitle>Instruksi &amp; delegasi dokter</CardTitle>
                            <CardDescription className="mt-1">{visit.diagnoses.length ? visit.diagnoses.join(', ') : 'Diagnosis belum dicatat'}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <AllergyHistory value={visit.allergies} unknownLabel="Belum diketahui; konfirmasi bersama pasien sebelum tindakan" />
                    {(visit.delegation || visit.generalConsent) && (
                        <div className="grid gap-3 md:auto-cols-fr md:grid-flow-col">
                            {visit.delegation && (
                                <div className="min-w-0 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">Delegasi dokter</p>
                                        <Badge variant="complete">Terverifikasi</Badge>
                                    </div>
                                    <p className="mt-1.5 wrap-anywhere text-sm font-semibold text-neutral-900">{visit.delegation.doctor}</p>
                                    <p className="mt-0.5 text-xs text-neutral-600">
                                        Catatan pemeriksaan ditandatangani {visit.delegation.signedAt || 'tercatat di RME'}. Laksanakan sesuai instruksi klinis dan SPO klinik.
                                    </p>
                                </div>
                            )}
                            {visit.generalConsent && (
                                <div className="min-w-0 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">Persetujuan Umum (General Consent)</p>
                                        <Badge variant={generalConsentActive ? 'complete' : visit.generalConsent.status === 'refused' ? 'cancelled' : 'warning'}>
                                            {generalConsentActive ? 'Aktif' : visit.generalConsent.status === 'refused' ? 'Ditolak' : 'Belum lengkap'}
                                        </Badge>
                                    </div>
                                    <p className="mt-1.5 wrap-anywhere text-sm font-medium text-neutral-900">
                                        {generalConsentActive
                                            ? `Disetujui${visit.generalConsent.signerName ? ` oleh ${visit.generalConsent.signerName}` : ''}${visit.generalConsent.recordedAt ? ` · ${visit.generalConsent.recordedAt}` : ''}`
                                            : 'Diperlukan sebagai dasar tindakan keperawatan rutin non-invasif.'}
                                    </p>
                                    <div className="mt-2 flex justify-end">
                                        <Button asChild size="sm" variant="secondary">
                                            <Link href={visit.generalConsent.url}>Lihat General Consent</Link>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-sm font-medium">Perawatan tanpa obat</p>
                            <p className="whitespace-pre-wrap text-sm text-muted-foreground">{visit.nonPharmacological || 'Belum ada catatan'}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium">Edukasi dan anjuran</p>
                            <p className="whitespace-pre-wrap text-sm text-muted-foreground">{visit.education || 'Belum ada catatan'}</p>
                        </div>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {visit.status === 'tindakan'
                            ? (visit.hasMedication ? 'Setelah semua tindakan selesai, pasien melanjutkan ke farmasi.' : 'Setelah semua tindakan selesai, kunjungan berakhir dan pasien dapat pulang.')
                            : visit.status === 'farmasi'
                                ? 'Tindakan selesai. Pasien sedang menunggu pelayanan farmasi.'
                                : 'Kunjungan telah selesai.'}
                    </p>
                </CardContent>
            </Card>

            <section id="pelaksanaan-tindakan" className="space-y-4 scroll-mt-6" aria-label="Pelaksanaan tindakan perawat">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-neutral-950">Tindakan perawat</h3>
                        <p className="mt-1 text-sm text-neutral-500">Pastikan Informed Consent atau General Consent aktif sebelum memulai dan mencatat hasil tiap tindakan.</p>
                    </div>
                    {canStart && (
                        <Button className="w-full sm:w-auto" disabled={startForm.processing} onClick={start} type="button">
                            <Play className="size-4" />{startForm.processing ? 'Memulai…' : 'Mulai tindakan'}
                        </Button>
                    )}
                </div>

                {startConsentError && (
                    <Alert dismissible={false} variant="destructive">
                        <ShieldAlert className="size-4" />
                        <AlertTitle>Tindakan belum dapat dimulai</AlertTitle>
                        <AlertDescription>{startConsentError}</AlertDescription>
                    </Alert>
                )}

                {blockedTreatments.length > 0 && (
                    <Alert dismissible={false} variant={blockedTreatments.some((item) => ['refused', 'withdrawn'].includes(item.consent?.decision ?? '')) ? 'destructive' : 'default'}>
                        <AlertTriangle className="size-4" />
                        <AlertTitle>Pelaksanaan terkunci oleh status persetujuan</AlertTitle>
                        <AlertDescription>
                            {blockedTreatments.map((item) => `${item.name}${item.consent?.blockReason ? ` (${item.consent.blockReason})` : ''}`).join(' · ')}
                        </AlertDescription>
                    </Alert>
                )}

                {visit.treatments.length
                    ? visit.treatments.map((item) => (
                        <TreatmentForm
                            adminAssistance={visit.adminAssistance === true}
                            canComplete={canComplete && consentAllowsExecution(item.consent)}
                            isEmergencyVisit={visit.isEmergencyVisit === true}
                            item={item}
                            key={item.id}
                            patientName={visit.patient}
                        />
                    ))
                    : <Empty title="Tidak ada tindakan perawat" />}
            </section>

            <div className="flex justify-end border-t border-neutral-100 pt-5">
                {visit.recordUrl && (
                    <Button asChild variant="secondary">
                        <Link href={visit.recordUrl}>Lihat rekam medis</Link>
                    </Button>
                )}
            </div>
        </div>
    </>;
}

function TreatmentForm({ item, canComplete, adminAssistance, isEmergencyVisit, patientName }: {
    item: Treatment;
    canComplete: boolean;
    adminAssistance: boolean;
    isEmergencyVisit: boolean;
    patientName: string;
}) {
    const form = useForm({ execution_result: '', assistance_reason: '' });
    const stockError = (form.errors as Record<string, string>).items;
    const consentError = (form.errors as Record<string, string>).procedure_consent;
    const completed = item.status === 'completed';
    const consent = item.consent;
    const allowed = consentAllowsExecution(consent);
    const isRefusedOrWithdrawn = ['refused', 'withdrawn'].includes(consent?.decision ?? '');
    const canRecordRefusal = !completed && Boolean(item.refuseUrl) && !isRefusedOrWithdrawn && !consent?.signaturePending;
    const canActivateEmergency = !completed && Boolean(item.emergencyUrl) && isEmergencyVisit && !allowed && !isRefusedOrWithdrawn && !consent?.signaturePending;
    const defaultRefusalDecision = ['accepted', 'oral'].includes(consent?.decision ?? '') ? 'withdrawn' : 'refused';

    const [refuseOpen, setRefuseOpen] = useState(false);
    const [emergencyOpen, setEmergencyOpen] = useState(false);

    const refuseForm = useForm({
        decision: defaultRefusalDecision,
        signer_name: patientName,
        signer_relationship: 'patient',
        representative_verification: '',
        reason: '',
        confirmed: false,
    });

    const emergencyForm = useForm({
        reason: '',
        emergency_without_available_signer: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        confirmAction(
            `Pastikan ${item.name} telah dilaksanakan sesuai instruksi dokter dan hasilnya sesuai. Catatan yang disimpan tidak dapat ditimpa.`,
            () => form.post(item.completeUrl, { preserveScroll: true }),
            'Simpan pelaksanaan',
            'Konfirmasi pelaksanaan tindakan',
        );
    }

    function submitRefusal(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!item.refuseUrl) return;
        refuseForm.post(item.refuseUrl, {
            preserveScroll: true,
            onSuccess: () => {
                setRefuseOpen(false);
                refuseForm.reset('reason', 'representative_verification', 'confirmed');
            },
        });
    }

    function submitEmergency(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!item.emergencyUrl) return;
        emergencyForm.post(item.emergencyUrl, {
            preserveScroll: true,
            onSuccess: () => {
                setEmergencyOpen(false);
                emergencyForm.reset();
            },
        });
    }

    return (
        <>
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="flex min-w-0 items-center gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">
                                <Syringe className="size-5" />
                            </span>
                            <div className="min-w-0">
                                <CardTitle className="break-words">{item.name}</CardTitle>
                                <CardDescription className="mt-1">
                                    {item.code ? `ICD-9-CM ${item.code}` : 'Tanpa kode'} · {item.quantity} kali{item.toothFdi ? ` · Gigi ${item.toothFdi}` : ''}
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {consent?.effectiveModeLabel && <Badge variant="default">{consent.effectiveModeLabel}</Badge>}
                            <Badge variant={completed ? 'complete' : isRefusedOrWithdrawn ? 'cancelled' : 'waiting'}>
                                {completed ? 'Sudah dilaksanakan' : isRefusedOrWithdrawn ? 'Terkunci (ditolak/dicabut)' : 'Menunggu pelaksanaan'}
                            </Badge>
                        </div>
                    </div>
                </CardHeader>

                <CardContent className="space-y-5">
                    <div>
                        <p className="text-xs font-medium text-neutral-500">Instruksi dokter</p>
                        <p className="mt-1 whitespace-pre-wrap text-sm text-neutral-700">{item.instructions || 'Tidak ada instruksi tambahan.'}</p>
                    </div>

                    {consent && (
                        <div className="space-y-3 min-w-0 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="flex items-start gap-2.5">
                                    {isRefusedOrWithdrawn || !allowed
                                        ? <ShieldAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-red-600 dark:text-red-400" />
                                        : <ShieldCheck aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-emerald-700 dark:text-emerald-400" />}
                                    <div>
                                        <p className="text-sm font-semibold text-neutral-900">{decisionHeadline(consent)}</p>
                                        <p className="mt-0.5 text-xs text-neutral-600">
                                            {consent.recordedAt
                                                ? `Tercatat ${consent.recordedAt}${consent.recordedBy ? ` oleh ${consent.recordedBy}` : ''}${consent.recordedByRole === 'perawat' ? ' (Perawat)' : consent.recordedByRole === 'dokter' ? ' (Dokter)' : ''}`
                                                : consent.requiredReview
                                                    ? 'Belum ada keputusan persetujuan tercatat. Koordinasikan dengan dokter penanggung jawab sebelum tindakan dimulai.'
                                                    : 'Tindakan historis tanpa catatan persetujuan terpisah.'}
                                        </p>
                                    </div>
                                </div>
                                <Badge variant={decisionBadgeVariant(consent)}>
                                    {allowed ? 'Siap dilaksanakan' : 'Terkunci'}
                                </Badge>
                            </div>

                            {(consent.signerName || consent.witnessName || consent.recordedBy || consent.reason) && (
                                <dl className="grid gap-3 border-t border-neutral-200/80 pt-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                                    {consent.signerName && (
                                        <div>
                                            <dt className="text-neutral-500">Penanda tangan / penyampai</dt>
                                            <dd className="mt-0.5 font-medium text-neutral-900">
                                                {consent.signerName}{consent.signerRelationshipLabel ? ` (${consent.signerRelationshipLabel})` : ''}
                                            </dd>
                                        </div>
                                    )}
                                    {consent.recordedBy && (
                                        <div>
                                            <dt className="text-neutral-500">Pemberi informasi / pencatat</dt>
                                            <dd className="mt-0.5 font-medium text-neutral-900">
                                                {consent.recordedBy}{consent.recordedByRole === 'perawat' ? ' · Perawat' : consent.recordedByRole === 'dokter' ? ' · Dokter' : ''}
                                            </dd>
                                        </div>
                                    )}
                                    {consent.witnessName && (
                                        <div>
                                            <dt className="text-neutral-500">Saksi</dt>
                                            <dd className="mt-0.5 font-medium text-neutral-900">{consent.witnessName}</dd>
                                        </div>
                                    )}
                                    {consent.recordedAt && (
                                        <div>
                                            <dt className="text-neutral-500">Waktu persetujuan</dt>
                                            <dd className="mt-0.5 font-medium tabular-nums text-neutral-900">{consent.recordedAt}</dd>
                                        </div>
                                    )}
                                </dl>
                            )}

                            {consent.reason && (
                                <p className="whitespace-pre-wrap border-t border-neutral-200/80 pt-2.5 text-xs leading-5 text-neutral-700">
                                    <span className="font-semibold text-neutral-800">Catatan / dasar keputusan: </span>{consent.reason}
                                </p>
                            )}

                            {consent.followupRequired && (
                                <Alert dismissible={false} variant="default">
                                    <AlertTriangle className="size-4" />
                                    <AlertTitle>Wajib kelengkapan dokumentasi menyusul</AlertTitle>
                                    <AlertDescription>
                                        Tindakan dibuka melalui pengecualian gawat darurat. Segera setelah kondisi pasien stabil atau wali hadir, lengkapi penjelasan klinis dan dokumentasi persetujuan lanjutan sesuai SPO klinik.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {!allowed && consent.blockReason && (
                                <Alert dismissible={false} variant={isRefusedOrWithdrawn ? 'destructive' : 'default'}>
                                    <AlertTriangle className="size-4" />
                                    <AlertTitle>{isRefusedOrWithdrawn ? 'Pelaksanaan dihentikan' : 'Persetujuan belum memenuhi syarat'}</AlertTitle>
                                    <AlertDescription>{consent.blockReason}</AlertDescription>
                                </Alert>
                            )}

                            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200/80 pt-3">
                                {consent.evidenceUrl && (
                                    <Button asChild size="sm" variant="secondary">
                                        <a href={consent.evidenceUrl}>
                                            <Download className="size-4" />Unduh bukti
                                        </a>
                                    </Button>
                                )}
                                <Button asChild size="sm" variant="secondary">
                                    <Link href={consent.url}>Lihat riwayat persetujuan</Link>
                                </Button>
                                {canRecordRefusal && (
                                    <Button
                                        size="sm"
                                        type="button"
                                        variant="secondary"
                                        onClick={() => {
                                            refuseForm.clearErrors();
                                            refuseForm.setData({
                                                decision: defaultRefusalDecision,
                                                signer_name: patientName,
                                                signer_relationship: 'patient',
                                                representative_verification: '',
                                                reason: '',
                                                confirmed: false,
                                            });
                                            setRefuseOpen(true);
                                        }}
                                    >
                                        <Ban className="size-4" />Catat penolakan / pencabutan
                                    </Button>
                                )}
                                {canActivateEmergency && (
                                    <Button
                                        size="sm"
                                        type="button"
                                        variant="destructive"
                                        onClick={() => {
                                            emergencyForm.clearErrors();
                                            emergencyForm.reset();
                                            setEmergencyOpen(true);
                                        }}
                                    >
                                        <ShieldAlert className="size-4" />Mode Darurat / Penjelasan Menyusul
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}

                    {completed ? (
                        <div className="space-y-2 rounded-xl border border-neutral-200 p-4">
                            <p className="text-xs font-medium text-neutral-500">Hasil pelaksanaan dan respons pasien</p>
                            <p className="whitespace-pre-wrap text-sm text-neutral-900">{item.result}</p>
                            <p className="text-xs text-neutral-500">Dicatat oleh {item.performedBy} · {item.performedAt}</p>
                        </div>
                    ) : canComplete ? (
                        <form className="space-y-5" onSubmit={submit}>
                            {adminAssistance && (
                                <AssistanceReasonField
                                    error={form.errors.assistance_reason}
                                    id={`assistance-${item.id}`}
                                    value={form.data.assistance_reason}
                                    onChange={(value) => form.setData('assistance_reason', value)}
                                    disabled={form.processing}
                                />
                            )}
                            {consentError && (
                                <Alert dismissible={false} variant="destructive">
                                    <ShieldAlert className="size-4" />
                                    <AlertTitle>Pelaksanaan dikunci oleh persetujuan tindakan</AlertTitle>
                                    <AlertDescription>{consentError}</AlertDescription>
                                </Alert>
                            )}
                            {stockError && (
                                <Alert key={stockError} autoDismissMs={12000} variant="destructive">
                                    <AlertTitle>Pelaksanaan belum tersimpan</AlertTitle>
                                    <AlertDescription>{stockError}</AlertDescription>
                                </Alert>
                            )}
                            <Field htmlFor={`result-${item.id}`} label="Hasil pelaksanaan dan respons pasien" error={form.errors.execution_result} required>
                                <Textarea
                                    id={`result-${item.id}`}
                                    maxLength={5000}
                                    minLength={3}
                                    required
                                    disabled={form.processing}
                                    value={form.data.execution_result}
                                    onChange={(event) => form.setData('execution_result', event.target.value)}
                                    placeholder="Catat tindakan yang benar-benar dilakukan, hasil observasi, dan respons pasien."
                                />
                            </Field>
                            <div className="flex justify-end border-t border-neutral-100 pt-4">
                                <Button type="submit" disabled={form.processing || form.data.execution_result.trim().length < 3}>
                                    <Save className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan pelaksanaan tindakan'}
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <p className="text-sm text-neutral-500">
                            {allowed
                                ? 'Klik Mulai tindakan di atas untuk membuka pencatatan hasil pelaksanaan.'
                                : isRefusedOrWithdrawn
                                    ? 'Pelaksanaan dikunci karena penolakan atau pencabutan persetujuan. Koordinasikan dengan dokter penanggung jawab.'
                                    : 'Pelaksanaan dikunci sampai dokter menyelesaikan persetujuan tindakan atau (khusus triase darurat) mode Darurat / Penjelasan Menyusul diaktifkan.'}
                        </p>
                    )}
                </CardContent>
            </Card>

            <Dialog open={refuseOpen} onOpenChange={(open) => { if (!open && !refuseForm.processing) setRefuseOpen(false); }}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Catat penolakan / pencabutan persetujuan</DialogTitle>
                        <DialogDescription>
                            {item.name} · Pencatatan ini mengunci pelaksanaan tindakan secara permanen pada jejak audit rekam medis sampai dokter mencatat keputusan baru yang sah.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitRefusal}>
                        <Field htmlFor={`refuse-decision-${item.id}`} label="Jenis keputusan di ruang tindakan" error={refuseForm.errors.decision} required>
                            <Select
                                id={`refuse-decision-${item.id}`}
                                value={refuseForm.data.decision}
                                onChange={(event) => refuseForm.setData('decision', event.target.value)}
                            >
                                <option value="refused">Pasien / wali menolak tindakan</option>
                                {['accepted', 'oral'].includes(consent?.decision ?? '') && (
                                    <option value="withdrawn">Pasien / wali mencabut persetujuan sebelumnya</option>
                                )}
                            </Select>
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field htmlFor={`refuse-signer-${item.id}`} label="Nama yang menyatakan" error={refuseForm.errors.signer_name} required>
                                <Input
                                    id={`refuse-signer-${item.id}`}
                                    minLength={2}
                                    maxLength={150}
                                    required
                                    value={refuseForm.data.signer_name}
                                    onChange={(event) => refuseForm.setData('signer_name', event.target.value)}
                                />
                            </Field>
                            <Field htmlFor={`refuse-rel-${item.id}`} label="Hubungan dengan pasien" error={refuseForm.errors.signer_relationship} required>
                                <Select
                                    id={`refuse-rel-${item.id}`}
                                    value={refuseForm.data.signer_relationship}
                                    onChange={(event) => {
                                        refuseForm.setData('signer_relationship', event.target.value);
                                        if (event.target.value === 'patient') {
                                            refuseForm.setData('signer_name', patientName);
                                            refuseForm.setData('representative_verification', '');
                                        }
                                    }}
                                >
                                    <option value="patient">Pasien sendiri</option>
                                    <option value="parent">Orang tua</option>
                                    <option value="spouse">Suami / istri</option>
                                    <option value="guardian">Wali / pengampu</option>
                                    <option value="other">Wakil lain yang sah</option>
                                </Select>
                            </Field>
                        </div>
                        {refuseForm.data.signer_relationship !== 'patient' && (
                            <Field htmlFor={`refuse-verif-${item.id}`} label="Verifikasi kewenangan wali" error={refuseForm.errors.representative_verification} required>
                                <Textarea
                                    id={`refuse-verif-${item.id}`}
                                    minLength={10}
                                    maxLength={1000}
                                    required
                                    rows={2}
                                    value={refuseForm.data.representative_verification}
                                    onChange={(event) => refuseForm.setData('representative_verification', event.target.value)}
                                    placeholder="Catat dasar kewenangan wali yang diverifikasi (minimal 10 karakter)."
                                />
                            </Field>
                        )}
                        <Field htmlFor={`refuse-reason-${item.id}`} label="Kronologi dan alasan penolakan / pembatalan" error={refuseForm.errors.reason} required>
                            <Textarea
                                id={`refuse-reason-${item.id}`}
                                minLength={20}
                                maxLength={2000}
                                required
                                rows={3}
                                value={refuseForm.data.reason}
                                onChange={(event) => refuseForm.setData('reason', event.target.value)}
                                placeholder="Catat pernyataan pasien/wali, edukasi risiko penolakan, dan pelaporan kepada dokter penanggung jawab (minimal 20 karakter)."
                            />
                        </Field>
                        <Field htmlFor={`refuse-confirm-${item.id}`} label="Konfirmasi perawat" error={refuseForm.errors.confirmed} required>
                            <label className="flex cursor-pointer items-start gap-2.5 text-sm leading-6 text-neutral-700">
                                <Checkbox
                                    id={`refuse-confirm-${item.id}`}
                                    className="mt-1 shrink-0"
                                    checked={refuseForm.data.confirmed}
                                    onCheckedChange={(checked) => refuseForm.setData('confirmed', checked === true)}
                                />
                                <span>Saya mengonfirmasi pasien atau wakil menolak/mencabut persetujuan tindakan ini dan dokter penanggung jawab telah/segera diinformasikan.</span>
                            </label>
                        </Field>
                        <FormActionFooter className="pt-2">
                            <Button disabled={refuseForm.processing} type="button" variant="secondary" onClick={() => setRefuseOpen(false)}>Batal</Button>
                            <Button disabled={refuseForm.processing || !refuseForm.data.confirmed || refuseForm.data.reason.trim().length < 20} type="submit" variant="destructive">
                                {refuseForm.processing ? 'Menyimpan…' : 'Simpan penolakan & kunci tindakan'}
                            </Button>
                        </FormActionFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={emergencyOpen} onOpenChange={(open) => { if (!open && !emergencyForm.processing) setEmergencyOpen(false); }}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Aktifkan Mode Darurat / Penjelasan Menyusul</DialogTitle>
                        <DialogDescription>
                            {item.name} · Khusus kondisi gawat darurat medis yang mengancam nyawa atau kecacatan saat persetujuan pasien/wali belum sempat diperoleh (PMK No. 290/2008).
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitEmergency}>
                        <Alert dismissible={false} variant="default">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>Kewajiban dokumentasi lanjutan</AlertTitle>
                            <AlertDescription>
                                Tindakan penyelamatan dicatat pada audit rekam medis. Setelah kondisi pasien stabil atau keluarga/wali hadir, penjelasan klinis dan dokumentasi persetujuan lanjutan wajib dilengkapi.
                            </AlertDescription>
                        </Alert>
                        <Field htmlFor={`emergency-reason-${item.id}`} label="Alasan gawat darurat & rencana kelengkapan menyusul" error={emergencyForm.errors.reason} required>
                            <Textarea
                                id={`emergency-reason-${item.id}`}
                                minLength={20}
                                maxLength={2000}
                                required
                                rows={3}
                                value={emergencyForm.data.reason}
                                onChange={(event) => emergencyForm.setData('reason', event.target.value)}
                                placeholder="Jelaskan kondisi klinis darurat, alasan tindakan tidak dapat ditunda, dan rencana penjelasan menyusul (minimal 20 karakter)."
                            />
                        </Field>
                        <Field htmlFor={`emergency-confirm-${item.id}`} label="Konfirmasi gawat darurat" error={emergencyForm.errors.emergency_without_available_signer} required>
                            <label className="flex cursor-pointer items-start gap-2.5 text-sm leading-6 text-neutral-700">
                                <Checkbox
                                    id={`emergency-confirm-${item.id}`}
                                    className="mt-1 shrink-0"
                                    checked={emergencyForm.data.emergency_without_available_signer}
                                    onCheckedChange={(checked) => emergencyForm.setData('emergency_without_available_signer', checked === true)}
                                />
                                <span>Saya mengonfirmasi pasien dalam kondisi gawat darurat, tidak cakap memberi persetujuan, dan tidak ada wali yang dapat memberi persetujuan saat ini.</span>
                            </label>
                        </Field>
                        <FormActionFooter className="pt-2">
                            <Button disabled={emergencyForm.processing} type="button" variant="secondary" onClick={() => setEmergencyOpen(false)}>Batal</Button>
                            <Button
                                disabled={emergencyForm.processing || !emergencyForm.data.emergency_without_available_signer || emergencyForm.data.reason.trim().length < 20}
                                type="submit"
                                variant="destructive"
                            >
                                {emergencyForm.processing ? 'Mengaktifkan…' : 'Aktifkan mode darurat'}
                            </Button>
                        </FormActionFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
