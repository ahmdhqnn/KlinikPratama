import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

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
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div><p className="text-sm font-medium text-neutral-700">Direktori instansi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Detail kepesertaan</h2><p className="mt-1 text-sm text-neutral-500">Informasi peserta terverifikasi untuk pendaftaran dan hak layanan klinik internal.</p></div>
                    <Button asChild variant="secondary"><Link href={urls.index}><ArrowLeft className="size-4" />Kembali ke daftar</Link></Button>
                </div>
                <Card>
                    <CardHeader><div className="flex items-start justify-between gap-4"><div><CardTitle>{member.nama}</CardTitle><CardDescription className="mt-1">{member.unitKerja} · {member.kategori}{member.jenisLainnya ? ` / ${member.jenisLainnya}` : ''}</CardDescription></div><Badge variant={member.reason ? 'cancelled' : 'complete'}><ShieldCheck className="size-3.5" />{member.reason ? 'Hak layanan tidak aktif' : 'Hak layanan tersedia'}</Badge></div></CardHeader>
                    <CardContent className="space-y-7">
                        {groups.map((group) => <section className="space-y-3" key={group.title}><h3 className="text-sm font-semibold text-neutral-950">{group.title}</h3><dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">{group.entries.map(([label, value]) => <div className="min-w-0" key={label}><dt className="text-xs font-medium uppercase tracking-wide text-neutral-500">{label}</dt><dd className="mt-1 break-words text-sm text-neutral-900">{value || 'Belum diisi'}</dd></div>)}</dl></section>)}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
