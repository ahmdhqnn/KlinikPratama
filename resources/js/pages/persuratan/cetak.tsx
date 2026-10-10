import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';

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

interface Props { document: DocumentData; }

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
                    <Button asChild variant="secondary"><Link href={document.backUrl}><ArrowLeft className="size-4" />{document.backLabel}</Link></Button>
                    <Button onClick={() => window.print()}><Printer className="size-4" />Cetak dokumen</Button>
                </div>

                <article className="min-h-[10in] rounded-xl border border-neutral-200 bg-white p-6 text-neutral-950 shadow-sm sm:p-12 print:min-h-0 print:rounded-none print:border-0 print:p-0 print:shadow-none">
                    <header className="flex items-start gap-4 border-b-2 border-neutral-900 pb-5">
                        {document.clinic.logoUrl && <img alt="Logo klinik" className="max-h-20 max-w-24 object-contain" src={document.clinic.logoUrl} />}
                        <div className="min-w-0 flex-1 text-center">
                            <h1 className="text-xl font-bold uppercase tracking-wide">{document.clinic.name}</h1>
                            {document.clinic.address && <p className="mt-1 text-sm">{document.clinic.address}</p>}
                            <p className="text-sm">{[document.clinic.phone, document.clinic.email].filter(Boolean).join(' · ')}</p>
                        </div>
                    </header>

                    <div className="mt-7 flex flex-wrap items-start justify-between gap-3">
                        <div><h2 className="text-lg font-bold uppercase">{titles[document.kind]}</h2>{document.number && <p className="mt-1 text-sm">Nomor: {document.number}</p>}</div>
                        <p className="text-sm">{printDate}</p>
                    </div>

                    <section className="mt-7 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                        <p><span className="inline-block w-24 text-neutral-600">Nama</span>: <strong>{document.patient.name}</strong></p>
                        <p><span className="inline-block w-24 text-neutral-600">No. RM</span>: {document.patient.medicalRecordNumber}</p>
                        <p><span className="inline-block w-24 text-neutral-600">Jenis kelamin</span>: {document.patient.gender === 'L' ? 'Laki-laki' : document.patient.gender === 'P' ? 'Perempuan' : '—'}</p>
                        <p><span className="inline-block w-24 text-neutral-600">Umur</span>: {document.patient.age === null ? '—' : `${document.patient.age} tahun`}</p>
                        <p><span className="inline-block w-24 text-neutral-600">Poliklinik</span>: {document.clinicName}</p>
                        <p><span className="inline-block w-24 text-neutral-600">No. kunjungan</span>: {document.visitNumber}</p>
                        {document.patient.address && <p className="sm:col-span-2"><span className="inline-block w-24 text-neutral-600">Alamat</span>: {document.patient.address}</p>}
                    </section>

                    {document.kind === 'resep_luar' && <section className="mt-8">
                        <h3 className="border-b border-neutral-400 pb-2 text-sm font-bold uppercase">R/</h3>
                        <div className="divide-y divide-neutral-200">{document.items?.map((item, index) => <div className="grid gap-1 py-4 sm:grid-cols-[2rem_1fr_auto] sm:gap-3" key={`${item.name}-${index}`}><span className="font-semibold">{index + 1}.</span><div><p className="font-semibold">{item.name} <span className="font-normal">({item.type === 'racikan' ? 'racikan' : 'obat jadi'})</span></p><p className="mt-1 text-sm">S {item.instructions || 'Aturan pakai sesuai petunjuk dokter'}</p>{item.note && <p className="mt-1 text-sm">Catatan: {item.note}</p>}</div><strong className="text-sm">{item.quantity} {item.unit ?? ''}</strong></div>)}</div>
                        <p className="mt-10 text-xs text-neutral-700">Obat tidak boleh diganti tanpa sepengetahuan dokter.</p>
                    </section>}

                    {document.kind === 'rujukan_internal' && <section className="mt-8 space-y-4 text-sm">
                        <p><strong>Rujukan dari:</strong> {document.fromClinic ?? document.clinicName}</p>
                        <p><strong>Rujukan ke:</strong> {document.toClinic ?? '—'}</p>
                        {document.content && <p className="whitespace-pre-wrap leading-6">{document.content}</p>}
                        {document.notes && <p><strong>Catatan klinis:</strong> {document.notes}</p>}
                    </section>}

                    {document.kind === 'surat' && <section className="mt-8 space-y-4 text-sm">
                        {document.type && <p><strong>Jenis surat:</strong> {document.type === 'sakit' ? 'Surat keterangan sakit' : document.type === 'sehat' ? 'Surat keterangan sehat' : document.type === 'rujukan' ? 'Surat rujukan eksternal' : 'Surat keterangan'}</p>}
                        {document.content && <div className="whitespace-pre-wrap leading-7">{document.content}</div>}
                    </section>}

                    <footer className="mt-14 flex justify-end">
                        <div className="min-w-56 text-center text-sm">
                            <p>{document.clinicName}, {printDate}</p>
                            <p className="mt-1">Dokter pemeriksa,</p>
                            {document.doctor.signatureDataUrl ? <img alt={`Tanda tangan ${document.doctor.name}`} className="mx-auto my-2 h-20 max-w-48 object-contain" src={document.doctor.signatureDataUrl} /> : <div className="h-20" />}
                            <p className="font-semibold underline underline-offset-2">{document.doctor.name}</p>
                            {document.doctor.sip && <p className="mt-1 text-xs">SIP: {document.doctor.sip}</p>}
                        </div>
                    </footer>
                </article>
            </main>
        </>
    );
}
