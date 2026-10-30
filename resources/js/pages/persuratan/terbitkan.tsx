import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DatePicker } from '@/components/ui/date-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileText, Plus } from 'lucide-react';
import { FormEvent } from 'react';

interface Props {
    visit: { id: number; number: string; date: string; patient: string; medicalRecordNumber: string; clinic: string; doctor: string };
    templates: { id: number; nama: string; jenis: string }[];
    today: string;
}

const types = [
    ['sakit', 'Surat keterangan sakit'], ['sehat', 'Surat keterangan sehat'],
    ['rujukan', 'Surat rujukan eksternal'], ['lainnya', 'Surat lainnya'],
];

export default function IssueLetter({ visit, templates, today }: Props) {
    const form = useForm({ jenis: 'sakit', tanggal: today, surat_template_id: '', konten: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/persuratan/terbitkan/${visit.id}`, { preserveScroll: true });
    }

    return <>
        <Head title="Terbitkan dokumen" />
        <div className="mx-auto max-w-5xl space-y-6">
            <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div><p className="text-sm font-medium text-neutral-700">Persuratan dokter</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Terbitkan dokumen</h1><p className="mt-1 text-sm text-neutral-500">Pilih jenis surat dan lengkapi keterangan untuk kunjungan ini.</p></div>
                <Button asChild variant="secondary"><Link href="/persuratan"><ArrowLeft className="size-4" />Kembali ke persuratan</Link></Button>
            </header>

            <Card>
                <CardHeader className="border-b border-neutral-100">
                    <div className="flex items-start gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><FileText className="size-5" /></span>
                        <div><CardTitle>Data kunjungan</CardTitle><CardDescription className="mt-1">Pastikan dokumen diterbitkan untuk pasien dan kunjungan yang benar.</CardDescription></div>
                    </div>
                </CardHeader>
                <CardContent className="pt-5">
                    <dl className="grid overflow-hidden rounded-xl border border-neutral-200 bg-neutral-50/60 sm:grid-cols-2 lg:grid-cols-4">
                        <Info label="Pasien" value={visit.patient} detail={`No. RM ${visit.medicalRecordNumber}`} />
                        <Info label="No. kunjungan" value={visit.number} detail={visit.date} />
                        <Info label="Poliklinik" value={visit.clinic} detail="Tempat pelayanan" />
                        <Info label="Dokter" value={visit.doctor} detail="Dokter pemeriksa" />
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Isi dokumen</CardTitle><CardDescription>Nomor surat akan dibuat otomatis saat template dipilih. Isi template tetap mengikuti format persuratan yang berlaku.</CardDescription></CardHeader>
                <CardContent>
                    <form className="space-y-5" onSubmit={submit}>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <Field error={form.errors.jenis} htmlFor="letter-type" label="Jenis surat" required><Select id="letter-type" onChange={(event) => form.setData((data) => ({ ...data, jenis: event.target.value, surat_template_id: '' }))} value={form.data.jenis}>{types.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>
                            <Field error={form.errors.surat_template_id} htmlFor="letter-template" label="Template surat"><Select id="letter-template" onChange={(event) => form.setData('surat_template_id', event.target.value)} value={form.data.surat_template_id}><option value="">Tanpa template</option>{templates.filter((template) => template.jenis === form.data.jenis).map((template) => <option key={template.id} value={template.id}>{template.nama}</option>)}</Select></Field>
                            <Field error={form.errors.tanggal} htmlFor="letter-date" label="Tanggal surat" required><DatePicker id="letter-date" onChange={(event) => form.setData('tanggal', event.target.value)} value={form.data.tanggal} /></Field>
                        </div>
                        <Field error={form.errors.konten} htmlFor="letter-content" label="Isi keterangan"><Textarea id="letter-content" maxLength={10000} onChange={(event) => form.setData('konten', event.target.value)} placeholder="Kosongkan untuk memakai isi template. Anda dapat menambahkan keterangan khusus untuk pasien." rows={6} value={form.data.konten} /></Field>
                        <div className="flex flex-col-reverse gap-2 border-t border-neutral-100 pt-4 sm:flex-row sm:justify-end"><Button asChild variant="secondary"><Link href="/persuratan">Batal</Link></Button><Button disabled={form.processing} type="submit"><Plus className="size-4" />{form.processing ? 'Menerbitkan…' : 'Terbitkan surat'}</Button></div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </>;
}

function Info({ label, value, detail }: { label: string; value: string; detail: string }) {
    return <div className="min-w-0 border-b border-neutral-200 p-4 last:border-b-0 sm:border-r sm:last:border-r-0 lg:border-b-0"><dt className="text-xs font-medium text-neutral-500">{label}</dt><dd className="mt-1 break-words text-sm font-semibold text-neutral-900">{value}</dd><dd className="mt-1 break-words text-xs text-neutral-500">{detail}</dd></div>;
}
