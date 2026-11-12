import { ClinicBrand } from '@/components/clinic-brand';
import { Head } from '@inertiajs/react';
import { ArrowLeft, CircleAlert, CircleHelp, FileQuestion, House, ListChecks, LogIn, RotateCw, ShieldAlert, Timer, Wrench } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { returnToPreviousScreen } from '@/lib/navigation';

const errors = {
    400: ['Permintaan tidak valid', 'Periksa alamat halaman atau buka layanan dari beranda.', CircleAlert],
    401: ['Masuk diperlukan', 'Masuk dengan akun staf untuk melanjutkan.', ShieldAlert],
    403: ['Akses dibatasi', 'Akun Anda tidak memiliki akses ke layanan ini. Buka layanan yang tersedia dari beranda.', ShieldAlert],
    404: ['Halaman tidak ditemukan', 'Alamat ini tidak mengarah ke halaman yang tersedia.', FileQuestion],
    405: ['Metode tidak didukung', 'Buka kembali layanan dari beranda, lalu gunakan tombol yang tersedia di halaman tersebut.', CircleAlert],
    409: ['Data perlu ditinjau ulang', 'Periksa status data terbaru sebelum melanjutkan.', CircleAlert],
    410: ['Halaman sudah tidak tersedia', 'Buka beranda untuk menemukan layanan yang masih tersedia.', FileQuestion],
    413: ['Berkas atau data terlalu besar', 'Kurangi ukuran berkas atau jumlah data, lalu kirim kembali dari formulir.', CircleAlert],
    419: ['Sesi telah berakhir', 'Buka kembali halaman untuk memperbarui sesi. Masuk kembali jika diminta.', Timer],
    422: ['Permintaan belum dapat diproses', 'Periksa isian dan tahapan layanan sebelum melanjutkan.', CircleAlert],
    429: ['Terlalu banyak percobaan', 'Tunggu sebentar sebelum mencoba lagi.', Timer],
    503: ['Layanan sementara tidak tersedia', 'Coba buka kembali layanan setelah beberapa saat.', Wrench],
} as const;

const recoverySteps: Partial<Record<number, string[]>> = {
    400: ['Buka layanan dari menu aplikasi jika tautan yang Anda gunakan sudah lama.'],
    401: ['Gunakan akun staf yang sesuai. Jika kesulitan masuk, buka panduan bantuan akses.'],
    403: ['Jika layanan ini diperlukan untuk pekerjaan Anda, minta administrator klinik memeriksa hak akses akun.'],
    404: ['Periksa kembali alamat tautan.', 'Jika mencari pasien atau kunjungan, buka daftar di modul terkait dan cari ulang dari sana.'],
    405: ['Kembali ke halaman layanan dan jalankan tindakan melalui tombol yang tersedia.'],
    409: ['Buka data terbaru dan tinjau statusnya sebelum mengulangi perubahan.'],
    410: ['Gunakan menu di beranda untuk mencari layanan pengganti.'],
    413: ['Pilih berkas yang lebih kecil dan periksa batas ukuran yang tercantum pada formulir.'],
    422: ['Kembali ke formulir dan periksa isian yang ditandai serta tahapan layanan saat ini.'],
    500: ['Periksa status data sebelum mengulang tindakan simpan.', 'Jika gangguan berulang, sampaikan kode 500 dan waktu kejadian kepada pengelola sistem.'],
    503: ['Coba kembali setelah beberapa saat. Jika layanan masih belum tersedia, hubungi pengelola sistem.'],
};

interface Props {
    status: number;
    message?: string | null;
    canRetry?: boolean;
    retryAfter?: number | null;
    returnToLogin?: boolean;
}

