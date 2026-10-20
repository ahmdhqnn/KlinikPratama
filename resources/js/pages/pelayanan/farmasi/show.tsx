import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronDown, ChevronUp, CircleCheck, CircleHelp, Printer, Save, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { toast } from 'sonner';

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
    patient: { id: number; name: string; medicalRecordNumber: string; allergies: string | null; screeningAllergies: string | null; examinationAllergies: string | null };
    doctor: string | null;
    pharmacy: { status: string; notes: string | null; items: { prescriptionItemId: number | null; medicineId: number | null; dispensedQuantity: number; instructions: string | null; notes: string | null }[] } | null;
    prescriptionItems: PrescriptionItem[];
    allocations: { medicine: string; batch: string | null; expiry: string | null; quantity: number; actor: string | null; at: string | null }[];
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
    const [allocationsExpanded, setAllocationsExpanded] = useState(false);
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
        form.post(`/pelayanan/farmasi/${visit.id}`, { onSuccess: () => form.setDefaults() });
    }

    function finishDispensing() {
        confirmAction('Selesaikan penyerahan obat? Perubahan form akan disimpan bersama proses ini dan stok akan berkurang dari batch layak pakai sesuai jumlah yang diserahkan.', () => {
            form.post(`/pelayanan/farmasi/${visit.id}/selesai`, { preserveScroll: true });
        }, 'Lanjutkan');
    }

    function printLabel() {
        if (!etiketItem) return;
        const printWindow = window.open('', '_blank', 'width=440,height=620');
        if (!printWindow) {
            toast.error('Izinkan jendela cetak untuk menampilkan etiket obat.');
            return;
        }

        printWindow.document.write(`<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Etiket Obat</title><style>body{font-family:Arial,sans-serif;padding:24px;background:hsl(0 0% 98%);color:hsl(0 0% 3.9%)}.label{border:2px solid hsl(0 0% 25.1%);border-radius:12px;padding:20px;text-align:center;max-width:320px;margin:0 auto}.brand{color:hsl(0 0% 14.9%);font-weight:700}.muted{color:hsl(0 0% 45.1%);font-size:12px}.separator{border-block:1px solid hsl(0 0% 89.8%);margin:14px 0;padding:10px}.medicine{font-size:20px;font-weight:700;margin:18px 0}.sig{background:hsl(0 0% 98%);border-radius:6px;padding:10px;font-weight:700}@media print{body{padding:0}}</style></head><body><main class="label"><p class="brand">${escapeHtml(appName)}</p><p class="muted">INSTALASI FARMASI</p><div class="separator"><strong>${escapeHtml(visit.patient.name)}</strong><p class="muted">${escapeHtml(today)}</p></div><p class="medicine">${escapeHtml(etiketItem.name)}</p><p class="sig">${escapeHtml(etiketItem.instructions || 'Ikuti petunjuk dokter')}</p><p class="muted"><em>Semoga lekas sembuh</em></p></main></body></html>`);
        printWindow.document.close();
        printWindow.focus();
        window.setTimeout(() => printWindow.print(), 250);
    }

    const alreadyComplete = visit.pharmacy?.status === 'selesai' || visit.status !== 'farmasi';
    const dispensedMedicineCount = new Set(visit.allocations.map((allocation) => allocation.medicine)).size;
    const firstAllocation = visit.allocations[0];
    const allergyStatus = getAllergyStatus([
        visit.patient.examinationAllergies,
        visit.patient.screeningAllergies,
        visit.patient.allergies,
    ]);

    return (
        <>
            <Head title={`Dispensing ${visit.patient.name}`} />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-neutral-700">Dispensing farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{visit.patient.name}</h2><p className="mt-1 text-sm text-neutral-500">{visit.patient.medicalRecordNumber} · Kunjungan {visit.number}</p></div><Button asChild variant="secondary"><Link href="/pelayanan/farmasi"><ArrowLeft className="size-4" />Kembali ke antrean</Link></Button></div>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <CardTitle>Detail resep</CardTitle>
                        <CardDescription>Informasi resep dan status alergi pasien.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto_minmax(15rem,1.4fr)] lg:items-center">
                        <div><p className="text-xs font-semibold uppercase tracking-wide text-neutral-600">Nomor resep</p><p className="mt-1 break-all font-mono text-lg font-semibold text-neutral-950">{visit.prescriptionNumber ?? 'Belum diisi'}</p></div>
                        <div><p className="text-xs font-semibold uppercase tracking-wide text-neutral-600">Dokter peresep</p><p className="mt-1 text-sm font-medium text-neutral-900">{visit.doctor ?? 'Belum ditentukan'}</p></div>
                        <div><p className="mb-1 text-xs font-semibold uppercase tracking-wide text-neutral-600">Status</p><StatusBadge status={alreadyComplete ? 'selesai' : 'farmasi'} /></div>
                        <div className={allergyStatus.state === 'reported' ? 'flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-900' : 'flex items-start gap-2 rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm text-neutral-700'}>
                            {allergyStatus.state === 'reported' ? <TriangleAlert className="mt-0.5 size-4 shrink-0" /> : allergyStatus.state === 'none' ? <CircleCheck className="mt-0.5 size-4 shrink-0 text-neutral-500" /> : <CircleHelp className="mt-0.5 size-4 shrink-0 text-neutral-500" />}
                            <span><strong>Status alergi:</strong> {allergyStatus.label}</span>
                        </div>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><CardTitle>Verifikasi dan penyiapan obat</CardTitle><CardDescription>Periksa jumlah dan aturan pakai. Stok keluar saat obat diserahkan. Catat alasan dan tindak lanjut jika resep belum terpenuhi seluruhnya.</CardDescription></CardHeader>
                    <form onSubmit={saveDispensing}><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[980px]"><TableHeader><tr><TableHead>Obat</TableHead><TableHead>Permintaan resep</TableHead><TableHead>Stok layak pakai</TableHead><TableHead>Jumlah diserahkan</TableHead><TableHead>Aturan pakai</TableHead><TableHead>Etiket</TableHead></tr></TableHeader><TableBody>
                        {visit.prescriptionItems.length ? visit.prescriptionItems.map((item, index) => {
                            const current = form.data.items[index];
                            const quantity = Number(current?.jumlah_diberikan ?? 0);
                            const sufficient = quantity <= item.requestedQuantity && quantity <= item.stock;
                            return <TableRow key={item.id}>
                                <TableCell><p className="font-medium text-neutral-900">{item.name}</p><div className="mt-1 flex flex-wrap items-center justify-center gap-1.5"><span className="rounded-full bg-neutral-50 px-2 py-0.5 text-[11px] font-medium text-neutral-700">{item.type === 'jadi' ? 'Obat jadi' : 'Racikan'}</span>{item.external && <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800">Resep luar</span>}</div></TableCell>
                                <TableCell className="whitespace-nowrap font-mono text-neutral-700">{item.requestedQuantity} {item.unit}</TableCell>
                                <TableCell className="whitespace-nowrap">{item.external ? <span className="text-xs text-neutral-500">Ditebus di luar</span> : item.reserved ? <span className="text-xs font-medium text-neutral-700">Sudah direservasi</span> : <span className={item.stock >= quantity ? 'font-medium text-emerald-700' : 'font-medium text-red-700'}>{item.stock} {item.stockUnit}</span>}</TableCell>
                                <TableCell>{item.external ? <div><p className="font-medium text-neutral-700">Tidak diserahkan di klinik</p><input name={`items[${index}][jumlah_diberikan]`} type="hidden" value="0" /></div> : <div className="space-y-1"><Input aria-label={`Jumlah ${item.name} diserahkan`} className="w-32 font-mono" disabled={alreadyComplete} min="0" onChange={(event) => form.setData('items', form.data.items.map((entry, itemIndex) => itemIndex === index ? { ...entry, jumlah_diberikan: event.target.value } : entry))} step="1" type="number" value={current?.jumlah_diberikan ?? '0'} />{!sufficient && <p className="text-xs font-medium text-red-700">Jumlah melebihi resep atau stok tersedia.</p>}{form.errors[`items.${index}.jumlah_diberikan` as keyof typeof form.errors] && <p className="text-xs text-red-700">{form.errors[`items.${index}.jumlah_diberikan` as keyof typeof form.errors]}</p>}</div>}</TableCell>
                                <TableCell><Input aria-label={`Aturan pakai ${item.name}`} disabled={alreadyComplete || item.external} onChange={(event) => form.setData('items', form.data.items.map((entry, itemIndex) => itemIndex === index ? { ...entry, aturan_pakai: event.target.value } : entry))} placeholder="3 x 1 setelah makan" value={current?.aturan_pakai ?? ''} /></TableCell>
                                <TableCell>{!item.external && <Button onClick={() => setEtiketItem({ name: item.name, instructions: current?.aturan_pakai ?? '' })} size="sm" type="button" variant="secondary"><Printer className="size-4" />Etiket</Button>}</TableCell>
                            </TableRow>;
                        }) : <TableRow><TableCell colSpan={6}><Empty size="compact" title="Tidak ada item resep untuk disiapkan." /></TableCell></TableRow>}
                    </TableBody></Table></div></CardContent>
                    <div className="space-y-4 border-t border-neutral-100 p-5 sm:p-6"><Field error={form.errors.catatan} htmlFor="catatan-farmasi" label="Catatan tambahan farmasi"><Input disabled={alreadyComplete} id="catatan-farmasi" onChange={(event) => form.setData('catatan', event.target.value)} placeholder="Informasi dispensing atau penyimpanan" value={form.data.catatan} /></Field>{form.errors.items && <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{form.errors.items}</p>}
                        {form.isDirty && <p role="status" className="text-sm text-muted-foreground">Perubahan akan disimpan bersama proses penyerahan obat.</p>}<div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><p className="text-xs text-neutral-500">Resep luar dicatat pada resep dan tidak dimasukkan dalam stok dispensing klinik.</p><div className="flex flex-wrap justify-end gap-2"><Button asChild variant="secondary"><Link href="/pelayanan/farmasi">Batalkan</Link></Button><Button disabled={form.processing || alreadyComplete} type="submit" variant="secondary"><Save className="size-4" />Simpan draf</Button><Button disabled={form.processing || alreadyComplete} onClick={finishDispensing} type="button"><Check className="size-4" />Serahkan obat & selesaikan</Button></div></div>
                    </div></form>
                </Card>
                {visit.allocations.length > 0 && (
                    <Card className="overflow-hidden">
                        <CardHeader>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle>Obat telah diserahkan</CardTitle>
                                    <CardDescription className="mt-1">
                                        {dispensedMedicineCount} jenis obat · {visit.allocations.length} rincian batch
                                        {firstAllocation ? ` · ${firstAllocation.actor ?? 'Petugas historis'} · ${firstAllocation.at ?? 'Waktu historis'}` : ''}
                                    </CardDescription>
                                </div>
                                <Button
                                    aria-controls="dispensed-allocation-details"
                                    aria-expanded={allocationsExpanded}
                                    onClick={() => setAllocationsExpanded((expanded) => !expanded)}
                                    size="sm"
                                    type="button"
                                    variant="secondary"
                                >
                                    {allocationsExpanded
                                        ? <><ChevronUp className="size-4" />Tutup detail</>
                                        : <><ChevronDown className="size-4" />Detail penyerahan</>}
                                </Button>
                            </div>
                        </CardHeader>
                        {allocationsExpanded && (
                            <div className="overflow-x-auto border-t border-neutral-100" id="dispensed-allocation-details">
                                <Table className="min-w-[760px]">
                                    <TableHeader><TableRow><TableHead>Obat</TableHead><TableHead>Batch</TableHead><TableHead>Kedaluwarsa</TableHead><TableHead>Jumlah</TableHead><TableHead>Petugas / waktu</TableHead></TableRow></TableHeader>
                                    <TableBody>
                                        {visit.allocations.map((allocation, index) => (
                                            <TableRow key={`${allocation.medicine}-${allocation.batch ?? 'historis'}-${index}`}>
                                                <TableCell>{allocation.medicine}</TableCell>
                                                <TableCell className="font-mono">{allocation.batch ?? 'Historis'}</TableCell>
                                                <TableCell>{allocation.expiry ?? 'Historis'}</TableCell>
                                                <TableCell>{allocation.quantity}</TableCell>
                                                <TableCell>{allocation.actor ?? 'Historis'}<p className="text-xs text-muted-foreground">{allocation.at}</p></TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </Card>
                )}
            </div>
            <Dialog onOpenChange={(open) => { if (!open) setEtiketItem(null); }} open={Boolean(etiketItem)}><DialogContent className="max-w-sm"><DialogHeader><DialogTitle>Pratinjau etiket</DialogTitle><DialogDescription>Periksa informasi pasien dan aturan pakai sebelum mencetak.</DialogDescription></DialogHeader>{etiketItem && <><div className="rounded-xl border-2 border-neutral-600 p-5 text-center"><p className="font-semibold text-neutral-800">{appName}</p><p className="mt-1 text-[10px] text-neutral-500">INSTALASI FARMASI</p><div className="my-3 border-y border-neutral-200 py-2"><p className="font-medium text-neutral-800">{visit.patient.name}</p><p className="text-xs text-neutral-500">{today}</p></div><p className="text-lg font-bold text-neutral-950">{etiketItem.name}</p><p className="mt-2 rounded-lg bg-neutral-50 px-3 py-2 text-sm font-semibold text-neutral-900">{etiketItem.instructions || 'Ikuti petunjuk dokter'}</p><p className="mt-3 text-xs italic text-neutral-400">Semoga lekas sembuh</p></div><div className="flex justify-end gap-2"><Button onClick={() => setEtiketItem(null)} type="button" variant="secondary">Tutup</Button><Button onClick={printLabel} type="button"><Printer className="size-4" />Cetak</Button></div></>}</DialogContent></Dialog>
        </>
    );
}

function escapeHtml(value: string) {
    return value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character] ?? character);
}

function getAllergyStatus(values: Array<string | null>): { label: string; state: 'reported' | 'none' | 'unknown' } {
    const allergies = values.map((value) => value?.trim()).filter((value): value is string => Boolean(value));
    const reportedAllergies = allergies.filter((allergy) => !isNoAllergyReport(allergy) && allergy.toLocaleLowerCase('id-ID') !== 'belum dicatat');

    if (reportedAllergies.length > 0) {
        return { label: [...new Set(reportedAllergies)].join(' · '), state: 'reported' };
    }

    if (allergies.some(isNoAllergyReport)) {
        return { label: 'Tidak ada riwayat alergi', state: 'none' };
    }

    return { label: 'Belum dicatat', state: 'unknown' };
}

function isNoAllergyReport(value: string): boolean {
    const normalizedValue = value.toLocaleLowerCase('id-ID').replace(/[.!]+$/, '').trim();

    return ['tidak ada riwayat alergi', 'tidak ada alergi', 'nihil', 'tidak ada'].includes(normalizedValue);
}
