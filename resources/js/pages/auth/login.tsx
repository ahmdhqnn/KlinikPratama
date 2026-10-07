import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, BriefcaseMedical, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { ThemeToggle } from '@/components/ui/theme-toggle';

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
            <main className="grid min-h-screen bg-surface lg:grid-cols-[minmax(0,1fr)_minmax(28rem,0.9fr)]">
                <section className="relative hidden overflow-hidden bg-inverse px-12 py-10 text-on-inverse lg:flex lg:flex-col lg:justify-between xl:px-20">
                    <div className="relative flex items-center gap-3">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-on-inverse/15 text-on-inverse shadow-lg shadow-inverse/30">
                            <BriefcaseMedical className="size-6" />
                        </span>
                        <span>
                            <span className="block text-base font-semibold">Klinik Pratama</span>
                            <span className="block text-xs text-on-inverse/70">Sistem Rekam Medis Elektronik</span>
                        </span>
                    </div>
                    <div className="relative max-w-xl pb-10">
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-on-inverse/15 bg-on-inverse/5 px-3 py-1.5 text-xs font-medium text-on-inverse/80">
                            <ShieldCheck className="size-4" />
                            Akses aman untuk tim klinik
                        </div>
                        <h1 className="text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">
                            Pelayanan klinik yang terhubung, dimulai dari satu tempat.
                        </h1>
                        <p className="mt-5 max-w-lg text-base leading-7 text-on-inverse/70">
                            Kelola pendaftaran, pemeriksaan, farmasi, dan administrasi pasien dengan alur kerja yang lebih jelas.
                        </p>
                        <div className="mt-10 flex items-center gap-3 text-sm text-on-inverse/70">
                            <span className="flex size-9 items-center justify-center rounded-full border border-on-inverse/15 bg-on-inverse/5">
                                <ArrowRight className="size-4" />
                            </span>
                            Satu sistem untuk seluruh tim pelayanan
                        </div>
                    </div>
                    <p className="relative text-xs text-on-inverse/55">© {new Date().getFullYear()} Klinik Pratama</p>
                </section>

                <section className="relative flex min-h-screen items-center justify-center px-5 py-12 sm:px-8">
                    <ThemeToggle className="absolute right-5 top-5 sm:right-8 sm:top-8" />
                    <div className="w-full max-w-md">
                        <div className="mb-8 lg:hidden">
                            <span className="flex size-11 items-center justify-center rounded-xl bg-neutral-600 text-neutral-50">
                                <BriefcaseMedical className="size-6" />
                            </span>
                        </div>
                        <div className="mb-8 space-y-2">
                            <p className="text-sm font-semibold text-neutral-700">Portal staf</p>
                            <h2 className="text-3xl font-semibold tracking-tight text-neutral-950">Selamat datang kembali</h2>
                            <p className="text-sm leading-6 text-neutral-500">Masuk untuk melanjutkan pekerjaan Anda di klinik.</p>
                        </div>

                        <Card>
                            <CardContent className="p-6 sm:p-8">
                                <form className="space-y-5" onSubmit={submit}>
                                    <div className="space-y-2">
                                        <Label className="text-sm font-medium text-neutral-700" htmlFor="email">Email</Label>
                                        <div className="relative">
                                            <Mail className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-neutral-400" />
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
                                        <Label className="text-sm font-medium text-neutral-700" htmlFor="password">Password</Label>
                                        <div className="relative">
                                            <LockKeyhole className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-neutral-400" />
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

                                    <Label className="flex cursor-pointer items-center gap-2.5 text-sm text-neutral-600">
                                        <Checkbox checked={form.data.remember} name="remember" onCheckedChange={(checked) => form.setData('remember', (checked === true))} />
                                        Ingat saya di perangkat ini
                                    </Label>

                                    <Button className="w-full" disabled={form.processing} size="lg" type="submit">
                                        {form.processing ? 'Memeriksa akses…' : 'Masuk ke sistem'}
                                        <ArrowRight className="size-4" />
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        <p className="mt-6 text-center text-xs leading-5 text-neutral-500">
                            Akses sistem hanya untuk staf klinik yang memiliki akun aktif.
                        </p>
                    </div>
                </section>
            </main>
        </>
    );
}
