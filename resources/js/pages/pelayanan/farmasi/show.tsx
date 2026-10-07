import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Printer, Save, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface PrescriptionItem {
    id: number;
    medicineId: number | null;
    name: string;
    type: string;
    requestedQuantity: number;
    dispensedQuantity: number;
    unit: string | null;
    instructions: string | null;
    external: boolean;
    reserved: boolean;
    stock: number;
    stockUnit: string | null;
}

interface Visit {
    id: number;
    number: string;
    status: string;
    prescriptionNumber: string | null;
    patient: { id: number; name: string; medicalRecordNumber: string; allergies: string | null };
    doctor: string | null;
    pharmacy: { status: string; notes: string | null; items: { prescriptionItemId: number | null; medicineId: number | null; dispensedQuantity: number; instructions: string | null; notes: string | null }[] } | null;
    prescriptionItems: PrescriptionItem[];
}

interface DispenseItem {
    resep_obat_id: number;
    obat_id: number | null;
    jumlah_diberikan: string;
    aturan_pakai: string;
    catatan: string;
}

interface FormData {
    items: DispenseItem[];
    catatan: string;
}

export default function PharmacyDispensing({ visit, today, appName }: { visit: Visit; today: string; appName: string }) {
    const [etiketItem, setEtiketItem] = useState<{ name: string; instructions: string } | null>(null);
    const form = useForm<FormData>({
        items: visit.prescriptionItems.map((item) => ({
            resep_obat_id: item.id,
            obat_id: item.medicineId,
            jumlah_diberikan: item.external ? '0' : String(item.dispensedQuantity),
            aturan_pakai: item.instructions ?? '',
            catatan: visit.pharmacy?.items.find((dispensed) => dispensed.prescriptionItemId === item.id)?.notes ?? '',
        })),
        catatan: visit.pharmacy?.notes ?? '',
    });

    function saveDispensing(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/pelayanan/farmasi/${visit.id}`);
    }

    function finishDispensing() {
        if (window.confirm('Selesaikan penyerahan obat? Stok obat klinik akan dipotong untuk item yang belum direservasi.')) {
            router.post(`/pelayanan/farmasi/${visit.id}/selesai`);
        }
    }

    function printLabel() {
        if (!etiketItem) return;
        const printWindow = window.open('', '_blank', 'width=440,height=620');
        if (!printWindow) {
            window.alert('Izinkan jendela cetak untuk menampilkan etiket obat.');
            return;
        }

        printWindow.document.write(`<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Etiket Obat</title><style>body{font-family:Arial,sans-serif;padding:24px}.label{border:2px solid #2563eb;border-radius:12px;padding:20px;text-align:center;max-width:320px;margin:0 auto}.brand{color:#1e40af;font-weight:700}.muted{color:#64748b;font-size:12px}.separator{border-block:1px solid #e2e8f0;margin:14px 0;padding:10px}.medicine{font-size:20px;font-weight:700;margin:18px 0}.sig{background:#eff6ff;border-radius:6px;padding:10px;font-weight:700}@media print{body{padding:0}}</style></head><body><main class="label"><p class="brand">${escapeHtml(appName)}</p><p class="muted">INSTALASI FARMASI</p><div class="separator"><strong>${escapeHtml(visit.patient.name)}</strong><p class="muted">${escapeHtml(today)}</p></div><p class="medicine">${escapeHtml(etiketItem.name)}</p><p class="sig">${escapeHtml(etiketItem.instructions || 'Ikuti petunjuk dokter')}</p><p class="muted"><em>Semoga lekas sembuh</em></p></main></body></html>`);
        printWindow.document.close();
        printWindow.focus();
        window.setTimeout(() => printWindow.print(), 250);
    }

    const alreadyComplete = visit.pharmacy?.status === 'selesai' || visit.status !== 'farmasi';

    return (
        <>
            <Head title={`Dispensing ${visit.patient.name}`} />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-orange-700">Dispensing farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{visit.patient.name}</h2><p className="mt-1 text-sm text-slate-500">{visit.patient.medicalRecordNumber} · Kunjungan {visit.number}</p></div><Button asChild variant="secondary"><Link href="/pelayanan/farmasi"><ArrowLeft className="size-4" />Kembali ke antrean</Link></Button></div>
                <Card><CardContent className="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center"><div><p className="text-xs font-medium uppercase tracking-wide text-orange-700">Nomor resep</p><p className="mt-1 font-mono text-lg font-semibold text-slate-950">{visit.prescriptionNumber ?? '—'}</p><p className="mt-1 text-sm text-slate-500">Dokter peresep: {visit.doctor ?? 'Belum ditentukan'}</p></div><div className="flex flex-wrap items-center gap-2"><StatusBadge status={alreadyComplete ? 'selesai' : 'farmasi'} />{visit.patient.allergies && <div className="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900"><TriangleAlert className="mt-0.5 size-4 shrink-0" /><span><strong>Alergi:</strong> {visit.patient.allergies}</span></div>}</div></CardContent></Card>
                <Card className="overflow-hidden"><CardHeader className="border-b border-slate-100"><CardTitle>Verifikasi dan penyiapan obat</CardTitle><CardDescription>Periksa jumlah dan aturan pakai. Stok baru dipotong saat dispensing selesai.</CardDescription></CardHeader>
                    <form onSubmit={saveDispensing}><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[980px]"><TableHeader><tr><TableHead>Obat</TableHead><TableHead>Permintaan resep</TableHead><TableHead>Stok klinik</TableHead><TableHead>Jumlah diserahkan</TableHead><TableHead>Aturan pakai</TableHead><TableHead>Etiket</TableHead></tr></TableHeader><TableBody>
                        {visit.prescriptionItems.length ? visit.prescriptionItems.map((item, index) => {
                            const current = form.data.items[index];
                            const quantity = Number(current?.jumlah_diberikan ?? 0);
                            const sufficient = item.reserved || quantity <= item.stock;
                            return <TableRow key={item.id}>
                                <TableCell><p className="font-medium text-slate-900">{item.name}</p><div className="mt-1 flex flex-wrap gap-1.5"><span className="rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700">{item.type === 'jadi' ? 'Obat jadi' : 'Racikan'}</span>{item.external && <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800">Resep luar</span>}</div></TableCell>
                                <TableCell className="whitespace-nowrap font-mono text-slate-700">{item.requestedQuantity} {item.unit}</TableCell>
                                <TableCell className="whitespace-nowrap">{item.external ? <span className="text-xs text-slate-500">Ditebus di luar</span> : item.reserved ? <span className="text-xs font-medium text-blue-700">Sudah direservasi</span> : <span className={item.stock >= quantity ? 'font-medium text-emerald-700' : 'font-medium text-red-700'}>{item.stock} {item.stockUnit}</span>}</TableCell>
                                <TableCell>{item.external ? <div><p className="font-medium text-slate-700">Tidak diserahkan di klinik</p><input name={`items[${index}][jumlah_diberikan]`} type="hidden" value="0" /></div> : <div className="space-y-1"><Input aria-label={`Jumlah ${item.name} diserahkan`} className="w-32 font-mono" disabled={alreadyComplete} min="0" onChange={(event) => form.setData('items', form.data.items.map((entry, itemIndex) => itemIndex === index ? { ...entry, jumlah_diberikan: event.target.value } : entry))} step="0.01" type="number" value={current?.jumlah_diberikan ?? '0'} />{!sufficient && <p className="text-xs font-medium text-red-700">Jumlah melebihi stok tersedia.</p>}{form.errors[`items.${index}.jumlah_diberikan` as keyof typeof form.errors] && <p className="text-xs text-red-700">{form.errors[`items.${index}.jumlah_diberikan` as keyof typeof form.errors]}</p>}</div>}</TableCell>
                                <TableCell><Input aria-label={`Aturan pakai ${item.name}`} disabled={alreadyComplete || item.external} onChange={(event) => form.setData('items', form.data.items.map((entry, itemIndex) => itemIndex === index ? { ...entry, aturan_pakai: event.target.value } : entry))} placeholder="3 x 1 setelah makan" value={current?.aturan_pakai ?? ''} /></TableCell>
                                <TableCell>{!item.external && <Button onClick={() => setEtiketItem({ name: item.name, instructions: current?.aturan_pakai ?? '' })} size="sm" type="button" variant="secondary"><Printer className="size-4" />Etiket</Button>}</TableCell>
                            </TableRow>;
                        }) : <TableRow><TableCell className="py-12 text-center text-slate-500" colSpan={6}>Tidak ada item resep untuk disiapkan.</TableCell></TableRow>}
                    </TableBody></Table></div></CardContent>
                    <div className="space-y-4 border-t border-slate-100 p-5 sm:p-6"><div><label className="mb-1.5 block text-sm font-medium text-slate-700" htmlFor="catatan-farmasi">Catatan tambahan farmasi</label><Input disabled={alreadyComplete} id="catatan-farmasi" onChange={(event) => form.setData('catatan', event.target.value)} placeholder="Informasi dispensing atau penyimpanan" value={form.data.catatan} />{form.errors.catatan && <p className="mt-1 text-xs text-red-700">{form.errors.catatan}</p>}</div>{form.errors.items && <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{form.errors.items}</p>}
                        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><p className="text-xs text-slate-500">Resep luar dicatat pada resep dan tidak dimasukkan dalam stok dispensing klinik.</p><div className="flex flex-wrap justify-end gap-2"><Button asChild variant="secondary"><Link href="/pelayanan/farmasi">Batalkan</Link></Button><Button disabled={form.processing || alreadyComplete} type="submit" variant="secondary"><Save className="size-4" />Simpan draf</Button><Button disabled={form.processing || alreadyComplete || !visit.pharmacy} onClick={finishDispensing} type="button"><Check className="size-4" />Selesaikan & lanjut ke kasir</Button></div></div>
                    </div></form>
                </Card>
            </div>
            {etiketItem && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" onClick={() => setEtiketItem(null)}><section aria-labelledby="etiket-heading" aria-modal="true" className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl" onClick={(event) => event.stopPropagation()} role="dialog"><h3 className="text-base font-semibold text-slate-950" id="etiket-heading">Pratinjau etiket</h3><div className="mt-4 rounded-xl border-2 border-blue-600 p-5 text-center"><p className="font-semibold text-blue-800">{appName}</p><p className="mt-1 text-[10px] text-slate-500">INSTALASI FARMASI</p><div className="my-3 border-y border-slate-200 py-2"><p className="font-medium text-slate-800">{visit.patient.name}</p><p className="text-xs text-slate-500">{today}</p></div><p className="text-lg font-bold text-slate-950">{etiketItem.name}</p><p className="mt-2 rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-900">{etiketItem.instructions || 'Ikuti petunjuk dokter'}</p><p className="mt-3 text-xs italic text-slate-400">Semoga lekas sembuh</p></div><div className="mt-5 flex justify-end gap-2"><Button onClick={() => setEtiketItem(null)} type="button" variant="secondary">Tutup</Button><Button onClick={printLabel} type="button"><Printer className="size-4" />Cetak</Button></div></section></div>}
        </>
    );
}

function escapeHtml(value: string) {
    return value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character] ?? character);
}
