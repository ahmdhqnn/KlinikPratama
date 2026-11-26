import { Head } from '@inertiajs/react';
import { ShieldCheck, UserRound } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { PageHeaderWithActions } from '@/components/ui/page-header';

interface Member {
    id: number; nama: string; nik: string | null; nip: string | null; kategori: string; jenisLainnya: string | null; status: string;
    unitKerja: string; tempatLahir: string | null; tanggalLahir: string | null;
    jenisKelamin: string; agama: string | null; golonganDarah: string | null; alamat: string | null;
    hakLayanan: boolean; reason: string | null;
    referensiBukti: string; verifiedAt: string | null;
}

export default function MembershipDetail({ member, urls }: { member: Member; urls: { index: string } }) {
    const groups = [
        { title: 'Identitas kependudukan', entries: [
            ['NIK', member.nik], ['NIP', member.nip], ['Nama lengkap', member.nama],
            ['Tempat, tanggal lahir', [member.tempatLahir, member.tanggalLahir].filter(Boolean).join(', ')],
            ['Jenis kelamin', member.jenisKelamin], ['Agama', member.agama], ['Golongan darah', member.golonganDarah],
        ] },
        { title: 'Alamat sesuai KTP', entries: [['Alamat', member.alamat]] },
        { title: 'Kepegawaian dan hak layanan', entries: [
            ['Kategori peserta', member.kategori], ['Jenis lainnya', member.jenisLainnya], ['Status', member.status], ['Unit kerja', member.unitKerja],
            ['Hak layanan', member.reason ?? (member.hakLayanan ? 'Berhak' : 'Tidak berhak')], ['Referensi bukti', member.referensiBukti],
            ['Diverifikasi pada', member.verifiedAt],
        ] },
    ];

    return (
        <>
            <Head title={`Detail kepesertaan · ${member.nama}`} />
            <div className="space-y-6">
                <PageHeaderWithActions
                    backHref={urls.index}
                    backLabel="Kembali ke daftar"
                    eyebrow="Direktori instansi"
                    subtitle="Informasi peserta terverifikasi untuk pendaftaran dan hak layanan klinik internal."
                    title="Detail kepesertaan"
                />
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex items-start gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><UserRound className="size-5" /></span>
                                <div>
                                    <CardTitle>{member.nama}</CardTitle>
                                    <CardDescription className="mt-1">{member.unitKerja} · {member.kategori}{member.jenisLainnya ? ` / ${member.jenisLainnya}` : ''}</CardDescription>
                                </div>
                            </div>
                            <Badge variant={member.reason ? 'cancelled' : 'complete'}><ShieldCheck className="size-3.5" />{member.reason ? 'Hak layanan tidak aktif' : 'Hak layanan tersedia'}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-7 p-5 sm:p-6">
                        {groups.map((group) => <section className="space-y-3" key={group.title}><h3 className="text-sm font-semibold text-neutral-950">{group.title}</h3><dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">{group.entries.map(([label, value]) => <div className="min-w-0" key={label}><dt className="text-xs font-medium uppercase tracking-wide text-neutral-500">{label}</dt><dd className="mt-1 break-words text-sm text-neutral-900">{value || 'Belum dicatat'}</dd></div>)}</dl></section>)}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
