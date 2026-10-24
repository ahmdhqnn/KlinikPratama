import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
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
const pageOf = (data) => ({ currentPage: 1, lastPage: 1, from: data.length ? 1 : null, to: data.length || null, total: data.length, previousUrl: null, nextUrl: null, data });

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
        assert.match(html, /Catatan penapisan \/ komunikasi kepada dokter/);
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
        assert.match(html, />Mulai pengkajian<\/button>/);
        assert.doesNotMatch(html, /Panggil pasien|Pasien hadir/);
        assert.doesNotMatch(html, /id="section-additional"/);
        assert.match(html, /aria-label="Profil pasien"/);
        assert.match(html, /Penisilin/);
        assert.doesNotMatch(html, /value="120"|value="80"|value="36.5"/);
        if (clinicType === 'gigi') {
            assert.match(html, /id="risiko_medis_gigi-belum_diketahui"/);
        } else {
            assert.doesNotMatch(html, /id="risiko_medis_gigi-hipertensi"|id="lokasi_nyeri_gigi"|id="pemicu_nyeri_gigi-mengunyah"|Pengkajian khusus gigi/);
        }
    });
}

test('compact screening retains saved answers and requires clarification of unknown dental risks', async () => {
    const html = await renderPage('pelayanan/screening/show', {
        visit: {
            id: 1, number: 'K-001', status: 'screening', clinic: 'Poli Gigi', clinicType: 'gigi', doctor: 'Dokter Uji',
            patient: { name: 'Pasien <Uji>', medicalRecordNumber: 'RM-001', gender: 'P', age: 0, bloodType: 'O', allergyHistory: null },
            screening: { keluhan_tambahan: 'Nyeri saat mengunyah', skala_nyeri: 0, spo2: 98, risiko_medis_gigi: ['belum_diketahui'], lokasi_nyeri_gigi: 'Geraham kiri bawah', durasi_keluhan_gigi: 'Saat mengunyah', pemicu_nyeri_gigi: ['dingin'], riwayat_infeksi_gigi: ['tidak_ada'], catatan_medis_gigi: 'Konfirmasi obat rutin' },
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
    assert.match(html, /id="lokasi_nyeri_gigi"[^>]*value="Geraham kiri bawah"/);
    assert.match(html, /id="durasi_keluhan_gigi"[^>]*value="Saat mengunyah"/);
    assert.match(html, /aria-checked="true"[^>]*id="pemicu_nyeri_gigi-dingin"/);
    assert.equal((html.match(/id="skala_nyeri"/g) ?? []).length, 1);
    assert.equal((html.match(/id="catatan_medis_gigi"/g) ?? []).length, 1);
    assert.equal((html.match(/id="konfirmasi_riwayat"/g) ?? []).length, 1);
    assert.doesNotMatch(html, />Mulai pengkajian<\/button>/);
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
        visits: { ...singlePage, total: 1, data: [{ id: 1, number: 'K-001', date: '21/10/2026', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', hasScreening: true, status: 'screening', screeningUrl: '/pelayanan/screening/1' }] },
    });
    assert.match(html, /Pengkajian awal berlangsung/);
    assert.match(html, /Lanjutkan pengkajian/);
    assert.doesNotMatch(html, /Pengkajian selesai/);
    assert.ok(html.indexOf('Pengkajian saya') < html.indexOf('<table'), 'history action should be above the queue table');
    assert.match(html, /aria-label="Filter data"[^>]*>[\s\S]*?Pengkajian saya[\s\S]*?aria-controls="[^"]+"[^>]*>[\s\S]*?Filter/, 'history action and filter should share a control row');
    assert.match(html, /id="screening-queue-search"/);
    assert.match(html, /id="screening-queue-date"/);
    const historyActionIndex = html.indexOf('Pengkajian saya');
    const historyActionMarkup = html.slice(html.lastIndexOf('<a', historyActionIndex), html.indexOf('>', historyActionIndex) + 1);
    assert.match(historyActionMarkup, /border-neutral-200 bg-surface/, 'history action should match the filter toggle style');
});

test('clinic visit tables show the appropriate nurse action and consistent controls', async () => {
    const visit = { id: 1, number: 'K-001', date: '22/10/2026', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', doctor: 'Dokter Uji', status: 'screening', actionUrl: '/pelayanan/screening/1', actionLabel: 'Lanjutkan pengkajian', ticketUrl: null, editUrl: null, cancelUrl: null };
    const html = await renderPage('pendaftaran/kunjungan-per-poli', {
        filters: { poliklinikId: 1, tanggal: '', status: '' }, clinics: [{ id: 1, name: 'Poli Umum' }],
        stats: { total: 1, waiting: 0, screening: 1, examination: 0 },
        access: { manageVisits: false, monitorClinicalFlow: true, selectClinic: false },
        visits: pageOf([visit]), recentVisits: pageOf([visit]),
    });
    assert.match(html, /Kunjungan poli/);
    assert.equal((html.match(/aria-label="Lanjutkan pengkajian"/g) ?? []).length, 2);
    assert.doesNotMatch(html, />Lanjutkan pengkajian<\/a>/);
    assert.equal((html.match(/href="\/pelayanan\/screening\/1"/g) ?? []).length, 2);
    assert.doesNotMatch(html, />Edit<\/a>|>Batalkan<\/button>|>Cetak<\/a>/);
});

test('dashboard queues clarify each workflow and center headers, cells, and status badges', async () => {
    const visit = { id: 1, number: 'K-001', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', actionUrl: '/pelayanan/kunjungan/1', actionLabel: 'Buka', triage: 'hijau', priority: 'normal' };
    const followUp = { upcoming: 0, overdue: 0, href: '/pelayanan/kunjungan' };
    for (const [page, props, expected, destination] of [
        ['perawat/dashboard', { stats: { total_kunjungan: 1, total_kunjungan_hari_ini: 1, menunggu_ttv: 1, menunggu_ttv_hari_ini: 1, siap_dokter: 0, siap_dokter_hari_ini: 0, triase_darurat: 0 }, visits: pageOf([{ ...visit, status: 'menunggu', actionType: 'screening' }]) }, 'Menunggu pengkajian awal', '/pelayanan/screening'],
        ['dokter/dashboard', { stats: { visitsToday: 1, waitingExaminations: 1, completedToday: 0 }, schedule: [], recentVisits: pageOf([{ ...visit, status: 'pemeriksaan', actionType: 'examination' }]) }, 'Pemeriksaan dokter', '/pelayanan/pemeriksaan'],
        ['farmasi/dashboard', { stats: { waitingVisits: 0, processingVisits: 1, completedToday: 0, lowStockMedicines: 0 }, recentVisits: [{ ...visit, status: 'diproses', actionType: 'pharmacy' }] }, 'Penyiapan obat berlangsung', '/pelayanan/farmasi'],
    ]) {
        const html = await renderPage(page, { ...props, followUp });
        assert.ok(html.includes(`href="${destination}"`), `${page} summary card should link to its related menu`);
        if (page === 'perawat/dashboard') {
            assert.doesNotMatch(html, /hari ini: 0/);
        }
        const table = html.match(/<table\b[\s\S]*?<\/table>/)?.[0];
        assert.ok(table, page);
        assert.ok(table.includes(expected), page);
        assert.match(table, /flex flex-col items-center/);
        assertIconActions(table, 1);
        for (const cell of table.match(/<(?:th|td)\b[^>]*>/g) ?? []) {
            assert.match(cell, /text-center/);
            assert.doesNotMatch(cell, /text-left|text-right/);
        }
        assert.doesNotMatch(table, />diproses<|>Menunggu</);
    }
});

test('dashboard summaries and day bars navigate to their related menus', async () => {
    const globalDashboard = await renderPage('dashboard/index', {
        stats: { totalPatients: 12, visitsToday: 3, visitsThisMonth: 18, prescriptionsToday: 2 },
        visitStatuses: { menunggu: 1, screening: 1, pemeriksaan: 1, farmasi: 0, kasir: 0, selesai: 0, batal: 0 },
        visitsByDay: [{ tanggal: '2026-10-21', jumlah: 2 }, { tanggal: '2026-10-22', jumlah: 3 }],
        canRegister: true,
        followUp: { upcoming: 0, overdue: 0, href: '/laporan/kunjungan' },
    });
    assert.ok(globalDashboard.includes('href="/pelayanan/pasien"'));
    assert.ok(globalDashboard.includes('href="/pendaftaran/kunjungan-per-poli?tanggal=2026-10-22"'));
    assert.ok(globalDashboard.includes('href="/laporan/kunjungan?dari=2026-10-21&amp;sampai=2026-10-21"'));

    const registrationDashboard = await renderPage('pendaftaran/dashboard', {
        today: '2026-10-22',
        stats: { visitsToday: 3, newPatientsToday: 1, waitingScreening: 1, inProgress: 1 },
        followUp: { upcoming: 0, overdue: 0, href: '/pendaftaran/laporan-kunjungan' },
    });
    assert.ok(registrationDashboard.includes('href="/pendaftaran/kunjungan-per-poli?tanggal=2026-10-22"'));
    assert.ok(registrationDashboard.includes('href="/pendaftaran/database-pasien"'));
});

test('shared waiting and completed codes retain their pharmacy and laboratory meanings', async () => {
    const { StatusBadge } = await server.ssrLoadModule('/resources/js/components/dashboard/status-badge.tsx');
    for (const [context, status, label] of [
        ['visit', 'menunggu', 'Menunggu pengkajian awal'],
        ['visit', 'screening', 'Pengkajian awal berlangsung'],
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
    assert.doesNotMatch(reservation, /Menunggu pengkajian awal/);
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
        const schedule = { id: 1, doctorId: 1, clinicId: 1, doctor: 'Dokter Uji', clinic: canManage ? 'Poli Umum' : 'Poli Gigi', day: 'senin', start: '08:00', end: '16:00', active: true, validFrom: '2026-10-20', validUntil: null, updateUrl: '/pendaftaran/jadwal-praktik/1', deleteUrl: '/pendaftaran/jadwal-praktik/1' };
        const html = await renderPage('pendaftaran/jadwal-praktik', {
            canManage, canManageHolidays: canManage, canSelectClinic: canManage,
            filters: { dokterId: '', poliklinikId: '', hari: '' },
            doctors: [{ id: 1, name: 'Dokter Uji' }], clinics: [{ id: 1, name: schedule.clinic }],
            days: ['senin', 'selasa', 'rabu', 'kamis', 'jumat'], today: '2026-10-20',
            schedules: { ...emptyPage, data: [schedule], total: 1 }, holidays: emptyPage, exceptions: emptyPage,
        });
        assert.match(html, /Detail jadwal Dokter Uji senin/);
        assertIconActions(html.match(/<table\b[\s\S]*?<\/table>/)?.[0], canManage ? 3 : 1);
        if (canManage) {
            assert.match(html, /id="asn-doctor"/);
            assert.match(html, /id="asn-clinic"/);
            assert.match(html, /<button[^>]*disabled=""[^>]*>Terapkan Senin/);
            assert.match(html, /Perbarui jadwal Dokter Uji senin/);
            assert.match(html, /Hapus jadwal Dokter Uji senin/);
        } else {
            assert.doesNotMatch(html, /id="asn-doctor"|Terapkan jadwal ASN|Simpan pengecualian/);
            assert.doesNotMatch(html, /Perbarui jadwal Dokter Uji senin|Hapus jadwal Dokter Uji senin/);
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

function assertIconActions(html, count) {
    const actions = [...html.matchAll(/<(button|a)\b([^>]*\baria-label="[^"]+"[^>]*)>([\s\S]*?)<\/\1>/g)]
        .filter(([, , attributes]) => /\btitle="/.test(attributes));
    assert.equal(actions.length, count);
    for (const [, , attributes, content] of actions) {
        assert.match(attributes, /size-9/);
        assert.match(content, /<svg\b[^>]*aria-hidden="true"/);
        assert.equal(content.replace(/<svg\b[\s\S]*?<\/svg>/g, '').trim(), '', 'row actions should display only their icon');
        assert.doesNotMatch(content, /<(?:a|button)\b/, 'an action must not nest interactive elements');
    }
}

test('icon actions preserve accessible labels, disabled states and native form behavior', async () => {
    const { ActionButton } = await server.ssrLoadModule('/resources/js/components/ui/action-button.tsx');
    const button = renderToString(createElement(ActionButton, { action: 'delete', label: 'Hapus lampiran <uji>', disabled: true }));
    assertIconActions(button, 1);
    assert.match(button, /type="button"/);
    assert.match(button, /disabled=""/);
    assert.match(button, /title="Hapus lampiran &lt;uji&gt;"/);
    assert.match(button, /text-red-600/);
    assert.doesNotMatch(button, /<script/);

    const download = renderToString(createElement(ActionButton, { action: 'download', label: 'Unduh lampiran', asChild: true }, createElement('a', { href: '/lampiran/7', download: 'hasil.pdf' })));
    assertIconActions(download, 1);
    assert.match(download, /href="\/lampiran\/7"/);
    assert.match(download, /download="hasil.pdf"/);
    assert.doesNotMatch(download, /<button\b|type="button"/);

    const disabledLink = renderToString(createElement(ActionButton, { action: 'download', label: 'Unduh lampiran', asChild: true, disabled: true }, createElement('a', { href: '/lampiran/7' })));
    assertIconActions(disabledLink, 1);
    assert.match(disabledLink, /<button\b[^>]*disabled=""/);
    assert.doesNotMatch(disabledLink, /href=|<a\b/);
});

test('patient row exposes verification, detail and RME as separate icon actions', async () => {
    const html = await renderPage('pelayanan/pasien/index', {
        filters: { search: '', kategori: '' }, permissions: { viewRme: true, managePatients: true },
        categories: {}, otherTypes: {}, sponsors: [], today: '2026-10-24',
        patients: pageOf([{ id: 7, medicalRecordNumber: 'RM-007', name: 'Pasien Uji', membership: 'ASN', canVerify: true, verificationUrl: '/pelayanan/pasien/7/verifikasi-kepesertaan', verificationData: null }]),
    });
    const table = html.match(/<table\b[\s\S]*?<\/table>/)?.[0];
    assertIconActions(table, 3);
    assert.match(table, /aria-label="Verifikasi"/);
    assert.match(table, /href="\/pelayanan\/pasien\/7"/);
    assert.match(table, /href="\/pelayanan\/pasien\/7\/rekam-medis"/);
    assert.match(table, /lucide-heart-pulse/);
});

for (const [page, props, expectedCount] of [
    ['master/alkes/index', { filters: { search: '' }, items: pageOf([{ id: 1, code: 'A-1', name: 'Alkes', stock: 1, minimumStock: 0, active: true }]) }, 2],
    ['master/depo-obat/index', { filters: { search: '' }, depots: pageOf([{ id: 1, code: 'D-1', name: 'Depo', active: true }]) }, 2],
    ['master/laboratorium/index', { filters: { search: '' }, clinics: [], laboratories: pageOf([{ id: 1, code: 'L-1', name: 'Lab', active: true }]) }, 3],
    ['master/obat/index', { filters: { search: '', type: '', lowStock: false }, medicines: pageOf([{ id: 1, code: 'O-1', name: 'Obat', stock: 1, minimumStock: 0, unitConversion: 1, type: 'obat', active: true }]) }, 3],
    ['dokter/patients', { search: '', patients: pageOf([{ id: 7, medicalRecordNumber: 'RM-007', name: 'Pasien Uji', visitCount: 1, recordUrl: '/pelayanan/pasien/7/rekam-medis' }]) }, 1],
    ['stok/persediaan/show', { medicine: { id: 1, code: 'O-1', name: 'Obat', stock: 10, unit: 'tablet' }, batches: [{ id: 1, number: 'B-1', stock: 10, status: 'karantina', source: 'distribusi', expired: false, nearExpiry: false }], movements: pageOf([{ id: 1, date: '24/10/2026', type: 'keluar', quantity: 1, before: 11, after: 10, canReturn: true }]), depots: [] }, 4],
]) {
    test(`${page} uses uniform icon actions in populated tables`, async () => {
        const html = await renderPage(page, props);
        const tables = html.match(/<table\b[\s\S]*?<\/table>/g) ?? [];
        assert.ok(tables.length > 0);
        assertIconActions(tables.join(''), expectedCount);
    });
}

test('user action icons reflect active status and omit protected self-account actions', async () => {
    const html = await renderPage('users/index', {
        users: pageOf([
            { id: 1, name: 'Saya', email: 'saya@example.test', role: 'admin', roleLabel: 'Admin', active: true, nakes: null, isCurrentUser: true },
            { id: 2, name: 'Aktif', email: 'aktif@example.test', role: 'perawat', roleLabel: 'Perawat', active: true, nakes: null, isCurrentUser: false },
            { id: 3, name: 'Nonaktif', email: 'nonaktif@example.test', role: 'dokter', roleLabel: 'Dokter', active: false, nakes: null, isCurrentUser: false },
        ]), filters: { search: '', role: '' }, roles: [], doctors: [], clinics: [],
    });
    const table = html.match(/<table\b[\s\S]*?<\/table>/)?.[0];
    assertIconActions(table, 10);
    assert.equal((table.match(/aria-label="Hapus"/g) ?? []).length, 2);
    assert.equal((table.match(/aria-label="Nonaktifkan pengguna"/g) ?? []).length, 1);
    assert.equal((table.match(/aria-label="Aktifkan pengguna"/g) ?? []).length, 1);
});

test('dashboard RME links use the same medical record icon as patient tables', async () => {
    const { VisitTable } = await server.ssrLoadModule('/resources/js/components/dashboard/visit-table.tsx');
    const html = renderToString(createElement(VisitTable, { visits: [{ id: 1, number: 'K-1', patient: 'Pasien', medicalRecordNumber: 'RM-007', clinic: 'Poli Gigi', status: 'selesai', actionType: 'detail', actionLabel: 'Lihat RME', actionUrl: '/pelayanan/pasien/7/rekam-medis' }] }));
    assertIconActions(html, 1);
    assert.match(html, /aria-label="Lihat RME"/);
    assert.match(html, /lucide-heart-pulse/);
    assert.match(html, /href="\/pelayanan\/pasien\/7\/rekam-medis"/);
});

test('dental nurse schedule management locks assigned clinic and keeps shared holidays read only', async () => {
    const emptyPage = pageOf([]);
    const html = await renderPage('pendaftaran/jadwal-praktik', {
        canManage: true, canManageHolidays: false, canSelectClinic: false,
        filters: { dokterId: '', poliklinikId: 2, hari: '' }, doctors: [{ id: 1, name: 'Dokter Gigi' }],
        clinics: [{ id: 2, name: 'Poli Gigi' }], days: ['senin'], today: '2026-10-24',
        schedules: emptyPage, holidays: pageOf([{ id: 1, tanggal: '2026-10-28', nama: 'Libur bersama', jenis: 'instansi', sumber: null }]), exceptions: emptyPage,
    });
    assert.match(html, /Jadwal praktik dokter gigi/);
    assert.match(html, /reservasi poli gigi/);
    assert.match(html, /Tambah jadwal praktik/);
    assert.match(html, /Simpan pengecualian/);
    for (const id of ['asn-clinic', 'schedule-clinic', 'exception-clinic']) {
        const select = html.match(new RegExp(`<button[^>]*id="${id}"[^>]*>`))?.[0];
        assert.ok(select, id);
        assert.match(select, /disabled=""/);

    }
    assert.doesNotMatch(html, /id="holiday-date"|Simpan hari libur|Hapus libur|id="schedule-clinic-filter"/);
});

for (const [history, expectedState] of [
    ['Tidak ada riwayat alergi menurut pasien', 'none'], ['Tidak ada alergi.', 'none'],
    ['Belum diketahui', 'unknown'], [null, 'unknown'], ['Penisilin', 'reported'],
    ['Tidak ada alergi makanan; alergi penisilin', 'reported'],
]) {
    test(`allergy history uses a neutral card for ${history ?? 'missing history'}`, async () => {
        const { AllergyHistory } = await server.ssrLoadModule('/resources/js/components/kunjungan/allergy-history.tsx');
        const html = renderToString(createElement(AllergyHistory, { value: history }));
        assert.equal(html.includes('bg-red-50'), expectedState === 'reported');
        assert.equal(html.includes('border-neutral-200'), expectedState !== 'reported');
        const { getAllergyStatus } = await server.ssrLoadModule('/resources/js/lib/allergy-status.ts');
        assert.equal(getAllergyStatus([history]).state, expectedState);
    });
}

test('a reported allergy remains visible when another source records no allergy', async () => {
    const { getAllergyStatus } = await server.ssrLoadModule('/resources/js/lib/allergy-status.ts');
    assert.deepEqual(getAllergyStatus(['Tidak ada riwayat alergi menurut pasien', 'Penisilin', 'Penisilin']), { state: 'reported', label: 'Penisilin' });
});

test('service detail resumes an unfinished assessment and shows a neutral negative allergy history', async () => {
    const html = await renderPage('pelayanan/kunjungan/show', {
        visit: { ...visitDetail, status: 'screening', patient: { ...visitDetail.patient, allergies: 'Tidak ada riwayat alergi menurut pasien' }, progress: [{ label: 'Pengkajian awal perawat', done: false, active: true, note: 'Draf pengkajian belum dikirim ke dokter.' }] },
        permissions: { editVisit: false, viewRme: true, processScreening: true }, backUrl: '/perawat/dashboard',
        links: { edit: null, patient: '/pelayanan/pasien/7', screening: '/pelayanan/screening/42', medicalRecord: '/pelayanan/pasien/7/rekam-medis' },
    });
    assert.match(html, /Lanjutkan pengkajian awal/);
    assert.match(html, /Draf pengkajian belum dikirim ke dokter/);
    assert.doesNotMatch(html, /bg-red-50|>Mulai pengkajian awal</);
});

for (const [name, controlProps] of [
    ['Input', {}], ['Textarea', {}], ['Select', { value: '' }], ['DatePicker', { value: '' }], ['DateRangePicker', { from: '', to: '', onChange: () => {} }],
]) {
    test(`${name} shares field error styling and accessible descriptions`, async () => {
        const { Field } = await server.ssrLoadModule('/resources/js/components/ui/field.tsx');
        const file = { Input: 'input', Textarea: 'textarea', Select: 'select', DatePicker: 'date-picker', DateRangePicker: 'date-range-picker' }[name];
        const module = await server.ssrLoadModule(`/resources/js/components/ui/${file}.tsx`);
        const control = createElement(module[name], { ...controlProps, id: 'control', 'aria-describedby': 'help' }, name === 'Select' ? createElement('option', { value: '' }, 'Pilih') : undefined);
        const html = renderToString(createElement(Field, { htmlFor: 'control', label: 'Isian', error: 'Periksa isian.', required: true }, control));
        assert.match(html, /aria-invalid="true"/);
        assert.match(html, /aria-describedby="help control-error"/);
        assert.match(html, /aria-required="true"/);
        assert.match(html, /aria-invalid:border-red-500/);
        assert.match(html, /id="control-error" role="alert"/);
    });
}

test('calendar dates reject impossible values and retain leap-day and month navigation rules', async () => {
    const { fromDateKey, toDateKey, clampDate, calendarNavigationDate, isDateWithinBounds, isMonthWithinBounds } = await server.ssrLoadModule('/resources/js/lib/calendar.ts');
    for (const value of ['2026-02-29', '2026-02-31', '2026-13-01', '2026-00-10', '2026-01-00', 'not-a-date']) {
        assert.equal(fromDateKey(value), null, value);
    }
    assert.equal(toDateKey(fromDateKey('2024-02-29')), '2024-02-29');
    assert.equal(toDateKey(calendarNavigationDate(fromDateKey('2026-01-31'), 'PageDown')), '2026-02-28');
    assert.equal(toDateKey(calendarNavigationDate(fromDateKey('2024-03-31'), 'PageUp')), '2024-02-29');
    assert.equal(toDateKey(calendarNavigationDate(fromDateKey('2026-10-24'), 'Home')), '2026-10-19');
    assert.equal(toDateKey(clampDate(fromDateKey('2026-10-24'), '2026-10-25', undefined)), '2026-10-25');
    assert.equal(isDateWithinBounds(fromDateKey('2026-10-24'), '2026-10-25'), false);
    assert.equal(isMonthWithinBounds(fromDateKey('2026-09-01'), '2026-10-25'), false);
});

test('date controls preserve partial periods and exclude disabled fields from native validation', async () => {
    const { DatePicker } = await server.ssrLoadModule('/resources/js/components/ui/date-picker.tsx');
    const { DateRangePicker } = await server.ssrLoadModule('/resources/js/components/ui/date-range-picker.tsx');
    const invalid = renderToString(createElement(DatePicker, { value: '2026-02-31' }));
    assert.match(invalid, /Pilih tanggal/);
    assert.doesNotMatch(invalid, /3 Maret/);
    for (const Component of [DatePicker, DateRangePicker]) {
        const html = renderToString(createElement(Component, { value: '', from: '', to: '', required: true, disabled: true, onChange: () => {} }));
        const nativeDates = html.match(/<input\b[^>]*type="date"[^>]*>/g) ?? [];
        assert.ok(nativeDates.length > 0);
        nativeDates.forEach((date) => assert.match(date, /disabled=""/));
    }
    const until = renderToString(createElement(DateRangePicker, { from: '', to: '2026-10-24', onChange: () => {} }));
    assert.match(until, /Sampai 24 Okt 2026/);
});

test('medical record fills available width, displays dental anthropometry and prints collapsed visit content', async () => {
    const screening = { systolic: 120, diastolic: 80, pulse: 80, temperature: 36.5, oxygenSaturation: 98, weight: 60, height: 160, bmi: 23.44, respiration: 18, complaint: 'Nyeri gigi', medicalHistory: null, familyHistory: null, allergyHistory: 'Tidak ada alergi', fallRisk: null, painScale: 0, dentalPainLocation: 'Geraham', dentalPainTriggers: [], dentalPainDuration: null, dentalMedicalRisks: ['belum_diketahui'], dentalInfectionHistory: [], dentalNotes: null };
    const visit = { id: 1, number: 'K-001', clinic: 'Poli Gigi', clinicType: 'gigi', doctor: 'Dokter Gigi', date: '24/10/2026', status: 'screening', screening, examination: null, prescriptions: [], treatments: [], odontogram: [], attachments: [] };
    const html = await renderPage('pelayanan/pasien/rekam-medis', {
        patient: { id: 7, name: 'Pasien Uji', medicalRecordNumber: 'RM-007', nik: null, gender: 'P', age: 30, bloodType: null, allergies: 'Tidak ada alergi' },
        visits: [visit, { ...visit, id: 2, number: 'K-002', status: 'batal' }, { ...visit, id: 3, number: 'K-003', status: 'menunggu', isReservation: true }], backUrl: '/pelayanan/pasien/7', canSelectClinic: false,
        filters: { search: '', poliklinik_id: '', status: '', dari: '', sampai: '' }, clinics: [], pagination: { ...singlePage, total: 2 },
    });
    for (const expected of ['xl:grid-cols-5', 'Berat / tinggi', '60 kg', '160 cm', 'Pengkajian awal berlangsung', 'Kunjungan dibatalkan', 'Reservasi', 'Belum dapat diketahui', 'aria-controls="medical-record-visit-1"', 'id="medical-record-visit-2"']) {
        assert.ok(html.includes(expected), expected);
    }
    assert.equal((html.match(/print:block hidden/g) ?? []).length, 3, 'collapsed visits remain available for printing');
    assert.doesNotMatch(html, /bg-emerald-50|xl:col-span-4|lg:grid-cols-2/);
});

for (const page of ['index', 'history']) {
    test(`screening ${page} date filter uses the shared date picker`, async () => {
        const html = await renderPage(`pelayanan/screening/${page}`, {
            filters: { search: '', tanggal: '', poliklinikId: 1 }, clinics: [], canSelectClinic: false,
            visits: pageOf([]), screenings: pageOf([]),
        });
        const id = page === 'index' ? 'screening-queue-date' : 'screening-history-date';
        const trigger = html.match(new RegExp(`<button[^>]*id="${id}"[^>]*>`))?.[0];
        assert.ok(trigger, id);
        assert.match(trigger, /aria-haspopup="dialog"/);
        assert.doesNotMatch(html, new RegExp(`<input[^>]*id="${id}"`));
    });
}

function printThemePage({ savedTheme = null, prefersDark = false, storageBlocked = false } = {}) {
    const template = readFileSync(new URL('../resources/views/screening/print.blade.php', import.meta.url), 'utf8');
    const script = template.match(/<script>([\s\S]*?)<\/script>/)?.[1];
    assert.ok(script);
    const elements = new Map();
    const events = new Map();
    const stored = new Map(savedTheme ? [['clinic-theme', savedTheme]] : []);
    function element() {
        const classes = new Set();
        const attributes = new Map();
        const listeners = new Map();
        return {
            attributes, listeners,
            classList: {
                toggle(name, enabled) { const next = enabled ?? !classes.has(name); if (next) { classes.add(name); } else { classes.delete(name); } return next; },
                contains(name) { return classes.has(name); },
            },
            setAttribute(name, value) { attributes.set(name, value); },
            addEventListener(name, listener) { listeners.set(name, listener); },
        };
    }
    const root = element();
    const system = { matches: prefersDark, addEventListener(name, listener) { events.set('system:' + name, listener); } };
    const storage = { getItem(key) { if (storageBlocked) { throw new Error('Storage blocked'); } return stored.get(key) ?? null; }, setItem(key, value) { if (storageBlocked) { throw new Error('Storage blocked'); } stored.set(key, value); } };
    runInNewContext(script, {
        document: { documentElement: root, getElementById(id) { return elements.get(id); }, addEventListener(name, listener) { events.set('document:' + name, listener); } },
        window: { localStorage: storage, matchMedia() { return system; }, addEventListener(name, listener) { events.set('window:' + name, listener); } },
    });
    const initialDark = root.classList.contains('dark');
    for (const id of ['themeToggleBtn', 'sunIcon', 'moonIcon']) { elements.set(id, element()); }
    events.get('document:DOMContentLoaded')();
    return { root, initialDark, elements, events, stored, system, click: () => elements.get('themeToggleBtn').listeners.get('click')() };
}

for (const [savedTheme, prefersDark, expectedDark] of [['dark', false, true], ['light', true, false], [null, true, true], [null, false, false]]) {
    test('print header honors saved theme ' + (savedTheme ?? 'system') + ' before rendering with OS dark ' + prefersDark, () => {
        const page = printThemePage({ savedTheme, prefersDark });
        assert.equal(page.initialDark, expectedDark);
        assert.equal(page.root.classList.contains('dark'), expectedDark);
        assert.equal(page.elements.get('themeToggleBtn').attributes.get('aria-pressed'), String(expectedDark));
        assert.equal(page.elements.get('themeToggleBtn').attributes.get('aria-label'), expectedDark ? 'Aktifkan tema terang' : 'Aktifkan tema gelap');
        assert.equal(page.elements.get('sunIcon').classList.contains('hidden'), !expectedDark);
        assert.equal(page.elements.get('moonIcon').classList.contains('hidden'), expectedDark);
    });
}

test('print header toggle persists selection and remains usable when storage is blocked', () => {
    for (const storageBlocked of [false, true]) {
        const page = printThemePage({ storageBlocked });
        page.click();
        assert.equal(page.root.classList.contains('dark'), true);
        assert.equal(page.elements.get('themeToggleBtn').attributes.get('title'), 'Aktifkan tema terang');
        if (!storageBlocked) { assert.equal(page.stored.get('clinic-theme'), 'dark'); }
        page.click();
        assert.equal(page.root.classList.contains('dark'), false);
        assert.equal(page.elements.get('themeToggleBtn').attributes.get('aria-pressed'), 'false');
    }
});

test('print header follows system changes until an explicit theme is chosen and synchronizes other tabs', () => {
    const page = printThemePage();
    page.system.matches = true;
    page.events.get('system:change')();
    assert.equal(page.root.classList.contains('dark'), true);
    page.click();
    page.events.get('system:change')();
    assert.equal(page.root.classList.contains('dark'), false);
    page.events.get('window:storage')({ key: 'clinic-theme', newValue: 'dark' });
    assert.equal(page.root.classList.contains('dark'), true);
    page.system.matches = false;
    page.events.get('window:storage')({ key: null, newValue: null });
    assert.equal(page.root.classList.contains('dark'), false);
});

function examinationProps(clinicType = 'gigi', overrides = {}) {
    return {
        initialTab: 'odontogram', doctors: [{ id: 1, name: 'Dokter Uji' }], treatments: [], medicines: [], clinics: [],
        diagnosisCodeSystems: [{ value: 'icd10_who', label: 'ICD-10 WHO' }], procedureCatalogAvailable: false, procedureSearchUrl: '/pelayanan/icd9cm/search',
        dentalOptions: { teeth: ['11', '46', '51', '85'], surfaces: { W: 'Seluruh gigi', O: 'Oklusal', D: 'Distal' }, findings: { caries: 'Karies', sound: 'Sehat', missing: 'Hilang' } },
        today: '2026-10-24', correspondenceTemplates: [],
        visit: {
            id: 1, number: 'K-001', status: 'pemeriksaan', canClaim: false, doctorId: 1, clinicId: 1, clinicType, clinic: 'Poli ' + clinicType,
            patient: { id: 1, name: 'Pasien Uji', medicalRecordNumber: 'RM-001', gender: 'P', age: 35, bloodType: null, allergies: 'Tidak ada alergi' },
            examinationFormDefaults: { anamnesis: 'Nyeri sejak kemarin', currentHistory: null, pastHistory: null, familyHistory: null, allergyHistory: 'Tidak ada alergi' },
            screening: null,
            examination: { doctorId: 1, status: 'draft', anamnesis: 'Nyeri sejak kemarin', physicalExam: 'Kavitas gigi 46', physicalSystems: {}, integrityValid: null, signedAt: null, versions: [] },
            diagnoses: [{ id: 1, code: 'K02.9', name: 'Karies', type: 'utama' }], treatments: [],
            odontogram: clinicType === 'gigi' ? [{ toothFdi: '46', surface: 'O', findingCode: 'caries', notes: 'Kavitas kecil' }] : [],
            prescription: { status: null, items: [], printUrl: null }, letters: [], referrals: [], ...overrides,
        },
    };
}

test('dental examination places the FDI entry before the findings table within its workflow tab', async () => {
    const html = await renderPage('pelayanan/pemeriksaan/show', examinationProps());
    const panel = html.match(/<section[^>]*id="[^"]*-panel-odontogram"[\s\S]*?Tabel temuan odontogram[\s\S]*?<\/table>/)?.[0];
    assert.ok(panel, 'the odontogram tab should contain both its entry form and recorded findings');
    assert.ok(panel.indexOf('id="dental-tooth"') < panel.indexOf('Tabel temuan odontogram'));
    assert.match(panel, /<table[\s\S]*?Kavitas kecil/);
    assert.match(panel, /aria-label="Ubah temuan gigi 46 permukaan O"/);
    assert.match(panel, /aria-label="Batalkan temuan gigi 46 permukaan O"/);
    assert.match(html, /aria-selected="true"[^>]*data-value="odontogram"/);
    assert.match(html, /Pemeriksaan &amp; odontogram/);
    assert.doesNotMatch(html, /Diagram Anatomi &amp; Lokasi Keluhan Tubuh/);
    assert.doesNotMatch(html, /id="riwayat-alergi-dokter"[^>]*class="[^"]*border-red|border-red-300[^>]*id="riwayat-alergi-dokter"/);
    assert.match(html, /Kesiapan finalisasi/);
    assert.doesNotMatch(html, /Isian belum disimpan/);
});

test('general examination keeps the body map with physical examination and omits the dental workflow', async () => {
    const html = await renderPage('pelayanan/pemeriksaan/show', examinationProps('umum'));
    assert.match(html, /aria-selected="true"[^>]*data-value="anamnesis"/);
    assert.match(html, /Diagram Anatomi &amp; Lokasi Keluhan Tubuh/);
    assert.match(examinationPanel(html, 'pemeriksaan'), /Diagram Anatomi &amp; Lokasi Keluhan Tubuh/);
    assert.doesNotMatch(examinationPanel(html, 'anamnesis'), /Diagram Anatomi|id="pemeriksaan_fisik"/);
    assert.doesNotMatch(html, /data-value="odontogram"|id="dental-tooth"|id="extraoral"/);
});

test('dental claim and integrity failures do not expose editable correction actions', async () => {
    const props = examinationProps('gigi', { canClaim: true, examination: null });
    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    assert.match(html, /Kunjungan belum ditugaskan/);
    assert.doesNotMatch(html, /Pemeriksaan terkunci|id="dental-tooth"/);
    assert.match(html, /<button[^>]*aria-label="Batalkan temuan gigi 46 permukaan O"[^>]*disabled=""/);

    const signed = examinationProps('gigi');
    signed.visit.status = 'selesai';
    signed.visit.examination = { ...signed.visit.examination, status: 'selesai', signedAt: '24 Oktober 2026', integrityValid: false };
    const invalid = await renderPage('pelayanan/pemeriksaan/show', signed);
    assert.match(invalid, /Integritas catatan perlu diperiksa/);
    assert.doesNotMatch(invalid, /id="dental-tooth"|id="addendum-reason"/);
});

test('a valid signed dental examination offers reasoned odontogram corrections', async () => {
    const props = examinationProps('gigi');
    props.visit.status = 'selesai';
    props.visit.examination = { ...props.visit.examination, status: 'selesai', signedAt: '24 Oktober 2026', integrityValid: true };
    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    assert.match(html, /id="dental-reason"/);
    assert.match(html, /Simpan addendum odontogram/);
    assert.match(html, /id="addendum-reason"/);
    assert.doesNotMatch(html, /<button[^>]*aria-label="Ubah temuan gigi 46 permukaan O"[^>]*disabled/);
});

for (const [clinicType, groupLabel, examLabel] of [['gigi', 'Operasional', 'Pemeriksaan Gigi &amp; Mulut'], ['umum', 'Operasional', 'Pemeriksaan Umum']]) {
    test(clinicType + ' doctor navigation uses clinic-specific labels without changing route access', async () => {
        const { default: Layout } = await server.ssrLoadModule('/resources/js/layouts/app-layout.tsx');
        const result = await createInertiaApp({
            page: { component: 'pelayanan/pemeriksaan/show', props: { errors: {}, flash: {}, auth: { user: { id: 1, name: 'Dokter', role: 'dokter', roleLabel: 'Dokter', clinicType } } }, url: '/pelayanan/pemeriksaan/1', version: null, clearHistory: false, encryptHistory: false },
            resolve: () => () => createElement(Layout, null, createElement('p', null, 'Pemeriksaan')),
            render: renderToString, setup: ({ App, props }) => createElement(App, props),
        });
        assert.ok(result.body.includes(groupLabel));
        assert.match(result.body, />Ringkasan<|>Ringkasan<\/h2>/);
        assert.match(result.body, />Penunjang<|>Penunjang<\/h2>/);
        assert.match(result.body, />Laporan<|>Laporan<\/h2>/);
        assert.ok(result.body.includes(examLabel));
        assert.match(result.body, /href="\/pendaftaran\/jadwal-praktik"/);
        assert.match(result.body, /max-w-none/);
    });
}


test('removing one selected dental surface keeps the tooth selected for treatment until its last selected finding is removed', async () => {
    const { selectedOdontogramTeeth } = await server.ssrLoadModule('/resources/js/lib/odontogram-selection.ts');
    const findings = [{ toothFdi: '46', surface: 'O' }, { toothFdi: '46', surface: 'D' }, { toothFdi: '85', surface: 'W' }];
    assert.deepEqual(selectedOdontogramTeeth(findings, ['46-O', '46-D', '85-W']), ['46', '85']);
    assert.deepEqual(selectedOdontogramTeeth(findings, ['46-D', '85-W']), ['46', '85']);
    assert.deepEqual(selectedOdontogramTeeth(findings, ['85-W', '48-O']), ['85']);
    assert.deepEqual(selectedOdontogramTeeth(findings, []), []);
});

function examinationPanel(html, value) {
    const opening = html.match(new RegExp('<section\\b[^>]*id="[^"]*-panel-' + value + '"[^>]*>'));
    assert.ok(opening, 'missing examination panel ' + value);
    const tags = /<\/?section\b[^>]*>/g;
    tags.lastIndex = opening.index + opening[0].length;
    let depth = 1;
    for (let tag = tags.exec(html); tag; tag = tags.exec(html)) {
        depth += tag[0].startsWith('</') ? -1 : 1;
        if (depth === 0) return html.slice(opening.index, tags.lastIndex);
    }
    assert.fail('unclosed examination panel ' + value);
}

for (const clinicType of ['gigi', 'umum']) {
    test(clinicType + ' clinical workflow separates history, physical examination, differential diagnosis and the follow-up section', async () => {
        const props = examinationProps(clinicType);
        props.initialTab = 'anamnesis';
        const html = await renderPage('pelayanan/pemeriksaan/show', props);
        const history = examinationPanel(html, 'anamnesis');
        const physical = examinationPanel(html, clinicType === 'gigi' ? 'odontogram' : 'pemeriksaan');
        const diagnosis = examinationPanel(html, 'diagnosa');
        assert.match(history, /id="anamnesis"/);
        assert.match(history, /id="riwayat-alergi-dokter"/);
        assert.match(history, /Simpan anamnesis/);
        assert.doesNotMatch(history, /id="pemeriksaan_fisik"|id="extraoral"|id="diagnosis-banding"|id="kontrol_berikutnya"|id="edukasi"/);
        assert.match(physical, /id="pemeriksaan_fisik"/);
        assert.match(physical, /Simpan pemeriksaan fisik/);
        assert.doesNotMatch(physical, /id="anamnesis"|id="diagnosis-banding"|id="kontrol_berikutnya"/);
        assert.match(diagnosis, /id="diagnosis-banding"/);
        assert.match(diagnosis, /id="kode_icd10"/);
        assert.match(diagnosis, /Simpan diagnosis banding/);
        assert.ok(diagnosis.indexOf('Diagnosis tercatat') < diagnosis.indexOf('id="diagnosis-banding"'), 'differential diagnosis follows recorded diagnoses');
        assert.doesNotMatch(diagnosis, /id="kontrol_berikutnya"|id="anamnesis"/);
        const followUp = html.slice(html.indexOf('id="rencana-tindak-lanjut"'));
        const disclosure = followUp.match(/<details\b[^>]*id="rencana-details"[^>]*>/)?.[0];
        assert.ok(disclosure, 'follow-up section can expand without unmounting its form');
        assert.doesNotMatch(disclosure, /\sopen(?:=|\s|>)/, 'follow-up starts closed');
        assert.match(followUp, /<summary\b[^>]*aria-controls="rencana-content"/);
        assert.match(followUp, /id="kontrol_berikutnya"/);
        assert.match(followUp, /id="edukasi"/);
        assert.match(followUp, /id="catatan"/);
        assert.match(followUp, /Simpan rencana tindak lanjut/);
        assert.ok(html.indexOf('id="rencana-tindak-lanjut"') > html.indexOf('-panel-rujukan'));
        assert.doesNotMatch(html, /id="ohis"|Indeks kebersihan mulut|Saran diagnosis dari keluhan|Hasil katalog terminologi/);
        if (clinicType === 'gigi') {
            assert.match(physical, /id="extraoral"/);
            assert.match(physical, /aria-label="Belum ada gigi dipilih"[^>]*role="img"[^>]*><svg/);
            assert.doesNotMatch(physical, />Belum diisi</);
            assert.match(physical, /id="dental-tooth"/);
            assert.ok(physical.indexOf('id="dental-tooth"') < physical.indexOf('Tabel temuan odontogram'));
        } else {
            assert.match(physical, /Diagram Anatomi/);
            assert.match(physical, /aria-label="Sistem Respirasi"[^>]*role="img"[^>]*><svg[^>]*lucide-wind/);
            assert.doesNotMatch(physical, /id="extraoral"|id="dental-tooth"/);
        }
    });
}

test('signed dental corrections follow the same clinical groups and omit OHIS', async () => {
    const props = examinationProps('gigi');
    props.visit.status = 'selesai';
    props.visit.examination = { ...props.visit.examination, signedAt: '24 Oktober 2026', status: 'selesai', integrityValid: true, followUpDate: '2026-10-30', differentialDiagnosis: 'Periodontitis' };
    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    assert.match(html, /id="addendum-history-title"/);
    assert.match(html, /id="addendum-exam-title"/);
    assert.match(html, /id="addendum-diagnosis-title"/);
    assert.match(html, /id="addendum-plan-title"/);
    assert.ok(html.indexOf('id="addendum-diagnosis-title"') < html.indexOf('id="addendum-diagnosis-banding"'));
    assert.ok(html.indexOf('id="addendum-plan-title"') < html.indexOf('id="addendum-kontrol"'));
    assert.doesNotMatch(html, /id="addendum-ohis"|id="ohis"|OHIS/);
});

async function renderRoleNavigation(role, url, clinicType = null) {
    const { default: Layout } = await server.ssrLoadModule('/resources/js/layouts/app-layout.tsx');
    const result = await createInertiaApp({
        page: { component: 'dashboard/index', props: { errors: {}, flash: {}, auth: { user: { id: 1, name: 'Staf Uji', role, roleLabel: role, clinicType } } }, url, version: null, clearHistory: false, encryptHistory: false },
        resolve: () => () => createElement(Layout, null, createElement('p', null, 'Halaman uji')),
        render: renderToString, setup: ({ App, props }) => createElement(App, props),
    });
    return result.body;
}

for (const [role, clinicType, dashboard, primary, categories, linkCount] of [
    ['admin', null, '/', '/pelayanan/kunjungan', ['Ringkasan', 'Operasional', 'Data Master', 'Pengelolaan', 'Laporan', 'Akun'], 23],
    ['pendaftaran', null, '/pendaftaran', '/pendaftaran/pendaftaran-baru', ['Ringkasan', 'Operasional', 'Pengelolaan', 'Laporan', 'Akun'], 9],
    ['perawat', 'umum', '/perawat/dashboard', '/pelayanan/screening', ['Ringkasan', 'Operasional', 'Koordinasi Layanan', 'Laporan', 'Akun'], 8],
    ['perawat', 'gigi', '/perawat/dashboard', '/pelayanan/screening', ['Ringkasan', 'Operasional', 'Koordinasi Layanan', 'Laporan', 'Akun'], 8],
    ['dokter', 'umum', '/dokter', '/pelayanan/pemeriksaan', ['Ringkasan', 'Operasional', 'Penunjang', 'Laporan', 'Akun'], 9],
    ['dokter', 'gigi', '/dokter', '/pelayanan/pemeriksaan', ['Ringkasan', 'Operasional', 'Penunjang', 'Laporan', 'Akun'], 9],
    ['farmasi', null, '/farmasi/dashboard', '/pelayanan/farmasi', ['Ringkasan', 'Operasional', 'Pengelolaan', 'Akun'], 4],
    ['manajemen', null, '/', '/laporan/kunjungan', ['Ringkasan', 'Laporan', 'Akun'], 5],
]) {
    test(role + ' ' + (clinicType ?? '') + ' groups its menus and places primary work immediately after dashboard', async () => {
        const html = await renderRoleNavigation(role, primary, clinicType);
        const navigation = html.match(/<nav\b[^>]*aria-label="Navigasi utama"[^>]*>([\s\S]*?)<\/nav>/)?.[1];
        assert.ok(navigation);
        const links = [...navigation.matchAll(/<a\b[^>]*href="([^"]+)"/g)].map((match) => match[1]);
        assert.deepEqual(links.slice(0, 2), [dashboard, primary]);
        assert.equal(links.length, linkCount, 'regrouping retains the role menu destinations');
        assert.equal(new Set(links).size, links.length, 'each destination appears once');
        assert.ok(links.includes('/profile'));
        const labels = [...navigation.matchAll(/<h2\b[^>]*>([^<]+)<\/h2>/g)].map((match) => match[1]);
        assert.deepEqual(labels, categories);
        const activeLinks = navigation.match(/<a\b[^>]*aria-current="page"[^>]*>/g) ?? [];
        assert.equal(activeLinks.length, 1);
        assert.ok(activeLinks[0].includes('href="' + primary + '"'));
    });
}

test('doctor breadcrumbs omit links from other role menus while admin retains its own parent links', async () => {
    const doctor = await renderRoleNavigation('dokter', '/pelayanan/pasien/7/rekam-medis', 'gigi');
    const doctorBreadcrumb = doctor.match(/<nav\b[^>]*aria-label="Breadcrumb"[^>]*>([\s\S]*?)<\/nav>/)?.[1];
    assert.ok(doctorBreadcrumb);
    assert.doesNotMatch(doctorBreadcrumb, /href="\/pelayanan\/pasien"/);
    const admin = await renderRoleNavigation('admin', '/master/obat/7/stok');
    const adminBreadcrumb = admin.match(/<nav\b[^>]*aria-label="Breadcrumb"[^>]*>([\s\S]*?)<\/nav>/)?.[1];
    assert.match(adminBreadcrumb, /href="\/master\/obat"/);
});


test('clinical answer normalization keeps unknown answers separate from confirmed absence and real history', async () => {
    const { normalizeClinicalAnswer } = await server.ssrLoadModule('/resources/js/lib/clinical-answer.ts');
    const { isReportedAllergy } = await server.ssrLoadModule('/resources/js/lib/allergy-status.ts');
    for (const value of [null, '', 'Belum dicatat', ' Belum di catat. ', 'BELUM   DIISI!', 'Belum dikaji', 'belum_diketahui', '—']) {
        assert.equal(normalizeClinicalAnswer(value), null);
        assert.equal(isReportedAllergy(value), false);
    }
    assert.equal(normalizeClinicalAnswer('Tidak ada riwayat penyakit keluarga menurut pasien'), 'Tidak ada riwayat penyakit keluarga menurut pasien');
    assert.equal(normalizeClinicalAnswer('  Hipertensi pada ayah  '), 'Hipertensi pada ayah');
    assert.equal(normalizeClinicalAnswer('Belum dicatat; pasien menyebut alergi penisilin'), 'Belum dicatat; pasien menyebut alergi penisilin');
    assert.equal(isReportedAllergy('Belum dicatat; pasien menyebut alergi penisilin'), true);
});

for (const clinicType of ['gigi', 'umum']) {
    test(clinicType + ' doctor history leaves placeholders empty and offers explicit absence confirmation', async () => {
        const props = examinationProps(clinicType);
        props.initialTab = 'anamnesis';
        props.visit.patient.allergies = 'Belum diisi';
        props.visit.examinationFormDefaults = { ...props.visit.examinationFormDefaults, currentHistory: 'Belum diketahui', pastHistory: 'Belum di catat', familyHistory: 'Belum dicatat', allergyHistory: 'BELUM DIISI.' };
        const html = await renderPage('pelayanan/pemeriksaan/show', props);
        const history = examinationPanel(html, 'anamnesis');
        for (const id of ['rps', 'rpd', 'riwayat-keluarga', 'riwayat-alergi-dokter']) {
            const textarea = history.match(new RegExp('<textarea\\b[^>]*id="' + id + '"[^>]*>([\\s\\S]*?)<\\/textarea>'));
            assert.ok(textarea, id + ' should be available');
            assert.equal(textarea[1], '', id + ' should not copy a placeholder into a clinical answer');
        }
        for (const id of ['rpd', 'riwayat-keluarga', 'riwayat-alergi-dokter']) {
            const checkbox = history.match(new RegExp('<button\\b[^>]*id="' + id + '-none"[^>]*>'))?.[0];
            assert.ok(checkbox);
            assert.match(checkbox, /aria-checked="false"/);
        }
        assert.match(history, /hanya setelah dikonfirmasi/);
        assert.doesNotMatch(html, /Peringatan alergi/);
        assert.doesNotMatch(history, /border-red-300/);
    });
}

test('confirmed absence is displayed explicitly while real inherited histories remain intact', async () => {
    const props = examinationProps('gigi');
    props.visit.examinationFormDefaults.pastHistory = 'Tidak ada riwayat penyakit dahulu menurut pasien';
    props.visit.examinationFormDefaults.familyHistory = 'Hipertensi pada ayah';
    props.visit.examinationFormDefaults.allergyHistory = 'Penisilin';
    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    const history = examinationPanel(html, 'anamnesis');
    assert.match(history, /id="rpd"[^>]*>Tidak ada riwayat penyakit dahulu menurut pasien<\/textarea>/);
    assert.match(history.match(/<button\b[^>]*id="rpd-none"[^>]*>/)?.[0], /aria-checked="true"/);
    assert.match(history, /id="riwayat-keluarga"[^>]*>Hipertensi pada ayah<\/textarea>/);
    assert.match(history.match(/<button\b[^>]*id="riwayat-keluarga-none"[^>]*>/)?.[0], /aria-checked="false"/);
    assert.match(history, /id="riwayat-alergi-dokter"[^>]*>Penisilin<\/textarea>/);
    assert.match(html, /Peringatan alergi/);
});


test('dental diagnosis starts with saved examination findings and counts distinct teeth across surfaces', async () => {
    const props = examinationProps('gigi');
    props.initialTab = 'diagnosa';
    props.visit.examination.extraoralExam = 'Tidak tampak pembengkakan';
    props.visit.odontogram.push(
        { toothFdi: '46', surface: 'D', findingCode: 'caries', notes: 'Kavitas distal' },
        { toothFdi: '85', surface: 'W', findingCode: 'legacy', notes: null },
    );

    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    const panel = examinationPanel(html, 'diagnosa');
    const summary = panel.slice(0, panel.indexOf('id="tab-diagnosa"')).replace(/<!--.*?-->/g, '');

    assert.match(html, /aria-selected="true"[^>]*data-value="diagnosa"/);
    assert.match(summary, /Ringkasan pemeriksaan &amp; odontogram/);
    assert.match(summary, />2 gigi</);
    assert.match(summary, />3 temuan</);
    assert.match(summary, /Nyeri sejak kemarin/);
    assert.match(summary, /Kavitas gigi 46/);
    assert.match(summary, /Tidak tampak pembengkakan/);
    assert.match(summary, /aria-label="Ringkasan temuan odontogram"/);
    assert.match(summary, /Oklusal[\s\S]*Kavitas kecil[\s\S]*Distal[\s\S]*Kavitas distal/);
    assert.match(summary, />legacy</, 'unrecognized historical finding codes must remain visible');
    assert.match(summary, /aria-label="Tinjau pemeriksaan dan odontogram"/);
    assert.doesNotMatch(summary, /Ada perubahan belum disimpan/);
});

test('dental treatment starts with the primary diagnosis, coding system and saved differential diagnosis', async () => {
    const props = examinationProps('gigi');
    props.initialTab = 'tindakan';
    props.visit.diagnoses = [
        { id: 2, code: 'K05.1', codeSystem: 'legacy_system', name: 'Gingivitis kronis', type: 'tambahan' },
        { id: 1, code: 'K02.9', codeSystem: 'icd10_who', name: 'Karies dentin', type: 'utama' },
    ];
    props.visit.examination.differentialDiagnosis = 'Pulpitis reversibel';

    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    const panel = examinationPanel(html, 'tindakan');
    const summary = panel.slice(0, panel.indexOf('id="tab-tindakan"'));

    assert.match(html, /aria-selected="true"[^>]*data-value="tindakan"/);
    assert.match(summary, /Ringkasan diagnosis/);
    assert.match(summary, /aria-label="Ringkasan diagnosis tercatat"/);
    assert.ok(summary.indexOf('Karies dentin') < summary.indexOf('Gingivitis kronis'));
    assert.match(summary, /ICD-10 WHO/);
    assert.match(summary, /legacy_system/);
    assert.match(summary, /Diagnosis banding[\s\S]*Pulpitis reversibel/);
    assert.match(summary, /aria-label="Tinjau diagnosis"/);
    assert.doesNotMatch(summary, /Pastikan tepat satu diagnosis utama/);
    assert.equal(props.visit.diagnoses[0].code, 'K05.1', 'summarizing must not reorder the original recorded diagnoses');
});

test('dental workflow summaries preserve unknown assessments and explain missing diagnoses without inventing normal results', async () => {
    const props = examinationProps('gigi', { examination: null, diagnoses: [], odontogram: [] });

    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    const diagnosis = examinationPanel(html, 'diagnosa');
    const treatment = examinationPanel(html, 'tindakan');

    assert.match(diagnosis, /Belum ada temuan odontogram tersimpan/);
    assert.match(diagnosis, /Pemeriksaan intraoral<\/dt><dd[^>]*>Belum dicatat/);
    assert.match(diagnosis, /Pemeriksaan ekstraoral<\/dt><dd[^>]*>Belum dicatat/);
    assert.doesNotMatch(diagnosis.slice(0, diagnosis.indexOf('id="tab-diagnosa"')), /Nyeri sejak kemarin|Normal|Tidak ada kelainan/);
    assert.match(treatment, /Belum ada diagnosis tersimpan/);
    assert.match(treatment, /Catat diagnosis pada tab Diagnosis sebelum melanjutkan/);
    assert.match(treatment, /Diagnosis banding<\/dt><dd[^>]*>Belum dicatat/);

    const general = await renderPage('pelayanan/pemeriksaan/show', examinationProps('umum'));
    assert.doesNotMatch(general, /id="dental-examination-summary"|id="dental-diagnosis-summary"/);
});

test('dental summaries identify pending edits and require exactly one main diagnosis', async () => {
    const { DentalExaminationSummary, DiagnosisSummary: DentalDiagnosisSummary } = await server.ssrLoadModule('/resources/js/components/kunjungan/clinical-workflow-summary.tsx');
    const diagnosis = (hasUnsavedChanges, diagnoses) => renderToString(createElement(DentalDiagnosisSummary, {
        diagnoses, differentialDiagnosis: 'Diagnosis banding tersimpan', codeSystems: [], hasUnsavedChanges, onReview() {},
    }));
    const additional = [{ id: 1, code: 'K02.9', name: 'Karies tersimpan', type: 'tambahan' }];

    const examination = renderToString(createElement(DentalExaminationSummary, {
        anamnesis: 'Keluhan tersimpan', intraoralExam: 'Kavitas tersimpan', extraoralExam: 'Belum dicatat',
        findings: [], options: { findings: {}, surfaces: {} }, hasUnsavedChanges: true, onReview() {},
    }));
    assert.match(examination, /Ada perubahan belum disimpan/);
    assert.match(examination, /Simpan di tab Anamnesis atau Pemeriksaan &amp; odontogram/);
    assert.match(examination, /Kavitas tersimpan/);
    assert.match(examination, /Pemeriksaan ekstraoral<\/dt><dd[^>]*>Belum dicatat/);
    const pending = diagnosis(true, additional);
    assert.match(pending, /Ada perubahan belum disimpan/);
    assert.match(pending, /Simpan di tab Diagnosis/);
    assert.match(pending, /Diagnosis banding tersimpan/);
    assert.match(pending, /Pastikan tepat satu diagnosis utama/);
    const saved = diagnosis(false, additional.map((item) => ({ ...item, type: 'utama' })));
    assert.doesNotMatch(saved, /Ada perubahan belum disimpan|Pastikan tepat satu diagnosis utama/);
    assert.match(diagnosis(false, [{ ...additional[0], type: 'utama' }, { ...additional[0], id: 2, type: 'utama' }]), /Pastikan tepat satu diagnosis utama/);
});

test('dental workflow summaries escape clinical text in findings and diagnoses', async () => {
    const unsafe = '<img src=x onerror=alert(1)>';
    const props = examinationProps('gigi');
    props.visit.examination.physicalExam = unsafe;
    props.visit.examination.differentialDiagnosis = unsafe;
    props.visit.odontogram[0].notes = unsafe;
    props.visit.diagnoses[0].name = unsafe;

    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    for (const tab of ['diagnosa', 'tindakan']) {
        const panel = examinationPanel(html, tab);
        assert.match(panel, /&lt;img src=x onerror=alert\(1\)&gt;/);
        assert.doesNotMatch(panel, /<img src=x onerror=alert\(1\)>/);
    }
});


test('general diagnosis starts with saved physical findings and omits unanswered systems', async () => {
    const props = examinationProps('umum');
    props.initialTab = 'diagnosa';
    props.visit.examination.anamnesis = 'Batuk sejak tiga hari';
    props.visit.examination.physicalExam = 'Ronki basal kanan';
    props.visit.examination.physicalSystems = { Thorax: 'Ronki kanan', Neurologis: 'Belum dicatat', Abdomen: '', Kulit: 'Ruam lengan' };

    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    const diagnosis = examinationPanel(html, 'diagnosa');
    const summary = diagnosis.slice(0, diagnosis.indexOf('id="tab-diagnosa"')).replace(/<!--.*?-->/g, '');

    assert.match(html, /aria-selected="true"[^>]*data-value="diagnosa"/);
    assert.match(summary, /id="general-examination-summary"/);
    assert.match(summary, /Batuk sejak tiga hari/);
    assert.match(summary, /Ronki basal kanan/);
    assert.match(summary, /Thorax[\s\S]*Ronki kanan/);
    assert.match(summary, /Kulit[\s\S]*Ruam lengan/);
    assert.match(summary, />2 sistem dicatat</);
    assert.doesNotMatch(summary, /Neurologis|Abdomen|odontogram|Pemeriksaan intraoral|Pemeriksaan ekstraoral/);
    assert.match(summary, /aria-label="Tinjau pemeriksaan fisik"/);
});

test('general treatment starts with recorded primary and additional diagnoses and differential diagnosis', async () => {
    const props = examinationProps('umum');
    props.initialTab = 'tindakan';
    props.visit.diagnoses = [
        { id: 2, code: 'R05', codeSystem: 'icd10_who', name: 'Batuk', type: 'tambahan' },
        { id: 1, code: 'J06.9', codeSystem: 'icd10_who', name: 'Infeksi saluran napas atas', type: 'utama' },
    ];
    props.visit.examination.differentialDiagnosis = 'Bronkitis akut';

    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    const treatment = examinationPanel(html, 'tindakan');
    const summary = treatment.slice(0, treatment.indexOf('id="tab-tindakan"'));

    assert.match(html, /aria-selected="true"[^>]*data-value="tindakan"/);
    assert.match(summary, /id="general-diagnosis-summary"/);
    assert.match(summary, /ICD-10 WHO/);
    assert.ok(summary.indexOf('Infeksi saluran napas atas') < summary.indexOf('Batuk'));
    assert.match(summary, /Bronkitis akut/);
    assert.match(summary, /aria-label="Tinjau diagnosis"/);
    assert.doesNotMatch(summary, /odontogram|Gigi FDI/);
});

test('general summaries keep unknown results neutral, flag unsaved changes and escape recorded clinical text', async () => {
    const { GeneralExaminationSummary } = await server.ssrLoadModule('/resources/js/components/kunjungan/clinical-workflow-summary.tsx');
    const empty = examinationProps('umum', { examination: null, diagnoses: [] });

    const html = await renderPage('pelayanan/pemeriksaan/show', empty);
    const diagnosis = examinationPanel(html, 'diagnosa');
    assert.match(diagnosis, /Belum ada temuan per sistem tersimpan/);
    assert.match(diagnosis, /Ringkasan pemeriksaan fisik<\/dt><dd[^>]*>Belum dicatat/);
    assert.doesNotMatch(diagnosis.slice(0, diagnosis.indexOf('id="tab-diagnosa"')), /Nyeri sejak kemarin|Normal|Tidak ada kelainan/);
    assert.match(examinationPanel(html, 'tindakan'), /Belum ada diagnosis tersimpan/);

    const unsafe = '<script>alert(1)</script>';
    const pending = renderToString(createElement(GeneralExaminationSummary, {
        anamnesis: 'Keluhan tersimpan', physicalExam: unsafe, physicalSystems: { Thorax: unsafe, Kulit: 'Belum diketahui' },
        hasUnsavedChanges: true, onReview() {},
    }));
    assert.match(pending, /Ada perubahan belum disimpan/);
    assert.match(pending, /Simpan di tab Anamnesis atau Pemeriksaan fisik/);
    assert.match(pending, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    assert.doesNotMatch(pending, /<script>|>Kulit<|>Belum diketahui</);
});

async function clinicalSaveHarness() {
    const { default: ts } = await import('typescript');
    const path = new URL('../resources/js/pages/pelayanan/pemeriksaan/show.tsx', import.meta.url);
    const source = readFileSync(path, 'utf8');
    const ast = ts.createSourceFile(path.pathname, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    const functions = [];
    function collect(node) {
        if (ts.isFunctionDeclaration(node) && ['confirmSave', 'submitClinicalSection', 'submitForm'].includes(node.name?.text)) functions.push(node.getText(ast));
        ts.forEachChild(node, collect);
    }
    collect(ast);
    assert.equal(functions.length, 3);
    const requests = [];
    const posts = [];
    const formNames = ['examinationForm', 'physicalForm', 'differentialForm', 'followUpForm', 'diagnosisForm', 'treatmentForm', 'prescriptionForm', 'externalPrescriptionForm', 'letterForm', 'referralForm'];
    const forms = Object.fromEntries(formNames.map((name) => [name, {
        data: { note: 'Isian dokter ' + name }, processing: false, resets: 0, defaults: null,
        post(url, options) { posts.push({ name, url, options }); },
        setDefaults(data) { this.defaults = data; },
        setData(data) { this.data = data; },
        reset() { this.resets++; },
    }]));
    const code = ts.transpileModule(functions.join('\n'), { compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.ESNext } }).outputText;
    const handlers = runInNewContext(code + '\n({ submitClinicalSection, submitForm })', {
        ...forms, visit: { id: 17 }, diagnosisCodeSystem: 'icd10_who',
        confirmAction(description, action, actionLabel, title) { requests.push({ description, action, actionLabel, title }); },
        setActiveTab() { assert.fail('saving must not change the active tab'); },
        navigateSection() { assert.fail('saving must not scroll or navigate to another section'); },
        setSelectedProcedure() {}, setProcedureQuery() {}, setProcedureResults() {}, setSelectedDentalFindingKeys() {},
    });
    return { handlers, forms, requests, posts, event: { preventDefault() {} } };
}

test('saving clinical sections waits for confirmation, preserves position and retains entered data until a successful response', async () => {
    for (const [section, name, label] of [
        ['anamnesis', 'examinationForm', 'Simpan anamnesis'],
        ['pemeriksaan', 'physicalForm', 'Simpan pemeriksaan fisik'],
        ['diagnosis', 'differentialForm', 'Simpan diagnosis banding'],
        ['rencana', 'followUpForm', 'Simpan rencana tindak lanjut'],
    ]) {
        const { handlers, forms, requests, posts, event } = await clinicalSaveHarness();
        const data = forms[name].data;

        handlers.submitClinicalSection(event, section);

        assert.equal(posts.length, 0, 'opening or cancelling the confirmation must not send a request');
        assert.equal(requests.length, 1);
        assert.equal(requests[0].title, label);
        assert.equal(requests[0].actionLabel, label);
        assert.equal(forms[name].data, data);
        requests[0].action();
        assert.equal(posts.length, 1);
        assert.equal(posts[0].url, '/pelayanan/pemeriksaan/17');
        assert.equal(posts[0].options.preserveScroll, true);
        assert.equal(posts[0].options.preserveState, true);
        assert.equal(forms[name].defaults, null, 'failed requests must leave the draft unsaved');
        posts[0].options.onSuccess();
        assert.equal(forms[name].defaults, data);
        assert.equal(forms[name].resets, 0);
    }
});

test('adding diagnoses, treatments, prescriptions and letters stays on the current section after confirmation', async () => {
    for (const name of ['diagnosisForm', 'treatmentForm', 'prescriptionForm', 'externalPrescriptionForm', 'letterForm', 'referralForm']) {
        const { handlers, forms, requests, posts, event } = await clinicalSaveHarness();
        const url = '/pelayanan/pemeriksaan/17/' + name;

        handlers.submitForm(event, forms[name], url);

        assert.equal(posts.length, 0);
        assert.equal(requests.length, 1);
        assert.match(requests[0].title, /Tambah|Simpan/);
        requests[0].action();
        assert.equal(posts[0].url, url);
        assert.equal(posts[0].options.preserveScroll, true);
        assert.equal(posts[0].options.preserveState, true);
        assert.equal(forms[name].resets, 0, 'validation failures must not clear entered data');
        posts[0].options.onSuccess();
        assert.equal(forms[name].resets, name === 'diagnosisForm' ? 0 : 1);
    }
});

test('busy clinical forms do not open another save confirmation or send another request', async () => {
    const { handlers, forms, requests, posts, event } = await clinicalSaveHarness();
    forms.examinationForm.processing = true;
    forms.treatmentForm.processing = true;

    handlers.submitClinicalSection(event, 'anamnesis');
    handlers.submitForm(event, forms.treatmentForm, '/pelayanan/pemeriksaan/17/tindakan');

    assert.equal(requests.length, 0);
    assert.equal(posts.length, 0);
});
