# Audit Prioritas Tinggi T02–T14 — 24 November 2026

## Temuan dan hasil

- **T02–T04 RBAC dan privasi:** inventaris runtime route menghasilkan 73 route sensitif yang seluruhnya memiliki middleware role eksplisit. Route token tanda tangan pasien (`procedure-signature.patient/pair/sign`) dikecualikan karena merupakan jalur publik berbasis token dan tetap diuji melalui kontrol token/expiry. Endpoint klinis admin, RME, farmasi, lampiran, laporan agregat, dan ekspor audit memiliki coverage penolakan atau masking pada suite feature.
- **T05–T07 retensi/lampiran:** audit jalur delete tidak menemukan `forceDelete` pada transaksi klinis. Lampiran memakai void/restore, hash immutable, cleanup file saat transaksi gagal, serta tidak menghapus file asli saat dibatalkan. Jalur master/schedule tetap dapat dihapus sesuai scope data master.
- **T08–T10 audit trail:** event view/print/export/addendum/consent/penolakan sudah dicatat pada recorder dan ekspor audit memakai projection termasking serta string binder. Append-only trigger diverifikasi oleh security check.
- **T11/T13/T14/T31:** guard sesi, akun, role, dan audit lifecycle teruji pada suite. TLS, proxy, secret custody, enkripsi disk/field, rotasi kunci, dan perangkat produksi tetap memerlukan bukti deployment/infrastruktur.
- **T21 KFA:** master obat masih menyimpan `kode_kfa` sebagai nilai lokal opsional. Belum ada katalog KFA berprovenance/rilis, pembedaan Virtual/Actual Product, status retired/unmapped, atau snapshot produk pada resep/dispensing. Task tetap **OPEN**; tidak dipaksakan dengan menebak kode dari nama obat.
- **T22–T24 dokumen/pelepasan:** resolver snapshot efektif, void/replacement, masking dan ledger pelepasan sudah memiliki coverage feature. Bukti upload aktual, QR pada perangkat, otorisasi wakil/scope parsial, dan uji cetak native tetap **IN_REVIEW** karena memerlukan UAT operasional.
- **T25 farmasi:** review terstruktur, hash sumber, gate dispensing, konseling dan tindak lanjut koreksi sudah tersedia; kualifikasi individual apoteker/TTK serta UAT retur/retry/resep luar masih menunggu bukti penugasan dan perangkat nyata.
- **T26 koreksi RME:** versi/addendum, stale guard, konteks koreksi dan penanda downstream review sudah tersedia. Acknowledgement lintas farmasi/dokumen/rujukan, approval administratif lewat tenggat, replacement/retur pasca-dispensing belum lengkap dan tetap **IN_PROGRESS**.

## Perubahan code/test

- Ditambahkan `RouteAccessInventoryTest` untuk mencegah route klinis/sensitif baru dibuat tanpa boundary role. Test saat ini memeriksa 73 route.
- Tidak ada perubahan flow klinis atau pengurangan akses operasional yang disetujui.

## Bukti eksekusi

- Regression T02–T14: **104/104 test, 1.546 assertions** (`storage/framework/testing/high-priority-02-14-2026-11-24.xml`).
- Route inventory test: **1/1, 73 assertions**.
- Batas bukti: belum mencakup TLS produksi, storage off-host, restore disaster recovery, rotasi key, dan browser UAT lintas tab/sleep.
