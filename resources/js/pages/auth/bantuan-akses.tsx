import { Head, Link } from '@inertiajs/react';
import { BriefcaseMedical, House, KeyRound, LogIn } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function AccessHelp() {
    return (
        <>
            <Head title="Bantuan Klinik Pratama" />
            <main className="grid min-h-dvh place-items-center bg-canvas px-5 py-12 sm:px-8">
                <div className="w-full max-w-xl space-y-5">
                    <header className="flex items-center">
                        <Link href="/login" className="flex items-center gap-3 rounded-lg text-sm font-semibold text-neutral-950">
                            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-neutral-900 text-neutral-50"><BriefcaseMedical aria-hidden="true" className="size-5" /></span>
                            Klinik Pratama
                        </Link>
                    </header>
                    <Card>
                        <CardContent className="space-y-5 p-6 sm:p-8">
                            <span className="grid size-11 place-items-center rounded-xl border border-neutral-200 bg-neutral-100 text-neutral-700"><KeyRound aria-hidden="true" className="size-5" /></span>
                            <div className="space-y-2">
                                <h1 className="text-2xl font-semibold tracking-tight text-neutral-950">Bantuan Klinik Pratama</h1>
                                <p className="text-sm leading-6 text-neutral-700">Hubungi administrator melalui kanal resmi klinik jika Anda kesulitan masuk, membuka halaman, atau menggunakan layanan.</p>
                            </div>
                            <section className="space-y-2 text-sm leading-6 text-neutral-700">
                                <h2 className="font-semibold text-neutral-950">Gangguan halaman atau layanan</h2>
                                <p>Sampaikan nama halaman, kode error, dan waktu kejadian. Hindari menyertakan data pasien atau kata sandi.</p>
                            </section>
                            <section className="space-y-2 text-sm leading-6 text-neutral-700">
                                <h2 className="font-semibold text-neutral-950">Bantuan masuk dan kata sandi</h2>
                                <ol className="list-decimal space-y-2 pl-5">
                                    <li>Sampaikan nama, peran, dan email akun Anda agar administrator dapat memeriksa akses.</li>
                                    <li>Jika kata sandi diatur ulang, masuk dengan kata sandi sementara yang diberikan.</li>
                                    <li>Perbarui kata sandi melalui Profil saya setelah berhasil masuk.</li>
                                </ol>
                            </section>
                            <nav aria-label="Pilihan untuk melanjutkan" className="flex flex-col gap-2 border-t border-neutral-200 pt-5 sm:flex-row sm:flex-wrap">
                                <Button asChild><Link href="/"><House aria-hidden="true" className="size-4" />Beranda</Link></Button>
                                <Button asChild variant="secondary"><Link href="/login"><LogIn aria-hidden="true" className="size-4" />Halaman masuk</Link></Button>
                            </nav>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
