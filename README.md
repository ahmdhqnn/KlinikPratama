# Klinik Pratama — Rekam Medis Elektronik

Aplikasi operasional Klinik Pratama dengan backend Laravel dan antarmuka React melalui Inertia. Implementasi berfokus pada pelayanan Poli Umum dan Poli Gigi: pendaftaran, pengkajian awal, pemeriksaan dokter, tindakan perawat, pelayanan farmasi, persuratan, dan pelaporan.

Dokumen ini merangkum implementasi dan cara pemeliharaan proyek. Kode, konfigurasi, migration, dan test merupakan sumber kebenaran teknis. Hasil test otomatis tidak menggantikan UAT browser atau persetujuan operasional klinis.

## Teknologi dan persyaratan

| Komponen | Implementasi |
| --- | --- |
| PHP | Lingkungan audit PHP 8.4; batas paket mengikuti `composer.json` |
| Backend | Laravel 13, Eloquent, PHPUnit 12 |
| Antarmuka | React 19, TypeScript 5, Inertia 3 |
| Tampilan | Tailwind CSS 4, komponen Radix UI, Lucide |
| Build | Vite 8.3.5 pada lockfile hasil audit |
| Database lokal dan test | SQLite; test backend memakai database `:memory:` |
| Impor/ekspor | Laravel Excel 4 |

Versi yang dapat direproduksi tersimpan dalam `composer.lock` dan `package-lock.json`. Gunakan versi PHP, Node.js, dan ekstensi yang memenuhi persyaratan paket terkunci. SQLite memerlukan ekstensi PDO SQLite.

## Cakupan aplikasi

- Admin: pengelolaan operasional, pasien, antrean, pengkajian, persuratan, data master, pengguna, audit bantuan, dan laporan sesuai hak akses.
- Pendaftaran: identitas pasien, kepesertaan internal, reservasi/kunjungan, jadwal praktik, serta monitoring pelayanan.
- Perawat: pengkajian awal, pelaksanaan tindakan, pencatatan BHP, dan laporan sesuai poli penugasan.
- Dokter: pemeriksaan, diagnosis berkode/manual, odontogram, tindakan, resep, edukasi, rencana kontrol, serta dokumen klinis sesuai otorisasi.
- Farmasi: penyiapan dan penyerahan obat, penyerahan parsial, resep luar, pemberian langsung sesuai eligibility, serta laporan dan jejak stok batch.
- Manajemen: ringkasan dan laporan sesuai pembatasan akses.

Alur utama: pendaftaran → pengkajian awal → pemeriksaan dokter → tindakan perawat jika diperlukan → farmasi jika diperlukan → selesai. Kunjungan dapat selesai tanpa resep; tindak lanjut mengikuti data pelayanan yang benar-benar dicatat. Reservasi mendatang mengikuti pembatasan tanggal pelayanan.

Menu dan endpoint modul laboratorium internal, manajemen depo, katalog alat kesehatan, serta master tindakan sudah dipensiunkan. Riwayat depo dan referensi tindakan bersejarah tetap dipertahankan untuk keterlacakan. Pencatatan tindakan klinis serta surat pengantar laboratorium tetap tersedia.

Pada upgrade, migration memindahkan status kunjungan kasir lama ke selesai dan menegakkan satu resep per kunjungan. Jika ada resep ganda, verifikasi dokumen sumber sebelum menjalankan ulang migration. Migration penghapusan Alkes dan laboratorium menolak tabel yang masih memiliki data. Untuk instalasi dengan data lama, verifikasi cadangan dan migrasikan catatan ke arsip yang dapat diakses petugas berwenang sebelum penghapusan skema.

## Struktur proyek

```text
app/
  Console/Commands/      Perintah pemeliharaan klinik
  Http/Controllers/     Endpoint per area dan peran
  Http/Middleware/      Autentikasi, otorisasi, audit, dan modul
  Http/Requests/        Validasi request yang telah dipisahkan
  Models/               Model dan relasi domain
  Exports/, Imports/    Impor dan ekspor data
  Rules/                Validasi domain khusus
  Providers/            Registrasi layanan aplikasi
  *.php                 Layanan, query, dan aturan domain yang digunakan bersama
bootstrap/              Bootstrap Laravel
config/                 Konfigurasi aplikasi dan modul
database/              Migration berurutan, factory, dan seeder
public/                 Entrypoint HTTP dan aset publik sumber
resources/
  css/                  Tema dan stylesheet aplikasi
  js/components/        Komponen UI dan komponen klinis
  js/layouts/           Layout dan navigasi berbasis peran
  js/lib/               Fungsi domain dan utilitas frontend
  js/pages/             Halaman Inertia per area
  js/types/             Kontrak TypeScript bersama
  views/app.blade.php   Shell Inertia
routes/                 Route web dan jadwal console
storage/                Berkas runtime, log, dan lampiran sesuai disk
tests/                 Feature test backend dan test render frontend
```

