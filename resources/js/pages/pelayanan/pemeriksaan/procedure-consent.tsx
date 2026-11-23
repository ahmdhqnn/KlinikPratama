import { Head, Link, useForm } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    Download,
    FileCheck2,
    ShieldCheck,
} from "lucide-react";
import { useEffect } from "react";
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

interface Props {
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
    requestId: string;
    storeUrl: string;
    backUrl: string;
}

const labels: Record<Decision, string> = {
    accepted: "Disetujui pasien",
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
}: Props) {
    const form = useForm({
        request_id: requestId,
        current_id: summary.currentId,
        plan_hash: planHash,
        text_version: textVersion,
        decision: "" as Exclude<Decision, "unreviewed"> | "",
        signature_password: "",
        explained: false,
        signer_name: "",
        signer_relationship: "patient",
        representative_verification: "",
        witness_name: "",
        reason: "",
        explanation: { ...emptyExplanation },
        evidence: null as File | null,
    });
    const fieldErrors = form.errors as Record<string, string>;
    const patientDecision = ["accepted", "refused", "withdrawn"].includes(
        form.data.decision,
    );
    const decisionBlocked =
        summary.decision === "refused" || summary.decision === "withdrawn";
    const ready = Boolean(
        form.data.decision &&
        form.data.signature_password &&
        form.data.reason.trim().length >= 20 &&
        form.data.explained &&
        explanationLabels.every(
            ([key]) => form.data.explanation[key].trim().length >= 3,
        ) &&
        (!patientDecision ||
            (form.data.signer_name.trim().length >= 2 && form.data.evidence)),
    );

    useEffect(() => {
        form.setData({
            request_id: requestId,
            current_id: summary.currentId,
            plan_hash: planHash,
            text_version: textVersion,
            decision: "",
            signature_password: "",
            explained: false,
            signer_name: "",
            signer_relationship: "patient",
            representative_verification: "",
            witness_name: "",
            reason: "",
            explanation: { ...emptyExplanation },
            evidence: null,
        });
    }, [summary.currentId, planHash, requestId]);

    return (
        <>
            <Head title="Persetujuan tindakan" />
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
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
                        <Link href={backUrl}>
                            <ArrowLeft className="size-4" />
                            Kembali ke pelayanan
                        </Link>
                    </Button>
                </div>

                <Alert
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

                {canWrite && (
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
                                    form.post(storeUrl, {
                                        forceFormData: true,
                                        preserveScroll: true,
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
                                            {summary.decision ===
                                                "accepted" && (
                                                <option value="withdrawn">
                                                    Pasien / wakil mencabut
                                                    persetujuan
                                                </option>
                                            )}
                                            <option value="not_required">
                                                Dokter menetapkan informed
                                                consent tidak diperlukan
                                            </option>
                                            <option value="emergency">
                                                Pengecualian tindakan darurat
                                            </option>
                                        </Select>
                                    </Field>
                                    <Field
                                        label="Alasan keputusan / klasifikasi"
                                        htmlFor="procedure-reason"
                                        error={form.errors.reason}
                                        required
                                    >
                                        <Textarea
                                            id="procedure-reason"
                                            minLength={20}
                                            maxLength={2000}
                                            required
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
                                    <Alert>
                                        <AlertTriangle className="size-4" />
                                        <AlertTitle>
                                            Khusus keadaan darurat
                                            terdokumentasi
                                        </AlertTitle>
                                        <AlertDescription>
                                            Pilihan ini hanya diterima bila
                                            pengkajian kunjungan mencatat
                                            prioritas darurat. Pilihan ini bukan
                                            pengganti persetujuan untuk
                                            pelayanan biasa.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {form.data.decision === "not_required" && (
                                    <Alert>
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

                                <div className="space-y-4">
                                    <div>
                                        <h3 className="text-sm font-semibold text-neutral-900">
                                            Materi penjelasan spesifik tindakan
                                        </h3>
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
                                </div>

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
                                                        form.data.signer_name
                                                    }
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
                                            <Field
                                                label="Nama saksi (bila ada)"
                                                htmlFor="procedure-witness"
                                                error={form.errors.witness_name}
                                            >
                                                <Input
                                                    id="procedure-witness"
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
                                            </Field>
                                        </div>
                                        <Attachment
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
                                        </p>
                                    </div>
                                )}

                                <div className="space-y-4 border-t border-neutral-100 pt-5">
                                    <Field
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
                                    </Field>
                                    <Field
                                        htmlFor="procedure-explained"
                                        label="Konfirmasi penjelasan"
                                        error={form.errors.explained}
                                        required
                                    >
                                        <label className="flex items-start gap-3 text-sm leading-6 text-neutral-700">
                                            <Checkbox
                                                id="procedure-explained"
                                                checked={form.data.explained}
                                                onCheckedChange={(value) =>
                                                    form.setData(
                                                        "explained",
                                                        value === true,
                                                    )
                                                }
                                            />
                                            <span>
                                                Saya telah memeriksa rencana
                                                tindakan, menjelaskan materi di
                                                atas, memberi kesempatan
                                                bertanya, memverifikasi
                                                keputusan dan mencocokkan bukti
                                                bila diwajibkan.
                                            </span>
                                        </label>
                                    </Field>
                                    {(
                                        [
                                            "current_id",
                                            "request_id",
                                            "text_version",
                                            "plan_hash",
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
                                    <Button
                                        type="submit"
                                        disabled={form.processing || !ready}
                                    >
                                        {form.processing
                                            ? "Menyimpan…"
                                            : "Simpan keputusan tindakan"}
                                    </Button>
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
