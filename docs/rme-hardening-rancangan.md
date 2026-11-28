# Rancangan penguatan RME Klinik Pratama

Baseline: [audit 18 November 2026](rme-hardening-audit-2026-11-18.md). Eksekusi dan status: [tracker](rme-hardening-tracker.md). Pemetaan: [metadata](rme-hardening-metadata.md).

Status: rancangan untuk implementasi bertahap, bukan fitur yang sudah aktif. Semua detail skema usulan harus disesuaikan dengan skema akhir saat migration dibuat.

## 1. Batas perubahan alur

Tetap gunakan tahapan, halaman dan bahasa yang sudah dipakai klinik. Tambahkan kontrol pada titik kerja yang sama: consent saat pendaftaran; asal data dan verifikasi pada pengkajian; review serta coding sebelum tanda tangan dokter; bukti persetujuan sebelum tindakan; review resep sebelum penyerahan; resume/salinan dari persuratan.

Tidak ada tahap layanan baru untuk admin. Admin kembali mengelola identitas administratif, pengguna, konfigurasi, katalog, monitoring status dan laporan agregat. Bantuan membuka kunjungan administratif tetap tersedia. Bantuan menulis asesmen, SOAP atau menyerahkan obat atas nama petugas dihapus dari kewenangan default admin sesuai arahan terbaru pengguna. Catatan bantuan historis tetap utuh.

## 2. Matriks akses target

Izin selalu merupakan gabungan **role + profil aktif + penugasan/poli + objek yang diminta + tahap + tujuan akses**. Daftar, pencarian, autocomplete, props Inertia, lampiran, print dan ekspor memakai batas yang sama. Menghapus tautan tidak cukup.

| Area                      | Admin                                                                                     | Pendaftaran                                           | Perawat/terapis gigi                                          | Dokter                                           | Farmasi                                                           | Manajemen                 |
| ------------------------- | ----------------------------------------------------------------------------------------- | ----------------------------------------------------- | ------------------------------------------------------------- | ------------------------------------------------ | ----------------------------------------------------------------- | ------------------------- |
| Identitas administratif   | Kelola sesuai tugas; NIK dimasking di daftar                                              | Registrasi/verifikasi sesuai tugas                    | Minimum pasien dalam poli                                     | Minimum pasien tanggung jawab                    | Minimum identifikasi pelayanan obat                               | Agregat                   |
| Kunjungan/antrean         | Identitas minimum, status, jadwal; buat/batal administratif                               | Operasional registrasi                                | Antrean poli dan pasien yang dilayani                         | Penugasan/jadwal/claim yang sah                  | Antrean obat                                                      | Agregat                   |
| Asesmen/TTV               | Tidak membaca/menulis detail                                                              | Tidak                                                 | Catat/revisi menurut batas layanan; history relevan read-only | Baca dan review kunjungan sendiri                | Proyeksi yang diperlukan pengkajian obat                          | Tidak                     |
| SOAP/diagnosis/odontogram | Tidak                                                                                     | Tidak                                                 | Riwayat yang relevan untuk pelayanan poli, tidak menulis SOAP | Tulis/final/addendum menurut penugasan           | Hanya indikasi dan kondisi minimum yang relevan, tanpa SOAP penuh | Tidak                     |
| Rencana tindakan          | Tidak                                                                                     | Tidak                                                 | Baca instruksi dokter dan catat realisasi yang didelegasikan  | Tetapkan rencana dan catat realisasi dokter      | Tidak                                                             | Tidak                     |
| E-resep                   | Tidak                                                                                     | Tidak                                                 | Konteks instruksi bila diperlukan, tanpa mengubah resep       | Tulis/final/revisi dengan prosedur koreksi       | Review dan dispensing; tidak mengubah keputusan terapi dokter     | Tidak                     |
| Consent                   | Status administratif dan general consent sesuai tugas; tanpa rincian klinis               | Catat general consent dan bukti administratif         | Baca status terkait tindakan; saksi/pelaksana sesuai SOP      | Penjelas, validasi tindakan dan pencatatan bukti | Hanya status yang relevan bila ada                                | Tidak                     |
| Dokumen/resume            | Kelola template tanpa rekam medis; salinan hanya mandat pelepasan yang sah                | Status permohonan/penyerahan, bukan bebas melihat isi | Cetak pengkajian/instruksi sesuai tugas                       | Terbitkan dokumen dan resume dari versi sah      | Cetak resep/etiket/bukti penyerahan sesuai tugas                  | Tidak                     |
| Lampiran                  | Tidak bebas unduh                                                                         | Tidak bebas unduh                                     | Unggah dan baca sesuai poli/tahap                             | Baca sesuai penugasan                            | Hanya hasil relevan yang secara eksplisit diperlukan              | Tidak                     |
| Laporan                   | Agregat tanpa identitas+detail klinis                                                     | Laporan administratif minimum                         | Layanan sendiri/poli sesuai tujuan                            | Pelayanan tanggung jawab                         | Pelayanan obat                                                    | Agregat                   |
| Audit                     | Metadata kejadian, identitas operator, target minimum; konten klinis mentah tidak dikirim | Tidak bebas                                           | Riwayat versi relevan                                         | Riwayat versi relevan                            | Riwayat penyerahan relevan                                        | Ringkasan bila ditetapkan |