`vendor/`, `node_modules/`, hasil build, cache, log, dan hasil generasi bukan kode sumber aplikasi. `output/`, `tmp/`, dan compiled view khusus test diabaikan Git. PDF referensi lokal yang disediakan pengguna dipertahankan. Instruksi/skill alat pengembangan di direktori tersembunyi bukan dokumentasi aplikasi.

## Instalasi lokal

Jalankan dari direktori proyek menggunakan PowerShell. Langkah pembuatan `.env` dan application key hanya untuk instalasi baru; pertahankan konfigurasi dan key instalasi yang sudah berisi data.

```powershell
composer install
npm ci --ignore-scripts
if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    php artisan key:generate --no-interaction
}
if (-not (Test-Path database/database.sqlite)) { New-Item database/database.sqlite -ItemType File | Out-Null }
php artisan migrate --no-interaction
php artisan storage:link --no-interaction
npm run build
```

Atur koneksi database, URL aplikasi, zona waktu, dan kebutuhan layanan di `.env`. Contoh konfigurasi memakai SQLite dan `Asia/Jakarta`. Alternatifnya, jalankan `composer run setup` untuk memasang dependensi, menyiapkan konfigurasi SQLite, menjalankan migrasi, dan membangun aset. Perintah ini mempertahankan `.env` yang sudah ada; tinjau koneksinya sebelum menjalankan migrasi. Jalankan `composer run dev` untuk server aplikasi, queue listener, dan Vite.

Setelah memperbarui dependensi frontend, jalankan ulang proses `composer run dev` yang sudah aktif agar memuat paket baru.

`php artisan db:seed --no-interaction` bersifat opsional untuk database pengembangan kosong. `DatabaseSeeder` berisi akun dan data contoh, serta dibatasi ke lingkungan `local`/`testing`. Jangan gunakan akun contoh untuk instalasi operasional. Jangan menjalankan `migrate:fresh` atau seeding contoh terhadap data klinis yang harus dipertahankan.

## Deploy produksi (Docker + Caddy)

