# Enkripsi at-rest dan pemulihan kunci

Status 19 November 2026: rancangan teknis T14, belum bukti enkripsi host produksi. Pemeriksaan read-only BitLocker lokal ditolak oleh akses CIM Windows; pesan volume sesudahnya tidak dipakai untuk menyimpulkan volume terenkripsi atau tidak.

## Salinan yang harus dilindungi

| Salinan | Risiko | Kontrol penerapan | Bukti penerimaan |
| --- | --- | --- | --- |
| SQLite/DB utama, jurnal/WAL, snapshot OS | Disk/perangkat dicuri; salinan DB dibaca di luar aplikasi | Enkripsi volume atau layanan DB terenkripsi; akun OS khusus; ACL direktori dan DB; kunci pemulihan institusi | Status volume/layanan aktif, ACL aktual, uji restart dan pemulihan |
| Lampiran, consent, signature, dokumen | File dibaca langsung, cache/salinan sementara tertinggal | Private storage pada volume terenkripsi; akses hanya controller berotorisasi; no-store; folder temp privat | Uji akses HTTP/OS, cakupan volume dan pemeriksaan folder temp |
| Backup lokal/off-site | Arsip atau kunci dicuri bersama | Arsip AES-256 yang sudah diterapkan; kunci terpisah dari repo/DB/arsip; off-site failure domain berbeda | Baca ulang terenkripsi, ACL kunci, tujuan off-site dan recovery drill |
| Ekspor/cetak yang sah | Salinan keluar dari server dan melampaui retensi lokal | Register pelepasan, cakupan minimum, perangkat tujuan terkendali; SOP penanganan hasil cetak | Bukti otorisasi dan penyerahan T24; pengujian endpoint/kolom ekspor |
| Log/audit, dumps diagnostik, monitoring | NIK/narasi/rahasia tercatat tanpa perlu | Metadata whitelist, masking, larangan password/token/key dan raw request; enkripsi volume log | Uji kebocoran log dan hak pembaca audit |

## Batas kontrol

Enkripsi disk melindungi media saat terkunci. Akun OS yang dapat membaca volume aktif tetap dapat membaca file; RBAC aplikasi, kontrol OS, audit, backup dan pemisahan hak harus tetap berlaku. Hash/checksum membuktikan konsistensi terhadap referensi yang dilindungi; hash sendiri bukan enkripsi dan tidak menahan administrator DDL yang dapat mengganti semua referensi.

Laravel encrypted casting pada NIK tidak boleh ditambahkan langsung ke kolom yang masih dipakai pencarian, deduplikasi atau import. Jika strategi field encryption dipilih, implementasi wajib bertahap: ciphertext terpisah; blind index HMAC dengan kunci berbeda; normalisasi NIK 16 digit; dual-read; backfill terukur; indeks duplikasi; masking; uji ekspor/import dan recovery/rotasi kunci. Narasi/versi klinis terenkripsi juga harus mempertahankan canonical hash dan pembacaan arsip. Jangan menulis ulang versi immutable untuk menyamarkan sejarah.

## Runbook kunci

1. Tetapkan operator dan pemegang kunci pemulihan aktual sesuai SOP. Kunci arsip backup dan APP_KEY memiliki fungsi berbeda; jangan mengganti APP_KEY untuk mengaktifkan enkripsi DB.
2. Simpan salinan pemulihan melalui pengelolaan rahasia institusi yang terpisah dari host dan arsip. Jangan memasukkan kunci ke command line, chat, Git, log atau dokumen ini.
3. Uji pemulihan pada host terisolasi: DB, file private, volume/layanan DB, kunci arsip, serta APP_KEY bila data terenkripsi aplikasi memerlukannya. Ukur RPO/RTO dan verifikasi hash versi/file.
4. Rotasi dimulai dengan inventaris key version dan backup terverifikasi. Pertahankan kemampuan membaca arsip lama sampai kewajiban retensinya selesai. Kehilangan kunci bukan proses penghapusan RME yang sah.
5. Aktifkan perubahan host produksi hanya melalui operator berwenang dengan recovery key yang sudah diuji. Task T14 tetap terbuka sampai bukti aktual tersedia; konfigurasi aplikasi tidak dianggap bukti enkripsi disk.

Tautan: [tracker](rme-hardening-tracker.md), [backup/restore](rme-backup-runbook.md), [SOP](rme-sop-rancangan.md).
