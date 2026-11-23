import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DatePicker } from '@/components/ui/date-picker';
import { FormEvent, useEffect } from 'react';

interface Props { staff: { id: number; name: string; sip: string | null; str: string | null }; currentId: number | null; history: { data: { id: number; qualification: string; at: string; data: { reference: string; valid_until: string | null } }[]; links: { url: string | null; label: string }[] } }
export default function PharmacyCredential({ staff, currentId, history }: Props) {
    const form = useForm({ current_id: currentId, qualification: '', reference: '', valid_until: '', documents_verified: false, signature_password: '' });
    useEffect(() => { form.setData('current_id', currentId); }, [currentId]);
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/master/nakes/${staff.id}/kualifikasi-farmasi`, { preserveScroll: true, onFinish: () => form.setData('signature_password', ''), onSuccess: () => form.reset('qualification', 'reference', 'valid_until', 'documents_verified') });
    }
    return <><Head title="Kualifikasi farmasi" /><div className="mx-auto max-w-3xl space-y-6">
        <header className="space-y-2"><h1 className="text-2xl font-semibold">Kualifikasi farmasi</h1><p className="text-sm text-neutral-500">{staff.name} · SIP {staff.sip ?? 'belum diisi'} · STR {staff.str ?? 'belum diisi'}</p><Button asChild variant="secondary"><Link href="/master/nakes">Kembali ke tenaga kerja</Link></Button></header>
        <Card><CardHeader><CardTitle>Verifikasi profesi dan penugasan</CardTitle><CardDescription>Periksa dokumen profesi dan penugasan yang disetujui klinik. Jabatan farmasi pada data lama tidak otomatis menjadi bukti kualifikasi apoteker.</CardDescription></CardHeader><CardContent><form className="space-y-4" onSubmit={submit}>
            {form.errors.current_id && <p role="alert" className="text-sm text-red-600">{form.errors.current_id}</p>}
            <Field label="Kualifikasi" htmlFor="pharmacy-qualification" error={form.errors.qualification} required><Select id="pharmacy-qualification" value={form.data.qualification} onChange={(event) => form.setData('qualification', event.target.value)}><option value="">Pilih hasil verifikasi</option><option value="apoteker">Apoteker</option><option value="ttk">Tenaga teknis kefarmasian</option><option value="revoked">Cabut verifikasi / penugasan</option></Select></Field>
            <Field label="Referensi bukti profesi, penugasan dan hasil pemeriksaan" htmlFor="pharmacy-reference" error={form.errors.reference} required><Textarea id="pharmacy-reference" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} required minLength={10} maxLength={2000} /></Field>
            {form.data.qualification !== 'revoked' && <Field label="Batas berlaku verifikasi penugasan" htmlFor="pharmacy-valid-until" error={form.errors.valid_until} required><DatePicker id="pharmacy-valid-until" value={form.data.valid_until} onChange={(event) => form.setData('valid_until', event.target.value)} /></Field>}
            <Field label="Dokumen dan kewenangan telah diperiksa" htmlFor="pharmacy-documents-verified" error={form.errors.documents_verified}><input id="pharmacy-documents-verified" type="checkbox" checked={form.data.documents_verified} onChange={(event) => form.setData('documents_verified', event.target.checked)} /></Field>
            <Field label="Kata sandi admin" htmlFor="pharmacy-admin-password" error={form.errors.signature_password} required><Input id="pharmacy-admin-password" type="password" autoComplete="current-password" value={form.data.signature_password} onChange={(event) => form.setData('signature_password', event.target.value)} /></Field>
            <Button type="submit" disabled={form.processing}>Simpan hasil verifikasi</Button>
        </form></CardContent></Card>
        <Card><CardHeader><CardTitle>Riwayat verifikasi</CardTitle></CardHeader><CardContent className="space-y-3">{history.data.length === 0 && <p className="text-sm text-neutral-500">Belum diverifikasi.</p>}{history.data.map((item) => <div key={item.id} className="space-y-1 border-t border-neutral-200 py-3 dark:border-neutral-800"><p className="text-sm font-medium">{item.qualification} · {item.at}</p><p className="text-sm">{item.data.reference}</p><p className="text-xs text-neutral-500">Berlaku sampai: {item.data.valid_until ?? 'Dicabut'}</p></div>)}<div className="flex gap-3">{history.links.map((link, index) => link.url ? <Link key={index} href={link.url} className="text-sm underline">{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</div></CardContent></Card>
    </div></>;
}
