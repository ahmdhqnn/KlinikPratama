import { printFontCss, printWithFonts } from '@/lib/print-typography';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { AssistanceReasonField } from '@/components/kunjungan/assistance-reason-field';
import { PatientContextBar } from '@/components/kunjungan/patient-context-bar';
import { ActionButton } from '@/components/ui/action-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableScroll } from '@/components/ui/table';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import { getAllergyStatus } from '@/lib/allergy-status';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, ChevronDown, ChevronUp, CircleCheck, CircleHelp, Pill, Printer, Save, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent, type MouseEvent } from 'react';
import { toast } from 'sonner';

interface PrescriptionItem {
    id: number;
    medicineId: number | null;
    name: string;
    type: string;
    route?: string | null;
    labelColor?: 'putih' | 'biru';
    expiryDate?: string | null;
    isHighAlert?: boolean;
    isLasa?: boolean;
    nearExpiryDate?: string | null;
    allergyOverrideReason?: string | null;
    allergyMatchedTerm?: string | null;
    requestedQuantity: number;
    dispensedQuantity: number;
    unit: string | null;
    instructions: string | null;
    external: boolean;
    externalByPharmacy: boolean;
    externalActionUrl: string;
    externalPrintUrl: string;
    reserved: boolean;
    stock: number;
    stockUnit: string | null;
}

interface Visit {
    id: number;
    number: string;
    status: string;
    examinationSigned: boolean;
    prescriptionNumber: string | null;
    patient: { maternal?: { hasRisk?: boolean; summaryText?: string | null } | null; weight?: number | null; weightRecordedAt?: string | null; weightSource?: string | null; id: number; name: string; medicalRecordNumber: string; birthDate?: string | null; gender?: string | null; age?: number | null; allergies: string | null; screeningAllergies: string | null; examinationAllergies: string | null };
    doctor: string | null;
    doctorSip?: string | null;
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
    pharmacy_review?: string;
    recipient_name: string;
    recipient_relationship: string;
    recipient_verified: boolean;
    counseling_completed: boolean;
    counseling_notes: string;
    assistance_reason: string;
    items: DispenseItem[];
    catatan: string;
}

interface ClinicProfile {
    name?: string;
    address?: string | null;
    phone?: string | null;
}

interface PharmacistInfo {
    name?: string | null;
    sipa?: string | null;
}