Matriks ini adalah target teknis sesuai permintaan, bukan kutipan susunan peran dari undang-undang. Wewenang membuka isi untuk permohonan pasien harus dibedakan dari role admin sistem. Tidak membuat role baru sebelum fungsi petugas pelepasan/PMIK diputuskan. Jika kelak diperlukan akses luar biasa, desain terpisah dengan alasan, scope, masa berlaku dan review pimpinan; tidak menjadi backdoor admin dalam batch RBAC.

Implementasi: pertahankan `EnsureRole` sebagai filter awal; tambahkan Policy untuk pasien, kunjungan, pengkajian, pemeriksaan, resep/dispensing, lampiran, dokumen dan pelepasan. Kebijakan memanggil resolver penugasan yang sudah ada. Hindari `Gate::before` yang memberi admin semua izin. Controller dan resource/DTO menggunakan proyeksi eksplisit; jangan `toArray()` model klinis ke role nonklinis.

## 3. Final, versi dan koreksi

- Gunakan `pemeriksaan.signed_at`, `signed_by_user_id`, `clinical_note_versions.kind=finalized/addendum` sebagai dasar. **Tidak perlu tabel `medical_record_addendums` kedua atau boolean `is_locked` yang berpotensi berbeda arti.** Bila `locked_at` kelak ditambah, sumber kebenarannya harus satu dan migration legacy tidak memalsukan waktu tanda tangan.
- Snapshot final tetap asli. Addendum membuat versi baru, alasan, pelaku, timestamp server, referensi versi sumber, scope perubahan dan persetujuan jika disyaratkan SOP. Consumer menggunakan satu resolver versi efektif terverifikasi; jangan campur payload final dan kolom dasar tanpa aturan.
- Kunci pemeriksaan tidak melarang realisasi tindakan/farmasi berikutnya. Rencana dokter, realisasi perawat, review farmasi, pengeluaran stok dan penyerahan adalah catatan berbeda dengan actor/waktu masing-masing.
- Narasi, diagnosis, odontogram, rencana dan resep memerlukan koreksi sesuai domain. Perubahan resep setelah final tidak boleh menimpa resep yang sudah dikeluarkan: gunakan pembatalan/pengganti terkait, review dokter, notifikasi petugas yang masih melayani, dan aturan retur/penanganan bila obat terlanjur diserahkan. Jangan melakukan pengurangan stok ulang lewat addendum.
- Bedakan waktu kejadian medis dari waktu dicatat/ditandatangani. Waktu server tidak diterima dari input client. Entri terlambat, jika disetujui SOP, menyimpan waktu kejadian yang dilaporkan beserta alasan; tidak memundurkan timestamp pencatatan. Reservasi dan rencana kontrol bukan backdating.
- Terapkan transaksi dan lock urutan konsisten, token revisi untuk mencegah stale overwrite, idempotensi untuk finalisasi/retry, serta pemeriksaan integritas. Model hook bukan perlindungan raw SQL; uji pembatasan DB juga.
- Sesuaikan koreksi administratif 48 jam dan prosedur persetujuan lewat tenggat tanpa menambah izin admin klinis. Deadline melekat pada entri yang diperbaiki, bukan otomatis sejak pasien pertama kali terdaftar. SOP addendum klinis lewat tenggat perlu eksplisit.