export default function ErrorPage({ status, message, canRetry, retryAfter, returnToLogin }: Props) {
    const [title, description, Icon] = errors[status as keyof typeof errors] ?? (status >= 500
        ? ['Terjadi gangguan sistem', 'Coba buka kembali layanan. Jika gangguan berulang, hubungi pengelola sistem klinik.', Wrench]
        : ['Permintaan tidak dapat diproses', 'Buka beranda untuk melanjutkan dari layanan yang tersedia.', CircleAlert]);
    const heading = useRef<HTMLHeadingElement>(null);
    const home = returnToLogin ? '/login' : '/';
    const hasRetryDelay = retryAfter != null && retryAfter > 0;
    let steps = recoverySteps[status] ?? ['Buka layanan dari beranda dan coba kembali.'];

    if (status === 419) {
        steps = [canRetry
            ? 'Pilih Muat ulang halaman untuk memperbarui sesi.'
            : 'Buka kembali formulir dari menu layanan dan periksa isian sebelum mengirim ulang.'];
    } else if (status === 429) {
        steps = [hasRetryDelay
            ? 'Tunggu hingga waktu yang tercantum sebelum mencoba lagi.'
            : 'Beri jeda sebelum mencoba kembali.'];
    } else if (status >= 500 && !recoverySteps[status]) {
        steps = ['Jika gangguan berulang, sampaikan kode dan waktu kejadian kepada pengelola sistem.'];
    }
    const HomeIcon = returnToLogin ? LogIn : House;
    const statusTone = status >= 500
        ? 'border-red-200 bg-red-50 text-red-800'
        : [401, 403, 409, 419, 429].includes(status)
            ? 'border-amber-200 bg-amber-50 text-amber-800'
            : 'border-blue-200 bg-blue-50 text-blue-800';

    useEffect(() => { heading.current?.focus({ preventScroll: true }); }, [status]);

    return (
        <>
            <Head title={title}><meta name="robots" content="noindex" /></Head>
            <main className="grid min-h-dvh place-items-center bg-canvas px-5 py-12 sm:px-8">
                <div className="w-full max-w-2xl space-y-5">
                    <header className="flex items-center">
                        <a className="flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4" href={home}>
                            <ClinicBrand />
                        </a>
                    </header>
                    <Card aria-labelledby="error-title">
                        <CardContent className="space-y-6 p-6 sm:p-8">
                            <div className="flex items-center gap-3">
                                <span className={`grid size-11 place-items-center rounded-xl border ${statusTone}`}><Icon aria-hidden="true" className="size-5" /></span>
                                <Badge variant="default">Kode {status}</Badge>
                            </div>
                            <div className="space-y-2">
                                <h1 className="text-2xl font-semibold tracking-tight text-neutral-950 focus:outline-none sm:text-3xl" id="error-title" ref={heading} tabIndex={-1}>{title}</h1>
                                <p className="text-sm leading-6 text-neutral-700 sm:text-base">{description}</p>
                                {message && message !== description && <p className="text-sm font-medium text-neutral-950">{message}</p>}
                                {hasRetryDelay && <p className="text-sm font-medium text-neutral-700">{`Coba lagi setelah ${retryAfter} detik.`}</p>}
                            </div>
                            <section aria-labelledby="recovery-title" className="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4 sm:p-5">
                                <h2 className="flex items-center gap-2 text-sm font-semibold text-neutral-950" id="recovery-title"><ListChecks aria-hidden="true" className="size-4" />Langkah berikutnya</h2>
                                <ul className="list-disc space-y-1.5 pl-5 text-sm leading-6 text-neutral-700">
                                    {steps.map((step) => <li key={step}>{step}</li>)}
                                </ul>
                            </section>
                            <nav aria-label="Pilihan untuk melanjutkan" className="flex flex-col gap-2 border-t border-neutral-200 pt-5 sm:flex-row sm:flex-wrap">
                                <Button asChild><a href={home}><HomeIcon aria-hidden="true" className="size-4" />{returnToLogin ? 'Kembali ke halaman masuk' : 'Kembali ke beranda'}</a></Button>
                                {!returnToLogin && <Button onClick={() => returnToPreviousScreen(home)} type="button" variant="secondary"><ArrowLeft aria-hidden="true" className="size-4" />Kembali</Button>}
                                {canRetry && <Button onClick={() => window.location.reload()} type="button" variant="secondary"><RotateCw aria-hidden="true" className="size-4" />Muat ulang halaman</Button>}
                                <Button asChild variant="secondary"><a href="/bantuan-akses"><CircleHelp aria-hidden="true" className="size-4" />Bantuan</a></Button>
                            </nav>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
