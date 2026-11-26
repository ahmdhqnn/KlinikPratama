import { Head, Link, useForm } from "@inertiajs/react";
import { handleBackNavigation } from "@/lib/navigation";
import {
    AlertTriangle,
    ArrowLeft,
    Download,
    FileCheck2,
    ShieldCheck,
} from "lucide-react";
import { useEffect, useState } from "react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Attachment } from "@/components/ui/attachment";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";

type Decision =
    | "accepted"
    | "oral"
    | "refused"
    | "withdrawn"
    | "not_required"
    | "emergency"
    | "unreviewed";
type Explanation = {
    indication: string;
    method: string;
    benefits: string;
    risks: string;
    alternatives: string;
    without_procedure: string;
    prognosis: string;
};

export interface ProcedureConsentProps {
    onSaved?: () => void;
    visit: { id: number; patient: string; medicalRecordNumber: string };
    procedure: {
        id: number;
        name: string;
        code: string | null;
        tooth: string | null;
        quantity: number;
        role: string;
    };
    summary: {
        decision: Decision;
        currentId: number | null;
        recordedAt: string | null;
        planMatches: boolean;
        requiredReview: boolean;
    };
    history: {
        id: number;
        decision: Decision;
        signer: string | null;
        relationship: string | null;
        witness: string | null;
        actor: string | null;
        at: string | null;
        reason: string | null;
        explanation: Explanation | null;
        evidenceUrl: string | null;
    }[];
    textVersion: string;
    planHash: string;
    canWrite: boolean;
    witnessSuggestion?: string | null;
    diagnosisSuggestion?: string | null;
    passwordVerified?: boolean;
    template?: { id: number; revision: string; version: string; mode: string; sop_reference: string; trigger_condition: string | null; explanation: Explanation } | null;
    templateArchived?: boolean;
    signatureAvailable?: boolean;
    emergencyAvailable?: boolean;
    signatureSessionUrl?: string | null;
    requestId: string;
    storeUrl: string;
    backUrl: string;
}

const labels: Record<Decision, string> = {
    accepted: "Disetujui pasien",
    oral: "Disetujui secara lisan",
    refused: "Ditolak pasien",
    withdrawn: "Persetujuan dicabut",
    not_required: "Tidak memerlukan informed consent",
    emergency: "Pengecualian kondisi darurat",
    unreviewed: "Belum ditinjau",
};

const explanationLabels: Array<[keyof Explanation, string, string]> = [
    [
        "indication",
        "Indikasi dan diagnosis kerja",
        "Mengapa tindakan ini diperlukan untuk kondisi pasien.",
    ],
    [
        "method",
        "Cara dan tahapan tindakan",
        "Jelaskan metode, lokasi, anestesi atau langkah relevan.",
    ],
    [
        "benefits",
        "Tujuan dan manfaat yang diharapkan",
        "Hasil yang diharapkan tanpa menjanjikan keberhasilan.",
    ],
    [
        "risks",
        "Risiko dan komplikasi",
        "Risiko umum serta risiko bermakna untuk pasien ini.",
    ],
    [
        "alternatives",
        "Alternatif tindakan",
        "Pilihan lain yang tersedia, termasuk menunda bila relevan.",
    ],
    [
        "without_procedure",
        "Konsekuensi bila tidak dilakukan",
        "Kemungkinan perjalanan kondisi tanpa tindakan.",
    ],
    [
        "prognosis",
        "Prognosis setelah tindakan",
        "Perkiraan hasil, pemulihan dan tindak lanjut yang diperlukan.",
    ],
];

const emptyExplanation: Explanation = {
    indication: "",
    method: "",
    benefits: "",
    risks: "",
    alternatives: "",
    without_procedure: "",
    prognosis: "",
};

