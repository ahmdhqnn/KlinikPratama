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
    const pageDefaults = {
        'pelayanan/screening/index': { serviceName: 'Poli Umum', stats: { total: 0, waiting: 0, submitted: 0, allPending: 0 }, todayVisits: pageOf([]), visits: pageOf([]) },
        'perawat/dashboard': { schedules: [], clinicName: 'Poli Umum' },
        'pelayanan/pemeriksaan/index': { filters: { search: '', tanggal: '' }, stats: { total: 0, waiting: 0, inProgress: 0, completed: 0 } },
        'dokter/appointments': { stats: { total: 0, today: 0, waiting: 0, completed: 0 } },
        'pendaftaran/dashboard': { schedules: [], visits: pageOf([]) },
    };
    props = { ...pageDefaults[path], ...props };
    if (path === 'pelayanan/screening/show' && props.visit) {
        props.visit = { examinationStarted: false, editable: true, submitted: false, ...props.visit };
    }
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

test('membership directory exposes management actions only to admin', async () => {
    const props = {
        members: pageOf([{ id: 1, nama: 'Pegawai Internal', nik: '3273015205900001', nip: null, kategori: 'lainnya', jenis_lainnya: 'staf', status_kepegawaian: 'aktif', unit_kerja: 'Unit Pelayanan', reason: null, detail_url: '/kepesertaan/1' }]),
        search: '', categories: { asn: 'ASN', lainnya: 'Lainnya' }, otherTypes: { staf: 'Staff', magang: 'Magang', lainnya: 'Lainnya' },
        urls: { index: '/kepesertaan', import: '/kepesertaan/import', template: '/kepesertaan/template' },
    };
    const admin = await renderPage('kepesertaan/index', { ...props, canManage: true });
    assert.match(admin, /Tambah peserta/);
    assert.match(admin, /Impor &amp; verifikasi/);
    assert.match(admin, /aria-label="Edit Pegawai Internal"/);
    assert.match(admin, /aria-label="Hapus Pegawai Internal"/);
    assert.match(admin, /Daftar peserta internal/);
    assert.ok(admin.indexOf('aria-label="Filter data"') < admin.indexOf('Daftar peserta internal'));
    assert.doesNotMatch(admin, /Hubungan keluarga|Pensiunan/);

    const registration = await renderPage('kepesertaan/index', { ...props, canManage: false });
    assert.match(registration, /aria-label="Detail"/);
    assert.doesNotMatch(registration, /Tambah peserta|Impor &amp; verifikasi|aria-label="Edit Pegawai Internal"|aria-label="Hapus Pegawai Internal"/);
});