export default function PharmacyDispensing({
    adminAssistance = false,
    visit,
    today,
    appName,
    clinicProfile,
    pharmacist,
}: {
    adminAssistance?: boolean;
    visit: Visit;
    today: string;
    appName: string;
    clinicProfile?: ClinicProfile;
    pharmacist?: PharmacistInfo;
}) {
    const [etiketItem, setEtiketItem] = useState<{
        name: string;
        instructions: string;
        quantity: number;
        unit: string | null;
        color: 'putih' | 'biru';
        expiryDate?: string | null;
    } | null>(null);
    const [allocationsExpanded, setAllocationsExpanded] = useState(false);
    const form = useForm<FormData>({
        recipient_name: '', recipient_relationship: '', recipient_verified: false, counseling_completed: false, counseling_notes: '',
        assistance_reason: '',
        items: visit.prescriptionItems.map((item) => ({
            resep_obat_id: item.id,
            obat_id: item.medicineId,
            jumlah_diberikan: item.external ? '0' : String(item.dispensedQuantity),
            aturan_pakai: item.instructions ?? '',
            catatan: visit.pharmacy?.items.find((dispensed) => dispensed.prescriptionItemId === item.id)?.notes ?? '',
        })),
        catatan: visit.pharmacy?.notes ?? '',
    });

    const alreadyComplete = visit.pharmacy?.status === 'selesai' || visit.status !== 'farmasi';
    useUnsavedChangesGuard(!alreadyComplete && form.isDirty && !form.processing);

    function saveDispensing(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (alreadyComplete) return;
        form.post(`/pelayanan/farmasi/${visit.id}`, { preserveScroll: true, onSuccess: () => form.setDefaults() });
    }

    function finishDispensing(event: MouseEvent<HTMLButtonElement>) {
        if (!event.currentTarget.form?.reportValidity()) return;
        confirmAction(allExternal ? 'Selesaikan layanan resep luar? Tidak ada obat yang diserahkan dari stok klinik. Pastikan dokumen resep luar sudah disiapkan untuk pasien.' : 'Selesaikan penyerahan obat? Perubahan formulir akan disimpan bersama proses ini dan stok akan berkurang dari batch layak pakai sesuai jumlah yang diserahkan.', () => {
            form.post(`/pelayanan/farmasi/${visit.id}/selesai`, { preserveScroll: true });
        }, 'Lanjutkan');
    }

    function markExternal(item: PrescriptionItem) {
        confirmAction(`Stok ${item.name} kosong. Catat obat ini untuk ditebus di luar klinik dan siapkan dokumen resep luar?${form.isDirty ? ' Simpan perubahan lain pada formulir terlebih dahulu; perubahan yang belum disimpan akan hilang.' : ''}`, () => {
            router.post(item.externalActionUrl, { assistance_reason: form.data.assistance_reason }, { preserveScroll: true, preserveState: false });
        }, 'Catat resep luar');
    }

    const facilityName = clinicProfile?.name || appName;
    const facilityAddress = clinicProfile?.address || 'Gedung Sapta Pesona, Jl. Medan Merdeka Barat No. 17, Jakarta Pusat';
    const facilityPhone = clinicProfile?.phone || '(021) 3838142';
    const sipaLine = pharmacist?.name
        ? `Apoteker: ${pharmacist.name}${pharmacist.sipa ? ` · SIPA: ${pharmacist.sipa}` : ''}`
        : 'Instalasi Farmasi Klinik Pratama';

    function printLabel() {
        if (!etiketItem) return;
        const printWindow = window.open('', '_blank', 'width=460,height=660');
        if (!printWindow) {
            toast.error('Izinkan jendela cetak untuk menampilkan etiket obat.');
            return;
        }

        const isBlue = etiketItem.color === 'biru';
        const bgColor = isBlue ? '#dbeafe' : '#ffffff';
        const borderColor = isBlue ? '#1d4ed8' : '#262626';
        const bannerHtml = isBlue
            ? '<div class="usage-banner blue">OBAT LUAR — TIDAK UNTUK DITELAN</div>'
            : '<div class="usage-banner white">OBAT DALAM</div>';
        const patientIdentity = [
            `No. RM: ${visit.patient.medicalRecordNumber}`,
            visit.patient.birthDate ? `Tgl Lahir: ${visit.patient.birthDate}` : null,
        ].filter(Boolean).join(' · ');
        const rxMeta = [
            visit.prescriptionNumber ? `No. Resep: ${visit.prescriptionNumber}` : `Kunjungan: ${visit.number}`,
            `Tgl: ${today}`,
        ].join(' · ');

        printWindow.document.write(`<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Etiket Obat - ${escapeHtml(visit.patient.name)}</title><style>${printFontCss}body{font-family:'Plus Jakarta Sans Variable',sans-serif;line-height:1.4;padding:18px;background:#f5f5f5;color:#111}.label{background:${bgColor};border:2px solid ${borderColor};border-radius:10px;padding:14px 16px;max-width:340px;margin:0 auto;print-color-adjust:exact;-webkit-print-color-adjust:exact}.head{text-align:center;border-bottom:1.5px solid ${borderColor};padding-bottom:8px}.brand{font-size:12px;font-weight:800;text-transform:uppercase}.sub{font-size:9.5px;color:#333;margin-top:2px}.meta{display:flex;justify-content:space-between;font-size:9.5px;margin-top:8px;color:#222}.patient{margin:8px 0;padding:6px 8px;border:1px solid ${borderColor};border-radius:6px;background:rgba(255,255,255,0.7)}.patient strong{display:block;font-size:13px}.patient span{font-size:10px;color:#333}.medicine{text-align:center;font-size:15px;font-weight:800;margin:10px 0 4px}.qty{text-align:center;font-size:11px;font-weight:600;color:#333;margin-bottom:8px}.sig{background:rgba(255,255,255,0.9);border:1px solid ${borderColor};border-radius:6px;padding:8px;text-align:center;font-size:13px;font-weight:700}.usage-banner{margin-top:8px;padding:4px 8px;border-radius:4px;text-align:center;font-size:10px;font-weight:800;letter-spacing:0.04em}.usage-banner.blue{background:#1e40af;color:#fff}.usage-banner.white{border:1px solid #525252;color:#171717;background:#fafafa}.foot{margin-top:8px;display:flex;justify-content:space-between;font-size:9.5px;color:#333}@media print{body{padding:0;background:#fff}@page{size:80mm auto;margin:3mm}}</style></head><body><main class="label"><div class="head"><div class="brand">${escapeHtml(facilityName)}</div><div class="sub">${escapeHtml(facilityAddress)} · Telp. ${escapeHtml(facilityPhone)}</div><div class="sub"><strong>${escapeHtml(sipaLine)}</strong></div></div><div class="meta"><span>${escapeHtml(rxMeta)}</span></div><div class="patient"><strong>${escapeHtml(visit.patient.name)}</strong><span>${escapeHtml(patientIdentity)}</span></div><div class="medicine">${escapeHtml(etiketItem.name)}</div><div class="qty">Jumlah: ${escapeHtml(`${etiketItem.quantity} ${etiketItem.unit ?? ''}`.trim())}</div><div class="sig">${escapeHtml(etiketItem.instructions || 'Ikuti petunjuk dokter')}</div>${bannerHtml}<div class="foot"><span>${etiketItem.expiryDate ? `ED: ${escapeHtml(etiketItem.expiryDate)}` : 'Simpan di tempat sejuk & kering'}</span><span>Semoga lekas sembuh</span></div></main></body></html>`);
        printWindow.document.close();
        void printWithFonts(printWindow);
    }

    const allExternal = visit.prescriptionItems.length > 0 && visit.prescriptionItems.every((item) => item.external);
    const pharmacyStatus = visit.status === 'batal' ? null : visit.pharmacy?.status ?? (visit.status === 'farmasi' ? 'menunggu' : null);
    const dispensedMedicineCount = new Set(visit.allocations.map((allocation) => allocation.medicine)).size;
    const firstAllocation = visit.allocations[0];
    const allergyStatus = getAllergyStatus([
        visit.patient.examinationAllergies,
        visit.patient.screeningAllergies,
        visit.patient.allergies,
    ]);

    return (
        <>
            <Head title={`Penyerahan obat ${visit.patient.name}`} />
            <div className="space-y-6">
                <PageHeaderWithActions
                    actions={alreadyComplete ? (
                        <Button asChild size="sm" variant="secondary">
                            <Link href={`/pelayanan/farmasi/${visit.id}/review`}>Riwayat telaah dan penyerahan</Link>
                        </Button>
                    ) : undefined}
                    backHref="/pelayanan/farmasi"
                    backLabel="Kembali ke penyerahan obat"
                    eyebrow="Penyerahan obat"
                    subtitle={`${visit.patient.medicalRecordNumber} · Kunjungan ${visit.number}`}
                    title={visit.patient.name}
                />
                <PatientContextBar
                    age={visit.patient.age}
                    allergyText={allergyStatus.state === 'reported' ? allergyStatus.label : null}
                    gender={visit.patient.gender}
                    maternalRisk={Boolean(visit.patient.maternal?.hasRisk)}
                    maternalSummary={visit.patient.maternal?.summaryText}
                    medicalRecordNumber={visit.patient.medicalRecordNumber}
                    patientName={visit.patient.name}
                    unsavedCount={!alreadyComplete && form.isDirty ? 1 : 0}
                    visitNumber={visit.number}
                    weight={visit.patient.weight}
                />
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <CardTitle>Detail resep</CardTitle>
                        <CardDescription>Informasi resep dan status alergi pasien.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto_minmax(15rem,1.4fr)] lg:items-center">
                        <div><p className="text-xs font-semibold uppercase tracking-wide text-neutral-600">Nomor resep</p><p className="mt-1 break-all font-sans tabular-nums text-lg font-semibold text-neutral-950">{visit.prescriptionNumber ?? 'Belum diisi'}</p></div>
                        <div><p className="text-xs font-semibold uppercase tracking-wide text-neutral-600">Dokter peresep</p><p className="mt-1 text-sm font-medium text-neutral-900">{visit.doctor ?? 'Belum ditentukan'}</p><p className="mt-1 text-xs text-neutral-500">SIP: {visit.doctorSip || 'Belum terdokumentasi'}</p></div>
                        <div><p className="mb-1 text-xs font-semibold uppercase tracking-wide text-neutral-600">Status</p><StatusBadge context={pharmacyStatus ? 'pharmacy' : 'visit'} status={pharmacyStatus ?? visit.status} /></div>
                        <div className={allergyStatus.state === 'reported' ? 'flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-900' : 'flex items-start gap-2 rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm text-neutral-700'}>
                            {allergyStatus.state === 'reported' ? <TriangleAlert className="mt-0.5 size-4 shrink-0" /> : allergyStatus.state === 'none' ? <CircleCheck className="mt-0.5 size-4 shrink-0 text-neutral-500" /> : <CircleHelp className="mt-0.5 size-4 shrink-0 text-neutral-500" />}
                            <span><strong>Status alergi:</strong> {allergyStatus.label}</span>
                        </div>
                    </CardContent>
                    <CardContent className="space-y-2 border-t border-neutral-100 pt-4 sm:pt-5"><p className="text-sm"><strong>Berat badan:</strong> {visit.patient.weight == null ? 'Belum diukur pada kunjungan ini' : `${visit.patient.weight} kg`}</p>{visit.patient.weight != null && <p className="mt-1 text-xs text-neutral-500">{visit.patient.weightSource ?? 'Pengkajian kunjungan ini'} · {visit.patient.weightRecordedAt ?? 'Waktu pengkajian belum tercatat'}</p>}{visit.patient.maternal?.summaryText && <p className={visit.patient.maternal.hasRisk ? 'rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-950' : 'text-xs text-neutral-600'}><strong>Kondisi kehamilan / laktasi:</strong> {visit.patient.maternal.summaryText}</p>}</CardContent>
                </Card>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Pill className="size-5" /></span><div><CardTitle>{alreadyComplete ? 'Rincian penyerahan resep' : 'Verifikasi dan penyiapan obat'}</CardTitle><CardDescription className="mt-1">{alreadyComplete ? 'Jumlah dan aturan pakai yang tercatat pada kunjungan ini.' : 'Periksa jumlah dan aturan pakai. Stok keluar saat obat diserahkan. Catat alasan dan tindak lanjut jika resep belum terpenuhi seluruhnya.'}</CardDescription></div></div></CardHeader>
                    {adminAssistance && !alreadyComplete && <CardContent className="space-y-4 pt-5 sm:pt-6"><p className="text-sm text-neutral-600">Bantuan farmasi admin. Akun Anda dicatat sebagai pelaku penyerahan; alasan bantuan dipisahkan dari catatan tindak lanjut resep.</p><AssistanceReasonField error={form.errors.assistance_reason} id="pharmacy-assistance" value={form.data.assistance_reason} onChange={(value) => form.setData('assistance_reason', value)} disabled={form.processing} /></CardContent>}
                    <form onSubmit={saveDispensing}><CardContent className="p-0"><TableScroll className="overflow-x-auto"><Table className={alreadyComplete ? 'min-w-[640px]' : 'min-w-[980px]'}><TableHeader><TableRow><TableHead className="text-center">Obat</TableHead><TableHead className="text-center">Permintaan resep</TableHead>{!alreadyComplete && <TableHead className="text-center">Stok layak pakai</TableHead>}<TableHead className="text-center">Jumlah diserahkan</TableHead><TableHead className="text-center">Aturan pakai</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>
                        {visit.prescriptionItems.length ? visit.prescriptionItems.map((item, index) => {
                            const current = form.data.items[index];
                            const quantity = alreadyComplete ? item.dispensedQuantity : Number(current?.jumlah_diberikan ?? 0);
                            const instructions = alreadyComplete ? item.instructions : current?.aturan_pakai;
                            const quantityError = form.errors[`items.${index}.jumlah_diberikan` as keyof typeof form.errors];
                            const quantityErrorId = `dispensed-quantity-${item.id}-error`;
                            const sufficient = quantity <= item.requestedQuantity && quantity <= item.stock;
                            return <TableRow key={item.id}>
                                <TableCell className="text-center"><p className="font-medium text-neutral-900">{item.name}</p><div className="mt-1 flex flex-wrap items-center justify-center gap-1.5"><span className="rounded-full bg-neutral-50 px-2 py-0.5 text-[11px] font-medium text-neutral-700">{item.type === 'jadi' ? 'Obat jadi' : 'Racikan'}</span>{item.labelColor === 'biru' && <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-900">Etiket biru · Obat luar</span>}{item.external && <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800">Resep luar</span>}{item.isHighAlert && <span className="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-800">HIGH ALERT</span>}{item.isLasa && <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-900">LASA</span>}{item.nearExpiryDate && <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800">ED dekat: {item.nearExpiryDate}</span>}</div>{item.allergyOverrideReason && <p className="mt-1 text-xs text-red-700">Override alergi ({item.allergyMatchedTerm}): {item.allergyOverrideReason}</p>}</TableCell>
                                <TableCell className="whitespace-nowrap text-center font-sans tabular-nums text-neutral-700">{item.requestedQuantity} {item.unit}</TableCell>
                                {!alreadyComplete && <TableCell className="whitespace-nowrap text-center">{item.external ? <span className="text-xs text-neutral-500">Ditebus di luar</span> : item.reserved ? <span className="text-xs font-medium text-neutral-700">Sudah direservasi</span> : <span className={item.stock >= quantity ? 'font-medium text-emerald-700' : 'font-medium text-red-700'}>{item.stock} {item.stockUnit}</span>}</TableCell>}
                                <TableCell className="text-center">
                                    {item.external ? <span className="font-medium text-neutral-700">Tidak diserahkan di klinik</span>
                                        : alreadyComplete ? <span className="whitespace-nowrap font-sans tabular-nums">{item.dispensedQuantity} {item.unit}</span>
                                        : <div className="flex flex-col items-center gap-1"><Input aria-invalid={Boolean(quantityError)} aria-describedby={quantityError ? quantityErrorId : undefined} aria-label={`Jumlah ${item.name} diserahkan`} className="w-32 text-center font-sans tabular-nums" min="0" onChange={(event) => form.setData('items', form.data.items.map((entry, itemIndex) => itemIndex === index ? { ...entry, jumlah_diberikan: event.target.value } : entry))} step="1" type="number" value={current?.jumlah_diberikan ?? '0'} />{!sufficient && <p className="text-xs font-medium text-red-700">Jumlah melebihi resep atau stok tersedia.</p>}{quantityError && <p id={quantityErrorId} className="text-xs text-red-700">{quantityError}</p>}</div>}
                                </TableCell>
                                <TableCell className="text-center">{alreadyComplete || item.external ? instructions || 'Belum dicatat' : <Input aria-label={`Aturan pakai ${item.name}`} className="text-center" onChange={(event) => form.setData('items', form.data.items.map((entry, itemIndex) => itemIndex === index ? { ...entry, aturan_pakai: event.target.value } : entry))} placeholder="Dosis, frekuensi, dan waktu penggunaan" value={instructions ?? ''} />}</TableCell>
                                <TableCell className="text-center"><div className="flex items-center justify-center gap-2">
                                    {(!alreadyComplete || !item.external) && <ActionButton disabled={item.external || (!alreadyComplete && item.stock <= 0) || quantity <= 0} onClick={() => setEtiketItem({ name: item.name, instructions: instructions ?? '', quantity, unit: item.unit, color: item.labelColor ?? 'putih', expiryDate: item.expiryDate })} type="button" action="print" label="Cetak etiket" />}
                                    {(!alreadyComplete || item.external) && <ActionButton asChild action="print" disabled={!item.external || !visit.examinationSigned || !item.externalPrintUrl} disabledReason="Cetak resep luar tersedia setelah obat ditetapkan sebagai resep luar dan pemeriksaan ditandatangani." label="Cetak resep luar"><Link href={item.externalPrintUrl ?? ''} /></ActionButton>}
                                    {!alreadyComplete && <ActionButton action="issue" disabled={item.external || !visit.examinationSigned || item.reserved || item.stock > 0 || item.medicineId === null} label="Catat resep luar" onClick={() => markExternal(item)} disabledReason={item.external ? 'Obat sudah dicatat sebagai resep luar.' : !visit.examinationSigned ? 'Pemeriksaan dokter belum ditandatangani.' : item.reserved ? 'Obat sudah disiapkan untuk penyerahan.' : item.stock > 0 ? 'Stok obat masih tersedia.' : 'Obat tidak memenuhi syarat untuk dicatat sebagai resep luar.'} />}
                                </div></TableCell>
                            </TableRow>;
                        }) : <TableRow className="hover:bg-transparent"><TableCell className="text-center" colSpan={alreadyComplete ? 5 : 6}><Empty size="compact" title="Tidak ada item resep." /></TableCell></TableRow>}
                    </TableBody></Table></TableScroll></CardContent>
                    {!alreadyComplete && <CardContent className="space-y-4 border-t border-neutral-100 pt-5 sm:pt-6">
                        <Button asChild variant="secondary"><Link href={`/pelayanan/farmasi/${visit.id}/review`}>Telaah resep klinis oleh apoteker</Link></Button>
                        <p className="text-sm text-neutral-500">Simpan rencana penyerahan sebelum telaah resep. Perubahan resep atau rencana penyerahan memerlukan telaah ulang.</p>
                        {form.errors.pharmacy_review && <p role="alert" className="text-sm text-red-600">{form.errors.pharmacy_review}</p>}
                        <div className="grid gap-4 sm:grid-cols-2"><Field label="Nama penerima obat / resep luar" htmlFor="pharmacy-recipient" error={form.errors.recipient_name}><Input id="pharmacy-recipient" value={form.data.recipient_name} onChange={(event) => form.setData('recipient_name', event.target.value)} maxLength={200} /></Field><Field label="Hubungan dengan pasien" htmlFor="pharmacy-recipient-relationship" error={form.errors.recipient_relationship}><Input id="pharmacy-recipient-relationship" value={form.data.recipient_relationship} onChange={(event) => form.setData('recipient_relationship', event.target.value)} maxLength={200} /></Field></div>
                        <div className="space-y-2.5 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4">
                            <div className="space-y-1">
                                <label className="flex cursor-pointer items-start gap-2.5 text-sm leading-6 text-neutral-800" htmlFor="pharmacy-recipient-verified">
                                    <Checkbox id="pharmacy-recipient-verified" className="mt-1 shrink-0" checked={form.data.recipient_verified} onCheckedChange={(checked) => form.setData('recipient_verified', checked === true)} />
                                    <span>Identitas penerima telah diperiksa</span>
                                </label>
                                {form.errors.recipient_verified && <p role="alert" className="text-xs text-red-600">{form.errors.recipient_verified}</p>}
                            </div>
                            <div className="space-y-1">
                                <label className="flex cursor-pointer items-start gap-2.5 text-sm leading-6 text-neutral-800" htmlFor="pharmacy-counseling">
                                    <Checkbox id="pharmacy-counseling" className="mt-1 shrink-0" checked={form.data.counseling_completed} onCheckedChange={(checked) => form.setData('counseling_completed', checked === true)} />
                                    <span>Konseling penggunaan, penyimpanan dan tindak lanjut telah diberikan</span>
                                </label>
                                {form.errors.counseling_completed && <p role="alert" className="text-xs text-red-600">{form.errors.counseling_completed}</p>}
                            </div>
                        </div>
                        <Field label="Catatan konseling dan pemahaman penerima" htmlFor="pharmacy-counseling-notes" error={form.errors.counseling_notes}><Input id="pharmacy-counseling-notes" value={form.data.counseling_notes} onChange={(event) => form.setData('counseling_notes', event.target.value)} maxLength={2000} /></Field>
                    </CardContent>}
                    {alreadyComplete ? (visit.pharmacy?.notes && <div className="space-y-1 border-t border-neutral-100 p-5 sm:p-6"><p className="text-sm font-medium text-neutral-900">Catatan penyerahan</p><p className="whitespace-pre-wrap break-words text-sm text-neutral-700">{visit.pharmacy.notes}</p></div>) : <div className="space-y-4 border-t border-neutral-100 p-5 sm:p-6"><Field error={form.errors.catatan} htmlFor="catatan-farmasi" label="Catatan tambahan"><Input id="catatan-farmasi" onChange={(event) => form.setData('catatan', event.target.value)} placeholder="Informasi penyerahan atau penyimpanan obat" value={form.data.catatan} /></Field>{form.errors.items && <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{form.errors.items}</p>}
                        {form.isDirty && <p role="status" className="text-sm text-muted-foreground">Perubahan akan disimpan saat layanan diselesaikan.</p>}<div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><p className="text-xs text-neutral-500">{allExternal ? 'Siapkan dokumen resep luar untuk pasien. Tidak ada obat yang diserahkan dari stok klinik.' : 'Obat dari resep luar dicatat, tetapi tidak mengurangi persediaan klinik.'}</p><div className="flex flex-wrap justify-end gap-2"><Button asChild variant="secondary"><Link href="/pelayanan/farmasi">Batalkan</Link></Button><Button disabled={form.processing || alreadyComplete} type="submit" variant="secondary"><Save className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan draf'}</Button><Button disabled={form.processing || alreadyComplete || visit.prescriptionItems.length === 0} onClick={finishDispensing} type="button"><Check className="size-4" />{allExternal ? 'Selesaikan resep luar' : 'Serahkan obat & selesaikan'}</Button></div></div>
                    </div>}</form>
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
                                    <TableHeader><TableRow><TableHead className="text-center">Obat</TableHead><TableHead className="text-center">Batch</TableHead><TableHead className="text-center">Kedaluwarsa</TableHead><TableHead className="text-center">Jumlah</TableHead><TableHead className="text-center">Petugas / waktu</TableHead></TableRow></TableHeader>
                                    <TableBody>
                                        {visit.allocations.map((allocation, index) => (
                                            <TableRow key={`${allocation.medicine}-${allocation.batch ?? 'historis'}-${index}`}>
                                                <TableCell className="text-center">{allocation.medicine}</TableCell>
                                                <TableCell className="text-center font-sans tabular-nums">{allocation.batch ?? 'Historis'}</TableCell>
                                                <TableCell className="text-center">{allocation.expiry ?? 'Historis'}</TableCell>
                                                <TableCell className="text-center">{allocation.quantity}</TableCell>
                                                <TableCell className="text-center">{allocation.actor ?? 'Historis'}<p className="text-xs text-muted-foreground">{allocation.at}</p></TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </Card>
                )}
            </div>
            <Dialog onOpenChange={(open) => { if (!open) setEtiketItem(null); }} open={Boolean(etiketItem)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Pratinjau etiket obat (Permenkes 73/2016)</DialogTitle>
                        <DialogDescription>Pilih jenis etiket Putih (obat dalam) atau Biru (obat luar) sebelum mencetak.</DialogDescription>
                    </DialogHeader>
                    {etiketItem && <>
                        <div className="flex gap-2" role="group" aria-label="Jenis warna etiket">
                            <Button type="button" size="sm" variant={etiketItem.color === 'putih' ? 'default' : 'secondary'} onClick={() => setEtiketItem({ ...etiketItem, color: 'putih' })}>Etiket Putih · Obat Dalam</Button>
                            <Button type="button" size="sm" variant={etiketItem.color === 'biru' ? 'default' : 'secondary'} onClick={() => setEtiketItem({ ...etiketItem, color: 'biru' })}>Etiket Biru · Obat Luar</Button>
                        </div>
                        <div
                            className="rounded-xl border-2 p-4 text-center"
                            style={{
                                backgroundColor: etiketItem.color === 'biru' ? '#eff6ff' : '#ffffff',
                                borderColor: etiketItem.color === 'biru' ? '#1d4ed8' : '#404040',
                                color: '#0a0a0a',
                            }}
                        >
                            <p className="text-xs font-bold uppercase" style={{ color: '#0a0a0a' }}>{facilityName}</p>
                            <p className="mt-0.5 text-[11px]" style={{ color: '#525252' }}>{facilityAddress} · Telp. {facilityPhone}</p>
                            <p className="mt-0.5 text-[11px] font-semibold" style={{ color: '#262626' }}>{sipaLine}</p>
                            <div className="my-2.5 border-y py-2 text-left text-xs" style={{ borderColor: '#d4d4d4' }}>
                                <p className="text-[11px]" style={{ color: '#525252' }}>{visit.prescriptionNumber ? `No. Resep: ${visit.prescriptionNumber}` : `Kunjungan: ${visit.number}`} · {today}</p>
                                <p className="mt-1 text-sm font-bold" style={{ color: '#0a0a0a' }}>{visit.patient.name}</p>
                                <p className="text-[11px] font-medium" style={{ color: '#404040' }}>No. RM: {visit.patient.medicalRecordNumber}{visit.patient.birthDate ? ` · Tgl Lahir: ${visit.patient.birthDate}` : ''}</p>
                            </div>
                            <p className="text-base font-bold" style={{ color: '#0a0a0a' }}>{etiketItem.name}</p>
                            <p className="text-xs font-medium" style={{ color: '#404040' }}>Jumlah: {etiketItem.quantity} {etiketItem.unit ?? ''}</p>
                            <p className="mt-2 rounded-lg border px-3 py-2 text-sm font-bold" style={{ backgroundColor: '#ffffff', borderColor: '#d4d4d4', color: '#0a0a0a' }}>{etiketItem.instructions || 'Ikuti petunjuk dokter'}</p>
                            <p
                                className="mt-2 rounded px-2 py-1 text-[11px] font-bold tracking-wide uppercase"
                                style={etiketItem.color === 'biru'
                                    ? { backgroundColor: '#1e40af', color: '#ffffff' }
                                    : { backgroundColor: '#f5f5f5', border: '1px solid #a3a3a3', color: '#171717' }}
                            >
                                {etiketItem.color === 'biru' ? 'OBAT LUAR — TIDAK UNTUK DITELAN' : 'OBAT DALAM'}
                            </p>
                            <div className="mt-2.5 flex items-center justify-between text-[11px]" style={{ color: '#525252' }}>
                                <span>{etiketItem.expiryDate ? `ED: ${etiketItem.expiryDate}` : 'Simpan sejuk & kering'}</span>
                                <span className="italic">Semoga lekas sembuh</span>
                            </div>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button onClick={() => setEtiketItem(null)} type="button" variant="secondary">Tutup</Button>
                            <Button onClick={printLabel} type="button"><Printer className="size-4" />Cetak</Button>
                        </div>
                    </>}
                </DialogContent>
            </Dialog>
        </>
    );
}

function escapeHtml(value: string) {
    return value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character] ?? character);
}
