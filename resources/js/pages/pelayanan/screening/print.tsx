import { ClinicalDocumentHeader } from '@/components/clinical-document-header';
import { printWithFonts } from '@/lib/print-typography';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/ui/theme-toggle';

type Rows = Record<string, string>;

interface Props {
    document: {
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
    const identityRows = [
        ['Nama', patient.name, '', 'font-semibold text-neutral-900 print:text-black'],
        ['No. RM', patient.medicalRecordNumber, '', 'font-sans tabular-nums text-neutral-900 print:text-black'],
        ['Jenis kelamin', patient.gender === 'L' ? 'Laki-laki' : patient.gender === 'P' ? 'Perempuan' : null, '', ''],
        ['Umur', patient.age === null ? null : `${patient.age} tahun`, '', ''],
        ['Poliklinik', document.clinicName, '', ''],
        ['No. kunjungan', document.visitNumber, '', 'font-sans tabular-nums text-neutral-900 print:text-black'],
        ['Petugas', document.nurseName, '', ''],
        ...(document.recordedBy ? [['Diinput oleh', document.recordedBy, '', '']] : []),
        ['Dokter tujuan', document.doctorName, '', ''],
        ['Alamat', patient.address, 'sm:col-span-2', ''],
    ].filter((row): row is [string, string, string, string] => Boolean(row[1]));

    return <>
        <Head title={`Pengkajian awal · ${patient.name ?? document.visitNumber}`} />
        <main className="mx-auto max-w-4xl space-y-4 p-4 sm:p-8 print:max-w-none print:p-0">
            <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <Button asChild variant="secondary">
                    <Link href={document.backUrl}>
                        <ArrowLeft className="size-4" />
                        Kembali ke pengkajian awal
                    </Link>
                </Button>
                <div className="flex items-center gap-2">
                    <ThemeToggle />
                    <Button onClick={() => void printWithFonts()} type="button">
                        <Printer className="size-4" />
                        Cetak dokumen
                    </Button>
                </div>
            </div>

            <article className="clinical-sheet min-h-[10in] rounded-xl border border-neutral-200 bg-surface p-6 text-neutral-950 shadow-sm sm:p-12 print:min-h-0 print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none">
                <ClinicalDocumentHeader clinic={clinic} />

                <div className="mt-7 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-bold uppercase tracking-tight text-neutral-950 print:text-black">Pengkajian awal perawat &amp; tanda vital</h2>
                        <p className="mt-1 text-sm text-neutral-600 print:text-neutral-700">Nomor: <span className="font-sans tabular-nums font-medium text-neutral-900 print:text-black">{document.visitNumber}</span></p>
                    </div>
                    <p className="text-sm text-neutral-600 print:text-neutral-700">{document.printDate}</p>
                </div>

                {document.isDraft && <div className="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-center text-xs font-bold uppercase tracking-wide text-amber-900 print:border-neutral-900 print:bg-neutral-100 print:text-neutral-900">DRAF — BELUM DITERUSKAN KE DOKTER</div>}
                {document.visitStatus === 'batal' && <div className="mt-4 rounded-lg border border-rose-300 bg-rose-50 px-4 py-2 text-center text-xs font-bold uppercase tracking-wide text-rose-900 print:border-neutral-900 print:bg-neutral-100 print:text-neutral-900">KUNJUNGAN DIBATALKAN</div>}

                <section className="mt-7 grid gap-x-8 gap-y-2 border-b border-neutral-200 pb-6 text-sm print:border-neutral-300 sm:grid-cols-2">
                    {identityRows.map(([label, value, rowClass, valueClass]) => <p className={rowClass} key={label}><span className="inline-block w-24 text-neutral-600 print:text-neutral-600">{label}</span>: <span className={valueClass || undefined}>{value}</span></p>)}
                </section>

                <PrintSection title="Keluhan & riwayat kesehatan" rows={sections.history} />
                <PrintSection title="Tanda vital" rows={sections.vitals} emphasized />
                <PrintSection title="Berat dan tinggi badan" rows={sections.measurements} emphasized note={sections.measurementNote ? <><strong>Catatan pengkajian antropometri:</strong> {sections.measurementNote}</> : null} />
                <PrintSection title="Kondisi yang perlu perhatian" rows={sections.triage} />
                {document.clinicType === 'gigi' && Object.keys(sections.dental).length > 0 && <PrintSection title="Pengkajian khusus gigi dan mulut" rows={sections.dental} note="Asesmen awal ini tidak menyatakan kelayakan anestesi atau tindakan. Keputusan klinis sepenuhnya oleh dokter gigi." />}
                {(Object.keys(sections.additional).length > 0 || document.startedAt || document.submittedAt) && <PrintSection title="Pengkajian tambahan & waktu pengkajian" rows={{
                    ...sections.additional,
                    ...(document.startedAt || document.submittedAt ? { 'Waktu pengkajian': [document.startedAt ? `Mulai ${document.startedAt}` : null, document.submittedAt ? `Dikirim ${document.submittedAt}` : document.isDraft ? 'Belum dikirim' : null].filter(Boolean).join(' · ') } : {}),
                }} />}

                <div className="mt-14 grid break-inside-avoid grid-cols-2 gap-8 text-center text-sm print:mt-10">
                    <SignatureBlock clinicName={document.clinicName} date={document.printDate} heading="Petugas pengkajian awal," name={document.nurseName ?? '________________________'} signature={document.signatureDataUrl} note={document.submittedAt ? `Dikirim: ${document.submittedAt}` : 'Petugas pengkaji'} />
                    <SignatureBlock date={document.printDate} heading="Dokter tujuan," name={document.doctorName ?? '________________________'} note="Dokter tujuan; bukan bukti verifikasi atau tanda tangan" subheading="Serah terima dokter pemeriksa," />
                </div>

                <footer className="mt-10 border-t border-neutral-200 pt-4 text-center text-xs text-neutral-500 print:border-neutral-300 print:text-neutral-600">
                    {document.visitNumber} · Dicetak {document.printedAt} · Dokumen rekam medis, bersifat rahasia.<br />
                    Bagian yang tidak ditampilkan belum diisi atau belum dikaji; hal tersebut tidak berarti hasilnya normal atau pasien tidak memiliki keluhan.
                </footer>
            </article>
        </main>
    </>;
}

function PrintSection({ title, rows, emphasized = false, note = null }: { title: string; rows: Rows; emphasized?: boolean; note?: React.ReactNode }) {
    if (Object.keys(rows).length === 0 && !note) { return null; }

    return <section className="clinical-section mt-6">
        <h3 className="border-b border-neutral-900 pb-1.5 text-xs font-bold uppercase tracking-wider text-neutral-900 print:border-neutral-900 print:text-neutral-900">{title}</h3>
        {Object.keys(rows).length > 0 && <div className="mt-2.5 overflow-hidden rounded-lg border border-neutral-200 print:border-neutral-400">
            <table className="w-full text-xs"><tbody className="divide-y divide-neutral-200 print:divide-neutral-300">
                {Object.entries(rows).map(([label, value]) => <tr key={label}>
                    <th className="w-1/3 bg-neutral-50 px-3 py-2 text-left font-medium text-neutral-600 print:bg-neutral-100 print:text-neutral-700">{label}</th>
                    <td className={`whitespace-pre-wrap px-3 py-2 text-left text-neutral-900 print:text-black ${emphasized ? 'font-medium' : ''}`}>{value}</td>
                </tr>)}
            </tbody></table>
            {note && <div className="border-t border-neutral-200 bg-neutral-50/50 px-3 py-2 text-xs text-neutral-700 print:border-neutral-300 print:bg-transparent print:text-neutral-800">{note}</div>}
        </div>}
        {note && Object.keys(rows).length === 0 && <p className="mt-2 text-xs italic text-neutral-500 print:text-neutral-600">{note}</p>}
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
