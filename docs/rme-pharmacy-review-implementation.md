# T25 — Review dan penyerahan farmasi

Checkpoint 20 November 2026. Status IN_PROGRESS sampai acceptance akhir dan verifikasi petugas nyata selesai.

## Perubahan yang diterapkan

- Master Tenaga Kerja → Edit profil farmasi → Verifikasi kualifikasi farmasi. Admin mencatat Apoteker/TTK/pencabutan, referensi bukti profesi dan penugasan, batas berlaku verifikasi, konfirmasi pemeriksaan dokumen, serta autentikasi ulang.
- Tidak ada migrasi yang otomatis menyatakan petugas lama sebagai apoteker. Profil aktif, akun farmasi aktif, SIP dan STR diperlukan. Perubahan SIP/STR, perpindahan akun/role, profil nonaktif, expiry atau pencabutan membuat verifikasi tidak berlaku. Profil ganda untuk satu akun ditolak resolver.
- Bukti profesi dan penugasan berupa event terenkripsi, actor/time server, append-only pada model dan trigger database. Referensi bukti tidak dianggap sebagai verifikasi otomatis oleh instansi profesi.
- Review resep hanya dapat ditulis apoteker terverifikasi. Review administratif, farmasetik dan klinis harus eksplisit; hasil, klarifikasi dokter, referensi komunikasi dan keputusan layak/tahan disimpan. Klarifikasi pending tidak dapat menghasilkan keputusan layak.
- Hash sumber mengikat versi catatan dokter, dokter/SIP, alergi efektif, BB/waktu, item resep/resep luar serta rencana jumlah/aturan pakai penyerahan. Perubahan sumber atau review oleh sesi lain memerlukan review kembali.
- Final penyerahan memerlukan review apoteker terbaru yang masih berlaku, petugas Apoteker/TTK terverifikasi, nama/hubungan penerima, verifikasi identitas, konfirmasi konseling dan catatan pemahaman. TTK tidak menandatangani review klinis sendiri.
- Event penyerahan mengacu ke review yang mendasarinya dan kualifikasi petugas pelaksana. Event, pengurangan stok dan audit satu transaksi. Retry setelah selesai tidak menggandakan stok/event.
- Pemberian langsung mempertahankan daftar obat yang sudah diizinkan dan basis pemberian existing. Review/konseling/penerima wajib; pelaksanaan langsung saat ini memerlukan apoteker terverifikasi. Bukti review terpisah, terenkripsi dan immutable; request fingerprint mencakup review, sehingga perubahan isi retry ditolak.
- Alergi pada pemberian langsung kini berasal dari riwayat efektif pasien sampai hari pelayanan, dengan fallback master jika belum ada riwayat; tidak diasumsikan negatif ketika kosong.

## Perubahan kerja yang perlu diketahui petugas

1. Verifikasi profil berdasarkan dokumen petugas farmasi klinik yang sebenarnya. Pesan pengguna “orang farmasi di klinik” menetapkan kelompok petugas yang digunakan, bukan bukti individual profesi atau nomor dokumen.
2. Simpan draf rencana penyerahan sebelum review apoteker.
3. Lakukan review dan selesaikan klarifikasi jika diperlukan.
4. Isi penerima/konseling, kemudian selesaikan penyerahan. Perubahan jumlah atau aturan pakai setelah review akan ditolak sampai direview kembali.
5. Arsip selesai tetap dapat dilihat; data lama tidak dibuatkan review retrospektif.

## Verifikasi batch

- PharmacyClinicalReviewTest + FarmasiInertiaTest awal: 14/14, 468 assertions.
- Regresi klinis terkait: 49/49, 896 assertions.
- Regresi farmasi/obat langsung/alergi/admin: 29/29, 702 assertions.
- Regresi farmasi terakhir: 20/20, 561 assertions, termasuk TTK menyerahkan berdasarkan review apoteker serta pencabutan kualifikasi.
- Full suite batch farmasi: 589/589, 10.211 assertions, nol error/failure; bukti storage/framework/testing/hardening-suite-20261120-pharmacy.xml. Hasil ini mendahului penambahan proyeksi indikasi 21 November 2026.
- Bukti di atas sebagian beririsan; tidak dijumlahkan sebagai total tes unik.
- Frontend 307/307, termasuk pembatasan form review Apoteker/TTK dan escaping catatan. TypeScript, build dan Pint lulus. Security check lokal 11/11 true, termasuk enam trigger baru farmasi.
- Backup sebelum kualifikasi/review: clinical-20261120-231628-ffe62cfd-cbb2-4b22-9190-8357a3f19074.zip.
- Backup sebelum review pemberian langsung: clinical-20261120-232223-09db8499-3ad3-4f0d-af6a-1b58557cf584.zip.
- Backup sesudah ketiga migrasi: clinical-20261120-232451-74a17460-4def-4c5b-ba5e-573a330058ec.zip.

## Acceptance tersisa

- Pengisian/verifikasi bukti profesi dan penugasan petugas nyata oleh pengelola yang berwenang; tidak dibuatkan data fiktif dalam database aplikasi.
- UAT browser Apoteker/TTK, hold/klarifikasi, parsial, resep luar, konseling dan retur.
- Jalur pemberian langsung oleh TTK dengan penugasan/review apoteker terpisah bila diperlukan SOP; saat ini ditolak agar tidak melewati review klinis.
- Proyeksi indikasi diagnosis efektif dan ringkasan konseling pada riwayat review telah ditambahkan 21 November 2026. Kondisi tambahan yang diperlukan apoteker tetap melalui klarifikasi dokter; seluruh SOAP tidak dibuka.
- Snapshot cetak pemberian langsung, terminologi KFA, retur dan koreksi pasca-dispensing tetap mengikuti T21/T22/T26.
