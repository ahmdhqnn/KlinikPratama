import { SearchResultsSkeleton } from '@/components/loading-skeletons';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useForm } from '@inertiajs/react';
import { Check, Search, UserRound, UserRoundPlus, X } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { VisitFields, type VisitFormValues } from '@/components/pendaftaran/visit-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

interface Option {
    id: number;
    name: string;
    type?: string;
}

interface PatientForm extends VisitFormValues {
    pasien_id: string;
    rujukan_internal_id?: number;
}

export interface PatientResult {
    id: number;
    no_rm: string;
    nama: string;
    jenis_kelamin: string | null;
    tanggal_lahir: string | null;
    tanggal_lahir_tampil: string | null;
    umur: number | null;
    telepon: string | null;
    eligibilityReason: string | null;
    generalConsentSignedAt?: string | null;
    nik: string | null;
    nip: string | null;
    tempat_lahir: string | null;
    golongan_darah: string | null;
    agama: string | null;
    alamat: string | null;
    rt: string | null;
    rw: string | null;
    kelurahan: string | null;
    kecamatan: string | null;
    unit_kerja: string | null;
    membership: string;
}

export interface InternalReferralRegistration { id: number; number: string | null; clinicId: number }

interface Props {
    internalReferral?: InternalReferralRegistration | null;
    initialPatient?: PatientResult | null;
    clinics: Option[];
    today: string;
    doctorsUrl: string;
}