## 4. Retensi dan larangan kehilangan data

Ganti delete kunjungan dengan status batal, alasan, `cancelled_at/by` server dan audit. Draft diagnosis/resep/tindakan yang pernah disimpan menggunakan pembatalan logis/versi dengan alasan, sambil menjaga UX hapus dari daftar aktif. Pasien/master salah input yang belum dipakai dapat diarsip sesuai guard yang ada. Jangan menyembunyikan pasien dengan transaksi klinis.

Lampiran disimpan private dengan metadata immutable (pemilik, kunjungan, ukuran, MIME, hash, waktu, uploader dan versi). Pembatalan mengganti status, tidak menghapus bukti yang sudah diterima. File upload yang gagal dan belum tercatat dapat dibersihkan sebagai orphan secara terukur; ini berbeda dari menghapus lampiran medis sah. Verifikasi semua FK akhir, gunakan restrict pada rekam transaksi; migration baru, tanpa mengedit migration bersejarah.

Kebijakan retensi mencakup isi RME, versi, consent, dokumen dan lampiran; audit terkait diatur agar bukti tetap tersedia sepanjang kebutuhan pembuktian. Backup operasional berotasi bukan pengganti arsip jangka panjang. Tidak menjadwalkan purge RME otomatis. Catat legal hold dan permintaan penghapusan yang berbenturan dengan kewajiban penyimpanan.

## 5. Audit yang dapat dipertanggungjawabkan

Perluas `clinical_audit_events` secara kompatibel; jangan membuat audit kedua yang menimbulkan celah cakupan. Field usulan: actor nullable untuk kejadian anonim, snapshot role/nama ID operator, action normal, domain event asli, target type/id, pasien/kunjungan bila berwenang, server time, IP tepercaya, user agent terbatas, request/correlation ID, outcome, reason, changed fields, old/new yang diizinkan, schema version dan integritas.

Kejadian minimum: create/update/cancel, lihat detail/riwayat, permintaan cetak, ekspor/download, finalisasi/addendum, consent, pelepasan, login gagal/sukses, logout/idle, penolakan akses dan perubahan hak akun. Pertahankan label event lama melalui pemetaan. Audit tidak menyimpan password, session token, key, isi file, raw NIK, atau full request. Diff klinis sensitif disimpan terlindungi dan hanya pembaca berwenang; admin melihat ringkasan field berubah/hash/referensi versi, bukan narasi lama/baru.

Mutasi kritis dan auditnya satu transaksi: gagal menyimpan audit berarti mutasi tidak commit. Audit penolakan/login gagal menggunakan jalur tersendiri agar tidak ikut rollback; kegagalan pencatatan menghasilkan alarm operasional. Akses sensitif yang wajib diaudit tidak diam-diam diberikan ketika recorder gagal. Hindari duplikasi akibat retry melalui korelasi/idempotency.

Append-only harus diuji dengan akun DB runtime. Larang update/delete model dan endpoint, lindungi lewat permission/trigger yang cocok engine; akun migration terpisah. SQLite memerlukan penilaian khusus karena tidak mempunyai grant per user setara server DB; proteksi filesystem, proses pemeliharaan dan salinan integritas di luar host tetap diperlukan. Hash berantai tanpa anchor eksternal dapat ditulis ulang oleh penguasa DB; jangan menjanjikan tamper-proof.

