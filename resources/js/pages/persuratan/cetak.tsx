import { Head } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import { returnToPreviousScreen } from '@/lib/navigation';

interface PrintItem {
    name: string;
    type: string;
    quantity: number;
    unit: string | null;
    instructions: string | null;
    note: string | null;
}

interface DocumentData {
    kind: 'resep_luar' | 'surat' | 'rujukan_internal';
    clinic: { name: string; address: string | null; phone: string | null; email: string | null; logoUrl: string | null };
    patient: { name: string; medicalRecordNumber: string; gender: string | null; age: number | null; address: string | null };
    visitNumber: string;
    clinicName: string;
    doctor: { name: string; sip: string | null; str: string | null; signatureDataUrl: string | null };
    number: string | null;
    backUrl: string;
    backLabel: string;
    items?: PrintItem[];
    type?: string;
    date?: string | null;
    content?: string | null;
    fromClinic?: string | null;
    toClinic?: string | null;
    notes?: string | null;
}

interface Props {
    document: DocumentData;
}

const titles: Record<DocumentData['kind'], string> = {
    resep_luar: 'Resep Obat',
    surat: 'Surat Medis',
    rujukan_internal: 'Rujukan Internal',
};

export default function Cetak({ document }: Props) {
    const printDate = document.date ?? new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date());

    return (
        <>
            <Head title={`${titles[document.kind]} · ${document.patient.name}`} />
            <main className="mx-auto max-w-4xl space-y-4 p-4 sm:p-8 print:max-w-none print:p-0">
                <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Button onClick={() => returnToPreviousScreen(document.backUrl)} type="button" variant="secondary">
                        <ArrowLeft className="size-4" />
                        {document.backLabel}
                    </Button>
                    <div className="flex items-center gap-2">
                        <ThemeToggle />
                        <Button onClick={() => window.print()}>
                            <Printer className="size-4" />
                            Cetak dokumen
                        </Button>
                    </div>
                </div>

                <article className="min-h-[10in] rounded-xl border border-neutral-200 bg-surface p-6 text-neutral-950 shadow-sm sm:p-12 print:min-h-0 print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none">
                    <header className="flex items-start gap-4 border-b-2 border-neutral-900 pb-5 print:border-neutral-900">
                        {document.clinic.logoUrl && (
                            <img alt="Logo klinik" className="max-h-20 max-w-24 object-contain print:max-h-16" src={document.clinic.logoUrl} />
                        )}
                        <div className="min-w-0 flex-1 text-center">
                            <h1 className="text-xl font-bold uppercase tracking-wide text-neutral-950 print:text-black">
                                {document.clinic.name}
                            </h1>
                            {document.clinic.address && (
                                <p className="mt-1 text-sm text-neutral-600 print:text-neutral-700">
                                    {document.clinic.address}
                                </p>
                            )}
                            <p className="text-sm text-neutral-600 print:text-neutral-700">
                                {[document.clinic.phone, document.clinic.email].filter(Boolean).join(' · ')}
                            </p>
                        </div>
                    </header>

                    <div className="mt-7 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-bold uppercase tracking-tight text-neutral-950 print:text-black">
                                {titles[document.kind]}
                            </h2>
                            {document.number && (
                                <p className="mt-1 text-sm text-neutral-600 print:text-neutral-700">
                                    Nomor: <span className="font-mono font-medium text-neutral-900 print:text-black">{document.number}</span>
                                </p>
                            )}
                        </div>
                        <p className="text-sm text-neutral-600 print:text-neutral-700">{printDate}</p>
                    </div>

                    <section className="mt-7 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 border-b border-neutral-200 pb-6 print:border-neutral-300">
                        <p><span className="inline-block w-24 text-neutral-600 print:text-neutral-600">Nama</span>: <strong className="text-neutral-900 print:text-black">{document.patient.name}</strong></p>
                        <p><span className="inline-block w-24 text-neutral-600 print:text-neutral-600">No. RM</span>: <span className="font-mono text-neutral-900 print:text-black">{document.patient.medicalRecordNumber}</span></p>
                        <p><span className="inline-block w-24 text-neutral-600 print:text-neutral-600">Jenis kelamin</span>: {document.patient.gender === 'L' ? 'Laki-laki' : document.patient.gender === 'P' ? 'Perempuan' : 'Belum diisi'}</p>
                        <p><span className="inline-block w-24 text-neutral-600 print:text-neutral-600">Umur</span>: {document.patient.age === null ? 'Belum diisi' : `${document.patient.age} tahun`}</p>
                        <p><span className="inline-block w-24 text-neutral-600 print:text-neutral-600">Poliklinik</span>: {document.clinicName}</p>
                        <p><span className="inline-block w-24 text-neutral-600 print:text-neutral-600">No. kunjungan</span>: <span className="font-mono text-neutral-900 print:text-black">{document.visitNumber}</span></p>
                        {document.patient.address && (
                            <p className="sm:col-span-2">
                                <span className="inline-block w-24 text-neutral-600 print:text-neutral-600">Alamat</span>: {document.patient.address}
                            </p>
                        )}
                    </section>

                    {document.kind === 'resep_luar' && (
                        <section className="mt-8">
                            <h3 className="border-b border-neutral-900 pb-2 text-sm font-bold uppercase tracking-wider print:border-neutral-900">
                                R/
                            </h3>
                            <div className="divide-y divide-neutral-200 print:divide-neutral-200">
                                {document.items?.map((item, index) => (
                                    <div className="grid gap-1 py-4 sm:grid-cols-[2rem_1fr_auto] sm:gap-3" key={`${item.name}-${index}`}>
                                        <span className="font-semibold text-neutral-600 print:text-neutral-700">{index + 1}.</span>
                                        <div>
                                            <p className="font-semibold text-neutral-900 print:text-black">
                                                {item.name} <span className="font-normal text-neutral-600 print:text-neutral-600">({item.type === 'racikan' ? 'racikan' : 'obat jadi'})</span>
                                            </p>
                                            <p className="mt-1 text-sm text-neutral-700 print:text-neutral-700">
                                                S {item.instructions || 'Aturan pakai sesuai petunjuk dokter'}
                                            </p>
                                            {item.note && (
                                                <p className="mt-1 text-sm text-neutral-500 print:text-neutral-600">
                                                    Catatan: {item.note}
                                                </p>
                                            )}
                                        </div>
                                        <strong className="text-sm text-neutral-900 print:text-black">
                                            {item.quantity} {item.unit ?? ''}
                                        </strong>
                                    </div>
                                ))}
                            </div>
                            <p className="mt-8 text-xs italic text-neutral-600 print:text-neutral-700">
                                Obat tidak boleh diganti tanpa sepengetahuan dokter.
                            </p>
                        </section>
                    )}

                    {document.kind === 'rujukan_internal' && (
                        <section className="mt-8 space-y-4 text-sm">
                            <div className="rounded-lg border border-neutral-200 bg-neutral-50/70 p-4 print:border-neutral-300 print:bg-transparent">
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <p><strong className="text-neutral-700 print:text-neutral-800">Rujukan dari:</strong> {document.fromClinic ?? document.clinicName}</p>
                                    <p><strong className="text-neutral-700 print:text-neutral-800">Rujukan ke:</strong> {document.toClinic ?? 'Belum diisi'}</p>
                                </div>
                            </div>
                            {document.content && (
                                <p className="whitespace-pre-wrap leading-relaxed text-neutral-800 print:text-black">{document.content}</p>
                            )}
                            {document.notes && (
                                <div className="mt-3 rounded-md border-l-2 border-neutral-400 pl-3 py-1 text-xs text-neutral-600 print:border-neutral-500 print:text-neutral-700">
                                    <strong>Catatan klinis:</strong> {document.notes}
                                </div>
                            )}
                        </section>
                    )}

                    {document.kind === 'surat' && (
                        <section className="mt-8 space-y-4 text-sm">
                            {document.type && (
                                <p className="text-xs font-semibold uppercase tracking-wider text-neutral-600 print:text-neutral-700">
                                    Jenis surat: <span className="font-bold text-neutral-900 print:text-black">{document.type === 'sakit' ? 'Surat keterangan sakit' : document.type === 'sehat' ? 'Surat keterangan sehat' : document.type === 'rujukan' ? 'Surat rujukan eksternal' : 'Surat keterangan'}</span>
                                </p>
                            )}
                            {document.content && (
                                <div className="whitespace-pre-wrap leading-relaxed text-neutral-800 print:text-black">
                                    {document.content}
                                </div>
                            )}
                        </section>
                    )}

                    <footer className="mt-14 flex break-inside-avoid justify-end">
                        <div className="min-w-56 text-center text-sm">
                            <p className="text-neutral-700 print:text-neutral-800">{document.clinicName}, {printDate}</p>
                            <p className="mt-1 text-neutral-600 print:text-neutral-700">Dokter pemeriksa,</p>
                            {document.doctor.signatureDataUrl ? (
                                <img
                                    alt={`Tanda tangan ${document.doctor.name}`}
                                    className="mx-auto my-2 h-20 max-w-48 bg-white object-contain"
                                    src={document.doctor.signatureDataUrl}
                                />
                            ) : (
                                <div className="h-20" />
                            )}
                            <p className="font-semibold underline underline-offset-2 text-neutral-900 print:text-black">
                                {document.doctor.name}
                            </p>
                            {document.doctor.sip && (
                                <p className="mt-1 text-xs text-neutral-500 print:text-neutral-600">SIP: {document.doctor.sip}</p>
                            )}
                        </div>
                    </footer>
                </article>
            </main>
        </>
    );
}
