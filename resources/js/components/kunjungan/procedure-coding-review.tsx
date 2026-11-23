import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';

interface Term { id: number; code: string; display: string; release: string; }
export function ProcedureCodingReview({ visitId, procedureId, name, revision, searchUrl, disabled }: {
    visitId: number; procedureId: number; name: string; revision: string; searchUrl: string; disabled: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Term[]>([]);
    const [selected, setSelected] = useState<Term | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const form = useForm({ revision, classification: 'mapped', terminology_id: '', reason: '', reviewed: false });
    useEffect(() => {
        const controller = new AbortController();
        if (!open || query.trim().length < 2 || selected || form.data.classification !== 'mapped') { setResults([]); setLoading(false); return () => controller.abort(); }
        const timer = window.setTimeout(async () => {
            setLoading(true); setFailed(false);
            try {
                const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query.trim().slice(0, 100))}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (!response.ok) throw new Error('Pencarian gagal');
                const data: Term[] = await response.json();
                if (!controller.signal.aborted) setResults(data.filter((item) => item.release));
            } catch { if (!controller.signal.aborted) setFailed(true); }
            finally { if (!controller.signal.aborted) setLoading(false); }
        }, 300);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [query, selected, searchUrl, open, form.data.classification]);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({ ...data, revision }));
        form.post(`/pelayanan/pemeriksaan/${visitId}/tindakan/${procedureId}/coding`, { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); setSelected(null); setQuery(''); } });
    }

    return <>
        <Button disabled={disabled} type="button" size="sm" variant="secondary" onClick={() => setOpen(true)}>Tinjau coding</Button>
        <Dialog open={open} onOpenChange={setOpen}><DialogContent className="max-h-[90dvh] overflow-y-auto"><DialogHeader><DialogTitle>Review coding tindakan</DialogTitle><DialogDescription>{name}. Tentukan pemetaan prosedur atau alasan tidak memerlukan coding prosedur medis.</DialogDescription></DialogHeader>
            <form className="space-y-4 text-left" onSubmit={submit}><fieldset className="space-y-4" disabled={form.processing}>
                <Field htmlFor={`coding-classification-${procedureId}`} label="Klasifikasi coding" error={form.errors.classification}><Select id={`coding-classification-${procedureId}`} value={form.data.classification} onChange={(event) => { form.setData('classification', event.target.value); form.setData('terminology_id', ''); setSelected(null); setQuery(''); }}><option value="mapped">Prosedur medis — ICD-9-CM</option><option value="not_applicable">Perawatan yang tidak memerlukan kode prosedur</option></Select></Field>
                {form.data.classification === 'mapped' && <><Field htmlFor={`coding-search-${procedureId}`} label="Cari ICD-9-CM" error={form.errors.terminology_id}><Input id={`coding-search-${procedureId}`} autoComplete="off" value={query} onChange={(event) => { setQuery(event.target.value); setSelected(null); form.setData('terminology_id', ''); }} /></Field>
                    {loading && <p role="status" className="text-sm text-neutral-500">Mencari prosedur…</p>}
                    {failed && <p role="alert" className="text-sm text-red-700">Pencarian gagal. Coba kembali setelah memeriksa koneksi.</p>}
                    {!!results.length && <div className="max-h-48 overflow-auto rounded-lg border border-neutral-200">{results.map((item) => <Button type="button" key={item.id} variant="ghost" className="h-auto w-full justify-start whitespace-normal rounded-none p-3 text-left" onClick={() => { setSelected(item); setResults([]); form.setData('terminology_id', String(item.id)); }}>{item.code} · {item.display} · {item.release}</Button>)}</div>}
                    {selected && <p className="rounded-lg bg-neutral-50 p-3 text-sm">{selected.code} · {selected.display} · {selected.release}</p>}
                </>}
                <Field htmlFor={`coding-reason-${procedureId}`} label="Dasar pemetaan / alasan tidak memerlukan kode" required error={form.errors.reason}><Textarea id={`coding-reason-${procedureId}`} minLength={20} maxLength={2000} required rows={3} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></Field>
                <label className="flex items-start gap-2 text-sm leading-6"><Checkbox checked={form.data.reviewed} onCheckedChange={(checked) => form.setData('reviewed', checked === true)} /><span>Saya telah meninjau tindakan sebenarnya dan kesesuaian klasifikasi coding ini.</span></label>
                {form.errors.reviewed && <p role="alert" className="text-sm text-red-700">{form.errors.reviewed}</p>}
                {form.errors.revision && <p role="alert" className="text-sm text-red-700">{form.errors.revision} <Button type="button" variant="secondary" onClick={() => window.location.reload()}>Muat ulang</Button></p>}
                <p className="text-xs text-neutral-500">Perubahan kode mengharuskan peninjauan ulang consent sebelum pelaksanaan.</p>
                <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setOpen(false)}>Batal</Button><Button type="submit" disabled={form.processing || !form.data.reviewed || form.data.reason.trim().length < 20 || (form.data.classification === 'mapped' && !selected)}>Simpan review coding</Button></div>
            </fieldset></form>
        </DialogContent></Dialog>
    </>;
}