Ekspor PDF/Excel audit memakai filter, pagination/batching, scope, masking dan jejak ekspor; cegah formula injection CSV/Excel. Catatan print berarti permintaan cetak, bukan bukti kertas keluar. `window.print()` tidak melaporkan keberhasilan printer; print route/aksi server mencatat intent, bukan mengarang hasil.

## 6. Consent dan hak pasien

General consent: record/version tersendiri terkait pasien, versi teks, keputusan per tujuan, penjelas, penandatangan/wakil dan hubungan, bukti, timestamp server serta status berlaku/ditarik/diganti. Tautkan bukti yang berlaku saat kunjungan. Status legacy adalah belum terdokumentasi, bukan otomatis setuju. Masa berlaku dan kebutuhan konfirmasi ulang mengikuti SOP. Persetujuan opsional tidak dipaksa menjadi syarat seluruh layanan; jalur darurat tidak boleh tertahan karena administrasi belum lengkap.

Informed consent: kembangkan tabel yang sudah ada dengan hubungan ke **pelaksanaan/rencana tindakan spesifik**, gigi/lokasi bila relevan, diagnosis/indikasi, penjelasan tujuan, risiko/komplikasi, alternatif, risiko tanpa tindakan, prognosis, dokter penjelas, pasien/wakil, saksi, keputusan, waktu penjelasan dan waktu bukti. Bukti scan private atau canvas terikat hash/version; tanda tangan canvas bukan sertifikasi TTE. Revokasi dan penolakan menambah record, tidak menimpa bukti lama.

Gate harus sebelum tindakan invasif/berisiko dilakukan, bukan baru ketika menutup kunjungan. Klasifikasi tindakan ditetapkan dokter/PJ klinis, bukan ditebak dari string nama tindakan. Tindakan manual belum diklasifikasikan perlu review. Darurat memakai dasar, alasan, actor dan review pascakejadian sesuai SOP; tidak ada tombol bypass admin.

Resume memakai jenis `ringkasan` yang ada, terikat kunjungan dan versi efektif, berisi identitas, hasil relevan, diagnosis, terapi/rencana vs realisasi, tindak lanjut dan penanggung jawab. Bila farmasi/tindakan belum selesai, dokumen menyatakan tahap tersebut secara jujur; jangan memaksa dokter menunggu farmasi hanya untuk surat rujukan mendesak.

Register pelepasan mencatat pemohon/wakil, identitas yang diverifikasi, dasar, tujuan, cakupan, persetujuan/otorisasi bila diperlukan, petugas pemberi, penerima, metode dan waktu penyerahan, dokumen/versi dan audit. Permintaan pasien tidak memberi admin akses browsing RME semua pasien. General consent tidak dipakai sebagai izin publikasi atau pengiriman ke atasan kementerian.

## 7. Kualitas pengisian dan terminologi

Riwayat kunjungan lalu adalah referensi dengan sumber, tanggal, pencatat dan status verifikasi. Alergi lama tetap terlihat sebagai peringatan; konfirmasi kunjungan kini mencatat actor/time/status. Bedakan unknown, tidak ada setelah ditanyakan, tidak diperiksa dan normal. Jangan mengisi TTV/temuan dinamis dari kunjungan lalu. Handoff perawat→dokter pada kunjungan sama dipertahankan dengan atribusi dan review revisi.

Jangan membuat kalimat klinis negatif dari field kosong. UI review akhir meminta pilihan eksplisit untuk diagnosis banding/edukasi/nonfarmakologis/tindak lanjut yang relevan. Rencana tindakan dokter menjadi completed hanya setelah konfirmasi pelaksanaan, hasil dan waktu yang sesuai. Tidak membangun engine diagnosis/tindakan otomatis.

