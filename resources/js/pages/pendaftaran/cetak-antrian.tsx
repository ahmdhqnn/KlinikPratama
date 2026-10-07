import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface Ticket { queueNumber: number; visitNumber: string; date: string; patient: string; medicalRecordNumber: string; clinic: string; doctor: string }

export default function QueueTicket({ ticket, backUrl }: { ticket: Ticket; backUrl: string }) {
    return <><Head title={`Nomor Antrean ${ticket.queueNumber}`} /><div className="mx-auto max-w-xl space-y-5"><div className="flex justify-between print:hidden"><Button asChild size="sm" variant="ghost"><Link href={backUrl}><ArrowLeft className="size-4" />Kembali ke laporan</Link></Button><Button onClick={() => window.print()}><Printer className="size-4" />Cetak tiket</Button></div><Card className="queue-ticket border-slate-200"><CardContent className="p-8 text-center"><header className="border-b border-slate-200 pb-6"><h2 className="text-2xl font-bold text-slate-950">Klinik Pratama</h2><p className="mt-1 text-sm text-slate-500">Nomor antrean kunjungan</p></header><section className="py-7"><div className="rounded-2xl bg-blue-700 px-6 py-8 text-white"><p className="text-sm font-medium text-blue-100">NOMOR ANTREAN</p><p className="mt-1 font-mono text-6xl font-bold">{ticket.queueNumber}</p></div><p className="mt-3 text-sm text-slate-500">No. kunjungan: <span className="font-mono font-semibold text-slate-800">{ticket.visitNumber}</span></p></section><section className="space-y-4 rounded-xl bg-slate-50 p-5 text-left"><TicketField label="Nama pasien" value={ticket.patient} /><TicketField label="No. rekam medis" value={ticket.medicalRecordNumber} /><TicketField label="Poliklinik tujuan" value={ticket.clinic} /><TicketField label="Dokter" value={ticket.doctor} /></section><section className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-left text-sm text-amber-900"><p className="font-semibold">Petunjuk</p><ul className="mt-2 list-inside list-disc space-y-1 text-xs"><li>Datang 10 menit sebelum jadwal pemeriksaan.</li><li>Bawa kartu identitas dan hasil pemeriksaan sebelumnya.</li><li>Tunjukkan tiket ini kepada petugas.</li></ul></section><p className="mt-5 text-sm text-slate-600">{ticket.date}</p></CardContent></Card></div></>;
}

function TicketField({ label, value }: { label: string; value: string }) {
    return <div className="border-b border-slate-200 pb-3 last:border-0 last:pb-0"><p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p><p className="mt-1 text-base font-semibold text-slate-900">{value}</p></div>;
}
