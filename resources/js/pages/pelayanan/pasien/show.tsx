import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { AllergyHistory } from '@/components/kunjungan/allergy-history';
import {
    DocumentArchiveModal,
    DocumentFileIcon,
    DocumentPreviewModal,
    DocumentUploadModal,
    formatDocumentSize,
    type DocumentPermissions,
    type PatientDocumentItem,
} from '@/components/pasien/document-uploader-modal';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    Archive,
    ArrowRightLeft,
    ArrowUpRight,
    CalendarCheck2,
    CalendarPlus,
    ChevronRight,
    ClipboardCheck,
    ClipboardList,
    Download,
    Eye,
    FileCheck2,
    FilePlus2,
    FileText,
    FolderOpen,
    Paperclip,
    Pencil,
    Pill,
    ShieldCheck,
    Stethoscope,
    UserRound,
} from 'lucide-react';
import { useEffect, useState, type ComponentType, type FormEvent } from 'react';

interface Action { label: string; url: string | null }
interface Visit {
    id: number;
    number: string;
    date: string | null;
    time?: string | null;
    clinic: string;
    room?: string | null;
    complaint?: string | null;
    visitType?: string;
    doctor: string;
    status: string;
    examinationStarted: boolean;
    isReservation: boolean;
    actions: Action[];
}
interface Patient {
    id: number;
    medicalRecordNumber: string;
    name: string;
    birthDate: string | null;
    gender: string | null;
    age: number | null;
    allergies: string | null;
    nik?: string | null;
    memberNip?: string | null;
    unit?: string | null;
    birthPlace?: string | null;
    bloodType?: string | null;
    religion?: string | null;
    phone?: string | null;
    guardianName?: string | null;
    guardianPhone?: string | null;
    address?: string | null;
    rt?: string | null;
    rw?: string | null;
    village?: string | null;
    district?: string | null;
    postalCode?: string | null;
    membership?: string;
    membershipActive?: boolean;
    visits: Visit[];
}
interface DirectRecord {
    id: number;
    number: string;
    date: string | null;
    medicine: string | null;
    quantity: number;
    unit: string | null;
    url: string;
}
interface ClinicalNoteSummary {
    visitId: number;
    visitNumber: string;
    date: string | null;
    clinic: string;
    doctor: string;
    title: string;
    signed: boolean;
    detailUrl: string;
}
interface AdminSummary {
    consentStatus: string;
    consentRecordedAt: string | null;
    consentUrl: string | null;
    openReferrals: number;
    referralsUrl: string;
    releaseCount: number;
    releasesUrl: string | null;
}
interface Props {
    patient: Patient;
    section: string;
    menu: { id: string; label: string; url: string }[];
    backUrl: string;
    editUrl: string | null;
    openVisitUrl: string | null;
    recordUrl: string | null;
    pagination: PaginationData | null;
    directRecords: DirectRecord[];
    generalConsent: { status: string; recordedAt: string | null; versionCurrent: boolean } | null;
    visitTab?: 'all' | 'upcoming';
    upcomingCount?: number;
    visitFilters?: { poli: string; dokter?: string; jenis?: string; status: string; tahun: string };
    visitFilterOptions?: { clinics: { id: number; name: string }[]; doctors?: { id: number; name: string }[]; years: string[] };
    documents?: PatientDocumentItem[];
    documentsTotal?: number;
    documentsPagination?: PaginationData | null;
    documentFilters?: { search?: string; category: string; archived: boolean };
    documentPermissions?: DocumentPermissions | null;
    clinicalNotes?: ClinicalNoteSummary[];
    adminSummary?: AdminSummary | null;
    permissions?: { viewRme: boolean; editPatient: boolean; openVisit: boolean; isPharmacy?: boolean };
}

const descriptions: Record<string, string> = {
    kontrol: 'Reservasi kontrol dan kunjungan yang ditautkan ke pelayanan sebelumnya.',
    'catatan-medis': 'Pemeriksaan dokter, diagnosis, rencana perawatan, dan riwayat koreksi per kunjungan.',
    pengkajian: 'Pengkajian awal dan tanda vital yang dicatat saat kunjungan.',
    odontogram: 'Kunjungan dengan temuan odontogram yang tercatat.',
    penunjang: 'Hasil penunjang dari pemeriksaan dan lampiran yang tersimpan pada kunjungan.',
    tindakan: 'Rencana dan catatan pelaksanaan tindakan per kunjungan.',
    obat: 'Buka kunjungan untuk melihat catatan obat sesuai kewenangan.',
    persetujuan: 'Persetujuan umum pasien serta persetujuan atau penolakan tindakan tertentu.',
    pelepasan: 'Register permintaan, otorisasi, dan penyerahan informasi medis per kunjungan.',
    dokumen: 'Berkas eksternal pasien (hasil laboratorium, radiologi, RME lama, surat rujukan, atau identitas).',
};

const consentLabels: Record<string, string> = {
    undocumented: 'Belum terdokumentasi',
    accepted: 'Disetujui',
    refused: 'Ditolak',
    withdrawn: 'Dicabut',
    invalid: 'Perlu verifikasi integritas',
};

const sectionIcons: Record<string, ComponentType<{ className?: string }>> = {
    informasi: UserRound,
    kunjungan: CalendarCheck2,
    kontrol: CalendarPlus,
    'catatan-medis': ClipboardList,
    pengkajian: Activity,
    odontogram: Stethoscope,
    penunjang: Paperclip,
    tindakan: ClipboardCheck,
    obat: Pill,
    'obat-langsung': Pill,
    persetujuan: ShieldCheck,
    surat: FileCheck2,
    rujukan: ArrowRightLeft,
    pelepasan: FileText,
    dokumen: FolderOpen,
};