Draft naratif/manual tetap memungkinkan dokumentasi saat katalog bermasalah. Target final normal: diagnosis memiliki kode ICD-10 WHO yang terverifikasi dan tindakan relevan memiliki ICD-9-CM; sumber, release, display dan ID mapping disimpan sebagai snapshot. Jangan konversi ICD-10-CM/ICD-9 diagnosis secara mengganti label. Gangguan katalog memakai SOP downtime, status unresolved yang terlihat, penanggung jawab dan rekonsiliasi; tidak membuat pelayanan darurat hilang atau menandai catatan unresolved sebagai integration-ready.

KFA: perlu status unmapped/verified/retired, tipe produk, sumber dan waktu validasi, pemisahan identitas lokal dari kode nasional, serta snapshot pada peresepan/dispensing. Obat racikan dan resep luar tidak dipaksa ke satu produk palsu. Detail zat aktif, kekuatan, bentuk, rute, dosis, frekuensi, durasi dan satuan dinilai berdasarkan kebutuhan nyata farmasi; jangan menebak struktur dari teks aturan pakai tanpa review.

Lab/radiologi: pertahankan surat pengantar dan lampiran eksternal, tanpa membangun LIS/RIS. Tambahkan metadata sumber fasilitas, tanggal pemeriksaan/diterima, tipe pemeriksaan dan hasil; LOINC/value/unit/method hanya ketika hasil terstruktur benar-benar ada dan diverifikasi. Scan tanpa kode tetap disimpan secara jujur dan belum diberi status hasil terstruktur siap kirim.

## 8. Konsistensi dokumen

Satu resolver versi efektif untuk RME, surat baru, resume dan proyeksi farmasi yang relevan. Surat yang telah diterbitkan menyimpan snapshot lengkap faskes/pasien/dokter termasuk tanda tangan yang digunakan, versi klinis, template dan nomor; perubahan profil master tidak merombak arsip. Dokumen lama tanpa snapshot lengkap diberi provenance legacy; jangan membuat identitas faskes historis tanpa bukti.

Perbaiki `completed` versus `selesai` dan jangan menyamakan status kunjungan dengan status tindakan. Dokumen dibatalkan/diganti melalui record terkait, nomor tidak dipakai ulang. QR/token acak mengarah ke verifikasi minimal (valid/dibatalkan/diganti, nomor dan penerbit yang layak ditampilkan), tanpa nama/NIK/diagnosis. Isi hanya untuk pihak terautentikasi/berwenang, rate limit dan audit; QR bukan pengganti TTE.

## 9. Keamanan sesi, komunikasi, enkripsi dan pemulihan

Target awal idle **15 menit**, warning sebelum habis. Server menyimpan batas idle yang tidak diperpanjang polling otomatis; frontend menyamarkan data segera dan menyinkronkan logout lintas tab, menghitung ulang setelah tab sleep. Aktivitas nyata dapat mengirim sinyal terbatas; client tidak dipercaya menentukan waktu server. Logout membatalkan session dan remember; uji kembali lewat browser back, print popup dan pergantian akun. Tidak menyimpan draf sensitif di localStorage. Draf manual yang sudah disimpan tetap tersedia setelah login sesuai kewenangan.

No-store diterapkan pada respons sensitif HTML/JSON/download, dan history Inertia ditinjau untuk clear/encryption sesuai versi terpasang. Verifikasi HTTPS efektif, redirect, secure/HttpOnly/SameSite cookies, trusted proxies, tidak ada mixed content, konfigurasi debug dan file publik. TLS/cipher diperiksa di terminator sebenarnya, tidak dibuktikan dengan `APP_URL` saja.

Enkripsi at-rest dimulai dari inventaris data serta backup. Pilih disk/database dan/atau field encryption berdasarkan threat model. Random encrypted cast NIK akan merusak equality/LIKE/index biasa; jika dipilih, gunakan blind index HMAC terpisah dengan kunci khusus, masking, backfill bertahap dan dual-read terkontrol, lalu batasi pencarian substring NIK. Cakup NIK di pasien, kepesertaan, impor dan snapshot; jangan hanya satu kolom. Enkripsi catatan harus mempertahankan canonical payload/hash versi dan pencarian yang memang berwenang. Key rotation, akses escrow, recovery dan uji kehilangan kunci adalah bagian acceptance, bukan menyimpan key bersama backup tanpa perlindungan.

