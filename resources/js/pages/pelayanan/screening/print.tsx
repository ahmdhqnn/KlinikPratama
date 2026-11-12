import { ScreeningVersionHistory, type ScreeningVersionSummary } from '@/components/kunjungan/screening-version-history';
import { ClinicalDocumentHeader } from '@/components/clinical-document-header';
import { printWithFonts } from '@/lib/print-typography';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Rows = Record<string, string | null>;

interface Props {
    document: {
        versions?: ScreeningVersionSummary[];
        clinic: { name: string; address: string | null; phone: string | null; email: string | null; logoUrl: string | null };
        patient: { name: string | null; medicalRecordNumber: string | null; gender: string | null; age: number | null; address: string | null };
        visitNumber: string;
        clinicName: string;
        clinicType: string | null;
        doctorName: string | null;
        nurseName: string | null;
        recordedBy?: string | null;
        visitStatus: string;
        isDraft: boolean;
        startedAt: string | null;
        submittedAt: string | null;
        printDate: string;
        printedAt: string;
        signatureDataUrl: string | null;
        backUrl: string;
        sections: {
            general?: Rows;
            history: Rows;
            vitals: Rows;
            measurements: Rows;
            measurementNote: string | null;
            triage: Rows;
            dental: Rows;
            additional: Rows;
        };
    };
}

export default function ScreeningPrint({ document }: Props) {
    const { clinic, patient, sections } = document;
    const patientDetails = [
        ['Jenis kelamin', patient.gender === 'L' ? 'Laki-laki' : patient.gender === 'P' ? 'Perempuan' : null],
        ['Umur', patient.age === null ? null : `${patient.age} tahun`],
        ['Alamat', patient.address?.trim() || 'Belum tercatat'],
    ] as Array<[string, string | null]>;
    const visitDetails = [
        ['Kunjungan', document.visitNumber], ['Poliklinik', document.clinicName],
        ['Petugas', document.nurseName], ['Dokter tujuan', document.doctorName],
        ...(document.recordedBy ? [['Diinput oleh', document.recordedBy] as [string, string]] : []),
    ] as Array<[string, string | null]>;

    return <>
        <Head title={`Pengkajian awal · ${patient.name ?? document.visitNumber}`} />
        <div className="mx-auto max-w-4xl space-y-4 p-4 sm:p-8 print:max-w-none print:p-0">
            <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <Button asChild variant="secondary">
                    <Link href={document.backUrl}>
                        <ArrowLeft className="size-4" />
                        Kembali ke pengkajian awal
                    </Link>
                </Button>
                <div className="flex items-center gap-2">
                    <Button onClick={() => void printWithFonts()} type="button">
                        <Printer className="size-4" />
                        Cetak dokumen
                    </Button>
                </div>
            </div>

            <ScreeningVersionHistory versions={document.versions} />
            <article className="clinical-sheet screening-print-document min-h-[10in] rounded-xl border border-neutral-200 bg-surface p-6 text-neutral-950 shadow-sm sm:p-10 print:min-h-0 print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none">
                <ClinicalDocumentHeader clinic={clinic} />

                <h1 className="mt-6 text-center text-lg font-bold uppercase tracking-tight text-neutral-950 print:text-black">Pengkajian awal perawat &amp; tanda vital</h1>
                <p className="mt-1 text-center text-xs text-neutral-500 print:text-neutral-700">Dokumen pengkajian awal · {document.clinicName} · {document.printDate}</p>

                {document.isDraft && <div className="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-center text-xs font-bold uppercase tracking-wide text-amber-900 print:border-neutral-900 print:bg-neutral-100 print:text-neutral-900">DRAF — BELUM DITERUSKAN KE DOKTER</div>}
                {document.visitStatus === 'batal' && <div className="mt-4 rounded-lg border border-rose-300 bg-rose-50 px-4 py-2 text-center text-xs font-bold uppercase tracking-wide text-rose-900 print:border-neutral-900 print:bg-neutral-100 print:text-neutral-900">KUNJUNGAN DIBATALKAN</div>}

                <section className="screening-print-identity mt-6 border border-neutral-400 text-sm" aria-label="Identitas pasien dan kunjungan">
                    <header className="screening-print-identity-heading flex items-end justify-between gap-4 border-b border-neutral-400 bg-neutral-50 px-3 py-2.5 print:bg-white">
                        <div className="min-w-0"><p className="text-xs text-neutral-500 print:text-neutral-700">Identitas pasien</p><h2 className="break-words text-base font-bold text-neutral-950 print:text-black">{patient.name?.trim() || 'Nama belum tercatat'}</h2></div>
                        <div className="shrink-0 text-right"><p className="text-xs text-neutral-500 print:text-neutral-700">Nomor rekam medis</p><strong className="font-sans tabular-nums text-base text-neutral-950 print:text-black">{patient.medicalRecordNumber?.trim() || 'Belum tercatat'}</strong></div>
                    </header>
                    <dl className="screening-print-identity-grid grid gap-px bg-neutral-300 sm:grid-cols-3">
                        {patientDetails.map(([label, value]) => <div className={`min-w-0 bg-surface px-3 py-2 print:bg-white ${label === 'Alamat' ? 'screening-print-address sm:col-span-1' : ''}`} key={label}><dt className="text-[11px] text-neutral-500 print:text-neutral-700">{label}</dt><dd className="mt-0.5 break-words font-medium text-neutral-900 print:text-black">{value?.trim() || 'Belum tercatat'}</dd></div>)}
                    </dl>
                    <dl className="screening-print-visit-grid grid gap-px border-t border-neutral-400 bg-neutral-300 sm:grid-cols-2 xl:grid-cols-4">
                        {visitDetails.map(([label, value]) => <div className="min-w-0 bg-surface px-3 py-2 print:bg-white" key={label}><dt className="text-[11px] text-neutral-500 print:text-neutral-700">{label}</dt><dd className="mt-0.5 break-words font-medium text-neutral-900 print:text-black">{value?.trim() || 'Belum tercatat'}</dd></div>)}
                    </dl>
                </section>

                <PrintSection title="Keluhan & riwayat kesehatan" rows={sections.history} />
                {<PrintSection title={document.clinicType === 'umum' ? "Pengkajian Poli Umum dan sistem tubuh" : "Ringkasan dan serah terima"} rows={sections.general ?? {}} />}
                <PrintSection title="Tanda vital" rows={sections.vitals} emphasized />
                <PrintSection title="Berat dan tinggi badan" rows={sections.measurements} emphasized note={sections.measurementNote ? <><strong>Catatan pengkajian antropometri:</strong> {sections.measurementNote}</> : null} />
                <PrintSection title="Kondisi yang perlu perhatian" rows={sections.triage} />
                {document.clinicType === 'gigi' && Object.keys(sections.dental).length > 0 && <PrintSection title="Pengkajian khusus gigi dan mulut" rows={sections.dental} note="Asesmen awal ini tidak menyatakan kelayakan anestesi atau tindakan. Keputusan klinis sepenuhnya oleh dokter gigi." />}
                {(Object.keys(sections.additional).length > 0 || document.startedAt || document.submittedAt) && <PrintSection title="Pengkajian tambahan & waktu pengkajian" rows={{
                    ...sections.additional,
                    ...(document.startedAt || document.submittedAt ? { 'Waktu pengkajian': [document.startedAt ? `Mulai ${document.startedAt}` : null, document.submittedAt ? `Dikirim ${document.submittedAt}` : document.isDraft ? 'Belum dikirim' : null].filter(Boolean).join(' · ') } : {}),
                }} />}

                <div className="screening-print-signatures mt-12 grid break-inside-avoid grid-cols-2 gap-8 border-t border-neutral-400 pt-4 text-center text-sm print:mt-8">
                    <SignatureBlock clinicName={document.clinicName} date={document.printDate} heading="Petugas pengkajian awal," name={document.nurseName?.trim() || 'Petugas belum tercatat'} signature={document.signatureDataUrl} note={document.submittedAt ? `Dikirim: ${document.submittedAt}` : 'Petugas pengkaji'} />
                    <SignatureBlock date={document.printDate} heading="Dokter tujuan," name={document.doctorName?.trim() || 'Dokter tujuan belum tercatat'} note="Dokter tujuan; bukan bukti verifikasi atau tanda tangan" subheading="Serah terima dokter pemeriksa," />
                </div>

                <footer className="mt-10 border-t border-neutral-200 pt-4 text-center text-xs text-neutral-500 print:border-neutral-300 print:text-neutral-600">
                    {document.visitNumber} · Dicetak {document.printedAt} · Dokumen rekam medis, bersifat rahasia.<br />
                    Dokumen ini memuat data pengkajian yang tercatat pada kunjungan ini.
                </footer>
            </article>
        </div>
    </>;
}