function initialsFor(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'PS';
}

function hasRecordedAllergy(allergies: string | null): boolean {
    if (!allergies) {
        return false;
    }
    const normalized = allergies.trim().toLowerCase();

    return normalized !== '' && !['belum dicatat', 'tidak ada', 'tidak ada alergi', 'nihil', '-'].includes(normalized);
}

export default function PatientDetail({
    patient,
    section,
    menu,
    backUrl,
    editUrl,
    openVisitUrl,
    recordUrl,
    pagination,
    directRecords,
    generalConsent,
    visitTab = 'all',
    upcomingCount = 0,
    visitFilters = { poli: '', dokter: '', jenis: '', status: '', tahun: '' },
    visitFilterOptions = { clinics: [], doctors: [], years: [] },
    documents = [],
    documentsTotal = 0,
    documentsPagination = null,
    documentFilters = { search: '', category: '', archived: false },
    documentPermissions = null,
    clinicalNotes = [],
    adminSummary = null,
    permissions,
}: Props) {
    const [uploadOpen, setUploadOpen] = useState(false);
    const [previewDoc, setPreviewDoc] = useState<PatientDocumentItem | null>(null);
    const [archiveDoc, setArchiveDoc] = useState<PatientDocumentItem | null>(null);
    const [visitDraft, setVisitDraft] = useState({
        poli: visitFilters.poli ?? '',
        dokter: visitFilters.dokter ?? '',
        jenis: visitFilters.jenis ?? '',
        status: visitFilters.status ?? '',
        tahun: visitFilters.tahun ?? '',
    });

    useEffect(() => {
        setVisitDraft({
            poli: visitFilters.poli ?? '',
            dokter: visitFilters.dokter ?? '',
            jenis: visitFilters.jenis ?? '',
            status: visitFilters.status ?? '',
            tahun: visitFilters.tahun ?? '',
        });
    }, [visitFilters.poli, visitFilters.dokter, visitFilters.jenis, visitFilters.status, visitFilters.tahun]);

    const overview = section === 'informasi';
    const history = menu.find((item) => item.id === 'kunjungan' || item.id === 'obat');
    const documentsMenu = menu.find((item) => item.id === 'dokumen');
    const notesMenu = menu.find((item) => item.id === 'catatan-medis');
    const title = menu.find((item) => item.id === section)?.label ?? 'Profil pasien';
    const gender = patient.gender === 'L' ? 'Laki-laki' : patient.gender === 'P' ? 'Perempuan' : null;
    const canSeeClinical = Boolean(permissions?.viewRme);
    const isPharmacy = Boolean(permissions?.isPharmacy);
    const hasAllergyAlert = hasRecordedAllergy(patient.allergies);
    const visitDoctors = visitFilterOptions.doctors ?? [];
    const SectionIcon = sectionIcons[section] ?? CalendarCheck2;
    const visitCardTitle = overview || section === 'kunjungan'
        ? (visitTab === 'upcoming' ? 'Kunjungan mendatang' : 'Riwayat kunjungan')
        : title;
    const visitCardDescription = overview || section === 'kunjungan'
        ? (visitTab === 'upcoming'
            ? `${new Intl.NumberFormat('id-ID').format(upcomingCount)} jadwal kunjungan mendatang atau reservasi aktif.`
            : `${new Intl.NumberFormat('id-ID').format(pagination?.total ?? patient.visits.length)} kunjungan tercatat sesuai filter.`)
        : (descriptions[section] ?? 'Catatan layanan pasien pada bagian ini.');

    function applyVisitQuery(next: Partial<{ visit_tab: string; visit_poli: string; visit_dokter: string; visit_jenis: string; visit_status: string; visit_tahun: string }>) {
        const params: Record<string, string> = {};
        if (section !== 'informasi') {
            params.bagian = section;
        }
        const merged = {
            visit_tab: next.visit_tab ?? visitTab,
            visit_poli: next.visit_poli ?? visitDraft.poli,
            visit_dokter: next.visit_dokter ?? visitDraft.dokter,
            visit_jenis: next.visit_jenis ?? visitDraft.jenis,
            visit_status: next.visit_status ?? visitDraft.status,
            visit_tahun: next.visit_tahun ?? visitDraft.tahun,
        };
        if (merged.visit_tab === 'upcoming') {
            params.visit_tab = 'upcoming';
        }
        if (merged.visit_poli) {
            params.visit_poli = merged.visit_poli;
        }
        if (merged.visit_dokter) {
            params.visit_dokter = merged.visit_dokter;
        }
        if (merged.visit_jenis) {
            params.visit_jenis = merged.visit_jenis;
        }
        if (merged.visit_status) {
            params.visit_status = merged.visit_status;
        }
        if (merged.visit_tahun) {
            params.visit_tahun = merged.visit_tahun;
        }
        router.get(`/pelayanan/pasien/${patient.id}`, params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function submitVisitFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        applyVisitQuery({
            visit_poli: visitDraft.poli,
            visit_dokter: visitDraft.dokter,
            visit_jenis: visitDraft.jenis,
            visit_status: visitDraft.status,
            visit_tahun: visitDraft.tahun,
        });
    }

    function applyDocumentQuery(next: { doc_search: string; doc_category: string; doc_archived: boolean }) {
        const params: Record<string, string> = { bagian: 'dokumen' };
        if (next.doc_search.trim()) {
            params.doc_search = next.doc_search.trim();
        }
        if (next.doc_category) {
            params.doc_category = next.doc_category;
        }
        if (next.doc_archived) {
            params.doc_archived = '1';
        }
        router.get(`/pelayanan/pasien/${patient.id}`, params, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title={`Profil ${patient.name}`} />
            <div className="min-w-0 space-y-5">
                <PageHeaderWithActions
                    actions={
                        <>
                            {editUrl && (
                                <Button asChild size="sm" variant="secondary">
                                    <Link href={editUrl}><Pencil className="size-4" />Edit data</Link>
                                </Button>
                            )}
                            {openVisitUrl && (
                                <Button asChild size="sm">
                                    <Link href={openVisitUrl}><CalendarPlus className="size-4" />Daftarkan kunjungan</Link>
                                </Button>
                            )}
                            {recordUrl && (
                                <Button asChild size="sm" variant="secondary">
                                    <Link href={recordUrl}><ClipboardList className="size-4" />Rekam medis lengkap</Link>
                                </Button>
                            )}
                        </>
                    }
                    backHref={backUrl}
                    backLabel="Daftar pasien"
                    eyebrow="Profil pasien"
                    subtitle={[`No. RM ${patient.medicalRecordNumber}`, gender, patient.age === null ? null : `${patient.age} tahun`].filter(Boolean).join(' · ')}
                    title={patient.name}
                />

                {patient.allergies !== null && <AllergyHistory value={patient.allergies} />}

                <div className="grid min-w-0 items-start gap-6 lg:grid-cols-[minmax(19rem,30%)_minmax(0,1fr)]">
                    {/* Left Identity & Quick Navigation Panel */}
                    <aside className="min-w-0 space-y-4">
                        <Card className="overflow-hidden">
                            <CardContent className="space-y-4 pt-5 sm:pt-6">
                                <div className="flex items-start gap-3.5">
                                    <Avatar className="size-12 shrink-0 border border-neutral-200 bg-neutral-100">
                                        <AvatarFallback className="bg-neutral-100 text-sm font-semibold text-neutral-800">
                                            {initialsFor(patient.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-base font-semibold text-neutral-950">{patient.name}</p>
                                        <p className="mt-0.5 text-xs font-medium tabular-nums text-neutral-500">
                                            No. RM {patient.medicalRecordNumber}
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {patient.membership !== undefined && (
                                                <Badge variant={patient.membershipActive ? 'success' : 'secondary'}>
                                                    {patient.membershipActive ? 'Peserta aktif' : patient.membership}
                                                </Badge>
                                            )}
                                            {hasAllergyAlert && <Badge variant="destructive">Riwayat alergi</Badge>}
                                        </div>
                                    </div>
                                </div>
                                <div className="border-t border-neutral-200 pt-3">
                                    <nav aria-label="Menu profil pasien" className="grid grid-cols-2 gap-1 sm:grid-cols-3 lg:flex lg:flex-col">
                                        {menu.map((item) => {
                                            const Icon = sectionIcons[item.id] ?? FileText;
                                            const active = section === item.id;

                                            return (
                                                <Link
                                                    key={item.id}
                                                    aria-current={active ? 'page' : undefined}
                                                    href={item.url}
                                                    className={cn(
                                                        'flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-sm outline-none transition-colors focus-visible:ring-2 focus-visible:ring-neutral-400 focus-visible:ring-inset',
                                                        active
                                                            ? 'bg-neutral-100 font-semibold text-neutral-950'
                                                            : 'text-neutral-600 hover:bg-neutral-100/70 hover:text-neutral-950',
                                                    )}
                                                >
                                                    <span className="flex min-w-0 items-center gap-2.5">
                                                        <Icon aria-hidden="true" className="size-4 shrink-0 text-neutral-500" />
                                                        <span className="truncate">{item.label}</span>
                                                    </span>
                                                    <ChevronRight aria-hidden="true" className="hidden size-3.5 shrink-0 text-neutral-400 lg:block" />
                                                </Link>
                                            );
                                        })}
                                    </nav>
                                </div>
                            </CardContent>
                        </Card>

                        {!isPharmacy && <GeneralInfoCard gender={gender} patient={patient} />}
                        {!isPharmacy && patient.address !== undefined && <ContactInfoCard patient={patient} />}
                    </aside>

                    {/* Right Primary Workspace */}
                    <section aria-label={title} className="min-w-0 space-y-5">
                        {generalConsent && (
                            <Card>
                                <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-5 sm:pt-6">
                                    <div>
                                        <p className="text-sm font-semibold text-neutral-950">Keputusan persetujuan umum terakhir</p>
                                        {generalConsent.recordedAt && (
                                            <p className="mt-1 text-xs text-neutral-500">
                                                {generalConsent.recordedAt}{!generalConsent.versionCurrent && ' · Versi penjelasan perlu diperbarui'}
                                            </p>
                                        )}
                                    </div>
                                    <Badge variant={generalConsent.status === 'accepted' ? 'success' : generalConsent.status === 'refused' ? 'destructive' : 'secondary'}>
                                        {consentLabels[generalConsent.status] ?? 'Status belum diketahui'}
                                    </Badge>
                                </CardContent>
                            </Card>
                        )}

                        {section === 'dokumen' ? (
                            <DocumentsSectionCard
                                documents={documents}
                                filters={documentFilters}
                                onArchive={setArchiveDoc}
                                onFilterChange={applyDocumentQuery}
                                onOpenUpload={() => setUploadOpen(true)}
                                onPreview={setPreviewDoc}
                                pagination={documentsPagination}
                                permissions={documentPermissions}
                                total={documentsTotal}
                            />
                        ) : section === 'obat-langsung' ? (
                            <Card className="min-w-0 overflow-hidden">
                                <CardHeader className="border-b border-neutral-100">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">
                                            <Pill aria-hidden="true" className="size-5" />
                                        </span>
                                        <div className="min-w-0">
                                            <CardTitle>{title}</CardTitle>
                                            <CardDescription className="mt-1">Catatan pemberian obat mandiri tanpa kunjungan poliklinik.</CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3 p-5 sm:p-6">
                                    {directRecords.length ? directRecords.map((item) => (
                                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-neutral-200 p-3" key={item.id}>
                                            <div>
                                                <p className="text-sm font-medium text-neutral-950">{item.medicine ?? 'Obat belum tercatat'} · {item.quantity} {item.unit}</p>
                                                <p className="text-xs tabular-nums text-neutral-500">{item.number} · {item.date ?? 'Tanggal belum tercatat'}</p>
                                            </div>
                                            <Button asChild size="sm" variant="secondary"><Link href={item.url}>Lihat bukti</Link></Button>
                                        </div>
                                    )) : <Empty size="compact" title="Belum ada pemberian obat langsung." />}
                                </CardContent>
                                {pagination && <Pagination pagination={pagination} />}
                            </Card>
                        ) : (
                            <div className="min-w-0 space-y-4">
                                {(overview || section === 'kunjungan') && (
                                    <Tabs
                                        className="w-full"
                                        onValueChange={(value) => applyVisitQuery({ visit_tab: value })}
                                        value={visitTab}
                                    >
                                        <TabsList aria-label="Filter tab kunjungan" className="h-auto w-full flex-wrap">
                                            <TabsTrigger value="upcoming">
                                                Kunjungan mendatang
                                                {upcomingCount > 0 && (
                                                    <span className="ml-1.5 inline-flex items-center rounded-full bg-neutral-200/80 px-1.5 py-0.5 text-xs tabular-nums text-neutral-700">
                                                        {upcomingCount}
                                                    </span>
                                                )}
                                            </TabsTrigger>
                                            <TabsTrigger value="all">Semua riwayat kunjungan</TabsTrigger>
                                        </TabsList>
                                    </Tabs>
                                )}

                                {(overview || section === 'kunjungan') && (
                                    <FilterPanel
                                        ariaLabel="Filter riwayat kunjungan"
                                        contextKeys={['bagian', 'visit_tab']}
                                        filterKeys={['visit_poli', 'visit_dokter', 'visit_jenis', 'visit_status', 'visit_tahun']}
                                    >
                                        <form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" onSubmit={submitVisitFilters}>
                                            {visitFilterOptions.clinics.length > 0 && (
                                                <Field htmlFor="patient-visit-poli" label="Poliklinik">
                                                    <Select
                                                        id="patient-visit-poli"
                                                        onChange={(event) => setVisitDraft({ ...visitDraft, poli: event.target.value })}
                                                        value={visitDraft.poli}
                                                    >
                                                        <option value="">Semua poliklinik</option>
                                                        {visitFilterOptions.clinics.map((clinic) => (
                                                            <option key={clinic.id} value={String(clinic.id)}>{clinic.name}</option>
                                                        ))}
                                                    </Select>
                                                </Field>
                                            )}
                                            {visitDoctors.length > 0 && (
                                                <Field htmlFor="patient-visit-doctor" label="Dokter">
                                                    <Select
                                                        id="patient-visit-doctor"
                                                        onChange={(event) => setVisitDraft({ ...visitDraft, dokter: event.target.value })}
                                                        value={visitDraft.dokter}
                                                    >
                                                        <option value="">Semua dokter</option>
                                                        {visitDoctors.map((doctor) => (
                                                            <option key={doctor.id} value={String(doctor.id)}>{doctor.name}</option>
                                                        ))}
                                                    </Select>
                                                </Field>
                                            )}
                                            <Field htmlFor="patient-visit-type" label="Jenis kunjungan">
                                                <Select
                                                    id="patient-visit-type"
                                                    onChange={(event) => setVisitDraft({ ...visitDraft, jenis: event.target.value })}
                                                    value={visitDraft.jenis}
                                                >
                                                    <option value="">Semua jenis</option>
                                                    <option value="baru">Baru</option>
                                                    <option value="lama">Lama</option>
                                                    <option value="kontrol">Kontrol</option>
                                                    <option value="rujukan_internal">Rujukan internal</option>
                                                </Select>
                                            </Field>
                                            <Field htmlFor="patient-visit-status" label="Status kunjungan">
                                                <Select
                                                    id="patient-visit-status"
                                                    onChange={(event) => setVisitDraft({ ...visitDraft, status: event.target.value })}
                                                    value={visitDraft.status}
                                                >
                                                    <option value="">Semua status</option>
                                                    <option value="menunggu">Menunggu</option>
                                                    <option value="screening">Pengkajian</option>
                                                    <option value="pemeriksaan">Pemeriksaan</option>
                                                    <option value="tindakan">Tindakan</option>
                                                    <option value="farmasi">Farmasi</option>
                                                    <option value="selesai">Selesai</option>
                                                    <option value="batal">Batal</option>
                                                </Select>
                                            </Field>
                                            {visitFilterOptions.years.length > 0 && (
                                                <Field htmlFor="patient-visit-year" label="Tahun kunjungan">
                                                    <Select
                                                        id="patient-visit-year"
                                                        onChange={(event) => setVisitDraft({ ...visitDraft, tahun: event.target.value })}
                                                        value={visitDraft.tahun}
                                                    >
                                                        <option value="">Semua tahun</option>
                                                        {visitFilterOptions.years.map((year) => (
                                                            <option key={year} value={year}>{year}</option>
                                                        ))}
                                                    </Select>
                                                </Field>
                                            )}
                                            <FilterSubmit />
                                        </form>
                                    </FilterPanel>
                                )}

                                {section === 'catatan-medis' && recordUrl && (
                                    <div className="flex flex-wrap items-center justify-end gap-2">
                                        <Button asChild size="sm" variant="secondary">
                                            <Link href={recordUrl}><ClipboardList className="size-4" />Rekam medis lengkap</Link>
                                        </Button>
                                    </div>
                                )}

                                <Card className="min-w-0 overflow-hidden">
                                    <CardHeader className="border-b border-neutral-100">
                                        <div className="flex items-start gap-3">
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">
                                                <SectionIcon aria-hidden="true" className="size-5" />
                                            </span>
                                            <div className="min-w-0">
                                                <CardTitle>{visitCardTitle}</CardTitle>
                                                <CardDescription className="mt-1">{visitCardDescription}</CardDescription>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        <VisitHistory canSeeClinical={canSeeClinical} visits={patient.visits} />
                                        {pagination && <Pagination pagination={pagination} />}
                                    </CardContent>
                                    {overview && history && (
                                        <div className="flex justify-end border-t border-neutral-100 p-4">
                                            <Button asChild size="sm" variant="secondary">
                                                <Link href={history.url}>Lihat semua kunjungan<ArrowUpRight className="size-4" /></Link>
                                            </Button>
                                        </div>
                                    )}
                                </Card>
                            </div>
                        )}

                        {overview && !isPharmacy && (
                            <div className="min-w-0 space-y-5">
                                {/* Patient Documents Section (Full Width) */}
                                <div className="min-w-0 space-y-3">
                                    {(documentPermissions?.canUpload || documentsMenu) && (
                                        <div className="flex flex-wrap items-center justify-end gap-2">
                                            {documentsMenu && (
                                                <Button asChild size="sm" variant="secondary">
                                                    <Link href={documentsMenu.url}>
                                                        Lihat semua dokumen{documentsTotal > 0 ? ` (${documentsTotal})` : ''}
                                                    </Link>
                                                </Button>
                                            )}
                                            {documentPermissions?.canUpload && (
                                                <Button onClick={() => setUploadOpen(true)} size="sm">
                                                    <FilePlus2 className="size-4" />
                                                    Tambah dokumen
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                    <Card className="min-w-0 overflow-hidden">
                                        <CardHeader className="border-b border-neutral-100">
                                            <div className="flex items-start gap-3">
                                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">
                                                    <FolderOpen aria-hidden="true" className="size-5" />
                                                </span>
                                                <div className="min-w-0">
                                                    <CardTitle>Dokumen pasien</CardTitle>
                                                    <CardDescription className="mt-1">Berkas eksternal, RME sebelumnya, penunjang, & arsip identitas ({new Intl.NumberFormat('id-ID').format(documentsTotal)} dokumen aktif).</CardDescription>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="p-5 sm:p-6">
                                            {documents.length > 0 ? (
                                                <ul className="divide-y divide-neutral-200">
                                                    {documents.map((doc) => (
                                                        <li className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0" key={doc.id}>
                                                            <div className="flex min-w-0 items-center gap-3">
                                                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-neutral-200 bg-neutral-50 text-neutral-600">
                                                                    <DocumentFileIcon mimeType={doc.mimeType} />
                                                                </span>
                                                                <div className="min-w-0">
                                                                    <p className="truncate text-sm font-medium text-neutral-950">{doc.title}</p>
                                                                    <p className="truncate text-xs text-neutral-500">
                                                                        {[doc.categoryLabel, doc.documentDate, formatDocumentSize(doc.fileSize)].filter(Boolean).join(' · ')}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div className="flex shrink-0 items-center gap-1">
                                                                {doc.canDownload && doc.downloadUrl && (
                                                                    <Button asChild size="icon-sm" title="Unduh berkas" variant="ghost">
                                                                        <a aria-label={`Unduh ${doc.title}`} href={doc.downloadUrl}>
                                                                            <Download className="size-4" />
                                                                        </a>
                                                                    </Button>
                                                                )}
                                                                {doc.canPreview && doc.previewUrl && (
                                                                    <Button
                                                                        aria-label={`Pratinjau ${doc.title}`}
                                                                        onClick={() => setPreviewDoc(doc)}
                                                                        size="icon-sm"
                                                                        title="Pratinjau berkas"
                                                                        variant="ghost"
                                                                    >
                                                                        <Eye className="size-4" />
                                                                    </Button>
                                                                )}
                                                                {doc.canArchive && doc.archiveUrl && (
                                                                    <Button
                                                                        aria-label={`Arsipkan ${doc.title}`}
                                                                        onClick={() => setArchiveDoc(doc)}
                                                                        size="icon-sm"
                                                                        title="Arsipkan dokumen"
                                                                        variant="ghost"
                                                                    >
                                                                        <Archive className="size-4" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <Empty size="compact" title="Belum ada dokumen pasien tersimpan." />
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>

                                {/* Below Documents: Clinical Notes (Dokter/Perawat) OR Administrative Summary (Admin/Pendaftaran) */}
                                {canSeeClinical ? (
                                    <div className="min-w-0 space-y-3">
                                        {notesMenu && (
                                            <div className="flex flex-wrap items-center justify-end gap-2">
                                                <Button asChild size="sm" variant="secondary">
                                                    <Link href={notesMenu.url}>Lihat semua catatan medis</Link>
                                                </Button>
                                            </div>
                                        )}
                                        <Card className="min-w-0 overflow-hidden">
                                            <CardHeader className="border-b border-neutral-100">
                                                <div className="flex items-start gap-3">
                                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">
                                                        <ClipboardList aria-hidden="true" className="size-5" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <CardTitle>Catatan medis</CardTitle>
                                                        <CardDescription className="mt-1">Ringkasan pemeriksaan dan diagnosis kronologis</CardDescription>
                                                    </div>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="p-5 sm:p-6">
                                                {clinicalNotes.length > 0 ? (
                                                    <ul className="divide-y divide-neutral-200">
                                                        {clinicalNotes.map((note) => (
                                                            <li className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0" key={note.visitId}>
                                                                <div className="flex min-w-0 items-center gap-3">
                                                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-neutral-200 bg-neutral-50 text-neutral-600">
                                                                        <FileText aria-hidden="true" className="size-4" />
                                                                    </span>
                                                                    <div className="min-w-0">
                                                                        <p className="truncate text-sm font-medium text-neutral-950">{note.title}</p>
                                                                        <p className="truncate text-xs text-neutral-500">
                                                                            {[note.date, note.clinic, note.doctor].filter(Boolean).join(' · ')}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <div className="flex shrink-0 items-center gap-1.5">
                                                                    <Badge variant={note.signed ? 'success' : 'secondary'}>
                                                                        {note.signed ? 'Final' : 'Draf'}
                                                                    </Badge>
                                                                    <Button asChild size="icon-sm" title="Lihat detail catatan" variant="ghost">
                                                                        <Link aria-label={`Lihat catatan ${note.visitNumber}`} href={note.detailUrl}>
                                                                            <Eye className="size-4" />
                                                                        </Link>
                                                                    </Button>
                                                                </div>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                ) : (
                                                    <Empty size="compact" title="Belum ada catatan pemeriksaan medis." />
                                                )}
                                            </CardContent>
                                        </Card>
                                    </div>
                                ) : adminSummary ? (
                                    <Card className="min-w-0 overflow-hidden">
                                        <CardHeader className="border-b border-neutral-100">
                                            <div className="flex items-start gap-3">
                                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">
                                                    <ShieldCheck aria-hidden="true" className="size-5" />
                                                </span>
                                                <div className="min-w-0">
                                                    <CardTitle>Persetujuan & administrasi layanan</CardTitle>
                                                    <CardDescription className="mt-1">Status legal administratif dan koordinasi antar-poli</CardDescription>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="p-5 sm:p-6">
                                            <div className="space-y-3">
                                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 p-3.5 sm:p-4">
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-medium text-neutral-950">Persetujuan umum</p>
                                                        <p className="mt-0.5 text-xs text-neutral-500">
                                                            {adminSummary.consentRecordedAt ? `Dicatat ${adminSummary.consentRecordedAt}` : 'Belum terdokumentasi'}
                                                        </p>
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-2">
                                                        <Badge variant={adminSummary.consentStatus === 'accepted' ? 'success' : adminSummary.consentStatus === 'refused' ? 'destructive' : 'secondary'}>
                                                            {consentLabels[adminSummary.consentStatus] ?? 'Belum dicatat'}
                                                        </Badge>
                                                        {adminSummary.consentUrl && (
                                                            <Button asChild size="icon-sm" title="Buka persetujuan umum" variant="ghost">
                                                                <Link aria-label="Buka persetujuan umum" href={adminSummary.consentUrl}><Eye className="size-4" /></Link>
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 p-3.5 sm:p-4">
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-medium text-neutral-950">Rujukan internal menunggu</p>
                                                        <p className="mt-0.5 text-xs text-neutral-500">Koordinasi pendaftaran antar-poliklinik</p>
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-2">
                                                        <Badge variant={adminSummary.openReferrals > 0 ? 'warning' : 'secondary'}>
                                                            {adminSummary.openReferrals} menunggu
                                                        </Badge>
                                                        <Button asChild size="icon-sm" title="Lihat rujukan internal" variant="ghost">
                                                            <Link aria-label="Lihat rujukan internal" href={adminSummary.referralsUrl}><ChevronRight className="size-4" /></Link>
                                                        </Button>
                                                    </div>
                                                </div>
                                                {adminSummary.releasesUrl && (
                                                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 p-3.5 sm:p-4">
                                                        <div className="min-w-0">
                                                            <p className="text-sm font-medium text-neutral-950">Register pelepasan informasi</p>
                                                            <p className="mt-0.5 text-xs text-neutral-500">Catatan otorisasi & penyerahan dokumen medis</p>
                                                        </div>
                                                        <div className="flex shrink-0 items-center gap-2">
                                                            <Badge variant="secondary">{adminSummary.releaseCount} tercatat</Badge>
                                                            <Button asChild size="icon-sm" title="Lihat register pelepasan" variant="ghost">
                                                                <Link aria-label="Lihat register pelepasan" href={adminSummary.releasesUrl}><ChevronRight className="size-4" /></Link>
                                                            </Button>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ) : null}
                            </div>
                        )}
                    </section>
                </div>
            </div>

            {documentPermissions?.canUpload && (
                <DocumentUploadModal onOpenChange={setUploadOpen} open={uploadOpen} permissions={documentPermissions} />
            )}
            <DocumentPreviewModal document={previewDoc} onOpenChange={(open) => !open && setPreviewDoc(null)} />
            <DocumentArchiveModal document={archiveDoc} onOpenChange={(open) => !open && setArchiveDoc(null)} />
        </>
    );
}

function GeneralInfoCard({ patient, gender }: { patient: Patient; gender: string | null }) {
    const birthDisplay = [patient.birthPlace, patient.birthDate].filter(Boolean).join(', ');
    const rows: [string, string | null | undefined][] = [
        ['Jenis kelamin', gender],
        ['Usia', patient.age !== null ? `${patient.age} tahun` : null],
        ['Tempat, tanggal lahir', birthDisplay || null],
        ['Golongan darah', patient.bloodType],
        ['Agama', patient.religion],
        ['NIK', patient.nik],
        ['Kepesertaan', patient.membership],
        ['NIP', patient.memberNip],
        ['Unit kerja', patient.unit],
    ];

    const visibleRows = rows.filter(([, value]) => value !== undefined);

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-sm font-semibold">Informasi umum</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2.5 pt-0">
                <dl className="divide-y divide-neutral-100 text-sm">
                    {visibleRows.map(([label, value]) => (
                        <div className="flex items-start justify-between gap-3 py-2 first:pt-0 last:pb-0" key={label}>
                            <dt className="shrink-0 text-xs text-neutral-500">{label}</dt>
                            <dd className="min-w-0 text-right text-xs font-medium break-words tabular-nums text-neutral-900">
                                {value || 'Belum dicatat'}
                            </dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
}

function ContactInfoCard({ patient }: { patient: Patient }) {
    const locality = [
        patient.rt && `RT ${patient.rt}`,
        patient.rw && `RW ${patient.rw}`,
        patient.village,
        patient.district,
        patient.postalCode,
    ].filter(Boolean);
    const fullAddress = [patient.address, locality.join(', ')].filter(Boolean).join('\n');
    const emergencyContact = [patient.guardianName, patient.guardianPhone].filter(Boolean).join(' · ');

    const rows: [string, string | null][] = [
        ['Nomor telepon', patient.phone ?? null],
        ['Kontak darurat / wali', emergencyContact || null],
        ['Alamat lengkap', fullAddress || null],
    ];

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-sm font-semibold">Informasi kontak</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2.5 pt-0">
                <dl className="divide-y divide-neutral-100 text-sm">
                    {rows.map(([label, value]) => (
                        <div className="flex items-start justify-between gap-3 py-2 first:pt-0 last:pb-0" key={label}>
                            <dt className="shrink-0 text-xs text-neutral-500">{label}</dt>
                            <dd className="min-w-0 text-right text-xs font-medium break-words whitespace-pre-wrap tabular-nums text-neutral-900">
                                {value || 'Belum dicatat'}
                            </dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
}

function DocumentsSectionCard({
    documents,
    total,
    pagination,
    filters,
    permissions,
    onFilterChange,
    onOpenUpload,
    onPreview,
    onArchive,
}: {
    documents: PatientDocumentItem[];
    total: number;
    pagination: PaginationData | null;
    filters: { search?: string; category: string; archived: boolean };
    permissions: DocumentPermissions | null;
    onFilterChange: (next: { doc_search: string; doc_category: string; doc_archived: boolean }) => void;
    onOpenUpload: () => void;
    onPreview: (doc: PatientDocumentItem) => void;
    onArchive: (doc: PatientDocumentItem) => void;
}) {
    const [draft, setDraft] = useState({
        search: filters.search ?? '',
        category: filters.category ?? '',
        archived: filters.archived,
    });

    useEffect(() => {
        setDraft({
            search: filters.search ?? '',
            category: filters.category ?? '',
            archived: filters.archived,
        });
    }, [filters.search, filters.category, filters.archived]);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        onFilterChange({
            doc_search: draft.search,
            doc_category: draft.category,
            doc_archived: draft.archived,
        });
    }

    return (
        <div className="min-w-0 space-y-3">
            <FilterPanel
                actions={
                    permissions?.canUpload ? (
                        <Button onClick={onOpenUpload} size="sm">
                            <FilePlus2 className="size-4" />
                            Tambah dokumen
                        </Button>
                    ) : undefined
                }
                ariaLabel="Filter dokumen pasien"
                contextKeys={['bagian']}
                filterKeys={['doc_search', 'doc_category', 'doc_archived']}
            >
                <form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" onSubmit={submit}>
                    <Field htmlFor="patient-doc-search" label="Cari dokumen atau penerbit">
                        <Input
                            id="patient-doc-search"
                            onChange={(event) => setDraft({ ...draft, search: event.target.value })}
                            placeholder="Judul dokumen, nama berkas, atau penerbit"
                            value={draft.search}
                        />
                    </Field>
                    {permissions && (
                        <Field htmlFor="patient-doc-category" label="Kategori dokumen">
                            <Select
                                id="patient-doc-category"
                                onChange={(event) => setDraft({ ...draft, category: event.target.value })}
                                value={draft.category}
                            >
                                <option value="">Semua kategori</option>
                                {permissions.allCategories.map((cat) => (
                                    <option key={cat.value} value={cat.value}>{cat.label}</option>
                                ))}
                            </Select>
                        </Field>
                    )}
                    <Field htmlFor="patient-doc-archived" label="Status arsip">
                        <Select
                            id="patient-doc-archived"
                            onChange={(event) => setDraft({ ...draft, archived: event.target.value === '1' })}
                            value={draft.archived ? '1' : '0'}
                        >
                            <option value="0">Dokumen aktif</option>
                            <option value="1">Diarsipkan</option>
                        </Select>
                    </Field>
                    <FilterSubmit />
                </form>
            </FilterPanel>

            <Card className="min-w-0 overflow-hidden">
                <CardHeader className="border-b border-neutral-100">
                    <div className="flex items-start gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">
                            <FolderOpen aria-hidden="true" className="size-5" />
                        </span>
                        <div className="min-w-0">
                            <CardTitle>Dokumen pasien ({new Intl.NumberFormat('id-ID').format(total)})</CardTitle>
                            <CardDescription className="mt-1">{descriptions.dokumen}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <Table className="min-w-[760px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Dokumen</TableHead>
                                    <TableHead>Kategori</TableHead>
                                    <TableHead>Tanggal & penerbit</TableHead>
                                    <TableHead>Pengunggah</TableHead>
                                    <TableHead className="text-center">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {documents.length > 0 ? documents.map((doc) => (
                                    <TableRow key={doc.id}>
                                        <TableCell>
                                            <div className="flex items-start gap-2.5">
                                                <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg border border-neutral-200 bg-neutral-50 text-neutral-600">
                                                    <DocumentFileIcon mimeType={doc.mimeType} />
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="font-medium text-neutral-950">{doc.title}</p>
                                                    <p className="text-xs text-neutral-500">
                                                        {doc.originalName} · {formatDocumentSize(doc.fileSize)}
                                                        {doc.toothFdi ? ` · Gigi FDI ${doc.toothFdi}` : ''}
                                                    </p>
                                                    {doc.archiveReason && (
                                                        <p className="mt-1 text-xs text-amber-700">Alasan arsip: {doc.archiveReason}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{doc.categoryLabel}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <p className="text-xs font-medium tabular-nums text-neutral-900">{doc.documentDate ?? 'Belum dicatat'}</p>
                                            {doc.issuerFacility && <p className="text-xs text-neutral-500">{doc.issuerFacility}</p>}
                                        </TableCell>
                                        <TableCell>
                                            <p className="text-xs text-neutral-900">{doc.uploadedBy ?? 'Petugas'}</p>
                                            <p className="text-xs tabular-nums text-neutral-500">{doc.uploadedAt ?? ''}</p>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                {doc.canPreview && doc.previewUrl && (
                                                    <Button onClick={() => onPreview(doc)} size="sm" variant="secondary">
                                                        <Eye className="size-3.5" />Pratinjau
                                                    </Button>
                                                )}
                                                {doc.canDownload && doc.downloadUrl && (
                                                    <Button asChild size="icon-sm" title="Unduh" variant="ghost">
                                                        <a aria-label={`Unduh ${doc.title}`} href={doc.downloadUrl}>
                                                            <Download className="size-4" />
                                                        </a>
                                                    </Button>
                                                )}
                                                {doc.canArchive && doc.archiveUrl && (
                                                    <Button
                                                        aria-label={`Arsipkan ${doc.title}`}
                                                        onClick={() => onArchive(doc)}
                                                        size="icon-sm"
                                                        title="Arsipkan"
                                                        variant="ghost"
                                                    >
                                                        <Archive className="size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )) : (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell colSpan={5}>
                                            <Empty size="compact" title={filters.archived ? 'Belum ada dokumen yang diarsipkan.' : 'Belum ada dokumen pasien tersimpan.'} />
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    {pagination && <Pagination pagination={pagination} />}
                </CardContent>
            </Card>
        </div>
    );
}

function VisitHistory({ visits, canSeeClinical }: { visits: Visit[]; canSeeClinical: boolean }) {
    return (
        <div className="overflow-x-auto">
            <Table className="min-w-[820px]">
                <TableHeader>
                    <TableRow>
                        <TableHead>No. kunjungan</TableHead>
                        <TableHead>Tanggal</TableHead>
                        <TableHead>{canSeeClinical ? 'Poliklinik / keluhan' : 'Poliklinik'}</TableHead>
                        <TableHead>Jenis</TableHead>
                        <TableHead>Dokter</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-center">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {visits.length ? visits.map((visit) => (
                        <TableRow key={visit.id}>
                            <TableCell className="whitespace-nowrap font-sans tabular-nums text-xs text-neutral-600">
                                {visit.number}
                            </TableCell>
                            <TableCell className="whitespace-nowrap text-neutral-600">
                                <p>{visit.date ?? 'Belum dicatat'}</p>
                                {visit.time && <p className="mt-0.5 text-xs tabular-nums text-neutral-500">{visit.time}</p>}
                            </TableCell>
                            <TableCell>
                                <p className="font-medium text-neutral-900">{visit.clinic}</p>
                                <p className="mt-0.5 max-w-[14rem] truncate text-xs text-neutral-500">
                                    {canSeeClinical && visit.complaint ? visit.complaint : (visit.room ?? 'Ruang poli')}
                                </p>
                            </TableCell>
                            <TableCell className="whitespace-nowrap text-neutral-600">
                                {visit.visitType ?? 'Reguler'}
                            </TableCell>
                            <TableCell className="text-neutral-600">{visit.doctor}</TableCell>
                            <TableCell>
                                <StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} status={visit.status} />
                            </TableCell>
                            <TableCell>
                                <VisitActions actions={visit.actions} />
                            </TableCell>
                        </TableRow>
                    )) : (
                        <TableRow className="hover:bg-transparent">
                            <TableCell colSpan={7}>
                                <Empty size="compact" title="Belum ada catatan untuk bagian ini." />
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}

function VisitActions({ actions }: { actions: Action[] }) {
    return (
        <div className="flex flex-wrap items-center justify-center gap-2">
            {actions.map((action, index) => (
                action.url ? (
                    <Button asChild className="h-auto min-h-8 whitespace-normal text-left" key={action.url} size="sm" variant="secondary">
                        <Link href={action.url}>{action.label}</Link>
                    </Button>
                ) : (
                    <span className="text-xs text-neutral-500" key={index}>{action.label}</span>
                )
            ))}
        </div>
    );
}