Produksi berjalan sebagai tiga container: `app` (PHP-FPM 8.4 Alpine dengan GD/ZIP/Intl), `scheduler` (`schedule:work`), dan `caddy` (TLS otomatis Let's Encrypt, gzip/zstd). `app` dan `scheduler` memakai source yang sama lewat bind mount; data klinis tetap di `database/database.sqlite`.

`public/build` di-ignore Git, jadi aset harus dibangun di host sebelum container dijalankan — tanpa langkah ini halaman terkirim tanpa CSS/JS:

```bash
cp .env.example .env          # lalu isi APP_KEY, APP_URL=https://<domain>, DB_CONNECTION=sqlite, APP_ENV=production, APP_DEBUG=false
npm ci --ignore-scripts && npm run build
docker compose build
docker compose up -d
```

`docker/entrypoint.sh` menjalankan `optimize:clear`, `migrate --force`, `storage:link`, lalu `optimize` sebelum PHP-FPM menerima trafik. Domain diatur di `Caddyfile`; ganti kedua kemunculan `rmeklinikpratama.web.id` bila memakai domain lain. Caddy menyimpan sertifikat di volume `caddy_data`, jadi jangan jalankan `docker compose down -v`.

`bootstrap/app.php` menaruh Caddy di daftar `trustProxies` (`172.16.0.0/12`) agar `X-Forwarded-Proto` dibaca dan `$request->isSecure()` bernilai benar untuk tanda tangan tindakan, cookie `secure`, dan HSTS. `public/hot` harus tidak ada saat produksi, jika tidak Vite mengarahkan aset ke server dev.

Backup terjadwal masih mati: `CLINIC_BACKUP_ENABLED=false` dan `CLINIC_BACKUP_KEY_FILE` belum diisi, sehingga `clinic:security-check --production` melaporkan tiga kontrol backup belum terpenuhi.

### Akun demo

`KemenparPreReleaseSeeder` menolak berjalan di `APP_ENV=production`. Karena config di-cache, variabel `APP_ENV=staging` perintah biasa tidak berpengaruh — cache harus dibersihkan lebih dulu, lalu dipulihkan:

```bash
docker compose exec -T app sh -c 'php artisan config:clear && APP_ENV=staging php artisan db:seed --class="Database\\Seeders\\KemenparPreReleaseSeeder" --force && php artisan optimize'
```

Sandi semua akun demo `Kemenpar2026!`, PIN tanda tangan klinis `123456`. Seeder memakai `firstOrNew`/`updateOrCreate`, jadi aman diulang dan tidak menghapus pasien/kunjungan/terminologi yang sudah ada.

## Database dan integritas

Kelompok tabel utama mencakup identitas/kepesertaan, kunjungan/pengkajian/pemeriksaan, diagnosis/odontogram/tindakan, resep/farmasi, obat/batch/mutasi, persuratan, jadwal praktik, dan audit/versioning catatan klinis.

- Pertahankan urutan dan identitas migration yang sudah diterapkan. Perubahan schema berikutnya dibuat melalui migration baru; migration lama penghapusan modul pensiun tetap diperlukan untuk instalasi dari awal.
- Pencatatan stok mengikuti batch, kedaluwarsa, kondisi, dan jejak mutasi. Nilai stok agregat saja tidak cukup untuk memastikan obat layak diserahkan.
- Snapshot dokumen dan nomor surat dipertahankan sesuai waktu penerbitan. Jangan menghitung ulang dokumen historis dari data pasien yang sudah berubah.
- Pembatasan poli/peran dan pemisahan operator dengan penandatangan klinis harus dijaga pada endpoint maupun UI.
- Empat indeks lookup ditambahkan untuk `diagnosa.pemeriksaan_id`, `tindakan_kunjungan.kunjungan_id`, `resep_obat.resep_id`, dan `farmasi_item.farmasi_id`. Migration melewati indeks yang sudah ada dan rollback hanya menghapus indeks bernama milik migration tersebut.

Audit relasi database tanpa menulis atau menghapus data:

```powershell
php artisan clinic:audit-database --no-interaction
```

Perintah mengembalikan exit code `0` jika relasi yang dideklarasikan tidak bermasalah, atau `1` jika menemukan pelanggaran. Pemeriksaan ini tidak memvalidasi seluruh aturan klinis, konsistensi stok, atau relasi logis yang tidak dideklarasikan sebagai foreign key.

Pada audit lokal 3 November 2026, integritas fisik SQLite berstatus `ok`, tetapi ditemukan **8 pelanggaran foreign key** terhadap tabel `kunjungan`: screening (1), pemeriksaan (1), resep (1), farmasi (1), odontogram_findings (2), dan tindakan_kunjungan (2). Temuan tersebut sudah ditangani pada pemeliharaan berikutnya; lihat status verifikasi lokal di bawah. Untuk temuan baru, gunakan sumber klinis yang sah sebelum mengaitkan ulang atau menghapus baris.

Backup sebelum perubahan indeks dan salinan berkas yang dibersihkan tersimpan di luar repositori pada `C:\Project\LARAVEL\klinik-pratama-cleanup-backup-20261103`. Backup database dapat berisi data klinis; perlakukan sesuai akses data sumber.

## Pemeriksaan kualitas

```powershell
php artisan test --compact
npm run test:frontend
npm run types:check
npm run build
php vendor/bin/pint --dirty --format agent
git diff --check
composer validate --no-check-publish
composer audit
npm audit
```

Test backend menggunakan SQLite in-memory dan compiled view di `storage/framework/testing/views`, terpisah dari server lokal. Test frontend memakai Node.js test runner, Vite, dan React server rendering; test tersebut tidak menjalankan interaksi browser. TypeScript menolak deklarasi lokal dan parameter yang tidak digunakan.

Saat melakukan perubahan, mulai dari test paling sempit yang mencakup perilaku terkait, lalu jalankan pemeriksaan menyeluruh sesuai cakupan. Pertahankan test yang dapat menangkap cacat berbeda; jangan menghapus regresi hanya karena modul sedang tidak aktif.

## Ringkasan pemeliharaan 3 November 2026

- Mengganti README bawaan framework dan mengonsolidasikan Markdown proyek lama menjadi dokumen ini.
- Menghapus entrypoint JavaScript lama, tiga komponen tanpa jalur penggunaan aplikasi, CSS anatomi lama, dua test contoh bawaan, perintah console inspirasi bawaan, dan PDF contoh hasil generasi.
- Membersihkan import/deklarasi yang tidak dipakai dan mengaktifkan pemeriksaan TypeScript agar masalah tersebut terdeteksi kembali.
- Memperbarui fixture dan ekspektasi test render agar mengikuti kontrak antrean, menu, label, dan aksi nonaktif saat ini, termasuk memeriksa bahwa aksi terlindungi tetap tidak dapat digunakan.
- Memperbarui Vite dari 8.3.2 ke 8.3.5 dengan persetujuan pengguna; audit npm setelah pembaruan melaporkan 0 kerentanan.
- Menambahkan audit foreign key yang tidak mengubah data dan empat indeks lookup dengan bukti perubahan query plan dari `SCAN` menjadi `SEARCH ... USING INDEX` pada SQLite lokal.
- Memperbaiki rollback konteks audit kepesertaan: hapus indeks sebelum menghapus kolom pada SQLite, dengan test rollback dan penerapan ulang.

| Pemeriksaan akhir | Hasil |
| --- | --- |
| Backend PHPUnit | 444 test lolos, 8.506 assertion |
| Frontend render | 236 test lolos, tanpa test dilewati |
| TypeScript | Lolos, termasuk deklarasi dan parameter tidak terpakai |
| Build produksi | Lolos pada Vite 8.3.5 |
| Pint dan git diff --check | Lolos |
| Validasi Composer | Valid; peringatan constraint Inertia yang dikunci tepat tetap dipertahankan |
| Audit npm | 0 kerentanan pada hasil registry saat audit |
| Audit keamanan Composer | Belum terverifikasi: Packagist gagal diakses karena reset koneksi/timeout |
| Database lokal | Integritas fisik ok; 8 pelanggaran foreign key yang telah ada tetap dipertahankan |

Build pada pemeriksaan 3 November menampilkan peringatan fitur font fallback optimal yang membutuhkan paket opsional fontaine.

## Status verifikasi lokal 15 November 2026

- Persiapan instalasi pada checkout bersih membuat SQLite dan application key. Pengulangan tidak mengubah `.env`. Folder runtime Laravel disiapkan dari Git tanpa menyimpan log, sesi, atau hasil kompilasi.
- Migrasi penghapusan kasir, katalog alat kesehatan, dan lab internal dapat diterapkan ulang setelah rollback pada SQLite sintetis. Data historis modul yang masih terisi perlu diarsipkan terlebih dahulu sesuai penjagaan migrasi; rollback skema tidak mengembalikan data yang sudah diarsipkan atau status historis kasir.
- Pemeriksaan sebelum commit: 485 tes backend (9.036 assertion), 278 tes render frontend, TypeScript, build produksi, Pint seluruh proyek, cache route, dan audit dependensi lolos. Composer masih memberi peringatan untuk constraint Inertia yang dikunci tepat.
- Uji browser pada aset hasil build memastikan fokus pemeriksaan fisik ketika penyelesaian kedua poli tertahan, koreksi serta penyimpanan temuan PSA, dan pengiriman pengkajian awal Poli Gigi ke antrean dokter. Setelah login dari browser dengan enam peran, 73 tautan menu lolos pemeriksaan akses HTTP sesuai sesi masing-masing.
- Uji penyerahan parsial farmasi mencakup validasi jumlah, penyimpanan draf dan alasan tindak lanjut, satu mutasi stok, serta pembukaan kembali hasil penyerahan tanpa formulir edit atau stok saat ini.
- Delapan pelanggaran FK kunjungan pada SQLite lokal telah dibersihkan setelah pencadangan. Audit FK bersih. Salinan pemulihan SQLite lolos pemeriksaan integritas dan ketiga lampiran terdaftar tersedia di disk privat sumber.
- Edge dengan data sintetis memverifikasi pengkajian gigi hingga masuk antrean dokter, tambah jadwal, penyerahan parsial, finalisasi dan cetak A4 RME kedua poli, serta penanganan fokus pada formulir terkait.
- Uji dua proses server pada SQLite sintetis menghasilkan satu jadwal untuk dua pengiriman bertumpuk, satu versi final per kunjungan saat tanda tangan dikirim dua kali, dan satu mutasi persediaan saat penyerahan resep dikirim dua kali.
- Verifikasi produksi memerlukan lingkungan target untuk mengulang migrasi, uji konkurensi dan pemulihan database beserta lampiran. Pemilik layanan klinis perlu memvalidasi SOP yang berlaku, termasuk cakupan pemeriksaan dan kewenangan bantuan Admin.
