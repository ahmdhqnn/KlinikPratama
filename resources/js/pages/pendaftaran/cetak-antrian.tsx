import { Head } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import { returnToPreviousScreen } from '@/lib/navigation';

interface Ticket { isReservation: boolean; queueNumber: number; visitNumber: string; date: string; patient: string; medicalRecordNumber: string; clinic: string; doctor: string }
interface Clinic { name: string; address: string | null; phone: string | null; email: string | null; logoUrl: string | null }

export default function QueueTicket({ ticket, clinic, backUrl }: { ticket: Ticket; clinic: Clinic; backUrl: string }) {
    return <>
        <Head title={`Tiket Kunjungan ${ticket.visitNumber}`} />
        <main className="mx-auto max-w-xl space-y-4 p-4 sm:p-6 print:max-w-none print:p-0">
            <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <Button onClick={() => returnToPreviousScreen(backUrl)} type="button" variant="secondary"><ArrowLeft className="size-4" />Kembali</Button>
                <div className="flex items-center gap-2"><ThemeToggle /><Button onClick={() => window.print()} type="button"><Printer className="size-4" />Cetak tiket</Button></div>
            </div>
            <article className="queue-ticket rounded-xl border border-neutral-200 bg-surface p-6 text-neutral-950 shadow-sm sm:p-8 print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none">
                <header className="border-b-2 border-neutral-900 pb-4 text-center">
                    {clinic.logoUrl && <img alt="Logo klinik" className="mx-auto mb-3 max-h-14 max-w-20 object-contain" src={clinic.logoUrl} />}
                    <h1 className="text-lg font-bold uppercase tracking-wide">{clinic.name}</h1>
                    {clinic.address && <p className="mt-1 whitespace-pre-line text-xs text-neutral-600">{clinic.address}</p>}
                    {(clinic.phone || clinic.email) && <p className="mt-1 break-words text-xs text-neutral-600">{[clinic.phone, clinic.email].filter(Boolean).join(' · ')}</p>}
                </header>
                <section className="py-5 text-center">
                    <h2 className="text-sm font-semibold uppercase tracking-wide">{ticket.isReservation ? 'Bukti reservasi kunjungan' : 'Tiket antrean kunjungan'}</h2>
                    <p className="mt-4 text-xs text-neutral-500">Nomor antrean · {ticket.clinic}</p>
                    <p className="mt-1 font-mono text-6xl font-bold leading-none tracking-tight">{String(ticket.queueNumber).padStart(3, '0')}</p>
                    <p className="mt-4 font-mono text-xs text-neutral-600">{ticket.visitNumber}</p>
                </section>
                <dl className="divide-y divide-neutral-200 border-y border-neutral-200">
                    <TicketField label="Tanggal kunjungan" value={ticket.date} />
                    <TicketField label="Nama pasien" value={ticket.patient} />
                    <TicketField label="No. rekam medis" value={ticket.medicalRecordNumber} />
                    <TicketField label="Poliklinik" value={ticket.clinic} />
                    <TicketField label="Dokter" value={ticket.doctor} />
                </dl>
                <footer className="mt-4 space-y-2 text-xs leading-relaxed text-neutral-600">
                    <p>{ticket.isReservation ? 'Datang sesuai tanggal reservasi dan konfirmasikan kehadiran kepada petugas pendaftaran.' : 'Tunjukkan tiket ini kepada petugas dan tunggu panggilan pengkajian awal.'}</p>
                    <p>Bawa kartu identitas dan dokumen pemeriksaan sebelumnya. Urutan pelayanan mengikuti penilaian kondisi pasien oleh petugas.</p>
                </footer>
            </article>
        </main>
    </>;
}

function TicketField({ label, value }: { label: string; value: string }) {
    return <div className="grid grid-cols-[7rem_minmax(0,1fr)] gap-3 py-2.5 text-sm"><dt className="text-neutral-500">{label}</dt><dd className="break-words font-medium">{value}</dd></div>;
}
