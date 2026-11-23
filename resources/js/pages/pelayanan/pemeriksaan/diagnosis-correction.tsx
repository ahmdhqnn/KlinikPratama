import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Search } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

interface Diagnosis { key: number; code: string | null; name: string; type: string; tooth: string | null; status: string; }
interface Term { id?: number; kode: string; nama: string; release: string; }
interface Props {
    visit: { id: number; patient: string; number: string };
    diagnoses: Diagnosis[];
    history: Array<{ request_id: string; at: string; reason: string; old: { name: string }; new: { name: string; code: string } }>;
    baseHash: string; requestId: string; storeUrl: string; searchUrl: string; backUrl: string;
}

export default function DiagnosisCorrection({ visit, diagnoses, history, baseHash, requestId, storeUrl, searchUrl, backUrl }: Props) {
    const form = useForm({ base_hash: baseHash, request_id: requestId, diagnosis_key: diagnoses[0]?.key ?? -1, terminology_id: '', reason: '', review_clinical_impact: false, signature_password: '' });
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Term[]>([]);
    const [selected, setSelected] = useState<Term | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const current = diagnoses.find((item) => item.key === form.data.diagnosis_key);
    useEffect(() => {
        const controller = new AbortController();
        if (query.trim().length < 2 || selected) { setResults([]); setLoading(false); return () => controller.abort(); }
        const timer = window.setTimeout(async () => {
            setLoading(true); setFailed(false);
            try {
                const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query.trim().slice(0, 100))}&code_system=icd10_who`, { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (!response.ok) throw new Error('Pencarian gagal');
                const data: Term[] = await response.json();
                if (!controller.signal.aborted) setResults(data.filter((item) => item.id && item.release));
            } catch {
                if (!controller.signal.aborted) { setFailed(true); setResults([]); }
            } finally { if (!controller.signal.aborted) setLoading(false); }
        }, 300);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [query, selected, searchUrl]);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(storeUrl, { preserveScroll: true, onSuccess: () => window.location.assign(backUrl), onFinish: () => form.reset('signature_password') });
    }

    return <>
        <Head title="Koreksi coding diagnosis" />
        <div className="mx-auto max-w-4xl space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-xl font-semibold">Koreksi coding diagnosis</h1><p className="mt-1 text-sm text-neutral-500">{visit.patient} · {visit.number}</p></div><Button asChild variant="secondary"><Link href={backUrl}><ArrowLeft className="size-4" />Kembali ke pemeriksaan</Link></Button></div>
            <Alert><AlertTitle>Addendum coding</AlertTitle><AlertDescription>Tinjau kesesuaian diagnosis dengan pelayanan yang sudah diberikan. Koreksi ini menyimpan versi baru beserta alasan; resep, pelaksanaan tindakan, dan stok tetap mengikuti catatan layanan masing-masing.</AlertDescription></Alert>
            <Card><CardHeader><CardTitle>Pemetaan ICD-10 WHO</CardTitle><CardDescription>Pilih diagnosis dari catatan final, lalu kode dan rilis katalog yang sesuai.</CardDescription></CardHeader><CardContent>
                <form className="space-y-5" onSubmit={submit}><fieldset className="space-y-5" disabled={form.processing}>
                    <Field htmlFor="correction-diagnosis" label="Diagnosis yang dikoreksi" required error={form.errors.diagnosis_key}><Select id="correction-diagnosis" value={form.data.diagnosis_key} onChange={(event) => { form.setData('diagnosis_key', Number(event.target.value)); setSelected(null); setQuery(''); form.setData('terminology_id', ''); }}>
                        {diagnoses.map((item) => <option key={item.key} value={item.key}>{item.code ?? 'Manual'} · {item.name}{item.tooth ? ` · Gigi ${item.tooth}` : ''}</option>)}
                    </Select></Field>
                    {current && <p className="rounded-lg bg-neutral-50 p-3 text-sm">{current.type === 'utama' ? 'Diagnosis utama' : 'Diagnosis tambahan'} · {current.status === 'verified' ? 'Terverifikasi' : 'Perlu verifikasi'}{current.tooth ? ` · Gigi ${current.tooth}` : ''}</p>}
                    <Field htmlFor="correction-code-search" label="Cari ICD-10 WHO" required error={form.errors.terminology_id}><div className="relative"><Search className="absolute left-3 top-3 size-4 text-neutral-500" /><Input id="correction-code-search" autoComplete="off" className="pl-9" value={query} onChange={(event) => { setQuery(event.target.value); setSelected(null); form.setData('terminology_id', ''); }} placeholder="Kode atau uraian diagnosis" /></div></Field>
                    {loading && <p role="status" className="text-sm text-neutral-500">Mencari diagnosis…</p>}
                    {failed && <p role="alert" className="text-sm text-red-700">Pencarian gagal. Periksa koneksi lalu coba kembali.</p>}
                    {!!results.length && <div className="max-h-64 overflow-y-auto rounded-xl border border-neutral-200">{results.map((item) => <Button key={item.id} type="button" variant="ghost" className="h-auto w-full justify-start whitespace-normal rounded-none border-b border-neutral-100 px-4 py-3 text-left" onClick={() => { setSelected(item); setResults([]); form.setData('terminology_id', String(item.id)); }}><span className="font-semibold tabular-nums">{item.kode}</span><span>{item.nama} · {item.release}</span></Button>)}</div>}
                    {!loading && !failed && !selected && query.trim().length >= 2 && !results.length && <p className="text-sm text-neutral-600" role="status">Kode dengan rilis terverifikasi belum ditemukan pada katalog aktif.</p>}
                    {selected && <p className="rounded-lg bg-neutral-50 p-3 text-sm"><strong>{selected.kode}</strong> · {selected.nama} · {selected.release}</p>}
                    <Field htmlFor="correction-reason" label="Alasan koreksi dan hasil tinjauan klinis" required error={form.errors.reason}><Textarea id="correction-reason" required minLength={10} maxLength={2000} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} rows={4} /></Field>
                    <label className="flex items-start gap-2 text-sm leading-6"><Checkbox checked={form.data.review_clinical_impact} onCheckedChange={(checked) => form.setData('review_clinical_impact', checked === true)} /><span>Saya telah memeriksa kesesuaian diagnosis, dampaknya pada terapi dan rujukan, serta kebutuhan tindak lanjut atau komunikasi kepada petugas terkait.</span></label>
                    {form.errors.review_clinical_impact && <p role="alert" className="text-sm text-red-700">{form.errors.review_clinical_impact}</p>}
                    <Field htmlFor="correction-password" label="Kata sandi akun" required error={form.errors.signature_password}><Input id="correction-password" type="password" autoComplete="current-password" required value={form.data.signature_password} onChange={(event) => form.setData('signature_password', event.target.value)} /></Field>
                    {form.errors.base_hash && <Alert variant="warning"><AlertTitle>Catatan berubah</AlertTitle><AlertDescription>{form.errors.base_hash}<Button className="mt-3" type="button" variant="secondary" onClick={() => window.location.reload()}>Muat ulang catatan</Button></AlertDescription></Alert>}
                    <div className="flex justify-end"><Button type="submit" disabled={!selected || !form.data.review_clinical_impact || form.data.reason.trim().length < 10 || form.processing}>Simpan addendum coding</Button></div>
                </fieldset></form>
            </CardContent></Card>
            {!!history.length && <Card><CardHeader><CardTitle>Riwayat koreksi coding</CardTitle></CardHeader><CardContent className="space-y-4">{history.map((item) => <div key={item.request_id} className="space-y-1 border-b border-neutral-200 pb-4 text-sm"><p className="font-medium">{item.old.name} → {item.new.code} · {item.new.name}</p><p className="whitespace-pre-wrap text-neutral-600">{item.reason}</p><p className="text-xs text-neutral-500">{new Date(item.at).toLocaleString('id-ID')}</p></div>)}</CardContent></Card>}
        </div>
    </>;
}
