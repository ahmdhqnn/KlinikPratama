# Audit admin Klinik Pratama internal — 18 November 2026

## Konteks dan dasar keputusan

Audit mencakup navigasi admin, route/middleware, pengelolaan pengguna dan petugas, monitoring kunjungan, alur klinis/farmasi, master data, laporan, dan catatan audit. Konteks layanan mengikuti implementasi proyek: klinik internal kementerian, Poli Umum dan Poli Gigi, kepesertaan internal, serta alur pendaftaran sampai penyerahan obat. Informasi publik tentang klinik kementerian lain tidak dipakai untuk menetapkan penerima layanan, jadwal, pembiayaan, maupun SOP klinik ini.

Rujukan yang diperiksa:

- [Permenkes 11/2025, standar kegiatan usaha subsektor kesehatan](https://jdih.kemkes.go.id/documents/peraturan-menteri-kesehatan-nomor-11-tahun-2025), khususnya standar klinik pemerintah dan klinik pratama. Menjadi konteks fasilitas dan tanggung jawab pelayanan, bukan resep susunan sidebar.
- [Permenkes 24/2022 tentang Rekam Medis](https://jdih.kemkes.go.id/documents/peraturan-menteri-kesehatan-nomor-24-tahun-2022), khususnya prinsip keamanan dan hak akses Pasal 29–30. Akses administratif, penginputan klinis, perbaikan, dan penandatanganan mengikuti kewenangan serta SOP fasilitas.
- [Permenkes 34/2021 tentang Standar Pelayanan Kefarmasian di Klinik](https://jdih.kemkes.go.id/documents/peraturan-menteri-kesehatan-nomor-34-tahun-2021). Pemisahan katalog, persediaan, penyerahan, dan pelaporan merupakan keputusan desain aplikasi untuk mendukung tanggung jawab tersebut.

Pengelompokan menu di bawah adalah hasil analisis alur aplikasi. Regulasi tersebut tidak mewajibkan nama atau jumlah menu tertentu. Audit ini tidak menyatakan sertifikasi kepatuhan atau persetujuan SOP klinis.

## Struktur menu pada checkpoint awal

Struktur final setelah arahan submenu tercantum pada bagian "Penyesuaian lanjutan: submenu sidebar" di akhir dokumen.

| Kelompok | Menu | Tujuan |
| --- | --- | --- |
| Ringkasan | Dasbor | Ringkasan dan pintasan operasional |
| Monitoring operasional | Kunjungan & antrean | Satu pusat kunjungan, antrean per tahap, riwayat, dan reservasi |
| Monitoring operasional | Pendaftaran pasien | Pasien baru/lama, verifikasi, dan pembukaan kunjungan |
| Monitoring operasional | Jadwal praktik | Jadwal dokter, libur, dan pengecualian |
| Monitoring operasional | Mutasi & stok farmasi | Batch, penerimaan, opname, verifikasi, dan retur |
| Monitoring operasional | Log aktivitas | Aktivitas yang dicatat aplikasi dan tautan audit bantuan |
| Master data | Pengguna & hak akses | Akun login, peran, status akses, dan pengaturan ulang kata sandi |
| Master data | Nakes & karyawan | Identitas profesi, SIP/STR, penugasan, dan status profil |
| Master data | Data pasien | Identitas pasien dan akses riwayat sesuai kewenangan |
| Master data | Kepesertaan internal | Direktori instansi dan hak layanan yang diverifikasi |
| Master data | Poliklinik & ruang | Konfigurasi unit layanan dan ruang |
| Master data | Referensi medis | Terminologi klinis yang tersedia pada aplikasi |
| Master data | Master obat & BHP | Katalog, satuan, kode, ambang minimum, impor/ekspor |
| Pelayanan klinis | Pengkajian awal; Pemeriksaan dokter; Tindakan perawat; Persuratan | Pekerjaan berbeda pada tahapan pelayanan |
| Pelayanan farmasi | Penyerahan obat; Obat langsung; Resep luar; Riwayat farmasi pasien | Resep klinik, pemberian langsung, tindak lanjut resep luar, dan penelusuran riwayat |
| Laporan & analitik | Pusat laporan | Pintu laporan kunjungan, diagnosis, pengkajian, dokter, tindakan, farmasi, dan stok |
| Administrasi | Pengaturan klinik | Identitas dan konfigurasi fasilitas |
| Akun | Profil saya | Data pribadi akun yang sedang digunakan |

## Duplikasi dan penyesuaian alur

1. **Antrean pasien, Kunjungan poli, dan Daftar kunjungan:** dua menu daftar dihapus dari sidebar admin. Monitoring memakai halaman antrean yang memiliki tabel hari ini, tabel seluruh kunjungan dengan filter/paginasi independen, serta aksi menurut tahap. Pintasan dasbor diarahkan ke pusat ini. Tombol kembali/batal pada pembukaan kunjungan admin serta tujuan kembali bawaan detail juga menuju monitoring; konteks daftar yang sah tetap dipertahankan bila tersedia. Route daftar lama tetap tersedia untuk kompatibilitas tautan dan akses peran lain; tidak ada penghapusan data kunjungan.
2. **Pengguna dan nakes:** tetap dua menu sesuai arahan pengguna karena sumber datanya berbeda. Tautan silang menjelaskan tempat mengelola akun versus profesi. Detail dipisahkan dari edit pada kedua daftar.
3. **Pembuatan akun untuk profil petugas yang sudah ada:** pemilihan profil diperluas dari dokter ke perawat/terapis gigi, farmasi/apoteker, dan pendaftaran. Backend menolak profil yang tidak aktif, terhapus, sudah terhubung ke akun lain, atau jabatannya tidak cocok. Identitas profesional dan riwayat profil lama dipertahankan saat pengaitan diganti. Jika belum ada profil, alur pembuatan profil otomatis non-dokter tetap tersedia.
4. **Terapis gigi:** pilihan poli pada formulir profil sekarang ditampilkan sesuai kewajiban validasi backend.
5. **Reservasi:** monitoring tidak lagi menyembunyikan reservasi yang lebih dari satu minggu mendatang. Aksi klinis tetap dibatasi tanggal dan konfirmasi reservasi.
6. **Katalog obat versus stok:** dipisahkan karena katalog mengelola identitas item, sedangkan persediaan menangani saldo per batch dan jejak mutasi. Modul Alkes/laboratorium yang sudah dipensiunkan tidak diaktifkan kembali hanya untuk menyesuaikan contoh menu.
7. **Laporan:** pusat laporan tetap menjadi satu pintu navigasi. Kartu laporan kunjungan pendaftaran yang mengulang laporan kunjungan utama dihapus dari hub admin; menu laporan pendaftaran tetap tersedia untuk peran pendaftaran. Cetak tiket antrean ditambahkan langsung ke pusat monitoring, dengan penolakan untuk kunjungan batal atau reservasi belum dikonfirmasi. Halaman operasional tetap menyediakan riwayat yang dibutuhkan untuk bekerja; riwayat tidak dianggap duplikat laporan agregat.
8. **Log aktivitas versus audit bantuan:** satu menu log menyediakan catatan audit yang tersedia lintas peran. Audit bantuan khusus tetap bisa dibuka dari halaman log. Tampilan baru menggunakan left join sehingga catatan tidak hilang hanya karena akun operator dihapus. Metadata mentah, kata sandi, dan identitas sensitif di metadata tidak dikirim ke tampilan log.

## Aksi dan batas kewenangan

| Area | Aksi yang diperiksa/dipertahankan |
| --- | --- |
| Monitoring kunjungan | Detail, RME pasien, pengkajian, cetak pengkajian, pemeriksaan, tindakan perawat, farmasi, persuratan, cetak tiket antrean, ubah, batal; aksi yang belum memenuhi tahap dinonaktifkan dengan alasan |
| Pengguna | Detail, tambah, edit, aktif/nonaktif, atur ulang kata sandi, hapus; akun sendiri tidak dapat diturunkan peran/dinonaktifkan/dihapus melalui pengelolaan pengguna |
| Nakes | Detail, tambah, edit, arsip melalui soft delete, pembuatan akun saat registrasi profil; penonaktifan profil menonaktifkan login tertaut |
| Kepesertaan/pasien | Detail dan perubahan identitas/hak layanan mengikuti middleware dan validasi yang sudah ada; data pasien dan direktori kepesertaan memiliki tujuan berbeda |
| Persediaan | Lihat batch/kartu stok, penerimaan, opname, verifikasi stok historis, retur penyerahan; kontrol stok mengikuti recorder dan test keterlacakan |
| Klinis | Bantuan admin, dokter penanggung jawab, peninjauan dan finalisasi mengikuti kontrol yang sudah ada; reorganisasi menu tidak memberikan kewenangan penandatanganan baru |
| Audit | Baca dan filter periode, jenis kegiatan, operator/nomor kunjungan, serta paginasi; tidak ada endpoint edit/hapus log yang ditambahkan |

Validasi kata sandi pembuatan akun dan pengaturan ulang admin kini mewajibkan string minimal delapan karakter, selaras dengan perubahan kata sandi mandiri. Akun dengan profil petugas nonaktif tidak dapat diaktifkan melalui tombol status ataupun dengan menghilangkan pilihan profil saat edit.

Perubahan akun dan profil petugas sekarang dicatat dalam transaksi yang sama dengan perubahan datanya. Metadata audit menyimpan ID target dan konteks akses yang diperlukan, tanpa kata sandi. Log belum merupakan pencatatan seluruh klik atau seluruh perubahan tabel: aktivitas yang belum diinstrumentasi tidak dianggap sudah tercatat, dan riwayat sebelum instrumentasi tidak direkonstruksi.

## Temuan dependensi yang belum terselesaikan

`composer audit` tidak melaporkan advisory. `npm audit` menemukan delapan entri high yang berasal dari satu advisory Picomatch dan paket-paket yang bergantung padanya. Versi terpasang mencakup Picomatch 4.0.7 dan 2.3.2 pada rantai Vite/file globbing. [GHSA-fc5q-39xc-vjpf](https://github.com/advisories/GHSA-fc5q-39xc-vjpf) menyebut versi sampai 4.0.7 terdampak ReDoS dan belum mencantumkan versi perbaikan saat diperiksa. Registry npm juga mengembalikan 4.0.7 sebagai versi terbaru.

Saran otomatis npm meliputi penurunan versi mayor Vite dan plugin terkait. Perubahan itu tidak diterapkan karena bukan perbaikan yang sudah terbukti kompatibel untuk aplikasi ini. Penilaian paparan aktual perlu membedakan tooling build/dev dan runtime produksi; audit ini tidak membuktikan adanya jalur input glob dari pasien ke Picomatch. Temuan tetap terbuka sampai patch upstream atau mitigasi yang teruji tersedia.

## Verifikasi dan batas audit

Pemeriksaan frontend akhir: 290 tes lulus; TypeScript lulus. Pint untuk berkas PHP yang diubah lulus; diff check lulus. `composer validate` lulus dengan peringatan constraint Inertia backend yang sudah ada (`3.0` persis). Audit database lokal tidak menemukan pelanggaran foreign key. Suite backend menyeluruh lulus: 510 tes, 9.502 assertion. Setelah penyelarasan tujuan kembali kunjungan, 40 tes backend terarah (551 assertion) dan 25 tes frontend terarah lulus; TypeScript dan build dijalankan ulang dan lulus. Build produksi lulus. Test backend menggunakan SQLite in-memory. Audit foreign key pada database lokal bersifat baca saja. Pemeriksaan browser mencakup menu admin baru dan monitoring kunjungan beserta aksi menurut status; pemeriksaan interaksi form lanjutan terhenti karena koneksi debugger browser terlepas. Tidak ada pembuatan akun, penggantian kata sandi, atau perubahan data pasien melalui browser pada audit ini.

Tidak ada migration, seeding, commit, atau deployment dalam pekerjaan ini. Worktree sudah berisi perubahan sebelum audit dimulai; perubahan tersebut dipertahankan. Pengujian lokal tidak membuktikan kesiapan backup produksi, akurasi stok fisik, persetujuan SOP, maupun validasi layanan yang belum dijalankan oleh klinik.


## Penyesuaian lanjutan: submenu sidebar

Menindaklanjuti arahan pengguna, struktur navigasi diubah menjadi dua tingkat. Bagian Operasional memuat Kunjungan & antrean, Pendaftaran pasien, dan Jadwal praktik. Bagian Pengelolaan memuat induk Master data, Pelayanan klinis, Farmasi & persediaan, Laporan & analitik, serta Audit & aktivitas; Pengaturan klinik tetap menjadi tujuan langsung. Profil saya tetap berada pada bagian Akun.

- Log aktivitas dan Audit bantuan layanan kini merupakan dua submenu di bawah Audit & aktivitas; navigasi tombol di dalam halaman audit dihapus.
- Pengguna & hak akses dan Nakes & karyawan tetap dua halaman terpisah di submenu Master data. Banner/tombol perpindahan antarkedua halaman dihapus.
- Laporan dapat dibuka langsung dari submenu Laporan & analitik. Pusat laporan tetap tersedia sebagai ikhtisar. Halaman laporan aktif mempunyai penanda submenu sendiri dan breadcrumb menuju pusat laporan.
- Satu komponen navigasi menangani tombol buka/tutup, anak menu berindentasi, indikator halaman aktif, badge antrean, dan popover submenu ketika sidebar diciutkan. Induk menu terbuka otomatis saat halaman anak diakses, termasuk dari pencarian. Drawer mobile menggunakan struktur yang sama dan menutup setelah tujuan dipilih.
- Pencarian mencakup seluruh halaman anak beserta kategori induknya; submenu tersembunyi tidak membatasi hasil pencarian. Hanya tautan halaman aktif yang memakai `aria-current=page`; tombol induk memakai `aria-expanded` dan `aria-controls`.

Pola komposisi mengikuti [Sidebar shadcn](https://ui.shadcn.com/docs/components/sidebar), dengan tombol disclosure dan daftar tautan bersarang sesuai [panduan navigasi W3C APG](https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/examples/disclosure-navigation/). Komponen aplikasi yang sudah ada dipertahankan; tidak ada dependensi UI baru atau perubahan kewenangan backend.

Uji browser pada pembaruan ini berhasil memeriksa navigasi antarhalaman audit, pengembangan Master data, pencarian laporan versus operasional, popover sidebar ringkas, dan drawer mobile 390×844 yang menutup setelah pemilihan halaman. Viewport dikembalikan ke ukuran semula. Tampilan desktop tema gelap juga diperiksa secara visual. Verifikasi pembaruan submenu: 292 tes frontend lulus; TypeScript, build produksi, dan diff check lulus. Seluruh 35 tujuan admin yang didefinisikan pada konfigurasi (di luar Profil saya bersama) unik dan cocok dengan route GET yang tersedia, termasuk route berparameter. Hasil backend sebelumnya merupakan checkpoint sebelum pembaruan navigasi ini; pembaruan submenu tidak mengubah kode backend.


## Penyelarasan isi halaman dan fokus admin

Checkpoint ini menggantikan penempatan Pendaftaran pasien pada struktur sidebar sebelumnya. Operasional admin kini hanya Kunjungan & antrean serta Jadwal praktik. Pendaftaran tetap tersedia sebagai tindakan bantuan di monitoring, bersama Buka kunjungan dan Perbarui antrean; seluruhnya sejajar dengan tombol Filter. Hak akses pendaftaran admin tetap dipertahankan.

- Akses cepat dasbor berisi delapan tujuan: monitoring, pengguna, nakes, kepesertaan, master obat/BHP, mutasi/stok, pusat laporan, dan log aktivitas. Grid empat kolom pada layar lebar membentuk dua baris; tombol Buat kunjungan pada judul dasbor dihapus.
- Kartu ringkasan pengguna memakai StatCard bersama, termasuk ukuran angka, posisi ikon, warna netral, padding, dan responsivitas. Profil petugas tersedia menghitung seluruh profil aktif yang kompatibel dan belum terhubung, bukan hanya dokter. Deskripsi jumlah pada direktori menegaskan cakupan filter.
- Impor kepesertaan dan terminologi menjadi dialog yang dibuka dari toolbar di sebelah Filter. Referensi bukti, template kepesertaan, validasi, batas berkas, dan pemilihan sistem/rilis tetap tersedia. Dialog mempunyai aksi Batal dan submit yang sesuai.
- Komponen FilterPanel menyamakan card filter dan perataan submit di kanan desktop; mobile memakai tombol selebar kontainer. Direktori pengguna, nakes, poliklinik, obat/BHP, kepesertaan, dan persediaan menggunakan label filter yang terlihat. Tindakan toolbar memakai ukuran konsisten.
- Pencarian ditambahkan pada katalog terminologi, laporan kunjungan, laporan stok, diagnosis terbanyak, dan jadwal praktik. Terminologi mencari katalog/sistem/rilis, bukan isi kode (pencarian kode tetap pada detail katalog). Jadwal mencari dokter/poliklinik pada daftar jadwal terdaftar; kalender libur dan pengecualian tetap panel terpisah. Diagnosis mencari kode/nama, laporan kunjungan nama/No. RM, stok nama/kode. Pencarian laporan memengaruhi tabel, ringkasan, pagination, dan Excel secara sama.
- ServiceReportLinks menyediakan tautan laporan menurut peran untuk pengkajian, pemeriksaan, tindakan, penyerahan obat, obat langsung, resep luar, riwayat farmasi, persediaan, dan monitoring. Admin menuju laporan admin, perawat/dokter menuju laporan perannya. Tautan laporan stok tidak ditampilkan bagi farmasi karena route stok laporan hanya mengizinkan admin/manajemen. Persuratan menunjuk laporan kunjungan terkait dengan label yang eksplisit; tidak dibuat laporan persuratan yang belum tersedia. Master obat juga mempunyai pintasan laporan stok. Tautan laporan pemberian langsung yang ganda pada riwayat farmasi dihapus.
- Pada laporan tindakan perawat, toolbar hanya menawarkan Ekspor Excel. Cetak dokumen klinis individual (pengkajian, resep, bukti penyerahan, dan surat) tetap tersedia sesuai fungsinya. Endpoint cetak laporan lama tetap kompatibel, tanpa pintasan pada daftar laporan.
- Bug reset filter detail terminologi diperbaiki: sistem dan rilis dipertahankan sebagai konteks wajib. Kata pencarian 0 tetap dianggap filter aktif. Karakter teks rusak pada keterangan rilis diperbaiki. Nilai filter kosong yang dikirim null pada laporan tindakan dinormalisasi untuk menghindari peringatan input React.

### Bukti pemeriksaan checkpoint ini

- Seluruh 35 tujuan sidebar, termasuk Profil saya, dibuka di browser sebagai admin; tidak ditemukan halaman error atau akses ditolak. Pemeriksaan ini mencakup akses dan render, bukan eksekusi semua mutasi data pada setiap halaman.
- Interaksi browser memeriksa delapan akses cepat, kartu pengguna, buka dialog impor kepesertaan/terminologi, pencarian katalog, reset detail katalog, tujuan laporan tindakan admin, keberadaan Excel dan ketiadaan pintasan cetak laporan.
- Pada desktop, posisi kanan tombol submit monitoring tepat sejajar dengan tepi kanan form. Filter terminologi pada viewport 390 x 844 diperiksa secara visual dan mempunyai scrollWidth sama dengan clientWidth (352 px). Viewport dikembalikan ke ukuran semula. Sesudah perbaikan null filter, halaman laporan tindakan tidak menghasilkan error console baru.
- Seluruh backend: 513 tes / 9.631 assertions lulus. Tes pencarian memeriksa baris hasil, angka ringkasan, hasil kosong, validasi panjang, serta isi Excel kunjungan, stok, dan diagnosis. Regresi akses peran dan jadwal juga lulus.

Tidak dilakukan impor ke database pengguna atau perubahan akun/data klinis melalui browser. Aktivitas membuka laporan mengikuti pencatatan audit normal aplikasi. Tidak ada migration, seeding, commit, atau deployment pada checkpoint ini.

Rujukan teknis pengujian: [HTTP Tests Laravel 13](https://laravel.com/docs/13.x/http-tests). Penyesuaian mengikuti komponen UI dan kontrak route proyek yang tersedia.

Verifikasi frontend akhir: seluruh 295 tes render lulus; ikon akses cepat diselaraskan dengan ikon tujuan sidebar dan tes dasbor diperiksa kembali. TypeScript, build produksi, Pint, dan diff check lulus. Artefak log pengujian disimpan pada `tmp/admin-consistency-*`; isi direktori tmp tidak disertakan sebagai perubahan sumber.
