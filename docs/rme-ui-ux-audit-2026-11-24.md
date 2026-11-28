# Audit UI/UX RME — 24 November 2026

## Temuan yang ditutup

- **Tabel diagnosis pemeriksaan:** aksi teks `Siapkan tindakan` dan `Jadikan utama` memakai ruang horizontal berlebihan dan tidak mengikuti pola aksi tabel lain. Keduanya diubah menjadi `ActionButton` ikon ringkas dengan label aksesibel, tooltip, dan status disabled yang konsisten. Kolom aksi dipersempit menjadi lebar proporsional.
- **Pola aksi:** ditambahkan aksi `primary` pada komponen `ActionButton` menggunakan ikon `BadgeCheck`; aksi klinis tetap dapat ditemukan melalui label aksesibilitas dan tooltip tanpa menghilangkan makna.

## Hasil audit pola

- Halaman general consent dan informed consent tindakan sudah memakai pola kartu, heading, alert status, tombol `secondary`, serta `ActionButton` yang sama; tidak dibuatkan pola visual baru.
- Navigasi pemeriksaan memakai tombol `sm` dan struktur `border-t` yang seragam. Tidak diubah karena sudah menjadi pola lintas tab dan perubahan global berisiko mengganggu flow yang disetujui.
- Template cetak sudah memiliki header dokumen bersama dan aturan font/print yang diuji melalui regression render. Konsistensi A4 dan preview printer nyata tetap membutuhkan UAT browser/printer.
- Route `409` yang ditemukan pada alur idempotensi, stale state, bukti tidak valid, dan tindakan selesai merupakan guard domain yang disengaja; bukan diubah menjadi navigasi diam-diam agar konflik klinis tidak tertutupi. Pesan harus tetap ditinjau melalui UAT agar tampil nyaman pada Inertia/browser.

## Verifikasi

- Frontend render: 305/305 lulus (`node --test tests/frontend-render.test.mjs`).
- TypeScript check: lulus.
- Vite build: lulus.
- Pint dan `git diff --check`: lulus.

## Temuan terbuka yang memerlukan UAT visual

- Perbandingan visual seluruh halaman pada viewport desktop/tablet dan dark mode.
- Native print preview untuk RME, surat, consent, dan laporan; termasuk multi-page A4.
- Validasi ukuran tombol dan wrapping pada data pasien/nama tindakan yang panjang.
- Verifikasi pesan konflik HTTP 409 pada browser agar tidak tampil sebagai error generik tanpa konteks.