export function RegisteredPatientForm({ clinics, today, doctorsUrl, initialPatient = null, internalReferral }: Props) {
    const form = useForm<PatientForm>({
        ...(internalReferral ? { rujukan_internal_id: internalReferral.id } : {}),
        pasien_id: initialPatient?.id.toString() ?? '', poliklinik_id: (internalReferral?.clinicId ?? clinics.find((clinic) => clinic.type === 'umum')?.id)?.toString() ?? '', dokter_id: '', tanggal: today, catatan: '',
    });
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<PatientResult[]>([]);
    const [selectedPatient, setSelectedPatient] = useState<PatientResult | null>(initialPatient);
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
        setResults([]);
        setSearching(true);
        setSearchError('');
        const timeout = window.setTimeout(async () => {
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

                const patients: PatientResult[] = await response.json();
                if (!abortController.signal.aborted) {
                    setResults(patients);
                }
            } catch (error) {
                if (!abortController.signal.aborted) {
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
        form.setData((data) => ({ ...data, pasien_id: String(patient.id), catatan: '' }));
        setQuery('');
        setResults([]);
        form.clearErrors();
    }

    function clearPatient() {
        setSelectedPatient(null);
        form.setData((data) => ({ ...data, pasien_id: '', catatan: '' }));
        setQuery('');
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/pendaftaran/pendaftaran-lama');
    }

    return (
        <>
            <form className="space-y-6" onSubmit={submit}>
                {internalReferral && <Alert dismissLabel="Tutup informasi rujukan"><AlertDescription>Rujukan {internalReferral.number ?? 'internal'}. Pilih tanggal dan dokter yang praktik di poli tujuan.</AlertDescription></Alert>}
                {form.errors.rujukan_internal_id && <Alert key={form.errors.rujukan_internal_id} autoDismissMs={12000} variant="destructive"><AlertDescription>{form.errors.rujukan_internal_id}</AlertDescription></Alert>}
                {selectedPatient?.eligibilityReason ? (
                    <Alert variant="destructive"><AlertDescription>{selectedPatient.eligibilityReason}</AlertDescription></Alert>
                ) : selectedPatient && (
                    <Alert dismissLabel="Tutup informasi pasien terpilih" key={selectedPatient.id} variant="success">
                        <AlertDescription>Pasien dipilih. Lengkapi tujuan dan tanggal kunjungan.</AlertDescription>
                    </Alert>
                )}
                <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Search className="size-5" /></span>
                            <div><CardTitle>{internalReferral ? 'Pasien rujukan' : 'Cari pasien'}</CardTitle>{!internalReferral && <CardDescription className="mt-1">Cari berdasarkan nama, nomor rekam medis, NIK, atau NIP.</CardDescription>}</div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {!internalReferral && <>
                        <Field error={form.errors.pasien_id ?? searchError} htmlFor="patient-search" label="Cari pasien" required>
                            <div className="relative">
                                <Input autoComplete="off" className="pr-10" id="patient-search" maxLength={100} onChange={(event) => {
                                    setQuery(event.target.value);
                                    setSelectedPatient(null);
                                    form.setData((data) => ({ ...data, pasien_id: '', catatan: '' }));
                                }} placeholder="Cari nama, No. RM, NIK, atau NIP" value={query} />
                                <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-neutral-500">
                                    <Search aria-hidden="true" className="size-4" />
                                </span>
                            </div>
                        </Field>
                        {searching && <SearchResultsSkeleton label="Mencari pasien…" />}
                        {!searching && results.length > 0 && (
                            <ul aria-label="Hasil pencarian pasien" className="max-h-72 divide-y divide-neutral-100 overflow-y-auto rounded-xl border border-neutral-200">
                                {results.map((patient) => (
                                    <li key={patient.id}>
                                        <Button className="h-auto w-full justify-between rounded-none px-4 py-3 text-left font-normal hover:bg-neutral-50/60 hover:text-neutral-900 focus-visible:bg-neutral-50/60" onClick={() => selectPatient(patient)} type="button" variant="ghost">
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-semibold text-neutral-900">{patient.nama}</span>
                                                <span className="mt-0.5 block text-xs text-neutral-500">{patient.no_rm} · {patient.jenis_kelamin === 'L' ? 'Laki-laki' : patient.jenis_kelamin === 'P' ? 'Perempuan' : 'Belum diisi'}</span>
                                            </span>
                                            <span className="shrink-0 text-xs font-medium text-neutral-700">Pilih pasien <span aria-hidden="true">→</span></span>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {query.trim().length >= 2 && !searching && !searchError && results.length === 0 && (
                            <Empty className="min-h-0 py-6" description="Periksa kembali nama, nomor rekam medis, NIK, atau NIP yang Anda masukkan." title="Pasien tidak ditemukan" />
                        )}
                        {query.trim().length > 0 && query.trim().length < 2 && (
                            <p className="text-xs text-neutral-500">Masukkan minimal 2 karakter untuk mulai mencari.</p>
                        )}
                        </>}
                        {selectedPatient && (
                            <div className="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 items-center justify-center rounded-full bg-surface text-neutral-700"><UserRound className="size-5" /></span>
                                        <div>
                                            <p className="text-xs font-medium uppercase tracking-wide text-neutral-700">Pasien terpilih</p>
                                            <p className="mt-0.5 font-semibold text-neutral-950">{selectedPatient.nama}</p>
                                            <p className="mt-0.5 text-sm text-neutral-600">{selectedPatient.no_rm} · {selectedPatient.jenis_kelamin === 'L' ? 'Laki-laki' : selectedPatient.jenis_kelamin === 'P' ? 'Perempuan' : 'Belum diisi'}</p>
                                            <p className="mt-1 text-xs text-neutral-500">Telepon: {selectedPatient.telepon || 'Belum tersedia'}</p>
                                        </div>
                                    </div>
                                    {!internalReferral && <Button aria-label="Ganti pasien" onClick={clearPatient} size="icon" type="button" variant="ghost"><X className="size-4" /></Button>}
                                </div>
                                <RegisteredPatientIdentity patient={selectedPatient} />
                                {selectedPatient.generalConsentSignedAt ? <p className="inline-flex w-fit rounded-full border border-green-300 px-3 py-1 text-xs font-medium text-green-800 dark:border-green-800 dark:text-green-300">
                                    {`Persetujuan Umum aktif · Ditandatangani ${selectedPatient.generalConsentSignedAt}`}
                                </p> : selectedPatient.generalConsentSignedAt === null && <p className="text-sm text-neutral-600">
                                    Persetujuan Umum aktif belum tercatat. Tinjau statusnya sebelum pengkajian dikirim.
                                </p>}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {selectedPatient && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-start gap-3">
                                <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><UserRoundPlus className="size-5" /></span>
                                <div><CardTitle>Data kunjungan baru</CardTitle><CardDescription className="mt-1">Daftarkan kunjungan baru untuk pasien yang telah terdaftar.</CardDescription></div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <VisitFields
                                clinics={clinics}
                                clinicLocked={Boolean(internalReferral)}
                                today={today}
                                doctorsUrl={doctorsUrl}
                                errors={form.errors}
                                onChange={(fields) => form.setData((data) => ({ ...data, ...fields }))}
                                values={form.data}
                            />
                        </CardContent>
                    </Card>
                )}

                {selectedPatient && (
                    <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                        <Button disabled={form.processing || Boolean(selectedPatient.eligibilityReason)} type="submit"><Check className="size-4" />{form.processing ? 'Mendaftarkan…' : internalReferral ? 'Daftarkan ke poli tujuan' : 'Daftarkan kunjungan'}</Button>
                    </div>
                )}
            </form>
        </>
    );
}

export function RegisteredPatientIdentity({ patient }: { patient: PatientResult }) {
    const fields = [
        ['NIK', patient.nik], ['NIP', patient.nip],
        ['Jenis kelamin', patient.jenis_kelamin === 'L' ? 'Laki-laki' : patient.jenis_kelamin === 'P' ? 'Perempuan' : null],
        ['Tempat, tanggal lahir', [patient.tempat_lahir, patient.tanggal_lahir_tampil || patient.tanggal_lahir].filter(Boolean).join(', ') + (patient.umur === null || patient.umur === undefined ? '' : ` (${patient.umur} tahun)`)],
        ['Golongan darah', patient.golongan_darah],
        ['Agama', patient.agama], ['Nomor telepon', patient.telepon],
        ['Kepesertaan', patient.membership], ['Unit kerja', patient.unit_kerja], ['Alamat', patient.alamat],
    ];

    return <dl className="mt-4 grid gap-4 border-t border-neutral-200 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        {fields.map(([label, value]) => <div className="min-w-0" key={label}><dt className="text-xs text-neutral-500">{label}</dt><dd className="mt-1 break-words text-sm font-medium text-neutral-900">{value || 'Belum diisi'}</dd></div>)}
    </dl>;
}
