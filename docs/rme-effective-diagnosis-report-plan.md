# T22 — Proyeksi diagnosis efektif untuk laporan

Checkpoint implementasi: 20 November 2026. Status: query efektif diterapkan dan regresi SQLite lulus; T22 keseluruhan tetap IN_PROGRESS.

Bukti: regresi DiagnosisCodingCorrectionTest + RegistrationRoleTest lulus 21/21, 560 assertions. Tambahan endpoint dokter setelah koreksi + LaporanInertiaTest dan filter agregat lulus 7/7, 216 assertions (cakupan sebagian beririsan). Pint dan diff check lulus. Tidak ada migrasi atau perubahan data lokal pada batch ini.

Implementasi memakai ekspansi JSON database atas versi terakhir, tanpa indeks turunan atau migrasi data. Draft tetap memakai baris aktif. Halaman dokter dan query bersama halaman/ekspor pendaftaran memakai EffectiveDiagnosisReport. Pemeriksaan integritas dan kedua cabang query sekarang dibatasi oleh subquery kunjungan sesuai dokter/poli/periode. Catatan rusak di luar scope tidak menghalangi laporan petugas lain. Regresi scope/integritas serta laporan lulus 26/26, 723 assertions. SQL SQLite/MySQL/PostgreSQL disediakan; pembuktian mesin selain SQLite masih diperlukan. Rancangan indeks di bawah tetap opsi optimasi, bukan perubahan yang telah diterapkan.

## Jalur yang harus diperbaiki bersama

- `DokterDashboardController::topDiagnoses`: agregasi langsung model Diagnosa.
- `RegistrationController::diagnosisReportQuery`: agregasi langsung tabel diagnosa; digunakan halaman dan ekspor.
- `DiagnosisExport`: mempertahankan streaming FromQuery, urutan stabil dan proteksi spreadsheet.

Koreksi melalui DiagnosisCodingCorrectionController membuat versi baru, tanpa menimpa diagnosis awal. Karena itu laporan saat ini dapat menampilkan kode sebelum koreksi.

## Kontrak implementasi

1. Catatan bertanda tangan memakai diagnosis dari versi terakhir yang rantai hash-nya valid. Jangan fallback diam-diam ke diagnosis awal saat integritas gagal.
2. Draft memakai diagnosis aktif yang belum dibatalkan; catatan legacy tanpa versi harus dibedakan secara eksplisit.
3. Gunakan proyeksi per versi dengan referensi examination/version/hash dan ordinal diagnosis. Proyeksi merupakan indeks turunan, bukan sumber klinis baru.
4. Isi proyeksi dalam transaksi yang sama dengan penerbitan versi; kegagalan harus membatalkan keseluruhan transaksi.
5. Backfill harus terukur, idempotent, memverifikasi rantai, dan melaporkan kegagalan. Jangan menulis ulang payload atau diagnosis asli.
6. Query laporan memilih satu versi terakhir per pemeriksaan, dengan scope dokter/poli/periode yang berlaku. Pertahankan ambang minimal lima pasien pada laporan administratif.
7. Tampilan dan ekspor menggunakan query dasar yang sama. Hindari memuat seluruh riwayat pasien ke memori untuk agregasi.

## Acceptance yang wajib dibuktikan

- Koreksi kode A menjadi B memindahkan hitungan laporan dan ekspor ke B; diagnosis asli A tetap ada.
- Dua addendum pada kunjungan yang sama tidak menggandakan kasus.
- Koreksi narasi tanpa perubahan diagnosis mempertahankan hitungan.
- Rantai hash rusak tidak menghasilkan laporan seolah valid.
- Draft voided, scope poli/dokter, rentang tanggal, ambang privasi dan spreadsheet tetap terjaga.
- Audit gagal membatalkan versi dan proyeksi secara atomik; retry tidak menggandakan indeks.
- Backfill ulang tidak mengubah hasil; pengujian mesin database target diperlukan sebelum rilis.
