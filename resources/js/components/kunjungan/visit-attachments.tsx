import { ActionButton } from '@/components/ui/action-button';
import { useForm } from '@inertiajs/react';
import { FileText, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Attachment } from '@/components/ui/attachment';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { confirmAction } from '@/components/ui/confirm-dialog';

export interface ClinicalAttachment {
    id: string;
    name: string;
    note: string | null;
    size: number;
    mime: string;
    downloadUrl: string;
    deleteUrl: string;
}

export function VisitAttachments({ attachments = [], uploadUrl, disabled = false, onPendingChange }: {
    attachments?: ClinicalAttachment[];
    uploadUrl?: string;
    disabled?: boolean;
    onPendingChange?: (pending: boolean) => void;
}) {
    const form = useForm<{ file: File | null; note: string }>({ file: null, note: '' });
    const removal = useForm({});
    const [inputKey, setInputKey] = useState(0);
    const busy = disabled || form.processing || removal.processing;
    const pending = Boolean(form.data.file) || form.processing || removal.processing;

    useEffect(() => { onPendingChange?.(pending); }, [pending, onPendingChange]);

    function reset() {
        form.reset();
        form.clearErrors();
        setInputKey((value) => value + 1);
    }

    function upload() {
        if (!uploadUrl || !form.data.file || busy) { return; }
        form.post(uploadUrl, { forceFormData: true, preserveScroll: true, onSuccess: reset });
    }

    return <div className="min-w-0 space-y-5">
        {attachments.length > 0 ? <ul className="divide-y divide-neutral-100 rounded-xl border border-neutral-200">
            {attachments.map((document) => <li key={document.id} className="flex min-w-0 flex-col gap-3 p-4 sm:flex-row sm:items-start">
                <div className="flex min-w-0 flex-1 gap-3">
                    <FileText aria-hidden="true" className="mt-1 size-5 shrink-0 text-neutral-500" />
                    <div className="min-w-0 flex-1">
                        <p className="break-words text-sm font-medium text-neutral-900 [overflow-wrap:anywhere]">{document.name}</p>
                        <p className="mt-1 text-xs text-neutral-500">{document.mime === 'application/pdf' ? 'PDF' : 'Gambar'} · {(document.size / 1024).toLocaleString('id-ID', { maximumFractionDigits: 0 })} KB</p>
                        {document.note && <p className="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-neutral-600 [overflow-wrap:anywhere]">{document.note}</p>}
                    </div>
                </div>
                <div className="flex shrink-0 flex-wrap gap-2">
                    <ActionButton asChild action="download" label={`Unduh ${document.name}`}><a href={document.downloadUrl} /></ActionButton>
                    {uploadUrl && <ActionButton disabled={busy} onClick={() => confirmAction(`Hapus lampiran “${document.name}” dari kunjungan ini?`, () => removal.delete(document.deleteUrl, { preserveScroll: true }), 'Hapus lampiran')} type="button" action="delete" label={`Hapus ${document.name}`} />}
                </div>
            </li>)}
        </ul> : <p className="text-sm text-neutral-500">Belum ada lampiran pada kunjungan ini.</p>}
        {Object.values(removal.errors).map((error, index) => <p className="text-sm text-red-600" key={index} role="alert">{String(error)}</p>)}
        {uploadUrl && <fieldset disabled={busy} className="min-w-0 space-y-4">
            <div className="grid min-w-0 items-start gap-4 md:grid-cols-2">
                <div className="min-w-0 space-y-2">
                    <Attachment id="patient-attachment" label="Dokumen pendukung (opsional)" accept=".pdf,.jpg,.jpeg,.png,.webp" inputKey={inputKey} fileName={form.data.file?.name} disabled={busy || attachments.length >= 10} error={form.errors.file} onFileChange={(file) => {
                        form.clearErrors('file');
                        form.setData('file', file);
                        if (file && file.size > 5 * 1024 * 1024) { form.setError('file', 'Ukuran setiap lampiran maksimal 5 MB.'); }
                    }} />
                    <p className="text-xs leading-5 text-neutral-500">PDF, JPG, PNG, atau WebP. Maksimal 5 MB per berkas dan 10 berkas per kunjungan ({attachments.length}/10).</p>
                </div>
                <Field htmlFor="attachment-note" label="Keterangan lampiran (opsional)" error={form.errors.note}>
                    <Textarea id="attachment-note" className="min-h-20" maxLength={1000} placeholder="Contoh: Hasil lab dari fasilitas lain, 20 Oktober 2026" value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} />
                </Field>
            </div>
            {form.progress && <div className="space-y-1"><progress aria-label="Progres unggah lampiran" className="h-2 w-full" max={100} value={form.progress.percentage} /><p className="text-xs text-neutral-500">Mengunggah {form.progress.percentage}%</p></div>}
            <div className="flex flex-wrap items-center gap-2">
                <Button disabled={busy || !form.data.file || form.data.file.size > 5 * 1024 * 1024 || attachments.length >= 10} onClick={upload} type="button" variant="secondary"><Upload className="size-4" />{form.processing ? 'Mengunggah…' : 'Unggah lampiran'}</Button>
                {(form.data.file || form.data.note) && <Button disabled={busy} onClick={reset} type="button" variant="ghost">Batalkan pilihan</Button>}
            </div>
            {form.data.file && <p className="text-xs leading-5 text-amber-700" role="status">Berkas belum tersimpan. Unggah atau batalkan pilihan sebelum menyimpan asesmen.</p>}
        </fieldset>}
    </div>;
}
