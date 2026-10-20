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