export default function ProcedureConsent({
    visit,
    procedure,
    summary,
    history,
    textVersion,
    planHash,
    canWrite,
    requestId,
    storeUrl,
    backUrl,
    onSaved,
    witnessSuggestion,
    diagnosisSuggestion,
    passwordVerified = false,
    template,
    templateArchived = false,
    signatureAvailable = false,
    emergencyAvailable = false,
    signatureSessionUrl,
}: ProcedureConsentProps) {
    const [useQr, setUseQr] = useState(signatureAvailable);
    const form = useForm({
        request_id: requestId,
        current_id: summary.currentId,
        plan_hash: planHash,
        template_id: template?.id ?? null,
        template_revision: template?.revision ?? null,
        written_trigger_absent: false,
        text_version: textVersion,
        decision: "" as Exclude<Decision, "unreviewed"> | "",
        signature_password: "",
        remember_verification: false,
        explained: false,
        emergency_without_available_signer: false,
        identity_verified: false,
        signer_name: "",
        signer_relationship: "patient",
        representative_verification: "",
        witness_name: "",
        reason: "",
        explanation: { ...emptyExplanation },
        evidence: null as File | null,
    });
    const fieldErrors = form.errors as Record<string, string>;
    const classificationOnly = form.data.decision === "not_required";
    const oralDecision = form.data.decision === "oral";
    const emergencyDecision = form.data.decision === "emergency";
    const reviewWrittenTrigger = oralDecision && template?.mode === "conditional";
    const needsPassword = !passwordVerified || Boolean(form.errors.signature_password);
    const patientDecision = ["accepted", "oral", "refused", "withdrawn"].includes(
        form.data.decision,
    );
    const startQr = signatureAvailable && useQr && patientDecision && !oralDecision;
    const decisionBlocked =
        summary.decision === "refused" || summary.decision === "withdrawn";
    const ready = Boolean(
        form.data.decision &&
        (!needsPassword || form.data.signature_password) &&
        (oralDecision && !form.data.reason.trim() || form.data.reason.trim().length >= 20) &&
        (emergencyDecision ? form.data.emergency_without_available_signer : form.data.explained) &&
        (!reviewWrittenTrigger || form.data.written_trigger_absent) &&
        (startQr ? form.data.identity_verified : form.data.decision !== "accepted" || form.data.witness_name.trim()) &&
        (classificationOnly || explanationLabels.every(
            ([key]) => form.data.explanation[key].trim().length >= 3,
        )) &&
        (!patientDecision ||
            ((startQr && form.data.signer_relationship === "patient" || form.data.signer_name.trim().length >= 2)
                && (startQr || oralDecision || form.data.evidence))),
    );

    useEffect(() => {
        form.setData({
            request_id: requestId,
            current_id: summary.currentId,
            plan_hash: planHash,
            template_id: template?.id ?? null,
            template_revision: template?.revision ?? null,
            written_trigger_absent: false,
            text_version: textVersion,
            decision: "",
            signature_password: "",
            remember_verification: false,
            explained: false,
            emergency_without_available_signer: false,
            identity_verified: false,
            signer_name: "",
            signer_relationship: "patient",
            representative_verification: "",
            witness_name: "",
            reason: "",
            explanation: { ...emptyExplanation },
            evidence: null,
        });
    }, [summary.currentId, planHash, requestId, template?.revision]);

    return (
        <>
            {!onSaved && <Head title="Persetujuan tindakan" />}
            <div className="mx-auto max-w-5xl space-y-6">
                {!onSaved && <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-neutral-700">
                            Dokumentasi klinis
                        </p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">
                            Persetujuan tindakan
                        </h2>
                        <p className="mt-1 text-sm text-neutral-500">
                            {visit.patient} · No. RM {visit.medicalRecordNumber}
                        </p>
                    </div>
                    <Button asChild variant="secondary">
                        <Link href={backUrl} onClick={(event) => handleBackNavigation(event, backUrl)}>
                            <ArrowLeft className="size-4" />
                            Kembali ke pelayanan
                        </Link>
                    </Button>
                </div>}

                <Alert
                    dismissible={false}
                    variant={
                        summary.decision === "refused" ||
                        summary.decision === "withdrawn" ||
                        (!summary.planMatches && summary.currentId)
                            ? "destructive"
                            : "default"
                    }
                >
                    {summary.decision === "refused" ||
                    summary.decision === "withdrawn" ? (
                        <AlertTriangle className="size-4" />
                    ) : (
                        <ShieldCheck className="size-4" />
                    )}
                    <AlertTitle>{labels[summary.decision]}</AlertTitle>
                    <AlertDescription>
                        {summary.recordedAt
                            ? `Keputusan terakhir tercatat ${summary.recordedAt}. `
                            : "Belum ada keputusan untuk rencana tindakan ini. "}
                        {!summary.planMatches && summary.currentId
                            ? "Rencana berubah sehingga keputusan sebelumnya tidak dapat digunakan. "
                            : ""}
                        {decisionBlocked
                            ? "Pelaksanaan dikunci sampai ada keputusan pasien baru yang sah."
                            : "Pelaksanaan hanya dibuka setelah tinjauan ini lengkap."}
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader>
                        <CardTitle>Rencana tindakan yang ditinjau</CardTitle>
                        <CardDescription>
                            Keputusan hanya berlaku untuk rincian ini. Perubahan
                            rencana akan meminta tinjauan ulang.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt className="text-xs text-neutral-500">
                                    Tindakan
                                </dt>
                                <dd className="mt-1 text-sm font-semibold text-neutral-900">
                                    {procedure.name}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-neutral-500">
                                    Kode
                                </dt>
                                <dd className="mt-1 font-sans text-sm text-neutral-900">
                                    {procedure.code || "Belum berkode"}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-neutral-500">
                                    Lokasi / jumlah
                                </dt>
                                <dd className="mt-1 text-sm text-neutral-900">
                                    {procedure.tooth
                                        ? `Gigi ${procedure.tooth} · `
                                        : ""}
                                    {procedure.quantity} kali
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-neutral-500">
                                    Pelaksana
                                </dt>
                                <dd className="mt-1 text-sm text-neutral-900">
                                    {procedure.role === "perawat"
                                        ? "Perawat sesuai instruksi dokter"
                                        : "Dokter pemeriksa"}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {signatureSessionUrl && (
                    <Alert
                        action={
                            <Button size="sm" variant="secondary" asChild>
                                <Link href={signatureSessionUrl}>Buka sesi tanda tangan</Link>
                            </Button>
                        }
                        dismissible={false}
                        variant="default"
                    >
                        <AlertTriangle aria-hidden="true" className="size-4" />
                        <AlertTitle>Sesi tanda tangan belum selesai</AlertTitle>
                        <AlertDescription>Ada sesi tanda tangan yang perlu diselesaikan.</AlertDescription>
                    </Alert>
                )}
                {canWrite && !signatureSessionUrl && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Catat penjelasan dan keputusan
                            </CardTitle>
                            <CardDescription>
                                Versi formulir {textVersion}. Data waktu dan
                                petugas direkam otomatis oleh server; entri baru
                                tidak menimpa riwayat.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="space-y-6"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.transform(({ explanation, ...data }) => ({
                                        ...data, ...(!classificationOnly ? { explanation } : {}),
                                        ...(startQr ? {
                                            start_signature: true, evidence: null, witness_name: "",
                                            signer_name: data.signer_relationship === "patient" ? visit.patient : data.signer_name,
                                        } : {}),
                                    }));
                                    form.post(storeUrl, {
                                        forceFormData: true,
                                        preserveScroll: true,
                                        preserveState: true,
                                        onSuccess: onSaved,
                                    });
                                }}
                            >
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        label="Keputusan / klasifikasi dokter"
                                        htmlFor="procedure-decision"
                                        error={form.errors.decision}
                                        required
                                    >
                                        <Select
                                            id="procedure-decision"
                                            required
                                            value={form.data.decision}
                                            onChange={(event) => {
                                                form.setData(
                                                    "decision",
                                                    event.target
                                                        .value as typeof form.data.decision,
                                                );
                                                form.setData("evidence", null);
                                                form.setData("explained", false);
                                                form.setData("emergency_without_available_signer", false);
                                                form.setData("written_trigger_absent", false);
                                            }}
                                        >
                                            <option value="">
                                                Pilih keputusan
                                            </option>
                                            <option value="accepted">
                                                Pasien / wakil menyetujui
                                            </option>
                                            <option value="refused">
                                                Pasien / wakil menolak setelah
                                                penjelasan
                                            </option>
                                            {["accepted", "oral"].includes(summary.decision) && (
                                                <option value="withdrawn">
                                                    Pasien / wakil mencabut
                                                    persetujuan
                                                </option>
                                            )}
                                            {["oral", "conditional"].includes(template?.mode ?? "") && !["accepted", "refused", "withdrawn"].includes(summary.decision) && <option value="oral">Pasien / wakil menyetujui secara lisan</option>}
                                            {!template && !templateArchived && !["accepted", "oral", "refused", "withdrawn"].includes(summary.decision) && <option value="not_required">
                                                Dokter menetapkan informed
                                                consent tidak diperlukan
                                            </option>}
                                            {emergencyAvailable && !["refused", "withdrawn"].includes(summary.decision) && <option value="emergency">
                                                Pengecualian tindakan darurat
                                            </option>}
                                        </Select>
                                    </Field>
                                    <Field
                                        label="Alasan keputusan / klasifikasi"
                                        htmlFor="procedure-reason"
                                        error={form.errors.reason}
                                        required={!oralDecision}
                                    >
                                        <Textarea
                                            id="procedure-reason"
                                            minLength={20}
                                            maxLength={2000}
                                            required={!oralDecision}
                                            value={form.data.reason}
                                            onChange={(event) =>
                                                form.setData(
                                                    "reason",
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Minimal 20 karakter; catat alasan klinis atau keputusan pasien secara faktual."
                                        />
                                    </Field>
                                </div>
                                {form.data.decision === "emergency" && (
                                    <Alert dismissible={false} variant="default">
                                        <AlertTriangle className="size-4" />
                                        <AlertTitle>
                                            Khusus keadaan darurat
                                            terdokumentasi
                                        </AlertTitle>
                                        <AlertDescription>
                                            Catat alasan tindakan perlu segera dilakukan serta kondisi pasien dan ketersediaan wakil.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {form.data.decision === "not_required" && (
                                    <Alert dismissible={false} variant="default">
                                        <FileCheck2 className="size-4" />
                                        <AlertTitle>
                                            Klasifikasi klinis, bukan
                                            persetujuan pasien
                                        </AlertTitle>
                                        <AlertDescription>
                                            Gunakan hanya bila dokter menilai
                                            tindakan tidak termasuk tindakan
                                            yang memerlukan informed consent.
                                            Penolakan atau pencabutan yang sudah
                                            tercatat tidak dapat dilewati dengan
                                            pilihan ini.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {!classificationOnly && <div className="space-y-4">
                                    <div>
                                        <h3 className="text-sm font-semibold text-neutral-900">
                                            Materi penjelasan spesifik tindakan
                                        </h3>
                                        {template && <div className="mt-2 space-y-2">
                                            <p className="text-sm text-neutral-600">SOP {template.sop_reference} · Versi {template.version}</p>
                                            {template.trigger_condition && <p className="whitespace-pre-wrap text-sm text-neutral-700">{template.trigger_condition}</p>}
                                            {reviewWrittenTrigger && <label className="flex items-start gap-2 text-sm">
                                                <Checkbox checked={form.data.written_trigger_absent} onCheckedChange={(value) => form.setData("written_trigger_absent", value === true)} />
                                                <span>Saya telah memeriksa kondisi pasien dan memastikan pemicu persetujuan tertulis pada SOP tidak berlaku.</span>
                                            </label>}
                                            <Button type="button" size="sm" variant="secondary" onClick={() => form.setData((data) => ({
                                                ...data,
                                                explained: false,
                                                explanation: Object.fromEntries(explanationLabels.map(([key]) => [
                                                    key, data.explanation[key].trim() ? data.explanation[key] : template.explanation[key],
                                                ])) as Explanation,
                                            }))}>Muat draf SOP</Button>
                                        </div>}
                                        <Button type="button" size="sm" variant="secondary" className="mt-2" onClick={() => form.setData((data) => ({
                                            ...data,
                                            explained: false,
                                            explanation: {
                                                ...data.explanation,
                                                indication: data.explanation.indication || (diagnosisSuggestion ? `Diagnosis utama: ${diagnosisSuggestion}\n` : ""),
                                                method: data.explanation.method || [procedure.name, procedure.tooth ? `Gigi ${procedure.tooth}` : null].filter(Boolean).join(" · "),
                                            },
                                        }))}>Ambil diagnosis dan rencana tercatat</Button>
                                        <p className="mt-1 text-sm text-neutral-500">
                                            Isi sesuai kondisi pasien dan
                                            rencana di atas; jangan memakai
                                            pernyataan otomatis yang belum
                                            diperiksa.
                                        </p>
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {explanationLabels.map(
                                            ([key, label, placeholder]) => (
                                                <Field
                                                    key={key}
                                                    label={label}
                                                    htmlFor={`explanation-${key}`}
                                                    error={
                                                        fieldErrors[
                                                            `explanation.${key}`
                                                        ]
                                                    }
                                                    required
                                                >
                                                    <Textarea
                                                        id={`explanation-${key}`}
                                                        minLength={3}
                                                        maxLength={3000}
                                                        required
                                                        value={
                                                            form.data
                                                                .explanation[
                                                                key
                                                            ]
                                                        }
                                                        onChange={(event) =>
                                                            form.setData(
                                                                "explanation",
                                                                {
                                                                    ...form.data
                                                                        .explanation,
                                                                    [key]: event
                                                                        .target
                                                                        .value,
                                                                },
                                                            )
                                                        }
                                                        placeholder={
                                                            placeholder
                                                        }
                                                    />
                                                </Field>
                                            ),
                                        )}
                                    </div>
                                </div>}

                                {signatureAvailable && patientDecision && !oralDecision && <Field htmlFor="procedure-evidence-method" label="Cara mengambil tanda tangan">
                                    <Select id="procedure-evidence-method" value={useQr ? "qr" : "paper"} onChange={(event) => {
                                        setUseQr(event.target.value === "qr");
                                        form.setData("identity_verified", false);
                                    }}>
                                        <option value="qr">QR tatap muka</option>
                                        <option value="paper">Unggah bukti kertas</option>
                                    </Select>
                                </Field>}
                                {patientDecision && (
                                    <div className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <Field
                                                label="Nama penandatangan"
                                                htmlFor="procedure-signer"
                                                error={form.errors.signer_name}
                                                required
                                            >
                                                <Input
                                                    id="procedure-signer"
                                                    minLength={2}
                                                    maxLength={150}
                                                    required
                                                    value={
                                                        startQr && form.data.signer_relationship === "patient" ? visit.patient : form.data.signer_name
                                                    }
                                                    readOnly={startQr && form.data.signer_relationship === "patient"}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "signer_name",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Hubungan dengan pasien"
                                                htmlFor="procedure-relationship"
                                                error={
                                                    form.errors
                                                        .signer_relationship
                                                }
                                                required
                                            >
                                                <Select
                                                    id="procedure-relationship"
                                                    value={
                                                        form.data
                                                            .signer_relationship
                                                    }
                                                    onChange={(event) => {
                                                        form.setData(
                                                            "signer_relationship",
                                                            event.target.value,
                                                        );
                                                        form.setData(
                                                            "representative_verification",
                                                            "",
                                                        );
                                                    }}
                                                >
                                                    <option value="patient">
                                                        Pasien sendiri
                                                    </option>
                                                    <option value="parent">
                                                        Orang tua
                                                    </option>
                                                    <option value="spouse">
                                                        Suami / istri
                                                    </option>
                                                    <option value="guardian">
                                                        Wali / pengampu
                                                    </option>
                                                    <option value="other">
                                                        Wakil lain yang sah
                                                    </option>
                                                </Select>
                                            </Field>
                                            {form.data.signer_relationship !==
                                                "patient" && (
                                                <Field
                                                    label="Dasar kewenangan dan verifikasi wakil"
                                                    htmlFor="procedure-representative"
                                                    error={
                                                        form.errors
                                                            .representative_verification
                                                    }
                                                    required
                                                >
                                                    <Textarea
                                                        id="procedure-representative"
                                                        minLength={10}
                                                        maxLength={1000}
                                                        required
                                                        value={
                                                            form.data
                                                                .representative_verification
                                                        }
                                                        onChange={(event) =>
                                                            form.setData(
                                                                "representative_verification",
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="Catat dasar kewenangan yang diperiksa; hindari menyalin nomor identitas lengkap."
                                                    />
                                                </Field>
                                            )}
                                            {!startQr && <Field
                                                label="Nama saksi tenaga medis / kesehatan"
                                                htmlFor="procedure-witness"
                                                error={form.errors.witness_name}
                                                required={form.data.decision === "accepted"}
                                            >
                                                <Input
                                                    id="procedure-witness"
                                                    required={form.data.decision === "accepted"}
                                                    maxLength={150}
                                                    value={
                                                        form.data.witness_name
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "witness_name",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                {witnessSuggestion && !form.data.witness_name && (
                                                    <Button type="button" size="sm" variant="secondary" onClick={() => form.setData("witness_name", witnessSuggestion)}>
                                                        Gunakan {witnessSuggestion} jika menyaksikan
                                                    </Button>
                                                )}
                                            </Field>}
                                        </div>
                                        {startQr && <Field htmlFor="procedure-identity-verified" label="Identitas penanda tangan" error={form.errors.identity_verified}>
                                            <label className="flex items-start gap-2 text-sm">
                                                <Checkbox id="procedure-identity-verified" checked={form.data.identity_verified} onCheckedChange={(value) => form.setData("identity_verified", value === true)} />
                                                <span>Saya telah memeriksa langsung identitas pasien atau kewenangan wakil yang akan menggunakan QR.</span>
                                            </label>
                                        </Field>}
                                        {!oralDecision && !startQr && <><Attachment
                                            id="procedure-evidence"
                                            required
                                            inputKey={summary.currentId ?? 0}
                                            label="Bukti keputusan bertanda tangan"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            fileName={form.data.evidence?.name}
                                            error={form.errors.evidence}
                                            onFileChange={(file) =>
                                                form.setData("evidence", file)
                                            }
                                        />
                                        <p className="text-xs text-neutral-500">
                                            PDF, JPG atau PNG, maksimal 5 MB.
                                            Bukti harus sesuai pasien, tindakan,
                                            keputusan dan materi penjelasan ini.
                                        </p></>}
                                    </div>
                                )}

                                <div className="space-y-4 border-t border-neutral-100 pt-5">
                                    {needsPassword ? <Field
                                        label="Konfirmasi ulang kata sandi dokter"
                                        htmlFor="procedure-password"
                                        error={form.errors.signature_password}
                                        required
                                    >
                                        <Input
                                            id="procedure-password"
                                            autoComplete="current-password"
                                            type="password"
                                            required
                                            value={form.data.signature_password}
                                            onChange={(event) =>
                                                form.setData(
                                                    "signature_password",
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Kata sandi akun yang sedang digunakan"
                                        />
                                        <label className="flex items-start gap-3 text-sm">
                                            <Checkbox checked={form.data.remember_verification} onCheckedChange={(checked) => form.setData("remember_verification", checked === true)} />
                                            <span>Gunakan konfirmasi ini selama 15 menit untuk persetujuan tindakan pada kunjungan ini.</span>
                                        </label>
                                    </Field> : <p className="text-sm text-neutral-600">Kata sandi sudah dikonfirmasi untuk kunjungan ini.</p>}
                                    <Field
                                        htmlFor="procedure-explained"
                                        label={classificationOnly || emergencyDecision ? "Konfirmasi dokter" : "Konfirmasi penjelasan"}
                                        error={emergencyDecision ? form.errors.emergency_without_available_signer : form.errors.explained}
                                        required
                                    >
                                        <label className="flex items-start gap-3 text-sm leading-6 text-neutral-700">
                                            <Checkbox
                                                id="procedure-explained"
                                                checked={emergencyDecision ? form.data.emergency_without_available_signer : form.data.explained}
                                                onCheckedChange={(value) =>
                                                    form.setData(
                                                        emergencyDecision ? "emergency_without_available_signer" : "explained",
                                                        value === true,
                                                    )
                                                }
                                            />
                                            <span>{classificationOnly ? (
                                                "Saya telah meninjau rencana tindakan dan mencatat dasar klinis bahwa formulir persetujuan khusus tidak diperlukan. Tindakan invasif atau berisiko tinggi wajib memiliki persetujuan tertulis."
                                            ) : emergencyDecision ? (
                                                "Saya mengonfirmasi pasien memerlukan tindakan gawat darurat, tidak cakap memberi persetujuan, dan tidak ada pihak yang dapat dimintai persetujuan saat ini."
                                            ) : startQr ? (
                                                "Saya telah meninjau dan menjelaskan materi ini serta memberi kesempatan bertanya. Pasien atau wakil akan mengonfirmasi keputusan pada layar tanda tangan."
                                            ) : (
                                                <>
                                                    Saya telah memeriksa rencana
                                                tindakan, menjelaskan materi di
                                                atas, memberi kesempatan
                                                bertanya, memverifikasi
                                                keputusan dan mencocokkan bukti
                                                bila diwajibkan.
                                                </>
                                            )}</span>
                                        </label>
                                    </Field>
                                    {(
                                        [
                                            "current_id",
                                            "request_id",
                                            "text_version",
                                            "plan_hash",
                                            "template_id",
                                        ] as const
                                    ).map(
                                        (key) =>
                                            fieldErrors[key] && (
                                                <p
                                                    key={key}
                                                    role="alert"
                                                    className="text-sm text-red-600"
                                                >
                                                    {fieldErrors[key]}
                                                </p>
                                            ),
                                    )}
                                    <div className="flex justify-end border-t border-neutral-200 pt-4">
                                        <Button
                                            type="submit"
                                            disabled={form.processing || !ready}
                                        >
                                            {form.processing
                                                ? "Menyimpan…"
                                                : startQr ? "Buat QR tanda tangan" : "Simpan keputusan tindakan"}
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat keputusan</CardTitle>
                        <CardDescription>
                            Urutan waktu berasal dari server. Bukti lama
                            dipertahankan dan tidak dapat diubah.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {history.length ? (
                            <ul className="divide-y divide-neutral-100">
                                {history.map((item) => (
                                    <li
                                        key={item.id}
                                        className="space-y-3 py-5 first:pt-0 last:pb-0"
                                    >
                                        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="text-sm font-semibold text-neutral-900">
                                                        {labels[item.decision]}
                                                    </p>
                                                    <Badge
                                                        variant={
                                                            item.decision ===
                                                            "accepted"
                                                                ? "complete"
                                                                : item.decision ===
                                                                        "refused" ||
                                                                    item.decision ===
                                                                        "withdrawn"
                                                                  ? "cancelled"
                                                                  : "default"
                                                        }
                                                    >
                                                        {item.at ||
                                                            "Waktu tidak tersedia"}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 text-xs text-neutral-500">
                                                    Dicatat oleh{" "}
                                                    {item.actor ||
                                                        "akun tidak tersedia"}
                                                    {item.signer
                                                        ? ` · Penandatangan ${item.signer}${item.relationship ? ` (${item.relationship})` : ""}`
                                                        : ""}
                                                    {item.witness
                                                        ? ` · Saksi ${item.witness}`
                                                        : ""}
                                                </p>
                                            </div>
                                            {item.evidenceUrl && (
                                                <Button
                                                    asChild
                                                    variant="secondary"
                                                    size="sm"
                                                >
                                                    <a href={item.evidenceUrl}>
                                                        <Download className="size-4" />
                                                        Unduh bukti
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                        {item.reason && (
                                            <p className="whitespace-pre-wrap text-sm text-neutral-700">
                                                {item.reason}
                                            </p>
                                        )}
                                        {item.explanation && (
                                            <details className="rounded-xl border border-neutral-200 p-4">
                                                <summary className="cursor-pointer text-sm font-medium text-neutral-800">
                                                    Lihat materi penjelasan
                                                </summary>
                                                <dl className="mt-4 grid gap-4 md:grid-cols-2">
                                                    {explanationLabels.map(
                                                        ([key, label]) => (
                                                            <div key={key}>
                                                                <dt className="text-xs font-medium text-neutral-500">
                                                                    {label}
                                                                </dt>
                                                                <dd className="mt-1 whitespace-pre-wrap text-sm text-neutral-700">
                                                                    {item
                                                                        .explanation?.[
                                                                        key
                                                                    ] ||
                                                                        "Tidak tersedia"}
                                                                </dd>
                                                            </div>
                                                        ),
                                                    )}
                                                </dl>
                                            </details>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-neutral-500">
                                Belum ada keputusan yang tercatat untuk tindakan
                                ini.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
