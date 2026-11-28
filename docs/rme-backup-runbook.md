# Rancangan infrastruktur backup dan pemulihan RME

Versi 0.1 — 19 November 2026. **Belum tersedia tujuan off-site yang disetujui.** Checkpoint yang pernah dijalankan di komputer pengembangan bersifat lokal dan tidak menutup T12/T30. Tautan: [tracker](rme-hardening-tracker.md), [rancangan SOP](rme-sop-rancangan.md).

## Kapabilitas yang tersedia dan batasnya

`clinic:backup` saat ini membuat snapshot SQLite konsisten melalui `VACUUM INTO`, mengarsip DB dan folder private `clinical-attachments`, `signatures`, `consents`, `documents`, mengenkripsi entri ZIP AES-256, mengunggah ke disk Laravel yang dikonfigurasi, lalu membaca ulang untuk verifikasi hash manifest serta integritas/FK database pemulihan terisolasi. Referensi lampiran (termasuk arsip dibatalkan), signature dan bukti consent pada database hasil pemulihan juga wajib ada di manifest; referensi hilang menggagalkan status sukses. `--local-only` menandai checkpoint lokal secara eksplisit. Ini bukan pemulihan seluruh layanan dan belum membuktikan konsistensi lintas DB/file ketika upload sedang berlangsung.

Engine selain SQLite ditolak sampai adapter backup native serta restore engine itu diuji. Storage Laravel dapat diganti setelah driver dan kredensial tujuan tersedia; konfigurasi bawaan berupa direktori lokal, **bukan cloud/off-site otomatis**. Jadwal 01.00 Asia/Jakarta hanya didaftarkan ketika enabled. Scheduler OS dan alarm belum dibuktikan di lingkungan target. Arsip belum dipangkas otomatis.

## Kebutuhan dan keputusan pengadaan

| Kebutuhan | Rancangan minimum | Bukti penerimaan |
| --- | --- | --- |
| Tujuan di luar host/failure domain | Storage institusi atau object storage yang disetujui PDP/keamanan; koneksi terenkripsi dan akses private | Pemilik, lokasi/yurisdiksi, endpoint, perjanjian dan uji baca/tulis |
| Kredensial | Akun khusus backup, hak minimum; bila tersedia pisahkan penulis dari penghapus, versioning/immutability | Policy aktual diuji, akses publik ditolak, akun runtime aplikasi tidak dapat menghapus arsip terlindungi |
| Kunci | Kunci acak terpisah dari repo/DB/arsip, pembatasan ACL OS; salinan pemulihan di penyimpanan rahasia institusi | Dua penjaga yang ditunjuk dapat memulihkan melalui prosedur darurat tanpa membagikan kunci lewat chat/log |
| Kapasitas | Ukur DB+lampiran, pertumbuhan dan waktu snapshot/upload; sediakan ruang temp privat dan kuota tujuan | Pengukuran volume, proyeksi biaya, alarm kapasitas, bukti ruang cukup |
| Jadwal | Setidaknya harian; usulan 01.00 WIB dan backup sebelum migrasi | Scheduler nyata, waktu server tersinkron, log sukses/gagal, overlap tidak menggandakan proses |
| Pemantauan | Alarm gagal/terlambat ke petugas TI; eskalasi ke PJ | Kanal dan petugas ditetapkan, simulasi kegagalan diterima, tidak berisi PHI/kunci |
| Pemulihan | Host terisolasi, versi aplikasi/PHP/SQLite dan private storage sesuai, jaringan SATUSEHAT/notifikasi dimatikan | Simulasi DB+file+alur sintetis, integritas versi dan waktu selesai tercatat |
| Retensi | Usulan backup harian 35 hari dan bulanan 12 bulan untuk diskusi kapasitas; arsip RME tetap ≥25 tahun menurut kebijakan terpisah | Jadwal disahkan, legal hold, versioning dan pengujian sebelum lifecycle penghapusan aktif |

**Target acuan SOP untuk implementasi**, belum diukur pada infrastruktur target: RPO ≤24 jam untuk backup harian; RTO ≤8 jam kerja. Ukur realisasinya pada infrastruktur target dan catat bila target belum tercapai. Untuk RPO lebih kecil perlu backup lebih sering atau mekanisme jurnal/replikasi yang juga diuji. Replikasi saja bukan backup.

## Aktivasi setelah tujuan disetujui

