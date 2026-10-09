import { Alert, AlertDescription } from '@/components/ui/alert';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Search, UserRound, UserRoundPlus, X } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { VisitFields, type VisitFormValues } from '@/components/pendaftaran/visit-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

interface Option {
    id: number;
    name: string;
}

interface PatientForm extends VisitFormValues {
    pasien_id: string;
}

interface PatientResult {
    id: number;
    no_rm: string;
    nama: string;
    jenis_kelamin: string | null;
    tanggal_lahir: string | null;
    telepon: string | null;
    eligibilityReason: string | null;
}

interface Props {
    clinics: Option[];
    insuranceProviders: Option[];
    today: string;
}

export default function ExistingPatientRegistration({ clinics, insuranceProviders, today }: Props) {
    const form = useForm<PatientForm>({
        pasien_id: '', poliklinik_id: '', dokter_id: '', asuransi_id: '', no_asuransi: '', jenis_bayar: 'internal', catatan: '',
    });
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<PatientResult[]>([]);
    const [selectedPatient, setSelectedPatient] = useState<PatientResult | null>(null);
    const [searching, setSearching] = useState(false);
    const [searchError, setSearchError] = useState('');

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults([]);
            setSearching(false);
            setSearchError('');
            return;
        }

        const abortController = new AbortController();
        const timeout = window.setTimeout(async () => {
            setSearching(true);
            setSearchError('');

            try {
                const url = new URL('/pendaftaran/pasien/search', window.location.origin);
                url.searchParams.set('q', query.trim());
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: abortController.signal,
                });

                if (!response.ok) {
                    throw new Error('Pencarian pasien gagal. Coba kembali.');
                }

                setResults(await response.json() as PatientResult[]);
            } catch (error) {
                if (!(error instanceof DOMException && error.name === 'AbortError')) {
                    setSearchError(error instanceof Error ? error.message : 'Pencarian pasien gagal.');
                    setResults([]);
                }
            } finally {
                if (!abortController.signal.aborted) {
                    setSearching(false);
                }
            }
        }, 300);

        return () => {
            window.clearTimeout(timeout);
            abortController.abort();
        };
    }, [query]);

    function selectPatient(patient: PatientResult) {
        setSelectedPatient(patient);
        form.setData('pasien_id', String(patient.id));
        setQuery('');
        setResults([]);
        form.clearErrors('pasien_id');
    }

    function clearPatient() {
        setSelectedPatient(null);
        form.setData('pasien_id', '');
        setQuery('');
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/pendaftaran/pendaftaran-lama');
    }

    return (
        <>
            <Head title="Pendaftaran Pasien Lama" />
            <form className="mx-auto max-w-5xl space-y-6" onSubmit={submit}>
                {selectedPatient && <Alert variant={selectedPatient.eligibilityReason ? 'destructive' : 'success'}><AlertDescription>{selectedPatient.eligibilityReason ?? 'Peserta terverifikasi dan berhak atas fasilitas klinik internal.'}</AlertDescription></Alert>}
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-neutral-700">Pendaftaran pasien</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Kunjungan pasien terdaftar</h2>
                        <p className="mt-1 text-sm text-neutral-500">Cari data rekam medis pasien lalu pilih tujuan kunjungannya.</p>
                    </div>
                    <Button asChild variant="secondary"><Link href="/pendaftaran"><ArrowLeft className="size-4" />Kembali ke dashboard</Link></Button>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Search className="size-5" /></span>
                            <div><CardTitle>Cari pasien</CardTitle><CardDescription className="mt-1">Gunakan nama, nomor rekam medis, atau NIK. Hasil dibatasi pada 10 pasien.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Field error={form.errors.pasien_id ?? searchError} htmlFor="patient-search" label="Cari pasien" required>
                            <div className="relative">
                                <Input autoComplete="off" id="patient-search" onChange={(event) => {
                                    setQuery(event.target.value);
                                    setSelectedPatient(null);
                                    form.setData('pasien_id', '');
                                }} placeholder="Ketik nama, No. RM, atau NIK…" value={query} />
                                <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400">
                                    {searching ? <Spinner label="Mencari pasien" /> : <Search className="size-4" />}
                                </span>
                            </div>
                        </Field>
                        {results.length > 0 && (
                            <ul aria-label="Hasil pencarian pasien" className="max-h-72 divide-y divide-neutral-100 overflow-y-auto rounded-xl border border-neutral-200">
                                {results.map((patient) => (
                                    <li key={patient.id}>
                                        <Button className="h-auto w-full justify-between rounded-none px-4 py-3 text-left font-normal hover:bg-neutral-50/60 hover:text-neutral-900 focus-visible:bg-neutral-50/60" onClick={() => selectPatient(patient)} type="button" variant="ghost">
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-semibold text-neutral-900">{patient.nama}</span>
                                                <span className="mt-0.5 block text-xs text-neutral-500">{patient.no_rm} · {patient.jenis_kelamin === 'L' ? 'Laki-laki' : patient.jenis_kelamin === 'P' ? 'Perempuan' : '—'}</span>
                                            </span>
                                            <span className="shrink-0 text-xs font-medium text-neutral-700">Pilih pasien <span aria-hidden="true">→</span></span>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {query.trim().length >= 2 && !searching && !searchError && results.length === 0 && (
                            <Empty className="min-h-0 py-6" description="Periksa kembali nama, nomor rekam medis, atau NIK yang Anda masukkan." title="Pasien tidak ditemukan" />
                        )}
                        {query.trim().length > 0 && query.trim().length < 2 && (
                            <p className="text-xs text-neutral-500">Masukkan minimal 2 karakter untuk mulai mencari.</p>
                        )}
                        {selectedPatient && (
                            <div className="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 items-center justify-center rounded-full bg-surface text-neutral-700"><UserRound className="size-5" /></span>
                                        <div>
                                            <p className="text-xs font-medium uppercase tracking-wide text-neutral-700">Pasien terpilih</p>
                                            <p className="mt-0.5 font-semibold text-neutral-950">{selectedPatient.nama}</p>
                                            <p className="mt-0.5 text-sm text-neutral-600">{selectedPatient.no_rm} · {selectedPatient.jenis_kelamin === 'L' ? 'Laki-laki' : selectedPatient.jenis_kelamin === 'P' ? 'Perempuan' : '—'}</p>
                                            <p className="mt-1 text-xs text-neutral-500">Telepon: {selectedPatient.telepon || 'Belum tersedia'}</p>
                                        </div>
                                    </div>
                                    <Button aria-label="Ganti pasien" onClick={clearPatient} size="icon" type="button" variant="ghost"><X className="size-4" /></Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {selectedPatient && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-start gap-3">
                                <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UserRoundPlus className="size-5" /></span>
                                <div><CardTitle>Data kunjungan baru</CardTitle><CardDescription className="mt-1">Pasien yang sama dapat memiliki banyak kunjungan pada tanggal yang berbeda.</CardDescription></div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <VisitFields
                                clinics={clinics}
                                today={today}
                                errors={form.errors}
                                insuranceProviders={insuranceProviders}
                                onChange={(field, value) => form.setData(field, value)}
                                values={form.data}
                            />
                        </CardContent>
                    </Card>
                )}

                {selectedPatient && (
                    <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                        <Button asChild variant="secondary"><Link href="/pendaftaran">Batal</Link></Button>
                        <Button disabled={form.processing || !form.data.poliklinik_id} type="submit"><Check className="size-4" />{form.processing ? 'Mendaftarkan…' : 'Daftarkan kunjungan'}</Button>
                    </div>
                )}
            </form>
        </>
    );
}
