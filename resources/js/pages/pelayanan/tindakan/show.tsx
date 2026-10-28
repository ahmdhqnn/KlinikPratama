import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Field } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Empty } from '@/components/ui/empty';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface Treatment { id: number; name: string; code: string | null; quantity: number; toothFdi: string | null; instructions: string | null; status: string; result: string | null; performedBy: string | null; performedAt: string | null; completeUrl: string }
interface Props { visit: { id: number; number: string; patient: string; medicalRecordNumber: string; clinic: string; doctor: string | null; status: string; allergies: string | null; education: string | null; nonPharmacological: string | null; diagnoses: string[]; hasMedication: boolean; recordUrl: string; treatments: Treatment[] } }

export default function NursingProcedureDetail({ visit }: Props) {
    return <><Head title="Pelaksanaan Tindakan" /><div className="space-y-6"><div className="flex flex-wrap items-start justify-between gap-4"><div><h2 className="text-2xl font-semibold tracking-tight">{visit.patient}</h2><p className="mt-1 text-sm text-muted-foreground">{visit.medicalRecordNumber} · {visit.number} · {visit.clinic}</p></div><StatusBadge status={visit.status} /></div><Card><CardHeader><CardTitle>Instruksi dokter</CardTitle><CardDescription>{visit.doctor} · {visit.diagnoses.join(', ')}</CardDescription></CardHeader><CardContent className="space-y-4"><Alert><AlertTitle>Riwayat alergi</AlertTitle><AlertDescription>{visit.allergies || 'Belum diketahui; konfirmasi sebelum tindakan.'}</AlertDescription></Alert><div className="grid gap-4 md:grid-cols-2"><div><p className="text-sm font-medium">Terapi nonfarmakologis</p><p className="whitespace-pre-wrap text-sm text-muted-foreground">{visit.nonPharmacological || 'Tidak dicatat'}</p></div><div><p className="text-sm font-medium">Edukasi / anjuran</p><p className="whitespace-pre-wrap text-sm text-muted-foreground">{visit.education || 'Tidak dicatat'}</p></div></div><p className="text-sm text-muted-foreground">{visit.status === 'tindakan' ? (visit.hasMedication ? 'Setelah seluruh tindakan selesai, pasien diteruskan ke farmasi.' : 'Setelah seluruh tindakan selesai, kunjungan selesai dan pasien dapat pulang.') : visit.status === 'farmasi' ? 'Tindakan selesai. Pasien menunggu pelayanan farmasi.' : 'Kunjungan telah selesai.'}</p></CardContent></Card>{visit.treatments.length ? visit.treatments.map((item) => <TreatmentForm item={item} key={item.id} canComplete={visit.status === 'tindakan'} />) : <Empty title="Tidak ada tindakan perawat" />}<div className="flex flex-wrap justify-between gap-3"><Button asChild variant="secondary"><Link href="/pelayanan/tindakan-perawat">Kembali ke antrean</Link></Button><Button asChild variant="secondary"><Link href={visit.recordUrl}>Lihat rekam medis</Link></Button></div></div></>;
}

function TreatmentForm({ item, canComplete }: { item: Treatment; canComplete: boolean }) {
    const form = useForm({ execution_result: '' });
    const stockError = (form.errors as Record<string, string>).items;
    const completed = item.status === 'completed';
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        confirmAction(`Pastikan ${item.name} telah dilaksanakan dan hasilnya sesuai. Catatan yang disimpan tidak dapat ditimpa.`, () => form.post(item.completeUrl, { preserveScroll: true }), 'Simpan pelaksanaan', 'Konfirmasi pelaksanaan tindakan');
    }
    return <Card><CardHeader><div className="flex flex-wrap justify-between gap-3"><CardTitle>{item.name}</CardTitle><Badge variant={completed ? 'complete' : 'waiting'}>{completed ? 'Sudah dilaksanakan' : 'Menunggu pelaksanaan'}</Badge></div><CardDescription>{item.code || 'Tanpa kode'} · {item.quantity} kali{item.toothFdi ? ` · Gigi ${item.toothFdi}` : ''}</CardDescription></CardHeader><CardContent className="space-y-4"><p className="whitespace-pre-wrap text-sm">{item.instructions || 'Tidak ada instruksi tambahan.'}</p>{completed ? <div className="space-y-2 rounded-xl border p-4"><p className="whitespace-pre-wrap text-sm">{item.result}</p><p className="text-xs text-muted-foreground">Dicatat oleh {item.performedBy} · {item.performedAt}</p></div> : <form className="space-y-4" onSubmit={submit}>{stockError && <Alert variant="destructive"><AlertTitle>Pelaksanaan belum tersimpan</AlertTitle><AlertDescription>{stockError}</AlertDescription></Alert>}<Field htmlFor={`result-${item.id}`} label="Hasil pelaksanaan dan respons pasien" error={form.errors.execution_result} required><Textarea id={`result-${item.id}`} maxLength={5000} minLength={3} required disabled={!canComplete || form.processing} value={form.data.execution_result} onChange={(event) => form.setData('execution_result', event.target.value)} placeholder="Catat tindakan yang benar-benar dilakukan, hasil, dan respons pasien." /></Field><div className="flex justify-end"><Button type="submit" disabled={!canComplete || form.processing || form.data.execution_result.trim().length < 3}>Simpan pelaksanaan tindakan</Button></div></form>}</CardContent></Card>;
}
