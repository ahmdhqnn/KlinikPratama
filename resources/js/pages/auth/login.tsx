import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, BriefcaseMedical, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

export default function LoginPage() {
    const form = useForm<LoginForm>({ email: '', password: '', remember: false });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    }

    return (
        <>
            <Head title="Masuk" />
            <main className="grid min-h-screen bg-white lg:grid-cols-[minmax(0,1fr)_minmax(28rem,0.9fr)]">
                <section className="relative hidden overflow-hidden bg-slate-950 px-12 py-10 text-white lg:flex lg:flex-col lg:justify-between xl:px-20">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_25%_15%,rgba(59,130,246,0.45),transparent_34%),radial-gradient(circle_at_90%_80%,rgba(14,165,233,0.22),transparent_30%)]" />
                    <div className="relative flex items-center gap-3">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-blue-500 text-white shadow-lg shadow-blue-950/30">
                            <BriefcaseMedical className="size-6" />
                        </span>
                        <span>
                            <span className="block text-base font-semibold">Klinik Pratama</span>
                            <span className="block text-xs text-slate-300">Sistem Rekam Medis Elektronik</span>
                        </span>
                    </div>
                    <div className="relative max-w-xl pb-10">
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-blue-100">
                            <ShieldCheck className="size-4" />
                            Akses aman untuk tim klinik
                        </div>
                        <h1 className="text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">
                            Pelayanan klinik yang terhubung, dimulai dari satu tempat.
                        </h1>
                        <p className="mt-5 max-w-lg text-base leading-7 text-slate-300">
                            Kelola pendaftaran, pemeriksaan, farmasi, dan administrasi pasien dengan alur kerja yang lebih jelas.
                        </p>
                        <div className="mt-10 flex items-center gap-3 text-sm text-slate-300">
                            <span className="flex size-9 items-center justify-center rounded-full border border-white/15 bg-white/5">
                                <ArrowRight className="size-4" />
                            </span>
                            Satu sistem untuk seluruh tim pelayanan
                        </div>
                    </div>
                    <p className="relative text-xs text-slate-400">© {new Date().getFullYear()} Klinik Pratama</p>
                </section>

                <section className="flex min-h-screen items-center justify-center px-5 py-12 sm:px-8">
                    <div className="w-full max-w-md">
                        <div className="mb-8 lg:hidden">
                            <span className="flex size-11 items-center justify-center rounded-xl bg-blue-600 text-white">
                                <BriefcaseMedical className="size-6" />
                            </span>
                        </div>
                        <div className="mb-8 space-y-2">
                            <p className="text-sm font-semibold text-blue-700">Portal staf</p>
                            <h2 className="text-3xl font-semibold tracking-tight text-slate-950">Selamat datang kembali</h2>
                            <p className="text-sm leading-6 text-slate-500">Masuk untuk melanjutkan pekerjaan Anda di klinik.</p>
                        </div>

                        <Card>
                            <CardContent className="p-6 sm:p-8">
                                <form className="space-y-5" onSubmit={submit}>
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-700" htmlFor="email">Email</label>
                                        <div className="relative">
                                            <Mail className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                            <Input
                                                autoComplete="username"
                                                className="pl-10"
                                                id="email"
                                                name="email"
                                                onChange={(event) => form.setData('email', event.target.value)}
                                                placeholder="nama@klinik.com"
                                                required
                                                type="email"
                                                value={form.data.email}
                                            />
                                        </div>
                                        {form.errors.email && <p className="text-sm text-red-600">{form.errors.email}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-700" htmlFor="password">Password</label>
                                        <div className="relative">
                                            <LockKeyhole className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                            <Input
                                                autoComplete="current-password"
                                                className="pl-10"
                                                id="password"
                                                name="password"
                                                onChange={(event) => form.setData('password', event.target.value)}
                                                placeholder="Masukkan password"
                                                required
                                                type="password"
                                                value={form.data.password}
                                            />
                                        </div>
                                        {form.errors.password && <p className="text-sm text-red-600">{form.errors.password}</p>}
                                    </div>

                                    <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
                                        <input
                                            checked={form.data.remember}
                                            className="size-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                            name="remember"
                                            onChange={(event) => form.setData('remember', event.target.checked)}
                                            type="checkbox"
                                        />
                                        Ingat saya di perangkat ini
                                    </label>

                                    <Button className="w-full" disabled={form.processing} size="lg" type="submit">
                                        {form.processing ? 'Memeriksa akses…' : 'Masuk ke sistem'}
                                        <ArrowRight className="size-4" />
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        <p className="mt-6 text-center text-xs leading-5 text-slate-500">
                            Akses sistem hanya untuk staf klinik yang memiliki akun aktif.
                        </p>
                    </div>
                </section>
            </main>
        </>
    );
}