function PrintSection({ title, rows, emphasized = false, note = null }: { title: string; rows: Rows; emphasized?: boolean; note?: React.ReactNode }) {
    const entries = Object.entries(rows).filter(([, value]) => value?.trim());
    if (entries.length === 0 && !note) { return null; }

    return <section className="clinical-section mt-5">
        <table className="screening-print-table w-full table-fixed border-collapse text-xs">
            <colgroup><col className="screening-print-label w-[32%]" /><col /></colgroup>
            <thead><tr><th className="border border-neutral-500 bg-neutral-100 px-3 py-2 text-left font-bold uppercase tracking-wide text-neutral-900 print:bg-neutral-100 print:text-black" colSpan={2} scope="colgroup">{title}</th></tr></thead>
            {entries.length > 0 && <tbody>{entries.map(([label, value]) => <tr className={value && (value.length > 1600 || value.split('\n').length > 20) ? 'screening-print-long-row' : undefined} key={label}>
                <th className="border border-neutral-400 bg-neutral-50 px-3 py-2 text-left align-top font-semibold text-neutral-700 print:bg-white print:text-black" scope="row">{label}</th>
                <td className={`whitespace-pre-wrap break-words border border-neutral-400 px-3 py-2 text-left align-top text-neutral-900 print:text-black ${emphasized ? 'font-semibold tabular-nums' : ''}`}>{value}</td>
            </tr>)}</tbody>}
            {note && <tfoot><tr><td className="border border-neutral-400 bg-neutral-50 px-3 py-2 text-left text-neutral-700 print:bg-white print:text-black" colSpan={2}>{note}</td></tr></tfoot>}
        </table>
    </section>;
}

function SignatureBlock({ clinicName, date, heading, name, signature, note, subheading }: { clinicName?: string; date: string; heading: string; name: string; signature?: string | null; note: string; subheading?: string }) {
    return <div>
        {subheading && <p className="text-neutral-700 print:text-neutral-800">{subheading}</p>}
        {clinicName && <p className="text-neutral-700 print:text-neutral-800">{clinicName}, {date}</p>}
        <p className="mt-1 text-neutral-600 print:text-neutral-700">{heading}</p>
        <div className="flex h-20 items-center justify-center">{signature && <img alt={`Tanda tangan ${name}`} className="h-20 max-w-48 bg-white object-contain" src={signature} />}</div>
        <p className="font-semibold text-neutral-900 underline underline-offset-2 print:text-black">{name}</p>
        <p className="mt-1 text-xs text-neutral-500 print:text-neutral-600">{note}</p>
    </div>;
}
