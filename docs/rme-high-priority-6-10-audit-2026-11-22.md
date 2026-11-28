# Audit lanjutan prioritas tinggi 6–10

Tanggal: 22 November 2026. Lingkup: T15, T16, T18, T19, T20 pada alur existing Poli Umum/Gigi. Persetujuan acceptance pengguna tetap berlaku; hasil pengujian dicatat terpisah dari persetujuan.

## Temuan dan perbaikan

F26 / T19,T20: ringkasan coding pemeriksaan sebelumnya hanya memeriksa status legacy diagnosis. Jika diagnosis terverifikasi tetapi tindakan legacy diterima sebagai kompatibilitas historis, pemeriksaan dapat terlihat terverifikasi penuh. ClinicalCodingService kini mempertahankan status legacy bila salah satu tindakan legacy. Transaksi historis tidak dikodekan ulang secara fiktif. Regression test mengisolasi kasus tanpa diagnosis aktif sehingga sumber status legacy benar-benar berasal dari tindakan.

F27 / T15: gate general consent untuk bukti unggahan sebelumnya hanya memeriksa kolom hash terisi. File yang hilang atau berubah masih dapat dianggap memenuhi gate. `GeneralConsentService` kini memverifikasi keberadaan dan SHA-256 file untuk status, tanggal aktif, dan handoff pengkajian; bukti HMAC in-person tetap memakai jalur verifikasi tanda tangan. Regression test menutup file yang dimodifikasi setelah pencatatan.

## Hasil inspeksi

| Task | Kontrol yang ditelusuri | Acceptance tersisa |
| --- | --- | --- |
| T15 | Versi general consent, accepted/refused terdokumentasi, withdrawn tidak dianggap accepted, gate pengkajian, penundaan darurat beralasan, legacy unknown | UAT petugas dan bukti pasien nyata; tidak membuat consent historis otomatis |
| T16 | Rencana tindakan di-hash, keputusan terbaru, refusal/withdrawal, scan private dan hash file sebelum pelaksanaan, kondisi emergency diperiksa ulang | QA perangkat/scan dan formulir nyata; audit klasifikasi tindakan klinik |
| T18 | Review final eksplisit, revision pengkajian, konfirmasi realisasi tindakan dokter, consent gate sebelum pelaksanaan | UAT klinis dan cakupan residual narasi otomatis lintas seluruh form |
| T19 | WHO aktif/display/release cocok pada final, kode palsu/retired ditolak, unresolved terbatas emergency/downtime server dengan alasan | Provenance katalog dan daftar release yang disetujui; label verified lokal bukan jaminan kompatibilitas SATUSEHAT |
| T20 | Review mapping/tidak berlaku beralasan, revision guard, katalog aktif, consent menjadi stale bila rencana berubah | Koreksi kode prosedur pasca-final, provenance/release katalog dan validasi klinisi |

## Audit lintas aplikasi

- Inventaris runtime: 238 route aplikasi non-vendor. Jumlah ini bukan bukti bahwa setiap endpoint sudah diuji ulang secara manual.
- composer audit: advisories kosong, abandoned kosong.
- npm audit: nol vulnerability pada hasil pemeriksaan saat ini.
- clinic:security-check lokal: 11/11 true, termasuk trigger retensi/audit/consent/dokumen/pelepasan/farmasi.
- git diff --check lulus. Pint perubahan coding lulus.
- Full suite terakhir sebelum batch ini: 589/589, 10.211 assertions. Tidak diklaim sebagai full suite setelah perubahan hari ini.
- Regression awal ProcedureCodingReviewTest setelah F26: 5/5, 36 assertions.
- Frontend: 307/307 lulus setelah batch ini.
- Regresi lintas T15/T16/T18/T19/T20 setelah F26 dan F27: 153/153, 2.234 assertions (`storage/framework/testing/high-priority-6-10-audit-2026-11-24.xml`).
- Frontend render/regression: 305/305 (`node --test tests/frontend-render.test.mjs`).
- `php artisan clinic:security-check`: 11/11 kontrol lulus; Pint dan `git diff --check` lulus.

Audit ini tidak menyatakan bebas bug seluruh aplikasi. Browser seluruh role, native print preview, QR scan perangkat, engine produksi, TLS dan off-site restore membutuhkan bukti aktual sesuai tracker. T23–T26 yang masih terbuka tidak dihapus ketika pengguna mengarahkan pekerjaan ke prioritas tinggi.
