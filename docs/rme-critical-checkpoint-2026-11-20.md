# Checkpoint prioritas kritis — 20 November 2026

## Diterapkan

- T22: scope verifikasi integritas dan sumber diagnosis efektif mengikuti dokter/poli/periode. Regresi 26/26, 723 assertions, termasuk penolakan integritas dalam scope dan isolasi dokter lain.
- T23: pilihan dokumen pengganti pada form penerbitan existing. Dokumen asal harus satu kunjungan dan jenis; bukti penerbitan lama harus tersedia. Password dokter dan alasan diperlukan. Snapshot pengganti menyimpan referensi/nomor/alasan/actor/time. Event append-only menghubungkan dokumen baru; bukti publik lama menjadi tidak berlaku. Arsip lama tidak diubah. Penggantian ganda ditolak di dalam transaksi dengan kunci kunjungan/proof. Cetak pengganti memuat nomor asal.
- Regresi awal persuratan 20/20, 587 assertions. Setelah tambahan tes kegagalan audit, MedicalDocumentVerificationTest lulus 7/7, 122 assertions, termasuk rollback surat/proof/event dan penolakan target tidak sah. Frontend 305/305, TypeScript, build, Pint dan diff check lulus. Pengujian ini tidak mencakup browser/scan perangkat nyata.

## Gate tetap terbuka

- T22: validasi engine selain SQLite serta QA dokumen efektif menyeluruh.
- T23: QR rujukan internal/consent, pemindaian perangkat dan preview cetak nyata. Dokumen legacy tanpa bukti tidak diberi attestasi retrospektif.
- T25: kualifikasi Apoteker/TTK, review administratif/farmasetik/klinis, hold/klarifikasi, hash sumber, gate penyerahan, penerima/konseling dan review pemberian langsung diterapkan. Regresi terarah lulus; frontend 307/307, TS/build/Pint/security 11/11 lulus. Verifikasi petugas nyata dan acceptance tersisa dilacak di rme-pharmacy-review-implementation.md; full suite backend dicatat setelah selesai.
- T24: register per kunjungan telah diterapkan: permintaan/otorisasi dokter/penyerahan/penolakan/pencabutan, data terenkripsi, event append-only, reauth, scope dokumen dan cek pembatalan sebelum penyerahan. Regresi 32/32 ditambah 3/3 (sebagian beririsan), frontend 306/306; TS/build/Pint/security 10/10 lulus. Acceptance operasional dan bukti scan terintegrasi masih terbuka; lihat rme-information-release-implementation.md.
- T26 (21 November 2026): setiap addendum kini menyimpan correction_context dalam payload hash-chain: versi/hash asal, changed_fields, indikator lewat 48 jam sejak versi sebelumnya, status farmasi/dispensed, jumlah surat existing dan kebutuhan downstream review. Tidak mengubah stok/arsip lama. Regresi diagnosis correction + clinical note version lulus 6/6, 176 assertions; Pint/diff lulus. Ini belum merupakan workflow acknowledgement, persetujuan administratif terlambat atau penggantian resep pasca-dispensing; task tetap terbuka.

Prioritas kritis belum ditutup seluruhnya; jangan memajukan gate rilis berdasarkan checkpoint ini.

## 22 November 2026 — tindak lanjut farmasi pasca-penyerahan

Persetujuan seluruh kebutuhan acceptance oleh pengguna dicatat. Halaman review kini menandai koreksi dokter yang memerlukan review downstream jika hash sumber terbaru belum tercakup review terakhir, termasuk peringatan khusus setelah dispensing. Penandaan menggunakan versi efektif terverifikasi; tidak membuka narasi SOAP kepada farmasi. Antrean lintas kunjungan dan acknowledgement lintas domain tetap memerlukan implementasi berikutnya.

Apoteker terverifikasi kini dapat menyimpan keputusan follow_up pada kunjungan yang dispensing-nya selesai. Review tetap memerlukan pemeriksaan administratif/farmasetik/klinis, password, versi/hash sumber terbaru, catatan dampak dan status/referensi klarifikasi. Keputusan approved/hold untuk penyerahan baru tidak dapat dipakai pada layanan selesai; follow_up tidak dapat dipakai sebelum penyerahan. Event lama, pelaku penyerahan dan stok tidak diubah. Regresi 4/4, 56 assertions; TypeScript/build/Pint lulus. Ini menambah pencatatan tindak lanjut; antrean notifikasi/acknowledgement koreksi dan penggantian resep/retur terstruktur masih terbuka di T26.