1. Inventaris engine/versi dan private file path aktual. Verifikasi tidak ada lampiran aktif yang hanya berada di luar folder yang dicakup.
2. Konfigurasikan disk tujuan private dan dukungan driver. Sediakan kunci melalui file ber-ACL terbatas di luar webroot/repo. Jangan menaruh kunci di dokumen ini atau hasil perintah.
3. Tetapkan `CLINIC_BACKUP_DISK`, `CLINIC_BACKUP_KEY_FILE`, `CLINIC_BACKUP_ENABLED=true`. `CLINIC_BACKUP_OFFSITE_CONFIRMED=true` hanya setelah lokasi/failure domain diverifikasi; flag ini merupakan pernyataan operator, bukan deteksi lokasi otomatis.
4. Jalankan `php artisan clinic:backup`. Periksa arsip tujuan dan hasil baca ulang; catat nama arsip, waktu, ukuran, hash arsip, operator dan hasil tanpa memuat data pasien.
5. Aktifkan pemanggilan `php artisan schedule:run` setiap menit oleh scheduler OS dengan service account yang benar. Periksa `php artisan schedule:list` dan bukti job nyata setelah jadwal terlewati.
6. Jalankan `php artisan clinic:security-check --production --json`. Hasil konfigurasi HTTPS/cookie bukan bukti handshake TLS. Uji endpoint/reverse proxy, akses private file dan TLS di server sebenarnya.
7. Pasang pemantauan eksternal/OS untuk kegagalan dan umur backup; tidak cukup mengandalkan proses di server yang mungkin mati. Lakukan uji kegagalan storage/kunci/jaringan/ruang temp dan pastikan alarm diterima.
8. Lakukan simulasi pemulihan di bawah sebelum menyatakan siap. Jangan mengaktifkan lifecycle hapus arsip selama retensi dan recovery drill belum disetujui.

## Runbook pemulihan terisolasi

1. PJ menetapkan insiden/simulasi, cakupan dan PIC. Pilih titik pemulihan berdasarkan waktu dan bukti; jangan menimpa DB aktif. Amankan salinan keadaan insiden terlebih dahulu.
2. Siapkan direktori/host tujuan privat yang terpisah. Matikan integrasi keluar, scheduler klinis, email dan notifikasi. Gunakan service account dan konfigurasi lingkungan isolasi.
3. Ambil arsip serta kunci melalui jalur terpisah. Verifikasi identitas arsip dan jalankan `php artisan clinic:backup --verify=NAMA_ARSIP.zip` pada konfigurasi disk yang benar. Perintah ini memverifikasi di workspace sementara lalu membersihkannya; bukan perintah mengganti DB produksi.
4. Operator memulihkan DB dan folder private dengan alat ZIP yang mendukung AES-256 ke lokasi isolasi; jangan mengetik kunci di argumen command line atau menyalin arsip ke public webroot. Periksa hash tiap file sesuai manifest. Simpan konfigurasi/APP_KEY aplikasi melalui pengelolaan rahasia terpisah bila data aplikasi membutuhkan dekripsi.
5. Jalankan pemeriksaan integritas DB, FK dan schema/migration yang sesuai versi arsip. Verifikasi hash rantai versi klinis, jumlah arsip/file yang dirujuk dan sampel lampiran; arsip sukses belum berarti seluruh referensi aplikasi lengkap.
6. Di lingkungan isolasi, uji login role, baca kunjungan sintetis, finalisasi sintetis, dokumen/unduh serta alur farmasi tanpa mengirim data keluar. Catat celah dan waktu pemulihan.
7. PJ dan TI menilai kehilangan transaksi sejak titik pemulihan, rekonsiliasi formulir downtime dan keputusan cutover. Cutover ke layanan nyata adalah perubahan operasional tersendiri dengan backup keadaan aktif dan rencana kembali yang konkret.
8. Rekam tanggal, operator/reviewer, arsip, versi aplikasi, waktu mulai/selesai, RPO/RTO terukur, hash/integritas, hasil uji dan keputusan. Lindungi serta bersihkan salinan simulasi melalui proses yang disetujui.

## Checklist yang masih terbuka

- [ ] PIC, lokasi, kontrak dan kebijakan kunci disahkan.
- [ ] Driver/disk off-site, ACL/immutability dan konektivitas diuji.
- [ ] Konsistensi DB+file saat layanan aktif diuji; gunakan maintenance window bila diperlukan sampai protokol snapshot tervalidasi.
- [ ] Scheduler nyata, alarm off-host dan kegagalan teruji.
- [ ] Restore seluruh layanan pada lingkungan target dan alur sintetis lulus.
- [ ] Target pemulihan, retensi backup/arsip dan latihan berkala ditetapkan.

Kebutuhan retensi RME mengacu pada [PMK 24/2022 Pasal 39](https://jdih.kemkes.go.id/storage/documents/pdfs/2022permenkes024.pdf). Frekuensi, angka RPO/RTO dan usulan rotasi dalam dokumen ini merupakan rancangan operasional untuk disepakati, bukan kutipan angka wajib dari pasal tersebut.
