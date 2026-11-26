import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardList, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { FormActionFooter } from '@/components/ui/form-action-footer';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
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
    return <><Head title="Kualifikasi farmasi" /><div className="space-y-6">
        <PageHeaderWithActions
            backHref="/master/nakes"
            backLabel="Kembali ke tenaga kerja"
            eyebrow="Kredensial kefarmasian"
            subtitle={`${staff.name} · SIP ${staff.sip ?? 'belum dicatat'} · STR ${staff.str ?? 'belum dicatat'}`}
            title="Kualifikasi farmasi"
        />
        <Card>
            <CardHeader className="border-b border-neutral-100">
                <div className="flex items-start gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ShieldCheck aria-hidden="true" className="size-5" /></span>
                    <div className="min-w-0">
                        <CardTitle>Verifikasi profesi dan penugasan</CardTitle>
                        <CardDescription className="mt-1">Periksa dokumen profesi dan penugasan yang disetujui klinik. Jabatan farmasi pada data lama tidak otomatis menjadi bukti kualifikasi apoteker.</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-5 sm:p-6"><form className="space-y-4" onSubmit={submit}>
                {form.errors.current_id && <p role="alert" className="text-sm text-red-600">{form.errors.current_id}</p>}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Kualifikasi" htmlFor="pharmacy-qualification" error={form.errors.qualification} required><Select id="pharmacy-qualification" value={form.data.qualification} onChange={(event) => form.setData('qualification', event.target.value)}><option value="">Pilih hasil verifikasi</option><option value="apoteker">Apoteker</option><option value="ttk">Tenaga teknis kefarmasian</option><option value="revoked">Cabut verifikasi / penugasan</option></Select></Field>
                    {form.data.qualification !== 'revoked' && <Field label="Batas berlaku verifikasi penugasan" htmlFor="pharmacy-valid-until" error={form.errors.valid_until} required><DatePicker id="pharmacy-valid-until" value={form.data.valid_until} onChange={(event) => form.setData('valid_until', event.target.value)} /></Field>}
                </div>
                <Field label="Referensi bukti profesi, penugasan dan hasil pemeriksaan" htmlFor="pharmacy-reference" error={form.errors.reference} required><Textarea id="pharmacy-reference" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} required minLength={10} maxLength={2000} /></Field>
                <div className="space-y-1 rounded-xl border border-neutral-200 bg-neutral-50/60 p-3.5">
                    <label className="flex cursor-pointer items-start gap-2.5 text-sm leading-6 text-neutral-800" htmlFor="pharmacy-documents-verified">
                        <Checkbox id="pharmacy-documents-verified" className="mt-1 shrink-0" checked={form.data.documents_verified} onCheckedChange={(checked) => form.setData('documents_verified', checked === true)} />
                        <span>Dokumen dan kewenangan telah diperiksa</span>
                    </label>
                    {form.errors.documents_verified && <p role="alert" className="text-xs text-red-600">{form.errors.documents_verified}</p>}
                </div>
                <Field label="Kata sandi admin" htmlFor="pharmacy-admin-password" error={form.errors.signature_password} required><Input id="pharmacy-admin-password" type="password" autoComplete="current-password" value={form.data.signature_password} onChange={(event) => form.setData('signature_password', event.target.value)} /></Field>
                <FormActionFooter>
                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan hasil verifikasi'}</Button>
                </FormActionFooter>
            </form></CardContent>
        </Card>
        <Card>
            <CardHeader className="border-b border-neutral-100">
                <div className="flex items-start gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardList aria-hidden="true" className="size-5" /></span>
                    <div className="min-w-0">
                        <CardTitle>Riwayat verifikasi</CardTitle>
                        <CardDescription className="mt-1">Catatan kronologis verifikasi dan pencabutan kualifikasi kefarmasian.</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-3 p-5 sm:p-6">{history.data.length === 0 && <p className="text-sm text-neutral-500">Belum diverifikasi.</p>}{history.data.map((item) => <div key={item.id} className="space-y-1 border-t border-neutral-200 py-3 first:border-t-0 first:pt-0"><p className="text-sm font-medium">{item.qualification} · {item.at}</p><p className="text-sm">{item.data.reference}</p><p className="text-xs text-neutral-500">Berlaku sampai: {item.data.valid_until ?? 'Dicabut'}</p></div>)}<div className="flex gap-3">{history.links.map((link, index) => link.url ? <Link key={index} href={link.url} className="text-sm underline">{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</div></CardContent>
        </Card>
    </div></>;
}
