import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createElement } from 'react';
import { renderToString } from 'react-dom/server';
import { createInertiaApp } from '@inertiajs/react';
import { createServer } from 'vite';

let server;
before(async () => {
    server = await createServer({
        configFile: false,
        root: fileURLToPath(new URL('../', import.meta.url)),
        optimizeDeps: { noDiscovery: true, include: [] },
        server: { middlewareMode: true, hmr: false, watch: null },
        resolve: { alias: { '@': fileURLToPath(new URL('../resources/js', import.meta.url)) } },
    });
});
after(async () => { await server?.close(); });

async function renderPage(path, props, url = '/pendaftaran') {
    const { default: Component } = await server.ssrLoadModule(`/resources/js/pages/${path}.tsx`);
    const result = await createInertiaApp({
        page: { component: path, props: { errors: {}, ...props }, url, version: null, clearHistory: false, encryptHistory: false },
        resolve: () => Component,
        render: renderToString,
        setup: ({ App, props: appProps }) => createElement(App, appProps),
    });
    return result.body;
}

const singlePage = { currentPage: 1, lastPage: 1, from: 1, to: 9, total: 9, previousUrl: null, nextUrl: null };

for (const clinicType of ['umum', 'gigi']) {
    test(`${clinicType} screening separates measurements, preserves unknown answers, and offers draft and handoff actions`, async () => {
        const html = await renderPage('pelayanan/screening/show', {
            visit: { id: 1, number: 'K-001', status: 'menunggu', clinic: `Poli ${clinicType}`, clinicType, doctor: 'Dokter Uji', patient: { name: 'Pasien Uji', medicalRecordNumber: 'RM-001', gender: 'P', age: 35, bloodType: null, allergyHistory: 'Penisilin' }, screening: { riwayat_alergi: 'Penisilin', status_riwayat_penyakit: 'ada', riwayat_penyakit: 'Hipertensi' }, triage: null, priority: null, printUrl: null, startedAt: null },
            staff: [], canSelectStaff: false, currentStaff: 'Perawat Uji',
        });
        assert.match(html, /id="section-ringkas"/);
        assert.match(html, /id="berat_badan"/);
        assert.match(html, /id="tinggi_badan"/);
        assert.match(html, /id="lama_keluhan"/);
        assert.match(html, /id="konfirmasi_riwayat"/);
        assert.match(html, /role="checkbox"/);
        assert.match(html, /aria-checked="true"[^>]*id="status_alergi-ada"/);
        assert.match(html, /id="riwayat_alergi"[^>]*>Penisilin/);
        assert.match(html, /min-w-0 col-span-full/, 'allergy details should span the full width in the compact tab');
        assert.ok((html.match(/min-w-0 col-span-full/g) ?? []).length >= 2, 'systemic disease history should also span the full width in the compact tab');
        assert.match(html, />Simpan draf</);
        assert.match(html, /Simpan &amp; kirim ke dokter/);
        assert.match(html, /<button[^>]*disabled=""[^>]*>[\s\S]*?Cetak/);
        assert.match(html, /aria-selected="true"[^>]*data-value="ringkas"/);
        assert.match(html, /role="tab"[^>]*>Lengkap</);
        assert.match(html, /id="section-ringkas"/);
        assert.doesNotMatch(html, /id="section-triage"|id="section-complaint"|id="section-history"/);
        assert.doesNotMatch(html, />Buka semua<|>Tutup semua</);
        assert.doesNotMatch(html, /Prioritas menurut penilaian petugas/);
        assert.match(html, /id="suhu"/);
        assert.match(html, /id="catatan_triase"[^>]*>/, 'the optional triage note should be available in compact mode');
        assert.match(html, /Catatan triase \/ komunikasi kepada dokter/);
        assert.match(html, /min-w-0 xl:col-span-2/, 'the triage note should fill the two columns beside the final triage item');
        assert.match(html, /id="td_sistole"/);
        assert.match(html, /Tekanan darah \(mmHg\)/);
        assert.match(html, /aria-label="Tekanan sistole"/);
        assert.match(html, /aria-label="Tekanan diastole"/);
        assert.match(html, /Catatan Pengkajian/);
        assert.ok((html.match(/min-w-0 md:col-span-2/g) ?? []).length >= 1, 'the primary complaint should span the full form width');
        assert.match(html, /grid min-w-0 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4/, 'vital signs should use the same four-column wide layout as anthropometry');
        assert.doesNotMatch(html, /rounded-lg border border-neutral-200 bg-neutral-50\/50 p-3 sm:col-start-2 xl:col-start-2/, 'the last triage card should stay at the start of its row');
        assert.match(html, /id="section-attachments"/);
        assert.match(html, /Lampiran pasien/);
        assert.match(html, /Opsional\. Kosong berarti belum dinilai, bukan hasil normal\./);
        assert.doesNotMatch(html, /Buka semua|Tutup semua|Alasan TTV \/ antropometri belum lengkap/);
        assert.match(html, /id="status_alergi-ada"/);
        assert.match(html, /id="status_riwayat_penyakit-ada"/);
        assert.match(html, /id="td_diastole"/);
        assert.match(html, /id="respirasi"/);
        assert.match(html, /id="sumber_informasi"/);
        assert.match(html, />Mulai skrining<\/button>/);
        assert.doesNotMatch(html, /Panggil pasien|Pasien hadir/);
        assert.doesNotMatch(html, /id="section-additional"/);
        assert.match(html, /aria-label="Profil pasien"/);
        assert.match(html, /Penisilin/);
        assert.doesNotMatch(html, /value="120"|value="80"|value="36.5"/);
        if (clinicType === 'gigi') {
            assert.match(html, /id="risiko_medis_gigi-belum_diketahui"/);
        } else {
            assert.doesNotMatch(html, /id="risiko_medis_gigi-hipertensi"/);
        }
    });
}