for (const clinicType of ['umum', 'gigi']) {
    test(`${clinicType} screening separates measurements, preserves unknown answers, and offers draft and handoff actions`, async () => {
        const html = await renderPage('pelayanan/screening/show', {
            visit: { id: 1, number: 'K-001', status: 'menunggu', clinic: `Poli ${clinicType}`, clinicType, doctor: 'Dokter Uji', patient: { name: 'Pasien Uji', medicalRecordNumber: 'RM-001', gender: 'P', age: 35, bloodType: null, allergyHistory: 'Penisilin' }, screening: { riwayat_alergi: 'Penisilin', status_riwayat_penyakit: 'ada', riwayat_penyakit: 'Hipertensi' }, triage: null, priority: null, printUrl: null, startedAt: null },
            staff: [], canSelectStaff: false, currentStaff: 'Perawat Uji',
        });
        assert.match(html, /id="section-ringkas"/);
        const assessment = html.slice(html.indexOf('id="section-ringkas"'));
        assert.ok(assessment.indexOf('Keluhan dan informasi pasien') < assessment.indexOf('Alergi dan riwayat kesehatan'));
        assert.ok(assessment.indexOf('Alergi dan riwayat kesehatan') < assessment.indexOf('Tanda vital'));
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
        assert.match(html, /id="triage-quick-normal-compact"[^>]*aria-checked="false"|aria-checked="false"[^>]*id="triage-quick-normal-compact"/, 'quick normal screening starts unchecked');
        assert.match(html, /Semua jawaban kondisi awal normal setelah diperiksa/);
        assert.doesNotMatch(html, /<label[^>]*rounded-lg border[^>]*for="triage-quick-normal-compact"/);
        assert.ok(html.indexOf('id="catatan_triase"') < html.indexOf('id="triage-quick-normal-compact"'), 'quick normal checkbox follows the screening fields');
        assert.match(html, /Catatan untuk dokter/);
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
        assert.match(html, /Berkas pendukung/);
        assert.match(html, /Jawaban kosong berarti belum dinilai/);
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

test('quick normal triage fills only assessed normal answers and clearing restores unknown state', async () => {
    const { allTriageAnswersNormal, hasAbnormalTriageAnswer, quickNormalTriagePatch } = await server.ssrLoadModule('/resources/js/lib/triage-quick-normal.ts');
    const normal = quickNormalTriagePatch(true);
    assert.deepEqual(normal, {
        nyeri_dada: 'tidak', kejang: 'tidak', nadi_teraba: 'teraba', pola_pernapasan: 'normal',
        kesadaran: 'sadar', kondisi_psikiatri: 'normal', risiko_jatuh_visual: 'rendah',
    });
    assert.equal(allTriageAnswersNormal(normal), true);
    assert.equal(hasAbnormalTriageAnswer(normal), false);
    assert.equal(hasAbnormalTriageAnswer({ ...normal, nyeri_dada: 'ya' }), true);
    const unknown = quickNormalTriagePatch(false);
    assert.ok(Object.values(unknown).every((answer) => answer === ''));
    assert.equal(allTriageAnswersNormal(unknown), false);
    assert.equal(hasAbnormalTriageAnswer(unknown), false);
});

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
    const visit = { id: 1, number: 'K-001', date: '29/10/2026', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', hasScreening: true, status: 'screening', screeningState: 'Dalam proses', screeningUrl: '/pelayanan/screening/1', actionUrl: '/pelayanan/screening/1', actionLabel: 'Lanjutkan pengkajian', printUrl: null };
    const html = await renderPage('pelayanan/screening/index', {
        filters: { search: '', tanggal: '2026-10-29', poliklinikId: 1 }, clinics: [], canSelectClinic: false,
        todayVisits: pageOf([visit]), visits: pageOf([visit]),
    });
    assert.match(html, /Pengkajian awal berlangsung/);
    assert.match(html, /Lanjutkan pengkajian/);
    assert.doesNotMatch(html, />Selesai<\/span>/);
    assert.match(html, /Menunggu pengkajian hari ini/);
    assert.match(html, /Daftar kunjungan pasien/);
    assert.doesNotMatch(html, /Pengkajian saya/);
    assert.match(html, /id="screening-queue-search"/);
    assert.match(html, /id="screening-queue-date"/);
    assert.equal((html.match(/aria-label="Lanjutkan pengkajian"/g) ?? []).length, 2);
});

test('screening queue reflects a started doctor examination', async () => {
    const visit = { id: 1, number: 'K-EXAM', date: '29/10/2026', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', hasScreening: true, status: 'pemeriksaan', examinationStarted: true, screeningState: 'Terkirim', screeningUrl: '/pelayanan/screening/1', actionUrl: '/pelayanan/pasien/1/rekam-medis', actionLabel: 'Lihat RME', printUrl: null };
    const html = await renderPage('pelayanan/screening/index', {
        filters: { search: '', tanggal: '', poliklinikId: 1 }, clinics: [], canSelectClinic: false,
        todayVisits: pageOf([]), visits: pageOf([visit]),
    });
    assert.equal((html.match(/Sedang diperiksa/g) ?? []).length, 1);
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
    assert.match(html, /Daftar kunjungan pasien/);
    assert.equal((html.match(/aria-label="Lanjutkan pengkajian"/g) ?? []).length, 2);
    assert.doesNotMatch(html, />Lanjutkan pengkajian<\/a>/);
    assert.equal((html.match(/href="\/pelayanan\/screening\/1"/g) ?? []).length, 2);
    assert.equal((html.match(/<th[^>]*>No\. kunjungan<\/th><th[^>]*>Tanggal<\/th>/g) ?? []).length, 2);
    assert.doesNotMatch(html, />Edit<\/a>|>Batalkan<\/button>|>Cetak<\/a>/);
});

test('nursing procedures show today and all visits with pending and completed actions', async () => {
    const pending = { id: 1, number: 'K-001', patient: 'Pasien Hari Ini', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', doctor: 'Dokter Uji', date: '28/10/2026', status: 'tindakan', treatmentStarted: false, pendingCount: 1, completedCount: 0, url: '/pelayanan/tindakan-perawat/1' };
    const completed = { ...pending, id: 2, number: 'K-002', patient: 'Pasien Sebelumnya', date: '27/10/2026', status: 'selesai', treatmentStarted: true, pendingCount: 0, completedCount: 1, url: '/pelayanan/tindakan-perawat/2' };
    const html = await renderPage('pelayanan/tindakan/index', {
        stats: { todayTotal: 1, todayPending: 1, todayCompleted: 0, allPending: 1 },
        filters: { search: '', tanggal: '', status: '' }, todayVisits: pageOf([pending]), visits: pageOf([pending, completed]),
    });
    assert.match(html, /Kunjungan poli hari ini/);
    assert.match(html, /Menunggu tindakan hari ini/);
    assert.match(html, /href="\/perawat\/laporan-tindakan"[^>]*>[\s\S]*?Laporan tindakan/);
    assert.match(html, /Tindakan tertunda/);
    assert.match(html, /Daftar kunjungan pasien/);
    assert.match(html, /Pasien Sebelumnya/);
    assert.match(html, /aria-label="Lihat pelaksanaan"/);
    assert.equal((html.match(/aria-label="Mulai tindakan"/g) ?? []).length, 2);
    assert.equal((html.match(/Menunggu tindakan<\/span>/g) ?? []).length, 2);
    assert.match(html, /href="\/pelayanan\/tindakan-perawat\/1#pelaksanaan-tindakan"/);
    assert.match(html, /id="nursing-action-status"/);
    assert.match(html, />Tertunda<\/span>/);
    assert.equal((html.match(/>0(?:<!-- -->)? selesai<\/p>/g) ?? []).length, 2);
    assert.match(html, />1(?:<!-- -->)? selesai<\/p>/);
    assert.doesNotMatch(html, /Total (?:<!-- -->)?1|Belum dimulai<\/p>/);
    assert.match(html, />Selesai<\/span>/);
});

test('nursing procedure report has filter actions and prints the complete filtered result', async () => {
    const common = {
        title: 'Laporan tindakan perawat', clinicName: 'Poli Umum',
        filters: { dari: '2026-10-01', sampai: '2026-10-29', search: 'Pasien Uji', status: 'completed' },
        stats: { total: 1, visits: 1, pending: 0, completed: 1 },
        procedures: pageOf([{ id: 1, date: '29/10/2026', visitNumber: 'K-001', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', doctor: 'Dokter Uji', name: 'Perawatan luka', code: '93.57', quantity: 1, tooth: null, instructions: 'Ikuti instruksi dokter', status: 'completed', result: 'Pasien nyaman setelah tindakan', performedBy: 'Perawat Uji', performedAt: '29/10/2026 09:00', visitUrl: '/pelayanan/tindakan-perawat/1' }]),
        exportUrl: '/perawat/laporan-tindakan/export?status=completed', printUrl: '/perawat/laporan-tindakan/cetak?status=completed', printMode: false,
    };
    const report = await renderPage('pelayanan/tindakan/report', common);
    assert.match(report, /Laporan tindakan perawat/);
    assert.match(report, /Periode tanggal kunjungan/);
    assert.match(report, /Cetak laporan/);
    assert.match(report, /Ekspor Excel/);
    assert.match(report, /Pasien nyaman setelah tindakan/);

    const print = await renderPage('pelayanan/tindakan/report', { ...common, printMode: true, printUrl: null, exportUrl: null, procedures: { data: common.procedures.data, total: 1 } });
    assert.match(print, /Kembali ke laporan tindakan/);
    assert.match(print, /Cetak laporan/);
    assert.match(print, /Pasien nyaman setelah tindakan/);
    assert.doesNotMatch(print, /Terapkan filter|Ekspor Excel/);
});

test('started examination appears in nurse treatment queue without an actionable link', async () => {
    const examining = { id: 3, number: 'K-EXAM', patient: 'Pasien Diperiksa', medicalRecordNumber: 'RM-003', clinic: 'Poli Gigi', doctor: 'Dokter Uji', date: '29/10/2026', status: 'pemeriksaan', examinationStarted: true, treatmentStarted: false, pendingCount: 0, completedCount: 0, url: null };
    const html = await renderPage('pelayanan/tindakan/index', {
        stats: { todayTotal: 1, todayPending: 0, todayCompleted: 0, allPending: 0 },
        filters: { search: '', tanggal: '', status: '' }, todayVisits: pageOf([]), visits: pageOf([examining]),
    });
    assert.match(html, /Menunggu instruksi dokter/);
    assert.match(html, /Sedang diperiksa/);
    assert.equal((html.match(/aria-label="Tindakan belum tersedia"[^>]*disabled=""/g) ?? []).length, 1);
    assert.doesNotMatch(html, /href="\/pelayanan\/tindakan-perawat\/3"/);
});

test('nurse sees an ongoing treatment while other roles see the pending handoff', async () => {
    const visit = { id: 1, number: 'K-001', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', doctor: 'Dokter Uji', date: '29/10/2026', status: 'tindakan', examinationStarted: false, treatmentStarted: true, pendingCount: 1, completedCount: 0, url: '/pelayanan/tindakan-perawat/1' };
    const nurse = await renderPage('pelayanan/tindakan/index', { stats: { todayTotal: 1, todayPending: 1, todayCompleted: 0, allPending: 1 }, filters: { search: '', tanggal: '', status: '' }, todayVisits: pageOf([visit]), visits: pageOf([visit]) });
    assert.equal((nurse.match(/Tindakan berlangsung<\/span>/g) ?? []).length, 2);
    assert.doesNotMatch(nurse, /Ke perawat \(tindakan\)/);
    const registration = await renderPage('pendaftaran/kunjungan-per-poli', { filters: { poliklinikId: 1, tanggal: '', status: '' }, clinics: [{ id: 1, name: 'Poli Umum' }], stats: { total: 1, waiting: 0, screening: 0, examination: 0 }, access: { manageVisits: false, monitorClinicalFlow: true, selectClinic: false }, visits: pageOf([visit]), recentVisits: pageOf([visit]) });
    assert.equal((registration.match(/Menunggu tindakan perawat<\/span>/g) ?? []).length, 2);
});

test('screening report keeps the assessment summary out of the compact table', async () => {
    const html = await renderPage('pelayanan/screening/history', {
        filters: { search: '', tanggal: '', poliklinikId: '' }, canSelectClinic: false, clinics: [], screenings: pageOf([{ id: 1, date: '29/10/2026', visitNumber: 'K-001', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', staff: 'Perawat Uji', triage: 'hijau', priority: 'normal', visitUrl: '/pelayanan/kunjungan/1', printUrl: '/pelayanan/screening/1/cetak', editUrl: '/pelayanan/screening/1', isDraft: false }]),
        isReport: true, serviceName: 'Poli Umum', exportUrl: null,
    });
    assert.match(html, /Laporan pengkajian/);
    assert.doesNotMatch(html, /Ringkasan pengkajian/);
    assert.match(html, /Pasien Uji/);
    assert.match(html, /Cetak pengkajian/);
    assert.match(html, /aria-label="Perbarui pengkajian"/);
});

test('dashboard queues clarify each workflow and center headers, cells, and status badges', async () => {
    const visit = { id: 1, number: 'K-001', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', actionUrl: '/pelayanan/kunjungan/1', actionLabel: 'Buka', triage: 'hijau', priority: 'normal' };
    const followUp = { upcoming: 0, overdue: 0, href: '/pelayanan/kunjungan' };
    for (const [page, props, expected, destination] of [
        ['perawat/dashboard', { stats: { total_kunjungan: 1, total_kunjungan_hari_ini: 1, menunggu_ttv: 1, menunggu_ttv_hari_ini: 1, siap_dokter: 0, siap_dokter_hari_ini: 0, triase_darurat: 0 }, visits: pageOf([{ ...visit, status: 'menunggu', actionType: 'screening' }]) }, 'Menunggu pengkajian awal', '/pelayanan/screening'],
        ['dokter/dashboard', { stats: { visitsToday: 1, totalVisits: 2, waitingExaminations: 1, completedToday: 0 }, schedule: [], recentVisits: pageOf([{ ...visit, status: 'pemeriksaan', actionType: 'examination' }]) }, 'Menunggu pemeriksaan', '/pelayanan/pemeriksaan'],
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

test('doctor activity reports explain the selected report and expose only the matching visit actions', async () => {
    for (const [type, title, row] of [
        ['tindakan', 'Laporan tindakan', { name: 'Perawatan luka', quantity: 1, executionRole: 'dokter' }],
        ['obat', 'Laporan obat', { name: 'Paracetamol', quantity: 6, instruction: '3 kali sehari', external: false }],
    ]) {
        const html = await renderPage('dokter/reports', {
            type, filters: { dari: '', sampai: '', search: '' }, stats: { total: 1, secondary: 0, tertiary: 1, related: 1 },
            exportUrl: `/dokter/laporan/${type}/export`,
            rows: pageOf([{ date: '30/10/2026', number: 'KNJ-001', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', detailUrl: '/pelayanan/pemeriksaan/1', letterUrl: '/persuratan/terbitkan/1', ...row }]),
        });
        assert.match(html, new RegExp(title, 'i'));
        assert.match(html, /Ekspor Excel/);
        assert.match(html, /Lihat pemeriksaan KNJ-001/);
        assert.match(html, /Terbitkan surat untuk Pasien Uji/);
        assert.match(html, /Pasien Uji/);
    }
});

test('doctor treatment monitoring shows operational filters, compact statuses and examination actions', async () => {
    const html = await renderPage('dokter/treatments', {
        filters: { dari: '', sampai: '', search: '', status: 'all' },
        stats: { total: 1, visits: 1, pending: 0, completed: 1 },
        actions: pageOf([{ id: 1, date: '30/10/2026', number: 'KNJ-001', patient: 'Pasien Uji', medicalRecordNumber: 'RM-001', clinic: 'Poli Umum', name: 'Perawatan luka', code: '86.22', quantity: 1, executionRole: 'dokter', status: 'completed', statusLabel: 'Selesai', performedBy: 'Dokter Uji', performedAt: '30/10/2026 10:00', detailUrl: '/pelayanan/pemeriksaan/1' }]),
    });
    assert.match(html, /Pantau tindakan dari pemeriksaan Anda/);
    assert.match(html, /Status tindakan/);
    assert.match(html, /Selesai/);
    assert.match(html, /Buka pemeriksaan KNJ-001/);
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
        stats: { visitsToday: 3, totalVisits: 18, waitingScreening: 1, inProgress: 1 },
        followUp: { upcoming: 0, overdue: 0, href: '/pendaftaran/laporan-kunjungan' },
    });
    assert.ok(registrationDashboard.includes('href="/pendaftaran/kunjungan-per-poli?tanggal=2026-10-22"'));
    assert.ok(registrationDashboard.includes('href="/pendaftaran/kunjungan-per-poli"'));
    assert.ok(registrationDashboard.includes('href="/pendaftaran/database-pasien"'));
    assert.match(registrationDashboard, />Pasien<\/span>/);
    assert.match(registrationDashboard, /Total kunjungan/);
    const registrationStats = registrationDashboard.split('aria-label="Statistik pendaftaran"')[1];
    assert.ok(registrationStats.indexOf('Total kunjungan') < registrationStats.indexOf('Kunjungan hari ini'));
    const quickAccessPosition = registrationDashboard.indexOf('Akses cepat');
    assert.ok(quickAccessPosition > registrationDashboard.indexOf('aria-label="Statistik pendaftaran"'));
    assert.ok(quickAccessPosition > registrationDashboard.indexOf('Tindak lanjut'));
    assert.ok(quickAccessPosition < registrationDashboard.indexOf('Jadwal praktik hari ini'));
    assert.ok(quickAccessPosition < registrationDashboard.indexOf('Kunjungan hari ini', quickAccessPosition));

    const nurseDashboard = await renderPage('perawat/dashboard', {
        clinicName: 'Poli Gigi',
        stats: { total_kunjungan: 18, total_kunjungan_hari_ini: 3, menunggu_ttv: 1, menunggu_ttv_hari_ini: 1, siap_dokter: 1, siap_dokter_hari_ini: 1, triase_darurat: 0, menunggu_tindakan: 0 },
        schedules: [{ id: 2, doctorId: 7, clinic: 'Poli Gigi', doctor: 'Dokter Gigi Uji', start: '08:00', end: '12:00' }],
        visits: pageOf([]),
        followUp: { upcoming: 0, overdue: 0, href: '/pelayanan/screening' },
    });
    const nurseStats = nurseDashboard.split('aria-label="Statistik keperawatan"')[1];
    assert.ok(nurseStats.indexOf('Total kunjungan') < nurseStats.indexOf('Pengkajian awal'));
    assert.ok(nurseDashboard.indexOf('Jadwal praktik hari ini') < nurseDashboard.indexOf('Tindakan perawat'));
    assert.match(nurseDashboard, /Dokter Gigi Uji/);
    assert.match(nurseDashboard, /08:00[\s\S]*?–[\s\S]*?12:00/);
    assert.match(nurseDashboard, /aria-label="Jadwal praktik hari ini di Poli Gigi\. Lihat jadwal"/);
    assert.match(nurseDashboard, /flex min-h-32 items-center justify-between gap-4 p-5 sm:p-6/);
    for (const [href, label] of [
        ['/pelayanan/screening', 'Pengkajian awal'],
        ['/pelayanan/tindakan-perawat', 'Tindakan perawat'],
        ['/pendaftaran/kunjungan-per-poli', 'Kunjungan poli'],
        ['/pendaftaran/database-pasien', 'Database pasien'],
    ]) {
        assert.match(nurseDashboard, new RegExp(`href="${href}"[\\s\\S]*?${label}`));
    }
    assert.equal((nurseDashboard.match(/lucide-arrow-right/g) ?? []).length, 4);
    assert.doesNotMatch(nurseDashboard, /Laporan tindakan/);
    assert.doesNotMatch(nurseDashboard, /Lihat antrean pengkajian/);
    assert.match(nurseDashboard, /<th[^>]*>Aksi<\/th>/);

    const activeTreatment = await renderPage('perawat/dashboard', {
        clinicName: 'Poli Gigi', schedules: [], followUp: { upcoming: 0, overdue: 0, href: '/pelayanan/screening' },
        stats: { total_kunjungan: 1, total_kunjungan_hari_ini: 1, menunggu_ttv: 0, menunggu_ttv_hari_ini: 0, siap_dokter: 0, siap_dokter_hari_ini: 0, triase_darurat: 0, menunggu_tindakan: 1 },
        visits: pageOf([{ id: 1, number: 'K-001', patient: 'Pasien Tindakan', medicalRecordNumber: 'RM-001', clinic: 'Poli Gigi', status: 'tindakan', treatmentStarted: true, actionType: 'procedure', actionLabel: 'Lanjutkan tindakan', actionUrl: '/pelayanan/tindakan-perawat/1#pelaksanaan-tindakan' }]),
    });
    const activeTreatmentTable = activeTreatment.match(/<table\b[\s\S]*?<\/table>/)?.[0];
    assert.match(activeTreatmentTable, /Tindakan berlangsung/);
    assert.match(activeTreatmentTable, /Belum ditentukan/);
    assert.match(activeTreatmentTable, /aria-label="Lanjutkan tindakan"[^>]*href="\/pelayanan\/tindakan-perawat\/1#pelaksanaan-tindakan"/);
    assertIconActions(activeTreatmentTable, 1);
});

test('registration visit editor returns to its visit detail with the shared back button style', async () => {
    const html = await renderPage('pendaftaran/edit-kunjungan', {
        visit: { id: 42, patient: { name: 'Pasien Uji', medicalRecordNumber: 'RM-42', gender: 'P', age: 30 }, clinicId: '1', doctorId: '2', date: '2026-10-22', notes: null },
        clinics: [{ id: 1, name: 'Poli Umum', type: 'umum' }], today: '2026-10-22', doctorsUrl: '/pendaftaran/dokter/terjadwal',
        updateUrl: '/pendaftaran/kunjungan/42', backUrl: '/pendaftaran/kunjungan/42',
    });

    assert.equal((html.match(/href="\/pendaftaran\/kunjungan\/42"/g) ?? []).length, 2);
    assert.match(html, /Kembali<\/a>/);
    assert.match(html, /Batalkan<\/a>/);
    assert.doesNotMatch(html, /Kembali ke laporan kunjungan/);
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
        assert.match(html, /border-neutral-300 bg-transparent[^\"]*text-neutral-700/, `${context}: ${status} uses a neutral outline status style`);
        assert.doesNotMatch(html, /bg-(?:blue|violet|orange|emerald)-50/, `${context}: ${status} avoids decorative colors`);
    }
    const cancelled = renderToString(createElement(StatusBadge, { status: 'batal' }));
    assert.match(cancelled, /border-red-300 text-red-700/, 'cancellation remains a clear warning');
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
    ['perawat', '/pelayanan/screening/riwayat?page=2', '/pelayanan/screening'],
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
        if (role === 'perawat') assert.doesNotMatch(result.body, /Pengkajian saya/);
    });
}

test('navigation does not match unrelated prefixes or select a dashboard for unknown pages', async () => {
    const { activeNavigationHref } = await server.ssrLoadModule('/resources/js/lib/navigation.ts');
    const targets = [{ href: '/dokter', exact: true }, { href: '/dokter/pasien' }];
    assert.equal(activeNavigationHref('/dokter/pasien-lain', targets), null);
    assert.equal(activeNavigationHref('/dokter/tidak-ada', targets), null);
    assert.equal(activeNavigationHref('/dokter/pasien/?page=2', targets), '/dokter/pasien');
});

test('nurse sidebar shows actionable queue badges and hides empty counts', async () => {
    const { default: Layout } = await server.ssrLoadModule('/resources/js/layouts/app-layout.tsx');
    async function renderFor(role, nurseQueues) {
        const result = await createInertiaApp({
            page: { component: 'dashboard/index', props: { errors: {}, flash: {}, modules: {}, nurseQueues, auth: { user: { id: 1, name: 'Petugas Uji', email: 'staff@example.test', role, roleLabel: role, photoUrl: null } } }, url: '/perawat/dashboard', version: null, clearHistory: false, encryptHistory: false },
            resolve: () => () => createElement(Layout, null, createElement('p', null, 'Halaman uji')),
            render: renderToString,
            setup: ({ App, props }) => createElement(App, props),
        });
        return result.body;
    }
    const nurse = await renderFor('perawat', { assessment: 3, treatments: 105 });
    assert.match(nurse, /aria-label="Pengkajian awal, 3 memerlukan layanan"/);
    assert.match(nurse, /aria-label="Tindakan, 105 memerlukan layanan"/);
    assert.match(nurse, /<span class="flex min-w-0 items-center gap-1\.5"><span class="truncate">Pengkajian awal<\/span><span/, 'expanded sidebar badge stays beside its menu text');
    assert.match(nurse, />99\+<\/span>/);
    const empty = await renderFor('perawat', { assessment: 0, treatments: 0 });
    assert.doesNotMatch(empty, /memerlukan layanan|>99\+<\/span>/);
    const otherRole = await renderFor('admin', { assessment: 3, treatments: 105 });
    assert.doesNotMatch(otherRole, /memerlukan layanan|>99\+<\/span>/);
});

test('registered patient preview presents full saved identity and honest missing values', async () => {
    const { RegisteredPatientIdentity } = await server.ssrLoadModule('/resources/js/components/pendaftaran/registered-patient-form.tsx');
    const html = renderToString(createElement(RegisteredPatientIdentity, { patient: {
        nik: '3273015205900001', nip: '1990052020101001', tanggal_lahir: '1990-05-20', tanggal_lahir_tampil: '20 Mei 1990', umur: 36, tempat_lahir: 'Bandung', jenis_kelamin: 'P',
        alamat: 'Alamat uji', telepon: '081234567890', membership: 'ASN', unit_kerja: 'Biro Umum',
        rt: '001', rw: '002', kelurahan: 'Kelurahan Uji', kecamatan: 'Kecamatan Uji',
    } }));
    for (const value of ['3273015205900001', '1990052020101001', 'Bandung, 20 Mei 1990 (36 tahun)', 'Perempuan', 'Alamat uji', 'Biro Umum', 'Belum diisi']) {
        assert.ok(html.includes(value));
    }
    assert.doesNotMatch(html, /Kelurahan Uji|Kecamatan Uji|RT \/ RW|Nama ibu kandung/);
    assert.doesNotMatch(html, /undefined|null/);
});

test('patient entry keeps birth place and date as separate inputs with a single address field', async () => {
    const { ManualPatientFields } = await server.ssrLoadModule('/resources/js/components/pasien/manual-patient-fields.tsx');
    const html = renderToString(createElement(ManualPatientFields, {
        value: { nama: '', nik: '', nip: '', tempat_lahir: '', tanggal_lahir: '', jenis_kelamin: '', golongan_darah: '', agama: '', unit_kerja: '', telepon: '', alamat: '', rt: '', rw: '', kelurahan: '', kecamatan: '', riwayat_alergi: '' },
        errors: {}, onChange: () => {}, suggestExisting: false,
    }));
    assert.match(html, /for="manual-tempat-lahir"[^>]*>Tempat lahir/);
    assert.match(html, /for="manual-tanggal-lahir"[^>]*>Tanggal lahir/);
    assert.match(html, /for="manual-alamat"[^>]*>Alamat/);
    assert.match(html, /for="manual-unit-kerja"[^>]*>Unit kerja/);
    assert.match(html, /id="manual-unit-kerja"[^>]*placeholder="Contoh: Bagian Umum"/);
    assert.doesNotMatch(html, /Pekerjaan|Nama ibu kandung/);
    assert.doesNotMatch(html, /for="manual-(?:rt|rw|kelurahan|kecamatan)"/);
});

test('both visit forms allow Poli Gigi on the current service date', async () => {
    const [{ VisitFields: RegistrationFields }, { VisitFields: ServiceFields }] = await Promise.all([
        server.ssrLoadModule('/resources/js/components/pendaftaran/visit-fields.tsx'),
        server.ssrLoadModule('/resources/js/components/kunjungan/visit-fields.tsx'),
    ]);
    const clinics = [{ id: 2, name: 'Poli Gigi', type: 'gigi' }];
    const registration = renderToString(createElement(RegistrationFields, {
        clinics, today: '2026-10-20', doctorsUrl: '/pendaftaran/dokter',
        values: { poliklinik_id: '2', dokter_id: '', tanggal: '2026-10-20', catatan: '' }, errors: {}, onChange: () => {},
    }));
    const service = renderToString(createElement(ServiceFields, {
        clinics, today: '2026-10-20', doctorsUrl: '/pelayanan/dokter',
        data: { pasien_id: '', poliklinik_id: '2', dokter_id: '', tanggal: '2026-10-20', jenis_pasien: 'lama', catatan: '' }, errors: {}, onChange: () => {},
    }));
    for (const html of [registration, service]) {
        assert.match(html, /min="2026-10-20"/);
        assert.match(html, /Tanggal kunjungan Poli Gigi/);
        assert.doesNotMatch(html, /Tanggal janji|tanggal setelah hari ini|reservasi terlebih dahulu/);
    }
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
        assert.doesNotMatch(html, /Kembali ke dashboard|href="\/pendaftaran">Batal/);
        assert.doesNotMatch(html, /Nama ibu kandung|id="nama_ibu"|id="manual-nama-ibu"/);
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
    assert.match(html, /grid min-w-0 items-stretch gap-4 lg:grid-cols-2/);
    assert.match(html, /min-w-0 overflow-hidden lg:col-span-2/);
    assert.ok(html.indexOf('Informasi kunjungan') < html.indexOf('Identitas pasien'));
    assert.ok(html.indexOf('Identitas pasien') < html.indexOf('Progres pelayanan'));
});

test('visit queue keeps all four stages in a balanced responsive grid', async () => {
    const visits = pageOf(['menunggu', 'pemeriksaan', 'tindakan', 'farmasi'].map((status, index) => ({
        id: index + 1, number: `KNJ-${index + 1}`, patient: `Pasien ${index + 1}`,
        clinic: 'Poli Umum', doctor: 'Dokter Uji', status,
    })));
    const html = await renderPage('pelayanan/kunjungan/antrian', { visits, today: '29 Oktober 2026' });
    assert.match(html, /aria-label="Antrean menurut tahap pelayanan"[^>]*md:grid-cols-2 2xl:grid-cols-4/);
    assert.equal((html.match(/max-h-96 min-h-56 flex-1/g) ?? []).length, 4);
    for (const label of ['Antrean pengkajian awal perawat', 'Pemeriksaan dokter', 'Tindakan perawat', 'Farmasi']) {
        assert.match(html, new RegExp(label));
    }
    for (let id = 1; id <= 4; id++) {
        assert.match(html, new RegExp(`href="/pelayanan/kunjungan/${id}"`));
    }
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
        const disclosures = [...html.matchAll(/<button[^>]*aria-expanded="(true|false)"[^>]*>(Tutup|Buka)<svg/g)]
            .map((match) => [match[1], match[2]]);
        assert.deepEqual(disclosures, canManage ? [['false', 'Buka'], ['true', 'Tutup']] : []);
        assert.match(html, /Kalender libur praktik/);
        assert.match(html, /Pengecualian per tanggal/);
        if (canManage) {
            assert.match(html, /aria-expanded="false"[^>]*>Buka/);
            assert.match(html, /hidden=""[^>]*id="[^\"]+"[^>]*><form/);
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
    ['dokter/patients', { search: '', patients: pageOf([{ id: 7, medicalRecordNumber: 'RM-007', name: 'Pasien Uji', visitCount: 1, recordUrl: '/pelayanan/pasien/7/rekam-medis', letterUrl: null }]) }, 2],
    ['stok/persediaan/show', { medicine: { id: 1, code: 'O-1', name: 'Obat', stock: 10, unit: 'tablet' }, batches: [{ id: 1, number: 'B-1', stock: 10, status: 'karantina', source: 'distribusi', expired: false, nearExpiry: false }], movements: pageOf([{ id: 1, date: '24/10/2026', type: 'keluar', quantity: 1, before: 11, after: 10, canReturn: true }]), depots: [], depotsEnabled: true }, 4],
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
    assert.match(html, /Jadwal praktik dokter/);
    assert.match(html, /Atur jadwal praktik dan pengecualian layanan untuk [\s\S]*?Poli Gigi/);
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

test('service detail offers submitted assessment updates before the doctor starts', async () => {
    const html = await renderPage('pelayanan/kunjungan/show', {
        visit: { ...visitDetail, status: 'pemeriksaan', screeningSubmitted: true },
        permissions: { editVisit: false, viewRme: true, processScreening: true }, backUrl: '/perawat/dashboard',
        links: { edit: null, patient: '/pelayanan/pasien/7', screening: '/pelayanan/screening/42', medicalRecord: '/pelayanan/pasien/7/rekam-medis' },
    });
    assert.match(html, /Perbarui pengkajian awal/);
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
    for (const expected of ['xl:grid-cols-4', 'Tempat, tanggal lahir', 'Alamat', 'Unit kerja', 'Berat / tinggi', '60 kg', '160 cm', 'Pengkajian awal berlangsung', 'Kunjungan dibatalkan', 'Reservasi', 'Belum dapat diketahui', 'aria-controls="medical-record-visit-1"', 'id="medical-record-visit-2"']) {
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

test('screening print returns to the nurse assessment queue and follows the shared correspondence document layout', async () => {
    const html = await renderPage('pelayanan/screening/print', { document: {
        clinic: { name: 'Klinik Uji', address: 'Jl. Contoh', phone: '021123', email: 'klinik@example.test', logoUrl: null },
        patient: { name: 'Pasien Uji', medicalRecordNumber: 'RM-007', gender: 'P', age: 30, address: null },
        visitNumber: 'K-007', clinicName: 'Poli Umum', clinicType: 'umum', doctorName: 'Dokter Uji', nurseName: 'Perawat Uji',
        visitStatus: 'pemeriksaan', isDraft: false, startedAt: '24/10/2026 09:00', submittedAt: '24/10/2026 09:10',
        printDate: '24 Oktober 2026', printedAt: '24/10/2026 09:15', signatureDataUrl: null, backUrl: '/pelayanan/screening',
        sections: { history: { 'Keluhan utama': 'Demam sejak kemarin' }, vitals: { 'Suhu tubuh': '38 °C' }, measurements: {}, measurementNote: null, triage: {}, dental: {}, additional: {} },
    } });
    assert.match(html, /href="\/pelayanan\/screening"[^>]*>[\s\S]*?Kembali ke pengkajian awal/);
    assert.match(html, /Cetak dokumen/);
    assert.match(html, /Klinik Uji/);
    assert.match(html, /Pengkajian Awal Perawat &amp; Tanda-Tanda Vital/);
    assert.match(html, /Demam sejak kemarin/);
    assert.doesNotMatch(html, /Pengkajian saya/);
    assert.doesNotMatch(html, /Pengkajian Khusus Gigi dan Mulut/);
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
            examination: { doctorId: 1, status: 'draft', anamnesis: 'Nyeri sejak kemarin', allergyHistory: 'Tidak ada alergi', physicalExam: 'Kavitas gigi 46', physicalSystems: {}, integrityValid: null, signedAt: null, versions: [] },
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
    assert.match(html, /Menunggu pemeriksaan/);
    assert.doesNotMatch(html, /Pemeriksaan terkunci|id="dental-tooth"/);
    assert.match(html, /<button[^>]*aria-label="Batalkan temuan gigi 46 permukaan O"[^>]*disabled=""/);

    const signed = examinationProps('gigi');
    signed.visit.status = 'selesai';
    signed.visit.examination = { ...signed.visit.examination, status: 'selesai', signedAt: '24 Oktober 2026', integrityValid: false };
    const invalid = await renderPage('pelayanan/pemeriksaan/show', signed);
    assert.match(invalid, /Catatan perlu diverifikasi/);
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
        assert.match(disclosure, /\sopen(?:=|\s|>)/, 'non-drug care is visible without opening a disclosure');
        assert.match(followUp, /id="non-farmakologis"/);
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
            assert.match(physical, /aria-label="Lokasi pemeriksaan"[^>]*role="img"[^>]*><svg[^>]*lucide-map-pin/);
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
    ['admin', null, '/', '/pelayanan/kunjungan', ['Ringkasan', 'Operasional', 'Data Master', 'Pengelolaan', 'Laporan', 'Akun'], 18],
    ['pendaftaran', null, '/pendaftaran', '/pendaftaran/pendaftaran-baru', ['Ringkasan', 'Operasional', 'Pengelolaan', 'Laporan', 'Akun'], 9],
    ['perawat', 'umum', '/perawat/dashboard', '/pelayanan/screening', ['Ringkasan', 'Operasional', 'Koordinasi Layanan', 'Laporan', 'Akun'], 10],
    ['perawat', 'gigi', '/perawat/dashboard', '/pelayanan/screening', ['Ringkasan', 'Operasional', 'Koordinasi Layanan', 'Laporan', 'Akun'], 10],
    ['dokter', 'umum', '/dokter', '/pelayanan/pemeriksaan', ['Ringkasan', 'Operasional', 'Penunjang', 'Laporan', 'Akun'], 13],
    ['dokter', 'gigi', '/dokter', '/pelayanan/pemeriksaan', ['Ringkasan', 'Operasional', 'Penunjang', 'Laporan', 'Akun'], 13],
    ['farmasi', null, '/farmasi/dashboard', '/pelayanan/farmasi', ['Ringkasan', 'Operasional', 'Pengelolaan', 'Akun'], 4],
    ['manajemen', null, '/', '/laporan/kunjungan', ['Ringkasan', 'Laporan', 'Akun'], 4],
]) {
    test(role + ' ' + (clinicType ?? '') + ' groups its menus and places primary work immediately after dashboard', async () => {
        const html = await renderRoleNavigation(role, primary, clinicType);
        const navigation = html.match(/<nav\b[^>]*aria-label="Navigasi utama"[^>]*>([\s\S]*?)<\/nav>/)?.[1];
        assert.ok(navigation);
        const links = [...navigation.matchAll(/<a\b[^>]*href="([^"]+)"/g)].map((match) => match[1]);
        assert.deepEqual(links.slice(0, 2), [dashboard, primary]);
        assert.equal(links.length, linkCount, 'menu count matches the role scope');
        assert.equal(new Set(links).size, links.length, 'each destination appears once');
        if (role === 'dokter') {
            assert.ok(links.includes('/dokter/tindakan'));
            assert.ok(links.includes('/dokter/kunjungan'));
            assert.ok(!links.includes('/dokter/laporan/kunjungan'));
            assert.ok(navigation.indexOf('/dokter/tindakan') < navigation.indexOf('Penunjang'));
            assert.ok(navigation.indexOf('/dokter/kunjungan') > navigation.indexOf('Penunjang'));
        }
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
    const table = summary.match(/<table[^>]*aria-label="Ringkasan diagnosis tercatat"[\s\S]*?<\/table>/)?.[0];
    assert.ok(table);
    for (const cell of table.matchAll(/<(?:th|td)\b[^>]*class="([^"]*)"/g)) {
        assert.match(cell[1], /text-center/);
        assert.doesNotMatch(cell[1], /text-left|align-top/);
    }
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
    assert.doesNotMatch(diagnosis, /Pemeriksaan intraoral<\/dt>|Pemeriksaan ekstraoral<\/dt>/);
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
    assert.doesNotMatch(examination, /Pemeriksaan ekstraoral<\/dt>/);
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
    assert.match(summary, />2 temuan dicatat</);
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
    assert.match(diagnosis, /Belum ada temuan fisik tersimpan/);
    assert.doesNotMatch(diagnosis, /Ringkasan pemeriksaan fisik<\/dt>/);
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

function attachDerivedState(ts, ast, scope, names) {
    function collect(node) {
        if (ts.isVariableDeclaration(node) && names.includes(node.name.getText(ast)) && node.initializer) {
            const code = ts.transpileModule('const value = ' + node.initializer.getText(ast) + ';', { compilerOptions: { target: ts.ScriptTarget.ESNext } }).outputText;
            Object.defineProperty(scope, node.name.getText(ast), { configurable: true, enumerable: true, get: () => runInNewContext('(() => { ' + code + '\nreturn value; })()', scope) });
        }
        ts.forEachChild(node, collect);
    }
    collect(ast);
}

async function clinicalSaveHarness(pendingBodyFinding = false) {
    const { default: ts } = await import('typescript');
    const path = new URL('../resources/js/pages/pelayanan/pemeriksaan/show.tsx', import.meta.url);
    const source = readFileSync(path, 'utf8');
    const ast = ts.createSourceFile(path.pathname, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    const functions = [];
    function collect(node) {
        if (ts.isFunctionDeclaration(node) && ['confirmSave', 'submitClinicalSection', 'submitForm', 'canSubmitEntry', 'submitAddendum', 'submitDentalFinding'].includes(node.name?.text)) functions.push(node.getText(ast));
        ts.forEachChild(node, collect);
    }
    collect(ast);
    assert.equal(functions.length, 6);
    const requests = [];
    const posts = [];
    const formNames = ['examinationForm', 'physicalForm', 'differentialForm', 'followUpForm', 'diagnosisForm', 'treatmentForm', 'prescriptionForm', 'externalPrescriptionForm', 'letterForm', 'referralForm', 'addendumForm', 'dentalForm'];
    const forms = Object.fromEntries(formNames.map((name) => [name, {
        data: { note: 'Isian dokter ' + name }, isDirty: true, processing: false, resets: 0, defaults: null,
        post(url, options) { this.processing = true; posts.push({ name, url, options }); },
        setDefaults(data) { this.defaults = data; this.isDirty = false; },
        setData(data) { this.data = data; },
        reset() { this.resets++; this.isDirty = false; },
    }]));
    Object.assign(forms.diagnosisForm.data, { is_manual: true, nama_diagnosa: 'Diagnosis dokter', kode_icd10: '', code_system: 'manual' });
    Object.assign(forms.treatmentForm.data, { terminology_id: '1', jumlah: '1' });
    Object.assign(forms.prescriptionForm.data, { obat_id: '1', jumlah: '1' });
    Object.assign(forms.externalPrescriptionForm.data, { nama_obat: 'Obat luar', jumlah: '1' });
    Object.assign(forms.letterForm.data, { tanggal: '2026-10-26', nomor_surat: '', surat_template_id: '', konten: 'Isi surat' });
    Object.assign(forms.referralForm.data, { ke_poli_id: '2' });
    Object.assign(forms.addendumForm.data, { reason: 'Koreksi hasil pemeriksaan pasien', pemeriksaan_fisik: 'Temuan terbaru' });
    Object.assign(forms.dentalForm.data, { tooth_fdi: '46', surface: 'O', finding_code: 'caries', notes: 'Kavitas baru', reason: '' });
    const code = ts.transpileModule(functions.join('\n'), { compilerOptions: { target: ts.ScriptTarget.ESNext, module: ts.ModuleKind.ESNext } }).outputText;
    const scope = {
        ...forms, visit: { id: 17, clinicId: 1, examination: null, odontogram: [] }, diagnosisCodeSystem: 'icd10_who', bodyFindingPending: pendingBodyFinding, addendumBodyPending: false, toast: { error() {} },
        canEdit: true, canCorrect: false, prescriptionEditable: true, treatmentMode: 'icd9cm', addendumDefaults: { reason: '', pemeriksaan_fisik: 'Temuan awal' },
        normalizeClinicalAnswer: (value) => value?.trim() || null,
        confirmAction(description, action, actionLabel, title) { requests.push({ description, action, actionLabel, title }); },
        setActiveTab() { assert.fail('saving must not change the active tab'); },
        navigateSection() { assert.fail('saving must not scroll or navigate to another section'); },
        setSelectedProcedure() {}, setProcedureQuery() {}, setProcedureResults() {}, setSelectedDentalFindingKeys() {},
    };
    Object.defineProperty(scope, 'saving', { get: () => Object.values(forms).some((form) => form.processing) });
    attachDerivedState(ts, ast, scope, ['savedAnamnesis', 'anamnesisChanged', 'sectionSaveAllowed', 'addendumChanged', 'canSaveAddendum', 'selectedDentalRecord', 'dentalFindingChanged', 'canSaveDentalFinding']);
    const handlers = runInNewContext(code + '\n({ submitClinicalSection, submitForm, canSubmitEntry, submitAddendum, submitDentalFinding })', scope);
    return { handlers, forms, requests, posts, scope, event: { preventDefault() {} } };
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


for (const clinicType of ['gigi', 'umum']) {
    test(clinicType + ' main workflow ends at clinic prescription and each optional tab can finish independently', async () => {
        const props = examinationProps(clinicType);
        props.initialTab = 'resep';

        const html = (await renderPage('pelayanan/pemeriksaan/show', props)).replace(/<!--.*?-->/g, '');

        assert.match(html, /aria-selected="true"[^>]*data-value="resep"/);
        assert.doesNotMatch(html, /Pilihan tambahan/);
        for (const tab of ['resep-luar', 'surat', 'rujukan']) {
            assert.ok(html.includes('data-value="' + tab + '"'), 'optional services remain available as ordinary tabs');
        }
        for (const tab of ['anamnesis', clinicType === 'gigi' ? 'odontogram' : 'pemeriksaan', 'diagnosa']) {
            assert.doesNotMatch(examinationPanel(html, tab), />Selesai periksa<|Selesai periksa<\/button>/);
        }
        const treatments = examinationPanel(html, 'tindakan');
        assert.match(treatments, /Selesai periksa<\/button>/);
        assert.match(treatments, /Lanjut ke resep klinik/);
        const prescription = examinationPanel(html, 'resep');
        assert.match(prescription, /Kembali ke Tindakan/);
        assert.match(prescription, /Selesai periksa<\/button>/);
        assert.doesNotMatch(prescription, /Lanjut ke/);
        for (const tab of ['resep-luar', 'surat', 'rujukan']) {
            const panel = examinationPanel(html, tab);
            assert.match(panel, /Kembali ke Resep klinik/);
            assert.match(panel, /Selesai periksa<\/button>/);
            assert.doesNotMatch(panel, /Lanjut ke/, 'an optional service must not force the next optional service');
        }
    });

    test(clinicType + ' optional service deep links stay available and finalized visits cannot finish again from tab footers', async () => {
        const props = examinationProps(clinicType);
        props.initialTab = 'rujukan';
        props.visit.status = 'selesai';
        props.visit.examination = { ...props.visit.examination, status: 'selesai', signedAt: '25 Oktober 2026', integrityValid: true };

        const html = (await renderPage('pelayanan/pemeriksaan/show', props)).replace(/<!--.*?-->/g, '');

        assert.match(html, /aria-selected="true"[^>]*data-value="rujukan"/);
        for (const tab of ['tindakan', 'resep', 'resep-luar', 'surat', 'rujukan']) {
            const panel = examinationPanel(html, tab);
            const buttons = [...panel.matchAll(/<button\b[^>]*>[\s\S]*?<\/button>/g)].map(([button]) => button);
            const finish = buttons.filter((button) => button.includes('Selesai periksa'));
            assert.equal(finish.length, 1, tab + ' should have one consistent finish action');
            assert.match(finish[0], / disabled=""/);
        }
    });
}


test('general physical examination offers interactive and annotation tabs with explicit clinical inputs and no forced defaults', async () => {
    const html = await renderPage('pelayanan/pemeriksaan/show', examinationProps('umum'));
    const physical = examinationPanel(html, 'pemeriksaan');
    assert.match(physical, /aria-label="Cara pemeriksaan fisik"/);
    assert.match(physical, /data-value="interactive"/);
    assert.match(physical, /data-value="manual"/);
    assert.match(physical, /Peta interaktif|Gambar &amp; anotasi/);
    for (const id of ['body-location', 'body-side', 'body-location-detail', 'body-finding-method', 'body-pain', 'body-local-complaint', 'body-objective-finding']) assert.ok(physical.includes('id="' + id + '"'), 'missing ' + id);
    const objective = physical.match(/<textarea[^>]*id="body-objective-finding"[^>]*>[\s\S]*?<\/textarea>/)?.[0];
    assert.ok(objective);
    assert.doesNotMatch(objective, / required=|dalam batas normal|tidak ada kelainan/);
    assert.match(physical, /Tambahkan temuan/);
    assert.doesNotMatch(physical, /data-value="quick"|Pemeriksaan manual/);
    assert.match(physical, /id="body-compact"/);
    assert.doesNotMatch(physical, /body-system-finding|Pemeriksaan fisik per sistem terarah|Temuan klinis -/);
    assert.doesNotMatch(physical, /aria-pressed="true"[^>]*role="button"/);
    assert.doesNotMatch(physical, /ICD-10 Mapped|Temuan normal otomatis/);
});

test('manual examination has a complete finding form beside annotations and preserves old notes without duplicate inputs', async () => {
    const { PhysicalExaminationWorkspace } = await server.ssrLoadModule('/resources/js/components/anatomy/physical-examination-workspace.tsx');
    const { emptyBodyFinding } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const html = renderToString(createElement(PhysicalExaminationWorkspace, {
        initialMode: 'manual', systems: { Thorax: 'Ronki basal kanan', Kulit: 'Ruam lengan' }, draft: { ...emptyBodyFinding }, onChange() {}, onDraftChange() {},
    }));
    assert.match(html, /aria-selected="true"[^>]*data-value="manual"/);
    assert.match(html, /src="\/body-model-reference.jpg"/);
    assert.match(html, /Ronki basal kanan/);
    assert.doesNotMatch(html, /body-system-finding|Temuan klinis -/);
    for (const id of ['body-location-detail', 'body-finding-method', 'body-pain', 'body-local-complaint', 'body-objective-finding']) assert.equal((html.match(new RegExp('id="' + id + '"', 'g')) ?? []).length, 1);
    assert.match(html, /Temuan pemeriksaan/);
});

test('body locations preserve patient laterality when changing anterior and posterior views', async () => {
    const { bodyLocations } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const front = bodyLocations('front');
    const back = bodyLocations('back');
    assert.equal(front.find((region) => region.id === 'front-upper-arm-kanan').x, 105);
    assert.equal(back.find((region) => region.id === 'back-upper-arm-kanan').x, 257);
    assert.equal(front.find((region) => region.id === 'front-upper-arm-kiri').x, 257);
    assert.equal(back.find((region) => region.id === 'back-upper-arm-kiri').x, 105);
    assert.equal(new Set(front.map((region) => region.id)).size, front.length);
    assert.equal(back.find((region) => region.id === 'back-spine').category, 'Neurologis');
});

test('body findings append with location, method and explicit zero pain without overwriting other clinical notes', async () => {
    const { appendBodyFinding, emptyBodyFinding } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const original = { Thorax: 'Suara napas vesikuler', Ekstremitas: 'Edema tungkai kiri' };
    const result = appendBodyFinding(original, { ...emptyBodyFinding, locationLabel: 'Siku kanan', category: 'Ekstremitas', view: 'depan', side: 'kanan', method: 'Palpasi', symptom: 'Nyeri gerak', finding: 'Tidak ada nyeri tekan', pain: '0' });
    assert.equal(result.error, undefined);
    assert.equal(original.Ekstremitas, 'Edema tungkai kiri');
    assert.equal(result.systems.Thorax, original.Thorax);
    assert.equal(result.systems.Ekstremitas, 'Edema tungkai kiri\n- [Siku kanan; depan] Metode: Palpasi. Keluhan lokal: Nyeri gerak. Skala nyeri: 0/10. Temuan: Tidak ada nyeri tekan');
});

test('body finding validation rejects unknown results, invalid pain and overflow without losing recorded findings', async () => {
    const { appendBodyFinding, emptyBodyFinding, hasBodyFindingDraft } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const systems = { Thorax: 'Temuan lama' };
    const draft = { ...emptyBodyFinding, locationLabel: 'Paru-paru', finding: 'Ronki kanan' };
    for (const change of [{ finding: 'Belum dicatat' }, { finding: '' }, { pain: '-1' }, { pain: '11' }, { pain: '1.5' }, { category: 'Tidak valid' }]) {
        const result = appendBodyFinding(systems, { ...draft, ...change });
        assert.ok(result.error);
        assert.equal(result.systems, systems);
    }
    const long = { Thorax: 'a'.repeat(2990) };
    assert.equal(appendBodyFinding(long, draft).systems, long);
    assert.ok(appendBodyFinding(long, draft).error);
    assert.equal(hasBodyFindingDraft({ ...emptyBodyFinding, locationLabel: 'Paru-paru', category: 'Thorax' }), false);
    assert.equal(hasBodyFindingDraft({ ...emptyBodyFinding, pain: '0' }), true);
    assert.equal(hasBodyFindingDraft({ ...emptyBodyFinding, symptom: 'Nyeri' }), true);
});

test('interactive body regions are keyboard accessible and locked exams cannot edit clinical inputs', async () => {
    const { HumanBodyModel } = await server.ssrLoadModule('/resources/js/components/anatomy/human-body-model.tsx');
    for (const view of ['front', 'back']) {
        const html = renderToString(createElement(HumanBodyModel, { mode: 'body', model: 'female', view, selectedId: '', onSelect() {} }));
        assert.match(html, /aria-label="Lengan atas kanan/);
        assert.match(html, /role="button" tabindex="0"/);
        const locked = renderToString(createElement(HumanBodyModel, { mode: 'body', model: 'male', view, selectedId: '', disabled: true, onSelect() {} }));
        assert.doesNotMatch(locked, /tabindex="0"/);
        assert.match(locked, /aria-disabled="true"/);
    }
    const props = examinationProps('umum');
    props.visit.status = 'selesai';
    props.visit.examination.status = 'selesai';
    const html = await renderPage('pelayanan/pemeriksaan/show', props);
    const physical = examinationPanel(html, 'pemeriksaan');
    assert.match(physical.match(/<textarea[^>]*id="body-objective-finding"[^>]*>/)?.[0], /disabled=""/);
    assert.match(physical.match(/<textarea[^>]*id="pemeriksaan_fisik"[^>]*>/)?.[0], /disabled=""/);
});

test('saving a physical examination blocks a pending location entry until it is added or cleared', async () => {
    const { handlers, requests, posts, event } = await clinicalSaveHarness(true);
    handlers.submitClinicalSection(event, 'pemeriksaan');
    assert.equal(requests.length, 0);
    assert.equal(posts.length, 0);
    handlers.submitClinicalSection(event, 'anamnesis');
    assert.equal(requests.length, 1);
});

test('finalizing an examination returns to a pending body finding before opening the signature dialog', async () => {
    const { default: ts } = await import('typescript');
    const source = readFileSync(new URL('../resources/js/pages/pelayanan/pemeriksaan/show.tsx', import.meta.url), 'utf8');
    const ast = ts.createSourceFile('show.tsx', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    let declaration;
    let finish;
    function collect(node) {
        if (ts.isVariableDeclaration(node) && node.name.getText(ast) === 'unsavedSections') declaration = 'const ' + node.getText(ast) + ';';
        if (ts.isFunctionDeclaration(node) && node.name?.text === 'finishExamination') finish = node.getText(ast);
        ts.forEachChild(node, collect);
    }
    collect(ast);
    assert.ok(declaration && finish);
    const { emptyBodyFinding, hasBodyFindingDraft } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    for (const pending of [true, false]) {
        const navigation = [];
        const signatures = [];
        const errors = [];
        const names = ['examinationForm', 'physicalForm', 'followUpForm', 'dentalForm', 'diagnosisForm', 'differentialForm', 'prescriptionForm', 'externalPrescriptionForm', 'letterForm', 'referralForm'];
        const scope = {
            ...Object.fromEntries(names.map((name) => [name, { isDirty: false }])),
            treatmentForm: { data: { terminology_id: '', tindakan_id: '', nama_tindakan_manual: '', catatan: '' } },
            hasDiagnosisDraft: false, anamnesisChanged: false, bodyFindingPending: pending, isDental: false, physicalTab: 'pemeriksaan', saving: false, readiness: [{ complete: true }],
            bodyFindingDraft: { ...emptyBodyFinding, finding: pending ? 'Nyeri tekan siku kanan' : '' }, hasBodyFindingDraft,
            navigateSection: (tab) => navigation.push(tab), setSignatureDialogOpen: (open) => signatures.push(open), toast: { error: (message) => errors.push(message) },
        };
        const code = ts.transpileModule(declaration + '\n' + finish + '\nfinishExamination();', { compilerOptions: { target: ts.ScriptTarget.ESNext } }).outputText;
        runInNewContext(code, scope);
        assert.deepEqual(navigation, pending ? ['pemeriksaan'] : []);
        assert.deepEqual(signatures, pending ? [] : [true]);
        assert.equal(errors.length, pending ? 1 : 0);
    }
});
function recordedBodyMap() {
    return { version: 1, findings: [{ id: 'head-1', locationId: 'front-head', mode: 'body', view: 'front', model: 'female', category: 'Kepala dan leher', side: 'kanan', detail: 'Dahi kanan', method: 'Palpasi', symptom: 'Benjolan', finding: 'Benjolan 1 cm <script>alert(1)</script>', pain: 0 }], annotation: { strokes: [{ id: 'draw-1', tool: 'pen', color: '#dc2626', width: 2, points: [{ x: 30, y: 40 }, { x: 50, y: 60 }] }], note: 'Tanda merah: benjolan dahi kanan' } };
}

test('body record shows selected anatomy, laterality, zero pain and stored annotation without exposing edit actions', async () => {
    const { BodyExaminationRecord } = await server.ssrLoadModule('/resources/js/components/anatomy/body-examination-record.tsx');
    const html = renderToString(createElement(BodyExaminationRecord, { data: recordedBodyMap() }));
    assert.match(html, /Kepala dan wajah/);
    assert.match(html, /Model perempuan/);
    assert.match(html, /aria-label="Kepala dan wajah \(depan\)" aria-pressed="true"/);
    assert.match(html, /Dahi kanan/);
    assert.match(html.replace(/<!--.*?-->/g, ''), /Nyeri: 0\/10/);
    assert.match(html, /data-annotation-id="draw-1"/);
    assert.match(html, /points="30,40 50,60"/);
    assert.match(html, /Tanda merah: benjolan dahi kanan/);
    assert.doesNotMatch(html, /<script>|Hapus semua goresan|<textarea/);
    assert.match(html, /&lt;script&gt;/);
    assert.match(html, /print:grid/);
});

test('manual image markings do not add an unselected interactive body to the diagnosis summary', async () => {
    const { GeneralExaminationSummary } = await server.ssrLoadModule('/resources/js/components/kunjungan/clinical-workflow-summary.tsx');
    const bodyMap = recordedBodyMap();
    bodyMap.findings[0].entryMode = 'manual';
    const html = renderToString(createElement(GeneralExaminationSummary, {
        anamnesis: 'Keluhan pasien', physicalExam: null, physicalSystems: {}, bodyMap,
        hasUnsavedChanges: false, onReview() {},
    }));
    assert.equal((html.match(/src="\/body-model-reference\.jpg"/g) ?? []).length, 1);
    assert.match(html, /Gambar &amp; anotasi|Tanda merah: benjolan dahi kanan/);
    assert.doesNotMatch(html, /aria-label="Peta seluruh tubuh interaktif|aria-label="Peta organ tubuh interaktif|Tampilkan lokasi pada peta/);
});

test('manual body annotation editor provides drawing controls and a locked record removes editing tools', async () => {
    const { BodyAnnotationEditor } = await server.ssrLoadModule('/resources/js/components/anatomy/body-annotation.tsx');
    const value = recordedBodyMap().annotation;
    const html = renderToString(createElement(BodyAnnotationEditor, { value, onChange() {} }));
    for (const label of ['Pena', 'Garis', 'Panah', 'Lingkaran', 'Hapus goresan', 'Urungkan goresan', 'Ulangi goresan', 'Hapus semua goresan']) assert.ok(html.includes('aria-label="' + label + '"'), label);
    assert.match(html, /touch-none/);
    assert.match(html, /viewBox="0 0 592 558"/);
    const locked = renderToString(createElement(BodyAnnotationEditor, { value, disabled: true, onChange() {} }));
    assert.doesNotMatch(locked, /aria-label="Pena"|Kanvas anotasi/);
    assert.match(locked, /data-annotation-id="draw-1"/);
    assert.match(locked, /<textarea[^>]*disabled=""/);
});

test('recorded body findings retain exact location and model and legacy recovery only accepts explicit location labels', async () => {
    const { createRecordedFinding, legacyBodyFindings, annotationPoint } = await server.ssrLoadModule('/resources/js/lib/body-map.ts');
    const { emptyBodyFinding } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const draft = { ...emptyBodyFinding, locationId: 'back-hand-kanan', locationLabel: 'Punggung tangan kanan', category: 'Ekstremitas', finding: 'Bengkak', pain: '0', side: 'kiri' };
    const result = createRecordedFinding(draft, 'body', 'back', 'female', 'finding-1');
    assert.equal(result.finding.locationId, 'back-hand-kanan');
    assert.equal(result.finding.side, 'kanan', 'a lateral region fixes laterality to the selected patient side');
    assert.equal(result.finding.model, 'female');
    assert.equal(result.finding.pain, 0);
    assert.ok(createRecordedFinding({ ...draft, pain: '1.5' }, 'body', 'back', 'female', 'finding-1').error);
    assert.ok(createRecordedFinding({ ...draft, finding: 'Belum dicatat' }, 'body', 'back', 'female', 'finding-1').error);
    const recovered = legacyBodyFindings({ 'Kepala dan leher': '- [Kepala dan wajah; depan] Temuan: Bengkak', Thorax: 'Nyeri kepala dan dada', Kulit: '- [Lokasi tidak dikenal] Temuan: Ruam' }, 'male');
    assert.equal(recovered.length, 1);
    assert.equal(recovered[0].locationId, 'front-head');
    assert.deepEqual(JSON.parse(JSON.stringify(annotationPoint(150, 200, { left: 50, top: 50, width: 296, height: 279 }))), { x: 200, y: 300 });
    assert.deepEqual(JSON.parse(JSON.stringify(annotationPoint(-20, 1000, { left: 0, top: 0, width: 592, height: 558 }))), { x: 0, y: 558 });
});

test('medical record renders current and historical body selections with matching annotation versions', async () => {
    const current = recordedBodyMap();
    const previous = structuredClone(current);
    previous.annotation.note = 'Anotasi versi final';
    previous.findings[0].finding = 'Benjolan versi final';
    const examination = { anamnesis: 'Benjolan', physicalExamination: 'Keadaan umum baik', physicalSystems: {}, bodyMap: current, diagnoses: [], versions: [{ version: 1, kind: 'finalized', actor: 'Dokter Uji', recordedAt: '25/10/2026', physicalSystems: {}, bodyMap: previous, odontogram: [] }], signedAt: '25/10/2026', signedBy: 'Dokter Uji', integrityValid: true };
    const html = await renderPage('pelayanan/pasien/rekam-medis', {
        patient: { id: 7, name: 'Pasien Uji', medicalRecordNumber: 'RM-007', gender: 'P', age: 30 },
        visits: [{ id: 1, number: 'K-001', clinic: 'Poli Umum', clinicType: 'umum', doctor: 'Dokter Uji', date: '25/10/2026', status: 'selesai', screening: null, examination, prescriptions: [], treatments: [], odontogram: [] }],
        backUrl: '/pelayanan/pasien/7', canSelectClinic: false, filters: { search: '', poliklinik_id: '', status: '', dari: '', sampai: '' }, clinics: [], pagination: singlePage,
    });
    assert.match(html, /Tanda merah: benjolan dahi kanan/);
    assert.match(html, /Anotasi versi final/);
    assert.match(html, /Benjolan versi final/);
    assert.equal((html.match(/data-annotation-id="draw-1"/g) ?? []).length, 2);
    assert.ok(html.indexOf('Ringkasan pemeriksaan fisik') < html.indexOf('Finalisasi &amp; riwayat koreksi'));
    assert.match(html, /Lihat riwayat versi pemeriksaan/);
    assert.doesNotMatch(html, /<script>|Hapus goresan|<textarea/);
});

test('physical summary shows only recorded map findings or annotations when narrative is empty', async () => {
    const { GeneralExaminationSummary } = await server.ssrLoadModule('/resources/js/components/kunjungan/clinical-workflow-summary.tsx');
    const mapOnly = recordedBodyMap();
    mapOnly.annotation = { strokes: [], note: '' };
    const props = { anamnesis: null, physicalExam: null, physicalSystems: {}, hasUnsavedChanges: false, onReview() {} };
    const mapHtml = renderToString(createElement(GeneralExaminationSummary, { ...props, bodyMap: mapOnly }));
    assert.match(mapHtml, /temuan dicatat/);
    assert.doesNotMatch(mapHtml, /<dt[^>]*>Ringkasan pemeriksaan fisik<\/dt>|Anotasi pemeriksaan tubuh|Temuan per sistem/);

    const annotationOnly = { ...mapOnly, findings: [], annotation: { strokes: [], note: 'Lokasi nyeri ditandai pada gambar' } };
    const annotationHtml = renderToString(createElement(GeneralExaminationSummary, { ...props, bodyMap: annotationOnly }));
    assert.match(annotationHtml, /Anotasi tersimpan|Lokasi nyeri ditandai pada gambar/);
    assert.doesNotMatch(annotationHtml, /temuan dicatat|Temuan per sistem|<dt[^>]*>Ringkasan pemeriksaan fisik<\/dt>/);
});

test('medical record displays annotation without an empty physical narrative card', async () => {
    const bodyMap = { version: 1, findings: [], annotation: { strokes: [], note: 'Lokasi keluhan ditandai' } };
    const examination = { anamnesis: 'Keluhan pasien', physicalExamination: null, physicalSystems: {}, bodyMap, diagnoses: [], versions: [], signedAt: null, signedBy: null, integrityValid: null };
    const html = await renderPage('pelayanan/pasien/rekam-medis', {
        patient: { id: 7, name: 'Pasien Uji', medicalRecordNumber: 'RM-007', gender: 'P', age: 30 },
        visits: [{ id: 1, number: 'K-001', clinic: 'Poli Umum', clinicType: 'umum', doctor: 'Dokter Uji', date: '29/10/2026', status: 'pemeriksaan', screening: null, examination, prescriptions: [], treatments: [], odontogram: [] }],
        backUrl: '/pelayanan/pasien/7', canSelectClinic: false, filters: { search: '', poliklinik_id: '', status: '', dari: '', sampai: '' }, clinics: [], pagination: singlePage,
    });
    assert.match(html, /Lokasi keluhan ditandai/);
    assert.doesNotMatch(html, /Ringkasan pemeriksaan fisik/);
});

test('body annotation pointer strokes persist immediately and preserve coordinates across resized canvases', async () => {
    const { default: ts } = await import('typescript');
    const { annotationPoint, annotationLimits } = await server.ssrLoadModule('/resources/js/lib/body-map.ts');
    const source = readFileSync(new URL('../resources/js/components/anatomy/body-annotation.tsx', import.meta.url), 'utf8');
    const ast = ts.createSourceFile('annotation.tsx', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    const declarations = [];
    function collect(node) { if (ts.isFunctionDeclaration(node) && ['commit', 'begin', 'move', 'finish'].includes(node.name?.text)) declarations.push(node.getText(ast)); ts.forEachChild(node, collect); }
    collect(ast);
    const current = { current: { strokes: [], note: 'Keterangan tetap ada' } };
    const changes = [];
    const captured = new Set();
    const code = ts.transpileModule(declarations.join('\n'), { compilerOptions: { target: ts.ScriptTarget.ESNext } }).outputText;
    const handlers = runInNewContext(code + '\n({ begin, move, finish })', { current, ownStrokes: { current: [] }, drawing: { current: null }, disabled: false, tool: 'pen', color: '#dc2626', width: 2, annotationLimits, annotationPoint, crypto: { randomUUID: () => 'stroke-test' }, setRedo() {}, setMessage() {}, onChange: (value) => changes.push(value) });
    const target = { setPointerCapture: (id) => captured.add(id), hasPointerCapture: (id) => captured.has(id), releasePointerCapture: (id) => captured.delete(id), getBoundingClientRect: () => ({ left: 10, top: 20, width: 296, height: 279 }) };
    const event = (clientX, clientY) => ({ isPrimary: true, button: 0, pointerId: 1, clientX, clientY, currentTarget: target, preventDefault() {} });
    handlers.begin(event(30, 40));
    assert.equal(changes.length, 1, 'an unfinished stroke is already part of the dirty clinical form');
    handlers.move(event(40, 60));
    handlers.finish(event(50, 70));
    const stroke = JSON.parse(JSON.stringify(current.current.strokes[0]));
    assert.deepEqual(stroke.points, [{ x: 40, y: 40 }, { x: 60, y: 80 }, { x: 80, y: 100 }]);
    assert.equal(current.current.note, 'Keterangan tetap ada');
    assert.equal(captured.size, 0);
});

test('legacy quick body findings keep custom locations without fabricating a map selection', async () => {
    const { PhysicalExaminationWorkspace } = await server.ssrLoadModule('/resources/js/components/anatomy/physical-examination-workspace.tsx');
    const { BodyExaminationRecord } = await server.ssrLoadModule('/resources/js/components/anatomy/body-examination-record.tsx');
    const { emptyBodyFinding } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const { createRecordedFinding, emptyBodyMap } = await server.ssrLoadModule('/resources/js/lib/body-map.ts');
    const draft = { ...emptyBodyFinding, locationId: 'other', locationLabel: 'Area sekitar luka operasi', category: 'Lainnya', finding: 'Luka kering', detail: 'Hidden detail', method: 'Palpasi', symptom: 'Hidden symptom', pain: '4' };
    const { finding } = createRecordedFinding(draft, 'body', 'front', 'male', 'quick-1', 'quick');
    assert.equal(finding.entryMode, 'quick');
    assert.equal(finding.locationLabel, draft.locationLabel);
    assert.equal(finding.detail, '');
    assert.equal(finding.method, '');
    assert.equal(finding.symptom, '');
    assert.equal(finding.pain, null);
    const record = renderToString(createElement(BodyExaminationRecord, { data: { ...emptyBodyMap(), findings: [finding] } }));
    assert.match(record, /Area sekitar luka operasi/);
    assert.match(record, /Luka kering/);
    assert.doesNotMatch(record, /aria-pressed="true"|Skala nyeri/);
});

test('manual findings preserve detailed examination fields and require a description for other examination methods', async () => {
    const { createRecordedFinding } = await server.ssrLoadModule('/resources/js/lib/body-map.ts');
    const { emptyBodyFinding } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const draft = { ...emptyBodyFinding, locationId: 'front-head', locationLabel: 'Kepala dan wajah', category: 'Kepala dan leher', detail: 'Dahi kanan', method: 'Lainnya', methodDetail: 'Dermatoskopi', symptom: 'Gatal', finding: 'Lesi berukuran 1 cm', pain: '0' };
    const result = createRecordedFinding(draft, 'body', 'front', 'female', 'manual-1', 'manual');
    assert.equal(result.finding.entryMode, 'manual');
    for (const key of ['detail', 'method', 'methodDetail', 'symptom', 'finding']) assert.equal(result.finding[key], draft[key]);
    assert.equal(result.finding.pain, 0);
    assert.ok(createRecordedFinding({ ...draft, methodDetail: '' }, 'body', 'front', 'female', 'manual-1', 'manual').error);
    assert.ok(createRecordedFinding({ ...draft, locationId: '', locationLabel: '' }, 'body', 'front', 'female', 'manual-1', 'manual').error);
});

test('interactive body selection toggles off without clearing clinical text and switching location requires confirmation', async () => {
    const { default: ts } = await import('typescript');
    const source = readFileSync(new URL('../resources/js/components/anatomy/physical-examination-workspace.tsx', import.meta.url), 'utf8');
    const ast = ts.createSourceFile('workspace.tsx', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    let fn;
    function collect(node) { if (ts.isFunctionDeclaration(node) && node.name?.text === 'chooseLocation') fn = node.getText(ast); ts.forEachChild(node, collect); }
    collect(ast);
    const changes = [];
    const confirmations = [];
    const draft = { locationId: 'front-head', locationLabel: 'Kepala dan wajah', category: 'Kepala dan leher', finding: 'Benjolan dahi', side: 'kanan' };
    const code = ts.transpileModule(fn, { compilerOptions: { target: ts.ScriptTarget.ESNext } }).outputText;
    const choose = runInNewContext(code + '\nchooseLocation', { disabled: false, draft, pending: true, mode: 'body', view: 'front', setMode() {}, setView() {}, setError() {}, onDraftChange: (data) => changes.push(data), confirmAction: (...args) => confirmations.push(args) });
    choose({ id: 'front-head', label: 'Kepala dan wajah', category: 'Kepala dan leher' }, 'body', 'front', true);
    assert.equal(changes[0].locationId, '');
    assert.equal(changes[0].finding, draft.finding);
    assert.equal(confirmations.length, 0);
    choose({ id: 'front-neck', label: 'Leher depan', category: 'Kepala dan leher' });
    assert.equal(confirmations.length, 1);
    assert.equal(changes.length, 1);
    confirmations[0][1]();
    assert.equal(changes[1].locationId, 'front-neck');
    assert.equal(changes[1].finding, draft.finding);
});

for (const clinicType of ['umum', 'gigi']) {
    test(clinicType + ' examination shares manual diagnosis and treatment switches and keeps nurse summary cards aligned', async () => {
        const props = examinationProps(clinicType);
        props.visit.diagnoses = [{ id: 1, code: null, codeSystem: 'manual', name: 'Diagnosis naratif dokter', type: 'utama' }];
        const html = await renderPage('pelayanan/pemeriksaan/show', props);
        assert.match(examinationPanel(html, 'diagnosa'), /id="diagnosis-manual"/);
        assert.match(examinationPanel(html, 'tindakan'), /id="treatment-manual"/);
        assert.match(examinationPanel(html, 'diagnosa'), />Manual</);
        assert.match(examinationPanel(html, 'tindakan'), /Diagnosis naratif dokter/);
        assert.doesNotMatch(examinationPanel(html, 'tindakan'), /<option value="manual">/);
        if (clinicType === 'gigi') assert.doesNotMatch(html, /data-value="quick"|body-location/);
    });
}

test('manual diagnosis form renders a text diagnosis and type without a hidden mandatory coding field', async () => {
    const { default: ts } = await import('typescript');
    const source = readFileSync(new URL('../resources/js/pages/pelayanan/pemeriksaan/show.tsx', import.meta.url), 'utf8');
    const ast = ts.createSourceFile('show.tsx', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    let form;
    function collect(node) {
        if (ts.isJsxElement(node) && node.openingElement.tagName.getText(ast) === 'form' && node.openingElement.getText(ast).includes('submitForm(event, diagnosisForm,')) form = node.getText(ast);
        ts.forEachChild(node, collect);
    }
    collect(ast);
    const components = {};
    for (const [file, names] of [['field', ['Field']], ['input', ['Input']], ['select', ['Select']], ['checkbox', ['Checkbox']], ['button', ['Button']], ['textarea', ['Textarea']]]) Object.assign(components, await server.ssrLoadModule('/resources/js/components/ui/' + file + '.tsx'));
    const { Plus } = await import('lucide-react');
    const { normalizeClinicalAnswer } = await server.ssrLoadModule('/resources/js/lib/clinical-answer.ts');
    const code = ts.transpileModule('const element = (' + form + ');', { compilerOptions: { target: ts.ScriptTarget.ESNext, jsx: ts.JsxEmit.React } }).outputText;
    const element = runInNewContext(code + '\nelement', { React: { createElement }, ...components, Plus, normalizeClinicalAnswer, canSubmitEntry: () => true, visit: { id: 1 }, diagnosisForm: { data: { is_manual: true, kode_icd10: '', nama_diagnosa: 'Diagnosis bebas', keterangan: 'Rincian diagnosis dokter', jenis: 'utama' }, errors: {}, processing: false } });
    const html = renderToString(element);
    assert.match(html, /Jenis diagnosis/);
    assert.match(html, /Keterangan diagnosis/);
    assert.match(html, /Rincian diagnosis dokter/);
    assert.match(html, /border-t[^>]*><label[^>]*for="diagnosis-manual"/);
    assert.match(html, /value="Diagnosis bebas"/);
    assert.doesNotMatch(html, /id="kode_icd10"|id="icd-search"|id="diagnosis-code-system"/);
    const submit = [...html.matchAll(/<button[^>]*type="submit"[^>]*>/g)].at(-1)?.[0];
    assert.ok(submit);
    assert.doesNotMatch(submit, /disabled=""/);
});


test('body location dropdown exposes all organ and body locations plus other through the shared Select', async () => {
    const { default: ts } = await import('typescript');
    const { Children } = await import('react');
    const { bodyLocations, organLocations } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const source = readFileSync(new URL('../resources/js/components/anatomy/physical-examination-workspace.tsx', import.meta.url), 'utf8');
    const ast = ts.createSourceFile('workspace.tsx', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    let fn;
    function collect(node) { if (ts.isFunctionDeclaration(node) && node.name?.text === 'renderLocationPicker') fn = node.getText(ast); ts.forEachChild(node, collect); }
    collect(ast);
    const code = ts.transpileModule(fn, { compilerOptions: { target: ts.ScriptTarget.ESNext, jsx: ts.JsxEmit.React } }).outputText;
    for (const tab of ['interactive', 'manual']) {
        const render = runInNewContext(code + '\nrenderLocationPicker', { React: { createElement }, Field: 'field', Select: 'select', Input: 'input', inputId: (id) => id, draft: { locationId: '' }, disabled: false, locationChanged() {}, tab, mode: 'organs', view: 'front', organLocations, bodyLocations });
        const select = render().props.children.props.children[0];
        // The shared Select accepts direct option children only, not optgroup or fragments.
        const options = Children.toArray(select.props.children);
        assert.ok(options.every((option) => option.type === 'option'));
        const values = options.map((option) => option.props.value);
        assert.equal(values[0], '');
        assert.equal(values.at(-1), 'other');
        assert.equal(options.at(-1).props.children, 'Lainnya');
        assert.equal(new Set(values).size, values.length);
        assert.ok(values.includes('lungs'));
        if (tab !== 'interactive') {
            for (const region of [...bodyLocations('front'), ...bodyLocations('back')]) assert.ok(values.includes(region.id), tab + ': ' + region.id);
        }
    }
});


for (const tab of ['interactive', 'manual']) {
    test('compact physical examination in ' + tab + ' keeps both body and finding columns with the same layout as full examination', async () => {
        const { PhysicalExaminationWorkspace } = await server.ssrLoadModule('/resources/js/components/anatomy/physical-examination-workspace.tsx');
        const { BodyExaminationRecord } = await server.ssrLoadModule('/resources/js/components/anatomy/body-examination-record.tsx');
        const { emptyBodyFinding } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
        const { createRecordedFinding, emptyBodyMap } = await server.ssrLoadModule('/resources/js/lib/body-map.ts');
        const draft = { ...emptyBodyFinding, finding: 'Temuan pemeriksaan naratif dokter' };
        const html = renderToString(createElement(PhysicalExaminationWorkspace, { initialMode: tab, initialCompact: true, systems: {}, draft, onChange() {}, onDraftChange() {} }));
        assert.equal((html.match(/role="tab"/g) ?? []).length, 2);
        assert.match(html, /grid min-w-0 items-start gap-5 xl:grid-cols-2/);
        assert.match(html, /<section class="min-w-0 space-y-4 rounded-xl border border-neutral-200 p-4 sm:p-5" aria-label="Input pemeriksaan ringkas"/);
        const visualLabel = tab === 'interactive' ? 'Diagram anatomi tubuh' : 'Gambar dan alat anotasi';
        assert.ok(html.indexOf('aria-label="' + visualLabel + '"') < html.indexOf('aria-label="Input pemeriksaan ringkas"'));
        if (tab === 'interactive') {
            assert.match(html, /aria-label="Peta organ tubuh interaktif tampak depan"/);
            assert.match(html, /id="body-model"/);
        } else {
            assert.match(html, /src="\/body-model-reference.jpg"/);
            assert.match(html, /aria-label="Alat anotasi"/);
            assert.match(html, /aria-label="Kanvas anotasi tubuh/);
        }

        assert.match(html, /id="body-objective-finding"/);
        assert.match(html, /aria-checked="true"[^>]*id="body-compact"/);
        assert.match(html, /Isian ringkas<\/label><button[^>]*type="button"[^>]*>[\s\S]*?Tambahkan temuan/);
        assert.doesNotMatch(html, /id="body-location"|id="body-side"|id="body-pain"|id="body-location-detail"|id="body-finding-method"|id="body-local-complaint"|annotation-note|data-value="quick"/);
        assert.equal((html.match(/<textarea/g) ?? []).length, 1);
        const { finding, error } = createRecordedFinding(draft, 'organs', 'front', 'male', 'compact-1', 'quick');
        assert.equal(error, undefined);
        assert.equal(finding.locationId, 'general');
        assert.equal(finding.locationLabel, undefined);
        const record = renderToString(createElement(BodyExaminationRecord, { data: { ...emptyBodyMap(), findings: [finding] } }));
        assert.match(record, /Pemeriksaan fisik/);
        assert.match(record, /Temuan pemeriksaan naratif dokter/);
        assert.doesNotMatch(record, /aria-pressed="true"|Tampilkan lokasi pada peta/);
    });
}

test('annotation examination aligns its cards and places one annotation note in the finding form', async () => {
    const { PhysicalExaminationWorkspace } = await server.ssrLoadModule('/resources/js/components/anatomy/physical-examination-workspace.tsx');
    const { emptyBodyFinding } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const html = renderToString(createElement(PhysicalExaminationWorkspace, { initialMode: 'manual', systems: {}, draft: emptyBodyFinding, onChange() {}, onDraftChange() {} }));
    assert.match(html, /Gambar &amp; anotasi/);
    assert.doesNotMatch(html, /Pemeriksaan manual/);
    assert.match(html, /grid min-w-0 items-start gap-5 xl:grid-cols-2/);
    const right = html.match(/<section[^>]*aria-label="Input temuan lokasi tubuh"[^>]*>([\s\S]*?)<\/section>/)?.[1];
    const editor = html.match(/<section[^>]*aria-label="Editor anotasi tubuh"[^>]*>([\s\S]*?)<\/section>/)?.[1];
    assert.ok(right && editor);
    assert.match(right, /id="body-annotation-note"/);
    assert.doesNotMatch(editor, /annotation-note/);
    assert.equal((html.match(/Keterangan anotasi<\/label>/g) ?? []).length, 1);
    for (const label of ['Alat anotasi', 'Kelola goresan', 'Warna pena', 'Ketebalan pena']) assert.ok(editor.includes('aria-label="' + label + '"'));
});

test('compact examination requires confirmation before dropping details and preserves the finding text', async () => {
    const { default: ts } = await import('typescript');
    const { emptyBodyFinding } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const source = readFileSync(new URL('../resources/js/components/anatomy/physical-examination-workspace.tsx', import.meta.url), 'utf8');
    const ast = ts.createSourceFile('workspace.tsx', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    let fn;
    function collect(node) { if (ts.isFunctionDeclaration(node) && node.name?.text === 'changeCompact') fn = node.getText(ast); ts.forEachChild(node, collect); }
    collect(ast);
    const changes = [], confirmations = [], modes = [];
    const draft = { ...emptyBodyFinding, locationId: 'front-head', detail: 'Dahi kanan', method: 'Palpasi', finding: 'Benjolan dahi', pain: '0' };
    const code = ts.transpileModule(fn, { compilerOptions: { target: ts.ScriptTarget.ESNext } }).outputText;
    const change = runInNewContext(code + '\nchangeCompact', { disabled: false, compact: false, draft, selected: { id: 'front-head' }, emptyBodyFinding, setError() {}, setCompact: (mode) => modes.push(mode), onDraftChange: (data) => changes.push(data), confirmAction: (...args) => confirmations.push(args) });
    change(true);
    assert.equal(changes.length, 0);
    assert.equal(modes.length, 0);
    assert.equal(confirmations.length, 1);
    confirmations[0][1]();
    assert.equal(changes[0].finding, draft.finding);
    assert.equal(changes[0].locationId, 'front-head');
    assert.equal(changes[0].method, '');
    assert.equal(changes[0].pain, '');
    assert.deepEqual(modes, [true]);
});


for (const clinicType of ['umum', 'gigi']) {
    test(clinicType + ' manual treatment keeps type and description fields with its checkbox beside the add action', async () => {
        const { default: ts } = await import('typescript');
        const source = readFileSync(new URL('../resources/js/pages/pelayanan/pemeriksaan/show.tsx', import.meta.url), 'utf8');
        const ast = ts.createSourceFile('show.tsx', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
        let form;
        function collect(node) {
            if (ts.isJsxElement(node) && node.openingElement.tagName.getText(ast) === 'form' && node.openingElement.getText(ast).includes('submitForm(event, treatmentForm,')) form = node.getText(ast);
            ts.forEachChild(node, collect);
        }
        collect(ast);
        const components = {};
        for (const file of ['field', 'input', 'select', 'checkbox', 'button', 'textarea']) Object.assign(components, await server.ssrLoadModule('/resources/js/components/ui/' + file + '.tsx'));
        const { Plus } = await import('lucide-react');
        const { normalizeClinicalAnswer } = await server.ssrLoadModule('/resources/js/lib/clinical-answer.ts');
        const code = ts.transpileModule('const element = (' + form + ');', { compilerOptions: { target: ts.ScriptTarget.ESNext, jsx: ts.JsxEmit.React } }).outputText;
        const element = runInNewContext(code + '\n element', { React: { createElement }, ...components, Plus, normalizeClinicalAnswer, canSubmitEntry: () => true, visit: { id: 1, clinicType }, isDental: clinicType === 'gigi', treatmentMode: 'manual', selectedProcedure: null, treatmentForm: { data: { nama_tindakan_manual: 'Jenis tindakan dokter', jumlah: '1', catatan: 'Rincian tindakan dokter', tooth_fdis: [] }, errors: {}, processing: false } });
        const html = renderToString(element);
        assert.match(html, /Jenis tindakan/);
        assert.match(html, /Keterangan tindakan/);
        assert.match(html, /Rincian tindakan dokter/);
        assert.match(html, /border-t[^>]*><label[^>]*for="treatment-manual"/);
        assert.doesNotMatch(html, /id="procedure-search"|id="tindakan_id"/);
        const submit = [...html.matchAll(/<button[^>]*type="submit"[^>]*>/g)].at(-1)?.[0];
        assert.ok(submit);
        assert.doesNotMatch(submit, /disabled=""/);
    });
}

test('manual diagnosis description and differential diagnosis remain visible in the treatment summary', async () => {
    const { DiagnosisSummary } = await server.ssrLoadModule('/resources/js/components/kunjungan/clinical-workflow-summary.tsx');
    const html = renderToString(createElement(DiagnosisSummary, { diagnoses: [{ id: 1, code: null, codeSystem: 'manual', name: 'Jenis diagnosis dokter', note: 'Keterangan diagnosis dokter', type: 'utama' }], differentialDiagnosis: 'Diagnosis alternatif yang dipertimbangkan', codeSystems: [], hasUnsavedChanges: false, onReview() {} }));
    assert.match(html, /Jenis diagnosis dokter/);
    assert.match(html, /Keterangan diagnosis dokter/);
    assert.match(html, /Diagnosis banding/);
    assert.match(html, /Diagnosis alternatif yang dipertimbangkan/);
});


async function examinationWorkspaceHandler(name, scope) {
    const { default: ts } = await import('typescript');
    const source = readFileSync(new URL('../resources/js/components/anatomy/physical-examination-workspace.tsx', import.meta.url), 'utf8');
    const ast = ts.createSourceFile('workspace.tsx', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    let fn;
    function collect(node) { if (ts.isFunctionDeclaration(node) && node.name?.text === name) fn = node.getText(ast); ts.forEachChild(node, collect); }
    collect(ast);
    assert.ok(fn, name + ' is missing');
    if (name === 'applyFinding') attachDerivedState(ts, ast, scope, ['preview', 'existingFinding', 'findingChanged', 'canApplyFinding']);
    const code = ts.transpileModule(fn, { compilerOptions: { target: ts.ScriptTarget.ESNext } }).outputText;
    return runInNewContext(code + '\n' + name, scope);
}

test('compact examination preserves a selected body location without requiring confirmation when no detailed inputs exist', async () => {
    const { emptyBodyFinding, bodyLocations } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const selected = bodyLocations('back').find((region) => region.id === 'back-elbow-kanan');
    const draft = { ...emptyBodyFinding, locationId: selected.id, locationLabel: selected.label, category: selected.category, side: selected.side, finding: 'Temuan siku kanan' };
    const changes = [], modes = [];
    const change = await examinationWorkspaceHandler('changeCompact', { disabled: false, compact: false, draft, selected, setError() {}, setCompact: (value) => modes.push(value), onDraftChange: (value) => changes.push(value), confirmAction() { assert.fail('A selected location alone must not trigger a data-loss confirmation'); } });
    change(true);
    assert.equal(changes[0].locationId, selected.id);
    assert.equal(changes[0].side, 'kanan');
    assert.equal(changes[0].finding, 'Temuan siku kanan');
    assert.deepEqual(modes, [true]);
});

test('compact mapped finding reopens in compact mode on its matching interactive body view', async () => {
    const { emptyBodyFinding, bodyLocations } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const { createRecordedFinding, findingLocation } = await server.ssrLoadModule('/resources/js/lib/body-map.ts');
    const region = bodyLocations('back').find((item) => item.id === 'back-elbow-kanan');
    const { finding } = createRecordedFinding({ ...emptyBodyFinding, locationId: region.id, locationLabel: region.label, category: region.category, side: region.side, finding: 'Temuan siku kanan' }, 'body', 'back', 'female', 'mapped-compact', 'quick');
    const state = {};
    const edit = await examinationWorkspaceHandler('editFinding', { disabled: false, pending: false, tab: 'manual', findingLocation, setCompact: (value) => state.compact = value, setTab: (value) => state.tab = value, setMode: (value) => state.mode = value, setView: (value) => state.view = value, setModel: (value) => state.model = value, setEditingId: (value) => state.editingId = value, setLegacySystem() {}, setError() {}, onDraftChange: (value) => state.draft = value });
    edit(finding);
    assert.equal(state.compact, true);
    assert.equal(state.tab, 'interactive');
    assert.equal(state.mode, 'body');
    assert.equal(state.view, 'back');
    assert.equal(state.model, 'female');
    assert.equal(state.draft.locationId, region.id);
    assert.equal(state.draft.side, 'kanan');
    assert.equal(state.draft.finding, finding.finding);
    assert.equal(state.editingId, finding.id);
});

test('updating compact body findings preserves annotations and other findings without duplicating the edited record', async () => {
    const { emptyBodyFinding, bodyLocations } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const { createRecordedFinding, emptyBodyMap } = await server.ssrLoadModule('/resources/js/lib/body-map.ts');
    const region = bodyLocations('back').find((item) => item.id === 'back-elbow-kanan');
    const draft = { ...emptyBodyFinding, locationId: region.id, locationLabel: region.label, category: region.category, side: region.side, finding: 'Temuan baru siku kanan' };
    const original = createRecordedFinding({ ...draft, finding: 'Temuan sebelum koreksi' }, 'body', 'back', 'female', 'mapped-compact', 'quick').finding;
    const other = createRecordedFinding({ ...emptyBodyFinding, finding: 'Temuan umum tetap tersedia' }, 'body', 'front', 'female', 'general-compact', 'quick').finding;
    const bodyMap = { ...emptyBodyMap(), findings: [original, other], annotation: { strokes: [{ id: 'mark-1', tool: 'pen', color: '#dc2626', width: 2, points: [{ x: 10, y: 20 }] }], note: 'Keterangan gambar tetap tersedia' } };
    const saved = [];
    const confirmations = [];
    let resets = 0;
    const apply = await examinationWorkspaceHandler('applyFinding', { disabled: false, bodyMap, onBodyMapChange: (value) => saved.push(value), editingId: original.id, compact: true, draft, mode: 'body', view: 'back', model: 'female', tab: 'interactive', legacySystem: null, createRecordedFinding, confirmAction: (...args) => confirmations.push(args), setError: (message) => assert.fail(message), resetDraft: () => resets++ });
    apply();
    assert.equal(saved.length, 0);
    assert.equal(resets, 0);
    assert.equal(confirmations.length, 1);
    confirmations[0][1]();
    assert.equal(saved.length, 1);
    assert.equal(saved[0].findings.length, 2);
    assert.equal(saved[0].findings[0].id, original.id);
    assert.equal(saved[0].findings[0].finding, draft.finding);
    assert.equal(saved[0].findings[0].entryMode, 'quick');
    assert.equal(saved[0].findings[0].locationId, region.id);
    assert.equal(saved[0].findings[1], other);
    assert.equal(saved[0].annotation, bodyMap.annotation);
    assert.equal(bodyMap.findings[0].finding, 'Temuan sebelum koreksi');
    assert.equal(resets, 1);
});

test('a stale body finding edit reports an error without clearing unsaved text', async () => {
    const errors = [];
    const apply = await examinationWorkspaceHandler('applyFinding', { disabled: false, bodyMap: { findings: [] }, editingId: 'removed-finding', onBodyMapChange() { assert.fail('Must not report an unchanged map as successfully updated'); }, resetDraft() { assert.fail('Must preserve unsaved text'); }, setError: (message) => errors.push(message) });
    apply();
    assert.equal(errors.length, 1);
    assert.match(errors[0], /sudah tidak tersedia/);
});

test('read-only physical examination can switch views without changing draft content or body selection', async () => {
    const mutations = [];
    const choose = await examinationWorkspaceHandler('chooseLocation', { disabled: true, mode: 'body', view: 'front', onDraftChange: (value) => mutations.push(value) });
    choose({ id: 'front-head' });
    const edit = await examinationWorkspaceHandler('editFinding', { disabled: true, onDraftChange: (value) => mutations.push(value) });
    edit({ id: 'finding-1' });
    const tabs = [];
    const changeTab = await examinationWorkspaceHandler('changeTab', { disabled: true, compact: false, tab: 'interactive', pending: true, setTab: (value) => tabs.push(value), resetDraft() { assert.fail('Read-only navigation must not discard a draft'); } });
    changeTab('manual');
    assert.deepEqual(tabs, ['manual']);
    assert.equal(mutations.length, 0);
});

test('compact annotation examination keeps existing drawing and note visible without introducing another input', async () => {
    const { PhysicalExaminationWorkspace } = await server.ssrLoadModule('/resources/js/components/anatomy/physical-examination-workspace.tsx');
    const { emptyBodyFinding } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const bodyMap = { version: 1, findings: [], annotation: { strokes: [{ id: 'stored-mark', tool: 'pen', color: '#dc2626', width: 2, points: [{ x: 10, y: 20 }] }], note: 'Tanda pada gambar yang sudah dicatat' } };
    const html = renderToString(createElement(PhysicalExaminationWorkspace, { initialMode: 'manual', initialCompact: true, systems: {}, draft: emptyBodyFinding, bodyMap, onChange() {}, onDraftChange() {} }));
    assert.match(html, /src="\/body-model-reference.jpg"/);
    assert.match(html, /data-annotation-id="stored-mark"/);
    assert.match(html, /Tanda pada gambar yang sudah dicatat/);
    assert.equal((html.match(/<textarea/g) ?? []).length, 1);
    assert.doesNotMatch(html, /id="body-annotation-note"/);
});


for (const page of ['dokter/appointments', 'pelayanan/pemeriksaan/index']) {
    test(page + ' provides an RME icon action for the patient rather than the visit id', async () => {
        const visit = { id: 17, date: '26/10/2026', number: 'K-17', patient: 'Pasien Uji', medicalRecordNumber: 'RM-88', clinic: 'Poli Umum', status: 'pemeriksaan', diagnosisCount: 0, recordUrl: '/pelayanan/pasien/88/rekam-medis', examinationUrl: '/pelayanan/pemeriksaan/17' };
        const html = await renderPage(page, { visits: { ...singlePage, data: [visit] }, todayVisits: { ...singlePage, data: [visit] }, filters: { dari: '', sampai: '' }, date: '', stats: { total: 1, waiting: 1, inProgress: 0, completed: 0 } });
        const rme = html.match(/<a[^>]*aria-label="Rekam medis[^"]*"[^>]*>[\s\S]*?<\/a>/)?.[0];
        assert.ok(rme);
        assert.match(rme, /href="\/pelayanan\/pasien\/88\/rekam-medis"/);
        assert.match(rme, /lucide-heart-pulse/);
        assert.doesNotMatch(rme, />Rekam medis</);
        assert.match(html, /href="\/pelayanan\/pemeriksaan\/17"/);
    });
}

test('unassigned examination can be started without exposing an unauthorized RME link', async () => {
    const visit = { id: 17, date: '26/10/2026', number: 'K-17', patient: 'Pasien Uji', medicalRecordNumber: 'RM-88', clinic: 'Poli Umum', status: 'pemeriksaan', diagnosisCount: 0, recordUrl: null };
    const html = await renderPage('pelayanan/pemeriksaan/index', { visits: { ...singlePage, data: [visit] }, todayVisits: { ...singlePage, data: [visit] }, date: '', stats: { total: 1, waiting: 1, inProgress: 0, completed: 0 } });
    assert.match(html, /Mulai pemeriksaan/);
    assert.doesNotMatch(html, /href="[^"]*rekam-medis/);
});

test('medical record uses one shared filter panel with one reset control and no nested filter card', async () => {
    const html = await renderPage('pelayanan/pasien/rekam-medis', { patient: { id: 88, name: 'Pasien Uji', medicalRecordNumber: 'RM-88', age: null }, visits: [], backUrl: '/dokter/pasien', canSelectClinic: false, filters: { search: 'batuk', poliklinik_id: '', status: '', dari: '', sampai: '' }, clinics: [], pagination: { ...singlePage, total: 0 } }, '/pelayanan/pasien/88/rekam-medis?search=batuk');
    assert.equal((html.match(/aria-label="Filter data"/g) ?? []).length, 1);
    assert.equal((html.match(/Reset filter/g) ?? []).length, 1);
    assert.match(html, /value="batuk"/);
    assert.match(html, /aria-expanded="true"/);
    assert.doesNotMatch(html, /Cari riwayat rekam medis|Atur ulang/);
});

for (const [status, reservation, title] of [['menunggu', false, 'Menunggu pengkajian awal'], ['menunggu', true, 'Reservasi kunjungan'], ['screening', false, 'Pengkajian awal berlangsung'], ['batal', false, 'Kunjungan dibatalkan']]) {
    test('examination status ' + status + ' reservation ' + reservation + ' explains its actual stage before a final record exists', async () => {
        const html = await renderPage('pelayanan/pemeriksaan/show', examinationProps('gigi', { status, isReservation: reservation, examination: null }));
        assert.ok(html.includes(title));
        assert.doesNotMatch(html, /Pemeriksaan terkunci|sudah melewati tahap pemeriksaan|Koreksi dicatat sebagai addendum|id="dental-tooth"/);
    });
}

test('examination state allows addenda only for valid signed records and blocks direct edits of signed drafts', async () => {
    const { examinationState } = await server.ssrLoadModule('/resources/js/lib/examination-state.ts');
    const waiting = examinationState({ status: 'pemeriksaan', canClaim: false, examination: null });
    assert.equal(waiting.canStart, true);
    assert.equal(waiting.canEdit, false);
    const draft = examinationState({ status: 'pemeriksaan', canClaim: false, examination: { status: 'draft' } });
    assert.equal(draft.canStart, false);
    assert.equal(draft.canEdit, true);
    assert.equal(draft.notice, null);
    const unsigned = examinationState({ status: 'selesai', canClaim: false, examination: { status: 'selesai' } });
    assert.equal(unsigned.canCorrect, false);
    const signedDraft = examinationState({ status: 'pemeriksaan', canClaim: false, examination: { status: 'draft', signedAt: '26/10/2026', integrityValid: true } });
    assert.equal(signedDraft.canEdit, false);
    assert.equal(signedDraft.canCorrect, false);
    for (const status of ['farmasi', 'kasir', 'selesai']) {
        const finalized = examinationState({ status, canClaim: false, examination: { status: 'selesai', signedAt: '26/10/2026', integrityValid: true } });
        assert.equal(finalized.canEdit, false);
        assert.equal(finalized.canCorrect, true);
        assert.match(finalized.notice.description, /addendum/);
    }
    const invalid = examinationState({ status: 'selesai', canClaim: false, examination: { signedAt: '26/10/2026', integrityValid: false } });
    assert.equal(invalid.canCorrect, false);
    assert.equal(invalid.notice.destructive, true);
});

for (const [dentition, count] of [['adult', 32], ['primary', 20]]) {
    test('dental ' + dentition + ' diagrams share FDI selections and use correct tooth type counts', async () => {
        const { OdontogramFdi, toothMetadata } = await server.ssrLoadModule('/resources/js/components/odontogram/odontogram-fdi.tsx');
        const html = renderToString(createElement(OdontogramFdi, { dentition, findings: [], selectedTooth: '', onSelect() {} }));
        const teeth = [...html.matchAll(/<g[^>]*aria-label="Gigi FDI ([0-9]{2})[^>]*>/g)];
        assert.equal(teeth.length, count * 2);
        const ids = new Set(teeth.map((match) => match[1]));
        assert.equal(ids.size, count);
        for (const fdi of ids) assert.equal(teeth.filter((match) => match[1] === fdi).length, 2);
        if (dentition === 'primary') {
            assert.doesNotMatch(html, />Premolar<|12 gigi/);
            assert.equal(toothMetadata('54').type, 'molar');
            assert.equal(toothMetadata('85').type, 'molar');
        }
        assert.doesNotMatch(html, /Loop universal|sesuai referensi|animate-pulse/);
        assert.match(html, /Tampilan gigi &amp; gusi/);
        assert.match(html, /Kanan pasien/);
        assert.match(html, /tooth-status-dot/);
    });
}

test('dental smile controls support keyboard selection, primary molars, and disabled views', async () => {
    const { default: ts } = await import('typescript');
    const { toothMetadata } = await server.ssrLoadModule('/resources/js/components/odontogram/odontogram-fdi.tsx');
    const source = readFileSync(new URL('../resources/js/components/odontogram/odontogram-fdi.tsx', import.meta.url), 'utf8');
    const ast = ts.createSourceFile('odontogram.tsx', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    let fn;
    function collect(node) { if (ts.isFunctionDeclaration(node) && node.name?.text === 'SmileTooth') fn = node.getText(ast); ts.forEachChild(node, collect); }
    collect(ast);
    const code = ts.transpileModule(fn, { compilerOptions: { target: ts.ScriptTarget.ESNext, jsx: ts.JsxEmit.React } }).outputText;
    const component = runInNewContext(code + '\nSmileTooth', { React: { createElement }, toothMetadata });
    for (const disabled of [false, true]) {
        const choices = [];
        const element = component({ fdi: '55', d: '', selectedTooth: '55', disabled, onClick: (value) => choices.push(value), onHover() {}, getToothClass: (fdi, type) => { assert.equal(type, 'molar'); return ''; } });
        assert.equal(element.props['aria-pressed'], true);
        assert.equal(element.props.tabIndex, disabled ? -1 : 0);
        element.props.onKeyDown({ key: 'Enter', preventDefault() {} });
        element.props.onKeyDown({ key: ' ', preventDefault() {} });
        element.props.onKeyDown({ key: 'Escape', preventDefault() {} });
        element.props.onClick();
        assert.equal(choices.length, disabled ? 0 : 3);
    }
});

for (const role of ['admin', 'dokter', 'perawat', 'farmasi', 'pendaftaran', 'manajemen']) {
    test(`${role} navigation hides unavailable modules`, async () => {
        const html = await renderRoleNavigation(role, '/');
        assert.doesNotMatch(html, /href="\/(?:master\/(?:laboratorium|depo-obat|alkes|tindakan)|laporan\/laboratorium)"/);
        assert.doesNotMatch(html, /aria-label="(?:Laboratorium|Laporan Laboratorium|Depo obat|Alat kesehatan)"/);
    });
}

test('clinic inventory keeps receipt and reconciliation actions without depot controls', async () => {
    const html = await renderPage('stok/persediaan/show', {
        medicine: { id: 1, code: 'BHP-1', name: 'Kasa steril', stock: 10, unit: 'pcs' },
        batches: [{ id: 1, number: 'OLD-BATCH', stock: 10, status: 'karantina', source: 'retur', depot: 'Depo lama', expired: false, nearExpiry: false }],
        movements: pageOf([]), depots: [], depotsEnabled: false,
    });
    assert.match(html, /Catat penerimaan/);
    assert.match(html, /aria-label="Verifikasi"/);
    assert.match(html, /aria-label="Opname \/ kondisi"/);
    assert.doesNotMatch(html.match(/<table\b[\s\S]*?<\/table>/)?.[0] ?? '', /Depo|depo|aria-label="Mutasi"/);
    assert.match(html, /Karantina/);
});

test('clinic settings omit depot columns and retain examination-room actions', async () => {
    const html = await renderPage('master/poliklinik/index', {
        clinics: pageOf([{ id: 1, code: 'UMUM', name: 'Poli Umum', type: 'umum', depotId: 2, depotName: 'Depo lama', active: true }]),
        filters: { search: '' }, depots: [], depotsEnabled: false, clinicTypes: [{ value: 'umum', label: 'Umum' }],
    });
    assert.doesNotMatch(html.match(/<table\b[\s\S]*?<\/table>/)?.[0] ?? '', /Depo|depo|sumber stok/);
    assert.match(html, /aria-label="Ruang poli"/);
});


for (const canClaim of [false, true]) {
    test(`doctor starts a waiting examination explicitly (unassigned: ${canClaim})`, async () => {
        const html = await renderPage('pelayanan/pemeriksaan/show', examinationProps('umum', { canClaim, examination: null, diagnoses: [] }));
        assert.match(html, />Menunggu pemeriksaan</);
        assert.match(html, /Mulai pemeriksaan<\/button>/);
        assert.match(html, /Klik Mulai pemeriksaan saat Anda mulai memeriksa pasien/);
        assert.match(html, /<fieldset[^>]*disabled=""/);
        assert.doesNotMatch(html, />Sedang diperiksa<|>Ambil kunjungan</);
    });
}

test('started examinations show their active progress and no longer offer a start button', async () => {
    const html = await renderPage('pelayanan/pemeriksaan/show', examinationProps('umum'));
    assert.match(html, />Sedang diperiksa</);
    assert.doesNotMatch(html, /Mulai pemeriksaan<\/button>/);
    assert.match(html, /<fieldset class="[^"]*">/);
});

test('visit progress labels distinguish waiting from active examinations without changing pharmacy context', async () => {
    const { StatusBadge } = await server.ssrLoadModule('/resources/js/components/dashboard/status-badge.tsx');
    for (const [props, label] of [
        [{ status: 'pemeriksaan' }, 'Menunggu pemeriksaan'],
        [{ status: 'pemeriksaan', examinationStarted: true }, 'Sedang diperiksa'],
        [{ status: 'screening' }, 'Pengkajian awal berlangsung'],
        [{ status: 'menunggu' }, 'Menunggu pengkajian awal'],
        [{ status: 'menunggu', context: 'pharmacy', examinationStarted: true }, 'Menunggu penyiapan obat'],
        [{ status: 'selesai', context: 'pharmacy' }, 'Penyerahan obat selesai'],
    ]) {
        assert.match(renderToString(createElement(StatusBadge, props)), new RegExp('>' + label + '<'));
    }
});

test('examination queue separates today\'s patients from all filtered visits and keeps contextual actions', async () => {
    const visit = { date: '26/10/2026', patient: 'Pasien Uji', medicalRecordNumber: 'RM-1', gender: 'P', clinic: 'Poli Umum', doctor: 'Dokter', diagnosisCount: 0, recordUrl: null };
    const today = pageOf([
        { ...visit, id: 1, number: 'KNJ-1', status: 'pemeriksaan', examinationStarted: false },
        { ...visit, id: 2, number: 'KNJ-2', status: 'pemeriksaan', examinationStarted: true },
    ]);
    const html = await renderPage('pelayanan/pemeriksaan/index', { date: '', search: '', stats: { total: 2, waiting: 1, inProgress: 1, completed: 0 }, todayVisits: today, visits: pageOf([
        ...today.data,
        { ...visit, id: 3, number: 'KNJ-3', status: 'selesai', examinationStarted: true },
    ]) });
    assert.match(html, /Pemeriksaan hari ini/);
    assert.match(html, /Seluruh kunjungan pasien/);
    for (const label of ['Mulai pemeriksaan', 'Lanjutkan pemeriksaan', 'Tinjau pemeriksaan']) {
        assert.match(html, new RegExp('aria-label="' + label + '"'));
    }
    assert.match(html, />Menunggu pemeriksaan</);
    assert.match(html, />Sedang diperiksa</);
    assert.doesNotMatch(html, /Semua periode|filter tersedia|tidak menggunakan filter/);
});


function submitButtonMarkup(html, label) {
    const button = [...html.matchAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/g)].find((match) => match[2].replace(/<[^>]*>/g, '') === label);
    assert.ok(button, label + ' must be present');
    return button[0];
}

for (const clinicType of ['umum', 'gigi']) {
    test(clinicType + ' examination save buttons start disabled for already saved or empty forms', async () => {
        const html = await renderPage('pelayanan/pemeriksaan/show', examinationProps(clinicType));
        for (const label of ['Simpan anamnesis', 'Simpan pemeriksaan fisik', 'Simpan diagnosis banding', 'Simpan rencana tindak lanjut', 'Tambah diagnosis', 'Tambah tindakan', 'Tambahkan resep klinik', 'Simpan resep luar', 'Buat surat medis', 'Kirim rujukan']) {
            assert.match(submitButtonMarkup(html, label), /disabled=""/, label);
        }
    });
}

test('prefilled nursing history can be saved as a doctor note without forcing an artificial edit', async () => {
    const props = examinationProps('umum', { examination: { status: 'draft', anamnesis: null, allergyHistory: null } });
    const waiting = await renderPage('pelayanan/pemeriksaan/show', props);
    assert.doesNotMatch(submitButtonMarkup(waiting, 'Simpan anamnesis'), /disabled=""/);
    props.visit.examination = { ...props.visit.examination, anamnesis: props.visit.examinationFormDefaults.anamnesis, allergyHistory: props.visit.examinationFormDefaults.allergyHistory };
    const saved = await renderPage('pelayanan/pemeriksaan/show', props);
    assert.match(submitButtonMarkup(saved, 'Simpan anamnesis'), /disabled=""/);
});

test('clinical save guards wait for changes, reject repeated submits, preserve failed drafts and re-enable after later edits', async () => {
    for (const [section, name] of [['anamnesis', 'examinationForm'], ['pemeriksaan', 'physicalForm'], ['diagnosis', 'differentialForm'], ['rencana', 'followUpForm']]) {
        const { handlers, forms, posts, requests, event } = await clinicalSaveHarness();
        const form = forms[name];
        form.isDirty = false;
        handlers.submitClinicalSection(event, section);
        assert.equal(requests.length, 0);
        form.isDirty = true;
        handlers.submitClinicalSection(event, section);
        assert.equal(posts.length, 0, 'cancelling leaves data unchanged');
        requests.at(-1).action();
        handlers.submitClinicalSection(event, section);
        assert.equal(posts.length, 1, 'processing blocks duplicate submissions');
        assert.equal(requests.length, 1);
        form.processing = false;
        handlers.submitClinicalSection(event, section);
        assert.equal(requests.length, 2, 'failed validation allows retrying the retained draft');
        requests.at(-1).action();
        posts.at(-1).options.onSuccess();
        form.processing = false;
        handlers.submitClinicalSection(event, section);
        assert.equal(requests.length, 2, 'saved data cannot be submitted again unchanged');
        form.data = { ...form.data, note: 'Perubahan berikutnya' };
        form.isDirty = true;
        handlers.submitClinicalSection(event, section);
        assert.equal(requests.length, 3, 'a later edit re-enables saving');
    }
});

test('entry forms disable after adding and only offer confirmation for new input', async () => {
    for (const name of ['diagnosisForm', 'treatmentForm', 'prescriptionForm', 'externalPrescriptionForm', 'letterForm', 'referralForm']) {
        const { handlers, forms, posts, requests, event } = await clinicalSaveHarness();
        const form = forms[name];
        const url = '/pelayanan/pemeriksaan/17/' + name;
        form.isDirty = false;
        handlers.submitForm(event, form, url);
        assert.equal(requests.length, 0);
        form.isDirty = true;
        handlers.submitForm(event, form, url);
        requests[0].action();
        handlers.submitForm(event, form, url);
        assert.equal(posts.length, 1);
        posts[0].options.onSuccess();
        form.processing = false;
        handlers.submitForm(event, form, url);
        assert.equal(requests.length, 1);
        assert.equal(handlers.canSubmitEntry(form), false);
    }
});

test('entry forms require their patient-specific input and block writes outside the active examination', async () => {
    const { handlers, forms, scope } = await clinicalSaveHarness();
    for (const [name, missing] of [['diagnosisForm', { nama_diagnosa: '' }], ['treatmentForm', { terminology_id: '' }], ['prescriptionForm', { obat_id: '' }], ['externalPrescriptionForm', { nama_obat: '' }], ['letterForm', { tanggal: '' }], ['referralForm', { ke_poli_id: '1' }]]) {
        assert.equal(handlers.canSubmitEntry(forms[name]), true);
        const original = forms[name].data;
        forms[name].data = { ...original, ...missing };
        assert.equal(handlers.canSubmitEntry(forms[name]), false, name);
        forms[name].data = original;
    }
    scope.canEdit = false;
    assert.equal(handlers.canSubmitEntry(forms.diagnosisForm), false);
    scope.canEdit = true;
    forms.physicalForm.processing = true;
    assert.equal(handlers.canSubmitEntry(forms.prescriptionForm), false, 'another save must finish before a new request starts');
});

test('addendum needs an actual clinical correction and reason, confirms once and retains saved fields', async () => {
    const { handlers, forms, scope, requests, posts, event } = await clinicalSaveHarness();
    scope.canEdit = false;
    scope.canCorrect = true;
    const form = forms.addendumForm;
    form.data = { ...scope.addendumDefaults, reason: 'Penjelasan koreksi pemeriksaan' };
    handlers.submitAddendum(event);
    assert.equal(requests.length, 0, 'a reason alone is not a clinical correction');
    form.data.pemeriksaan_fisik = 'Temuan koreksi';
    scope.addendumBodyPending = true;
    handlers.submitAddendum(event);
    assert.equal(requests.length, 0, 'unapplied body findings block saving');
    scope.addendumBodyPending = false;
    handlers.submitAddendum(event);
    assert.equal(posts.length, 0);
    requests[0].action();
    posts[0].options.onSuccess();
    form.processing = false;
    assert.equal(form.data.pemeriksaan_fisik, 'Temuan koreksi');
    assert.equal(form.data.reason, '');
    assert.equal(form.isDirty, false);
    handlers.submitAddendum(event);
    assert.equal(requests.length, 1);
});

test('odontogram updates require a change, retain the selection after saving and require a reason for signed corrections', async () => {
    const { handlers, forms, scope, requests, posts, event } = await clinicalSaveHarness();
    const form = forms.dentalForm;
    scope.visit.odontogram = [{ toothFdi: '46', surface: 'O', findingCode: 'caries', notes: 'Kavitas baru' }];
    handlers.submitDentalFinding(event);
    assert.equal(requests.length, 0);
    form.data.notes = 'Kavitas diperbarui';
    handlers.submitDentalFinding(event);
    requests[0].action();
    posts[0].options.onSuccess();
    form.processing = false;
    assert.equal(form.data.tooth_fdi, '46');
    assert.equal(form.data.notes, 'Kavitas diperbarui');
    handlers.submitDentalFinding(event);
    assert.equal(requests.length, 1);
    scope.canEdit = false;
    scope.canCorrect = true;
    form.data.notes = 'Koreksi ukuran kavitas';
    form.isDirty = true;
    handlers.submitDentalFinding(event);
    assert.equal(requests.length, 1, 'signed corrections need a reason');
    form.data.reason = 'Koreksi setelah pemeriksaan ulang';
    handlers.submitDentalFinding(event);
    assert.equal(requests.length, 2);
});

test('new body findings require confirmation and disable again once added until new input is entered', async () => {
    const { emptyBodyFinding } = await server.ssrLoadModule('/resources/js/lib/body-examination.ts');
    const { emptyBodyMap, createRecordedFinding } = await server.ssrLoadModule('/resources/js/lib/body-map.ts');
    const confirmations = [];
    const scope = {
        disabled: false, editingId: null, legacySystem: null, bodyMap: emptyBodyMap(), draft: { ...emptyBodyFinding },
        compact: true, tab: 'interactive', mode: 'body', view: 'front', model: 'female', createRecordedFinding,
        crypto: { randomUUID: () => 'new-finding' }, confirmAction: (...args) => confirmations.push(args),
        onBodyMapChange: (value) => { scope.bodyMap = value; }, resetDraft: () => { scope.draft = { ...emptyBodyFinding }; },
    };
    const apply = await examinationWorkspaceHandler('applyFinding', scope);
    apply();
    assert.equal(confirmations.length, 0);
    scope.draft.finding = 'Temuan yang diperiksa';
    apply();
    assert.equal(scope.bodyMap.findings.length, 0);
    assert.equal(scope.draft.finding, 'Temuan yang diperiksa', 'cancelling retains the draft');
    confirmations[0][1]();
    assert.equal(scope.bodyMap.findings.length, 1);
    apply();
    assert.equal(confirmations.length, 1);
    scope.draft.finding = 'Temuan berikutnya';
    apply();
    assert.equal(confirmations.length, 2);
    scope.disabled = true;
    apply();
    assert.equal(confirmations.length, 2);
});


for (const kind of ['surat', 'resep_luar', 'rujukan_internal']) {
    test(`${kind} print includes the issuer signature without altering image colors`, async () => {
        const signature = 'data:image/png;base64,aXNzdWVy';
        const html = await renderPage('persuratan/cetak', { document: {
            kind, clinic: { name: 'Klinik Uji', logoUrl: null }, patient: { name: 'Pasien Uji', medicalRecordNumber: 'RM-007' },
            doctor: { name: 'dr. Penerbit', sip: 'SIP-007', signatureDataUrl: signature },
            visitNumber: 'K-007', clinicName: 'Poli Umum', number: 'SURAT-007', date: '26 Oktober 2026',
            backUrl: '/persuratan', backLabel: 'Kembali ke persuratan', content: 'Isi surat uji', items: [],
            fromClinic: 'Poli Umum', toClinic: 'Poli Gigi',
        } });
        const signatureImage = html.match(/<img[^>]*alt="Tanda tangan[^>]*>/)?.[0];
        assert.ok(signatureImage);
        assert.ok(signatureImage.includes(signature));
        assert.match(signatureImage, /object-contain/);
        assert.doesNotMatch(signatureImage, /invert|brightness/);
        assert.match(html, /dr\. Penerbit/);
        assert.match(html, /break-inside-avoid/);
    });
}

test('medical record displays a profile signature only after a valid finalization', async () => {
    const signature = 'data:image/png;base64,c2lnbmVy';
    for (const [signedAt, integrityValid, visible] of [[null, null, false], ['26/10/2026', false, false], ['26/10/2026', true, true]]) {
        const html = await renderPage('pelayanan/pasien/rekam-medis', {
            patient: { id: 7, name: 'Pasien Uji', medicalRecordNumber: 'RM-007', gender: 'P', age: 30 },
            visits: [{ id: 1, number: 'K-001', clinic: 'Poli Umum', clinicType: 'umum', doctor: 'Dokter Uji', date: '26/10/2026', status: 'selesai', screening: null,
                examination: { anamnesis: 'Demam', physicalExamination: 'Suhu 38 C', physicalSystems: {}, diagnoses: [], versions: [], signedAt, signedBy: 'Dokter Penerbit', integrityValid, signatureDataUrl: signature },
                prescriptions: [], treatments: [], odontogram: [] }],
            backUrl: '/pelayanan/pasien/7', canSelectClinic: false, filters: { search: '', poliklinik_id: '', status: '', dari: '', sampai: '' }, clinics: [], pagination: singlePage,
        });
        assert.equal(html.includes(signature), visible);
        if (visible) assert.match(html, /alt="Tanda tangan Dokter Penerbit"/);
    }
});

for (const hasMedication of [true, false]) {
    test(`nursing procedure explains next destination with medication=${hasMedication}`, async () => {
        const visit = {
            id: 1, number: 'K-CARE', patient: 'Pasien Uji', medicalRecordNumber: 'RM-CARE', clinic: 'Poli Umum', doctor: 'Dokter Uji', status: 'tindakan', treatmentStartedAt: null, startUrl: '/pelayanan/tindakan-perawat/1/mulai', allergies: null, education: 'Edukasi pasien', nonPharmacological: 'Latihan sesuai toleransi', diagnoses: ['Diagnosis uji'], hasMedication, recordUrl: '/pelayanan/pasien/1/rekam-medis',
            treatments: [{ id: 1, name: 'Perawatan uji', code: null, quantity: 1, toothFdi: null, instructions: 'Instruksi dokter', status: 'pending', result: null, performedBy: null, performedAt: null, completeUrl: '/pelayanan/tindakan-perawat/1/1/selesai' }],
        };
        const html = await renderPage('pelayanan/tindakan/show', { visit });
        assert.match(html, /mx-auto max-w-5xl space-y-6/);
        assert.match(html, /href="\/pelayanan\/tindakan-perawat"[^>]*>[\s\S]*?Kembali<\/a>/);
        assert.match(html, /href="\/perawat\/laporan-tindakan\/cetak\?search=K-CARE"[^>]*target="_blank"[^>]*>[\s\S]*?Cetak tindakan/);
        assert.match(html, /Belum diketahui; konfirmasi bersama pasien sebelum tindakan/);
        assert.match(html, /Latihan sesuai toleransi/);
        assert.match(html, />Mulai tindakan<\/button>/);
        assert.doesNotMatch(html, /Hasil pelaksanaan dan respons pasien|<textarea/);
        assert.match(html, hasMedication ? /pasien melanjutkan ke farmasi/ : /kunjungan berakhir dan pasien dapat pulang/);

        const started = await renderPage('pelayanan/tindakan/show', { visit: { ...visit, treatmentStartedAt: '29/10/2026 09:00' } });
        assert.match(started, /Hasil pelaksanaan dan respons pasien/);
        assert.match(started, /border-t border-neutral-100 pt-4/);
        assert.match(started, /Simpan pelaksanaan tindakan<\/button>/);
        assert.doesNotMatch(started, />Mulai tindakan<\/button>/);
    });
}

test('completed nursing result shows attribution and cannot be resubmitted', async () => {
    const html = await renderPage('pelayanan/tindakan/show', { visit: {
        id: 1, number: 'K-CARE', patient: 'Pasien Uji', medicalRecordNumber: 'RM-CARE', clinic: 'Poli Umum', doctor: 'Dokter Uji', status: 'selesai', treatmentStartedAt: '27/10/2026 08:30', startUrl: '/pelayanan/tindakan-perawat/1/mulai', allergies: null, education: null, nonPharmacological: null, diagnoses: [], hasMedication: false, recordUrl: '/pelayanan/pasien/1/rekam-medis',
        treatments: [{ id: 1, name: 'Perawatan uji', code: null, quantity: 1, toothFdi: null, instructions: 'Instruksi dokter', status: 'completed', result: 'Respons pasien tercatat', performedBy: 'Perawat Uji', performedAt: '27/10/2026 09:00', completeUrl: '/pelayanan/tindakan-perawat/1/1/selesai' }],
    } });
    assert.match(html, /Respons pasien tercatat/);
    assert.match(html, /Perawat Uji/);
    assert.doesNotMatch(html, /<textarea|Simpan pelaksanaan tindakan/);
});