Backup: job minimum harian sebagai target RPO awal ≤24 jam; RTO diusulkan ≤8 jam sampai disepakati operator. Database konsisten + file private + dokumen/consent + manifest/checksum + konfigurasi pemulihan dan akses kunci yang terpisah. Off-site terenkripsi dengan kredensial dan lifecycle tersendiri, retention lock bila tersedia. Pilih lokasi/penyedia sesuai ketentuan dan kebijakan institusi, jangan asal bucket luar negeri. Uji kesesuaian spatie/laravel-backup dengan Laravel 13/engine lokal sebelum menambah dependency; paket bukan syarat hukum.

Scheduler OS, locking job, failure notification, usia backup terakhir dan restore drill wajib dibuktikan. Snapshot SQLite perlu mekanisme backup konsisten termasuk WAL; jangan sekadar menyalin file DB yang sedang ditulis. Restore di lingkungan terisolasi memeriksa DB integrity/FK, jumlah record, versi, hash dan sampel file yang diizinkan, login serta alur layanan sintetis. Backup sebelum migration dan rollback aplikasi tidak boleh menghancurkan versi baru yang sudah tercatat.

## 10. SATUSEHAT sesudah celah internal

Tahap persiapan menghasilkan kamus data dan validator readiness offline, bukan koneksi produksi. Pemetaan resource bersifat kontrak desain dan wajib divalidasi ulang terhadap playbook saat implementasi. Organization/Location/Practitioner/Patient IHS terpisah dari ID lokal. Rujukan internal tidak otomatis dipaksa menjadi satu Encounter atau dua tanpa definisi use case yang diverifikasi.

Setelah gate internal lulus, desain adapter memakai outbox transaksional, urutan dependency resource, idempotency key, ID resource eksternal, mapping versi, retry/backoff, error queue, rekonsiliasi dan koreksi/amendment. Pengiriman asinkron tidak memblokir pelayanan lokal; gagal kirim tidak ditampilkan sukses. Akses token/secrets hanya server, payload log dimasking, sandbox dan produksi dipisahkan. Keberhasilan POST bukan bukti seluruh rangkaian semantik selesai.

Tidak otomatis mengirim data legacy yang belum diverifikasi. Jangan menginventasikan IHS, kode, consent atau timestamp agar validasi lolos. Integrasi produksi dan registrasi faskes/RME tetap gate terpisah dan belum selesai dalam kajian ini.

## 11. Strategi migrasi dan penerimaan

1. Inventaris agregat data lama yang diizinkan + backup/restore terbukti sebelum migration data.
2. Tambah kolom/tabel baru secara additive; identitas dan relasi asli dipertahankan. Backfill hanya fakta yang dapat ditelusuri, dengan provenance migrasi.
3. Uji upgrade dari salinan terisolasi dan fresh schema pada engine yang akan dipakai, bukan SQLite memory saja. Deteksi FK orphan, duplicate dan data tidak terpetakan secara eksplisit.
4. Rilis per batch berukuran terbatas; test yang mempertahankan perilaku lama diperbarui menjadi kontrak baru, bukan sekadar dihapus agar hijau.
5. UAT skenario Poli Umum/Gigi tetap melewati alur yang sama, termasuk tanpa resep, tindakan perawat, obat parsial/resep luar, rujukan internal, reservasi dan kondisi darurat.
6. Tahap dinyatakan lulus setelah hasil test, review akses/props/export, QA cetak dan bukti SOP/operasional yang relevan tercatat. Temuan baru dibuat ID baru dan tidak disembunyikan dalam catatan umum.