test('compact screening retains saved answers and requires clarification of unknown dental risks', async () => {
    const html = await renderPage('pelayanan/screening/show', {
        visit: {
            id: 1, number: 'K-001', status: 'screening', clinic: 'Poli Gigi', clinicType: 'gigi', doctor: 'Dokter Uji',
            patient: { name: 'Pasien <Uji>', medicalRecordNumber: 'RM-001', gender: 'P', age: 0, bloodType: 'O', allergyHistory: null },
            screening: { keluhan_tambahan: 'Nyeri saat mengunyah', skala_nyeri: 0, spo2: 98, risiko_medis_gigi: ['belum_diketahui'] },
            triage: null, priority: null, printUrl: '/pelayanan/screening/1/cetak', startedAt: '21/10/2026 09:00',
        },
        staff: [], canSelectStaff: false, currentStaff: 'Perawat Uji',
    });

    assert.match(html, /Pasien &lt;Uji&gt;/);
    assert.match(html, /0 tahun/);
    assert.match(html, /id="keluhan_tambahan"[^>]*>Nyeri saat mengunyah</);
    assert.match(html, /id="spo2"[^>]*value="98"/);
    assert.match(html, /id="section-ringkas"/);
    assert.match(html, /id="risiko_medis_gigi-belum_diketahui"/);
    assert.doesNotMatch(html, /id="section-triage"/);
    assert.match(html, /Riwayat sistemik yang perlu diperhatikan dokter gigi/);
    assert.match(html, /id="catatan_medis_gigi"[^>]*required=""/);
    assert.doesNotMatch(html, />Mulai skrining<\/button>/);
});

test('known negative allergy history selects no allergy and leaves its details hidden', async () => {
    const html = await renderPage('pelayanan/screening/show', {
        visit: {
            id: 1, number: 'K-002', status: 'screening', clinic: 'Poli Umum', clinicType: 'umum', doctor: 'Dokter Uji',
            patient: { name: 'Pasien Uji', medicalRecordNumber: 'RM-002', gender: 'P', age: 30, bloodType: null, allergyHistory: 'Tidak ada riwayat alergi menurut pasien' },
            screening: {}, triage: null, priority: null, printUrl: null, startedAt: null,
        },
        staff: [], canSelectStaff: false, currentStaff: 'Perawat Uji',
    });

    assert.match(html, /aria-checked="true"[^>]*id="status_alergi-tidak_ada"/);
    assert.doesNotMatch(html, /id="riwayat_alergi"/);
});

test('nurse queue labels saved draft as in progress rather than completed screening', async () => {
    const html = await renderPage('pelayanan/screening/index', {
        filters: { search: '', tanggal: '2026-10-21', poliklinikId: 1 }, clinics: [], canSelectClinic: false,
        visits: { ...singlePage, total: 1, data: [{ id: 1, number: 'K-001', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', hasScreening: true, status: 'screening', screeningUrl: '/pelayanan/screening/1' }] },
    });
    assert.match(html, /Proses skrining/);
    assert.match(html, /Lanjutkan asesmen/);
    assert.doesNotMatch(html, /Sudah skrining/);
});

test('dashboard queues clarify each workflow and center headers, cells, and status badges', async () => {
    const visit = { id: 1, number: 'K-001', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', actionUrl: '/pelayanan/kunjungan/1', actionLabel: 'Buka', triage: 'hijau', priority: 'normal' };
    const followUp = { upcoming: 0, overdue: 0, href: '/pelayanan/kunjungan' };
    for (const [page, props, expected] of [
        ['perawat/dashboard', { stats: { menunggu_ttv: 1, siap_dokter: 0, triase_darurat: 0 }, visits: [{ ...visit, status: 'menunggu', actionType: 'screening' }] }, 'Menunggu skrining'],
        ['dokter/dashboard', { stats: { visitsToday: 1, waitingExaminations: 1, completedToday: 0 }, schedule: [], recentVisits: [{ ...visit, status: 'pemeriksaan', actionType: 'examination' }] }, 'Pemeriksaan dokter'],
        ['farmasi/dashboard', { stats: { waitingVisits: 0, processingVisits: 1, completedToday: 0, lowStockMedicines: 0 }, recentVisits: [{ ...visit, status: 'diproses', actionType: 'pharmacy' }] }, 'Penyiapan obat berlangsung'],
    ]) {
        const html = await renderPage(page, { ...props, followUp });
        const table = html.match(/<table\b[\s\S]*?<\/table>/)?.[0];
        assert.ok(table, page);
        assert.ok(table.includes(expected), page);
        assert.match(table, /flex flex-col items-center/);
        for (const cell of table.match(/<(?:th|td)\b[^>]*>/g) ?? []) {
            assert.match(cell, /text-center/);
            assert.doesNotMatch(cell, /text-left|text-right/);
        }
        assert.doesNotMatch(table, />diproses<|>Menunggu</);
    }
});

test('shared waiting and completed codes retain their pharmacy and laboratory meanings', async () => {
    const { StatusBadge } = await server.ssrLoadModule('/resources/js/components/dashboard/status-badge.tsx');
    for (const [context, status, label] of [
        ['visit', 'menunggu', 'Menunggu skrining'],
        ['visit', 'screening', 'Proses skrining'],
        ['visit', 'farmasi', 'Pelayanan farmasi'],
        ['visit', 'selesai', 'Kunjungan selesai'],
        ['pharmacy', 'menunggu', 'Menunggu penyiapan obat'],
        ['pharmacy', 'farmasi', 'Menunggu penyiapan obat'],
        ['pharmacy', 'selesai', 'Penyerahan obat selesai'],
        ['laboratory', 'menunggu', 'Menunggu pemeriksaan lab'],
        ['laboratory', 'selesai', 'Pemeriksaan lab selesai'],
    ]) {
        const html = renderToString(createElement(StatusBadge, { context, status }));
        assert.ok(html.includes(`>${label}</span>`), `${context}: ${status}`);
    }
    const reservation = renderToString(createElement(StatusBadge, { status: 'menunggu', reservation: true }));
    assert.match(reservation, />Reservasi</);
    assert.doesNotMatch(reservation, /Menunggu skrining/);
});

test('pharmacy detail shows saved processing status instead of treating it as waiting', async () => {
    const html = await renderPage('pelayanan/farmasi/show', {
        appName: 'Klinik Uji', today: '2026-10-20',
        visit: {
            id: 1, number: 'K-001', status: 'farmasi', prescriptionNumber: 'R-001', doctor: 'Dokter Uji',
            patient: { id: 1, name: 'Pasien Uji', medicalRecordNumber: 'RM-001', allergies: null, screeningAllergies: null, examinationAllergies: null },
            pharmacy: { status: 'diproses', notes: null, items: [] }, prescriptionItems: [], allocations: [],
        },
    });
    assert.match(html, />Penyiapan obat berlangsung</);
    assert.doesNotMatch(html, />Menunggu penyiapan obat<|>Penyerahan obat selesai</);
});

for (const [role, url, expected] of [
    ['pendaftaran', '/pendaftaran', '/pendaftaran'],
    ['pendaftaran', '/pendaftaran/pendaftaran-baru?tab=registered', '/pendaftaran/pendaftaran-baru'],
    ['pendaftaran', '/pendaftaran/kunjungan-per-poli', '/pendaftaran/kunjungan-per-poli'],
    ['pendaftaran', '/pendaftaran/kunjungan/42/edit', '/pendaftaran/kunjungan-per-poli'],
    ['pendaftaran', '/kepesertaan/5', '/kepesertaan'],
    ['dokter', '/dokter/kunjungan', '/dokter/kunjungan'],
    ['dokter', '/pelayanan/pemeriksaan/42', '/pelayanan/pemeriksaan'],
    ['perawat', '/pelayanan/screening/riwayat?page=2', '/pelayanan/screening/riwayat'],
    ['perawat', '/pelayanan/screening/42', '/pelayanan/screening'],
    ['admin', '/master/obat/12/stok', '/master/obat'],
    ['farmasi', '/pelayanan/farmasi/42', '/pelayanan/farmasi'],
    ['manajemen', '/laporan/kunjungan', '/laporan/kunjungan'],
]) {
    test(`sidebar ${role} activates only the matching menu for ${url}`, async () => {
        const { default: Layout } = await server.ssrLoadModule('/resources/js/layouts/app-layout.tsx');
        const result = await createInertiaApp({
            page: { component: 'dashboard/index', props: { errors: {}, flash: {}, auth: { user: { id: 1, name: 'Staf Uji', email: 'staff@example.test', role, roleLabel: role, photoUrl: null } } }, url, version: null, clearHistory: false, encryptHistory: false },
            resolve: () => () => createElement(Layout, null, createElement('p', null, 'Halaman uji')),
            render: renderToString,
            setup: ({ App, props }) => createElement(App, props),
        });
        const activeLinks = result.body.match(/<a\b[^>]*aria-current="page"[^>]*>/g) ?? [];
        assert.equal(activeLinks.length, 1);
        assert.ok(activeLinks[0].includes(`href="${expected}"`));
        assert.doesNotMatch(result.body, /Kepesertaan &amp; [Hh]ak/);
    });
}

test('navigation does not match unrelated prefixes or select a dashboard for unknown pages', async () => {
    const { activeNavigationHref } = await server.ssrLoadModule('/resources/js/lib/navigation.ts');
    const targets = [{ href: '/dokter', exact: true }, { href: '/dokter/pasien' }];
    assert.equal(activeNavigationHref('/dokter/pasien-lain', targets), null);
    assert.equal(activeNavigationHref('/dokter/tidak-ada', targets), null);
    assert.equal(activeNavigationHref('/dokter/pasien/?page=2', targets), '/dokter/pasien');
});

test('registered patient preview presents full saved identity and honest missing values', async () => {
    const { RegisteredPatientIdentity } = await server.ssrLoadModule('/resources/js/components/pendaftaran/registered-patient-form.tsx');
    const html = renderToString(createElement(RegisteredPatientIdentity, { patient: {
        nik: '3273015205900001', nip: '1990052020101001', tanggal_lahir: '1990-05-20', tempat_lahir: 'Bandung',
        alamat: 'Alamat uji', telepon: '081234567890', membership: 'ASN', unit_kerja: 'Biro Umum',
        rt: '001', rw: '002', kelurahan: 'Kelurahan Uji', kecamatan: 'Kecamatan Uji',
    } }));
    for (const value of ['3273015205900001', '1990052020101001', '1990-05-20', 'Alamat uji', 'Biro Umum', 'Kelurahan Uji', 'Belum diisi']) {
        assert.ok(html.includes(value));
    }
    assert.doesNotMatch(html, /undefined|null/);
});

for (const [label, dates] of [
    ['without dates', { tanggalMulai: '', tanggalSelesai: '' }],
    ['with only a start date', { tanggalMulai: '2026-10-01', tanggalSelesai: '' }],
    ['with only an end date', { tanggalMulai: '', tanggalSelesai: '2026-10-20' }],
]) {
    test(`top diagnosis renders ${label}`, async () => {
        const html = await renderPage('pendaftaran/laporan-top-diagnosa', {
            filters: dates,
            summary: { diagnosisCount: 1, caseCount: 3 },
            diagnoses: { ...singlePage, total: 1, to: 1, data: [{ rank: 1, code: 'J00', name: 'Diagnosis uji', count: 3, percentage: 100 }] },
        });
        assert.match(html, /Diagnosis terbanyak/);
        assert.match(html, /Diagnosis uji/);
        assert.doesNotMatch(html, /Invalid Date|NaN/);
    });
}

for (const initialTab of ['manual', 'directory', 'registered']) {
    test(`registration tab ${initialTab} renders only its own form`, async () => {
        const html = await renderPage('pendaftaran/pendaftaran-baru', {
            initialTab,
            today: '2026-10-20',
            doctorsUrl: '/pendaftaran/dokter/by-poli',
            clinics: [{ id: 1, name: 'Poli Umum', type: 'umum' }, { id: 2, name: 'Poli Gigi', type: 'gigi' }],
        });
        assert.equal((html.match(/<form(?:\s|>)/g) ?? []).length, 1);
        if (initialTab === 'manual') {
            assert.match(html, /id="manual-nip"/);
            assert.match(html, /Daftarkan dan buat kunjungan/);
        } else {
            assert.doesNotMatch(html, /id="manual-nama"/);
        }
        assert.match(html, /Pasien Terdaftar/);
        assert.doesNotMatch(html, /Pendaftaran manual/);
    });
}

test('pagination omits navigation when all records fit on one page', async () => {
    const { Pagination } = await server.ssrLoadModule('/resources/js/components/dashboard/pagination.tsx');
    const html = renderToString(createElement(Pagination, { pagination: singlePage }));
    assert.match(html, /dari 9 data/);
    assert.doesNotMatch(html, /<nav/);
});

test('pagination renders separate disabled and linked controls', async () => {
    const { Pagination } = await server.ssrLoadModule('/resources/js/components/dashboard/pagination.tsx');
    const html = renderToString(createElement(Pagination, { pagination: { ...singlePage, lastPage: 2, total: 11, nextUrl: '/patients?page=2' } }));
    assert.match(html, /aria-label="Halaman sebelumnya" disabled=""/);
    assert.match(html, /href="\/patients\?page=2"/);
    assert.match(html, /aria-current="page"/);
});

const visitDetail = {
    id: 42, number: 'KNJ-TEST-42', date: '20 Oktober 2026', status: 'menunggu',
    clinic: 'Poli Umum', doctor: null, eligibility: { status: 'pending_verification' },
    verifiedAt: null, patientType: 'baru', notes: null,
    patient: { id: 7, name: 'Pasien Uji', medicalRecordNumber: 'RM-000007', gender: 'P', age: 30, bloodType: null, allergies: null },
    progress: [{ label: 'Skrining tanda vital', done: false }],
};

test('registration visit detail uses the accessible URLs supplied by the backend', async () => {
    const html = await renderPage('pelayanan/kunjungan/show', {
        visit: visitDetail,
        backUrl: '/pendaftaran/kunjungan-per-poli',
        permissions: { editVisit: true, viewRme: false, processScreening: false },
        links: { edit: '/pendaftaran/kunjungan/42/edit', patient: '/pendaftaran/database-pasien?search=RM-000007', screening: null, medicalRecord: null },
    });
    assert.match(html, /href="\/pendaftaran\/kunjungan\/42\/edit"/);
    assert.match(html, /href="\/pendaftaran\/database-pasien\?search=RM-000007"/);
    assert.match(html, /href="\/pendaftaran\/kunjungan-per-poli"/);
    assert.match(html, /Menunggu verifikasi kepesertaan/);
    assert.doesNotMatch(html, /href="\/pelayanan\/|Lihat rekam medis|Proses skrining/);
});

test('visit detail omits unavailable patient and workflow links', async () => {
    const html = await renderPage('pelayanan/kunjungan/show', {
        visit: { ...visitDetail, status: 'batal', patient: { ...visitDetail.patient, id: null } },
        backUrl: '/pendaftaran/kunjungan-per-poli',
        permissions: { editVisit: false, viewRme: false, processScreening: false },
        links: { edit: null, patient: null, screening: null, medicalRecord: null },
    });
    assert.match(html, /Pasien Uji/);
    assert.doesNotMatch(html, /href="[^"]*(?:null|undefined|\/edit|screening|rekam-medis)/);
});

test('nurse visit detail preserves authorized screening and medical record links', async () => {
    const html = await renderPage('pelayanan/kunjungan/show', {
        visit: visitDetail,
        backUrl: '/perawat',
        permissions: { editVisit: false, viewRme: true, processScreening: true },
        links: { edit: null, patient: '/pelayanan/pasien/7', screening: '/pelayanan/screening/42', medicalRecord: '/pelayanan/pasien/7/rekam-medis' },
    });
    assert.match(html, /href="\/pelayanan\/screening\/42"/);
    assert.match(html, /href="\/pelayanan\/pasien\/7\/rekam-medis"/);
    assert.doesNotMatch(html, /href="[^"]*\/edit"/);
});

test('shared field preserves labels and announces validation errors with a select control', async () => {
    const { Field } = await server.ssrLoadModule('/resources/js/components/ui/field.tsx');
    const { Select } = await server.ssrLoadModule('/resources/js/components/ui/select.tsx');
    const html = renderToString(createElement('form', null,
        createElement(Field, { label: 'Dokter', htmlFor: 'doctor', required: true, error: 'Pilih dokter.' },
            createElement(Select, { id: 'doctor', value: '', required: true, 'aria-describedby': 'doctor-error' },
                createElement('option', { value: '' }, 'Pilih dokter'),
                createElement('option', { value: '1' }, 'Dokter Uji')))));
    assert.match(html, /for="doctor"/);
    assert.match(html, /id="doctor"/);
    assert.match(html, /aria-describedby="doctor-error"/);
    assert.match(html, /id="doctor-error" role="alert">Pilih dokter\./);
});

for (const canManage of [true, false]) {
    test(`practice schedule renders with management access ${canManage}`, async () => {
        const emptyPage = { ...singlePage, from: 0, to: 0, total: 0, data: [] };
        const html = await renderPage('pendaftaran/jadwal-praktik', {
            canManage, canSelectClinic: canManage,
            filters: { dokterId: '', poliklinikId: '', hari: '' },
            doctors: [{ id: 1, name: 'Dokter Uji' }], clinics: [{ id: 1, name: 'Poli Umum' }],
            days: ['senin', 'selasa', 'rabu', 'kamis', 'jumat'], today: '2026-10-20',
            schedules: emptyPage, holidays: emptyPage, exceptions: emptyPage,
        });
        if (canManage) {
            assert.match(html, /id="asn-doctor"/);
            assert.match(html, /id="asn-clinic"/);
            assert.match(html, /<button[^>]*disabled=""[^>]*>Terapkan Senin/);
        } else {
            assert.doesNotMatch(html, /id="asn-doctor"|Terapkan jadwal ASN|Simpan pengecualian/);
        }
    });
}


test('assessment attachments expose upload controls without nesting forms and escape patient document names', async () => {
    const html = await renderPage('pelayanan/screening/show', {
        visit: {
            id: 1, number: 'K-001', status: 'screening', clinic: 'Poli Umum', clinicType: 'umum', doctor: 'Dokter Uji',
            patient: { name: 'Pasien Uji', medicalRecordNumber: 'RM-001', gender: 'P', age: 35, bloodType: null, allergyHistory: null },
            screening: {}, triage: null, priority: null, printUrl: null, startedAt: null,
            attachmentUploadUrl: '/pelayanan/kunjungan/1/lampiran',
            attachments: [{ id: 'file-1', name: '<script>Dokumen</script>.pdf', note: 'Hasil lab dari pasien', size: 1024, mime: 'application/pdf', downloadUrl: '/pelayanan/kunjungan/1/lampiran/file-1', deleteUrl: '/pelayanan/kunjungan/1/lampiran/file-1' }],
        }, staff: [], canSelectStaff: false, currentStaff: 'Perawat Uji',
    });
    assert.equal((html.match(/<form\b/g) ?? []).length, 1);
    assert.match(html, /id="patient-attachment"/);
    assert.match(html, /accept=".pdf,.jpg,.jpeg,.png,.webp"/);
    assert.match(html, /id="attachment-note"/);
    assert.match(html, /Unggah lampiran/);
    assert.match(html, /Hasil lab dari pasien/);
    assert.match(html, /&lt;script&gt;Dokumen&lt;\/script&gt;.pdf/);
    assert.doesNotMatch(html, /<script>Dokumen/);
    assert.match(html, /href="\/pelayanan\/kunjungan\/1\/lampiran\/file-1"/);
    assert.doesNotMatch(html, /clinical-attachments/);
});
