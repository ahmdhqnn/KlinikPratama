# Pemetaan data dan kesiapan interoperabilitas

Baseline: 18 November 2026. Status: **pemetaan awal kelompok data dan gap**, belum kamus lengkap setiap variabel KMK atau sertifikasi kesesuaian FHIR. Rujukan: [audit R02/R06–R08](rme-hardening-audit-2026-11-18.md). Pekerjaan melengkapi setiap field, format, value set, cardinality dan aturan kondisional dilacak T27; tidak ditandai selesai pada kajian ini.

Tabel memisahkan elemen yang ditemukan di aplikasi dari **target desain** interoperabilitas. Nama resource bukan bukti resource itu telah dibuat/dikirim. Tidak semua data referensi wajib menjadi input baru atau diwajibkan pada semua kunjungan; kelengkapan mengacu pelayanan nyata dan SOP. KMK rawat jalan memuat identitas, persetujuan umum dan asesmen; pilihan variable/apotek lain perlu keputusan applicability tertulis.

## 1. Pemetaan awal berdasarkan implementasi

| ID | Kelompok | Pemilik data saat ini | Penilaian dan pekerjaan | Target kontrak setelah diverifikasi |
| --- | --- | --- | --- | --- |
| M01 | Nomor RM lokal | `Pasien.no_rm`, `PatientRegistrationService` | Ada; pertahankan identitas internal dan riwayat merge, bukan menggantinya dengan NIK/IHS | Patient.identifier dengan namespace faskes |
| M02 | NIK/NIP | `Pasien`, `Kepesertaan`, registrasi dan verifikasi membership | Validasi NIK 16 digit pada jalur terkait sudah ada. Audit semua jalur impor/update, duplicate withTrashed, koreksi identitas, masking dan enkripsi/index. NIP bukan identitas MPI | Patient identifier NIK bila sah; NIP tetap identifier administratif lokal |
| M03 | Nama/tempat/tanggal lahir/jenis kelamin | `Pasien` | Ada; periksa normalisasi string/tanggal, gender lokal→value set, umur dihitung pada tanggal layanan bukan hari cetak | Patient.name/birthDate/gender |
| M04 | Alamat/wilayah/telepon | `Pasien.alamat/rt/rw/kelurahan/kecamatan/telepon` | Ada sebagian; belum membuktikan kode provinsi/kabupaten/negara/domisiI terstruktur. Mapping kode wilayah/telepon ditelaah tanpa memaksa data fiktif | Patient.address/telecom sesuai profil |
| M05 | Agama, pekerjaan, status perkawinan, pendidikan, kewarganegaraan | `Pasien.agama/pekerjaan/status_perkawinan` | Tiga pertama ada. Pendidikan/kewarganegaraan tidak ditemukan pada model pasien yang diperiksa; lakukan applicability per variabel dan gap skema/value set | Patient extensions/value set hanya bila use case berlaku |
| M06 | Ibu/wali/kontak darurat/wakil | `Pasien.nama_ibu/nama_wali/telepon_wali`; `InformedConsent.hubungan_pasien` | Belum struktur lengkap hubungan/otoritas wakil dan snapshot identitas pemberi persetujuan | Patient.contact/RelatedPerson sesuai use case |
| M07 | Pasien tanpa NIK | Registrasi manual dan kepesertaan lokal | Mekanisme lokal tersedia sebagian; exception kategori, deduplikasi dan mapping MPI belum ada. Tidak mengisi NIK dummy | MPI lookup sesuai mekanisme resmi; unresolved ditampilkan |
| M08 | ID nasional pasien | Belum ditemukan IHS pada model aktif | Tambah mapping terpisah, status cocok/tidak cocok, sumber verifikasi dan waktu; merge tidak menimpa identitas historis | Patient.id/IHS dari MPI, bukan hasil generate lokal |
| M09 | Faskes/poli/ruang | `KlinikSetting`, `Poliklinik`, `RuangPoli`, `ClinicDocumentProfile` | Ada lokal; identitas resmi, kode faskes, Organization/Location ID dan lokasi efektif belum dibuktikan | Organization/Location identifiers |
| M10 | Tenaga medis dan kesehatan | `User`, `Nakes`, jadwal/resolver profesi | SIP/STR dan profil ada; verifikasi status profesi, penugasan, masa berlaku bila relevan, Practitioner IHS dan otoritas apoteker/TTK | Practitioner/PractitionerRole mapping sesuai dukungan |
| M11 | Hak layanan/pembiayaan | `Kepesertaan`, `hak_layanan_snapshot`, `verified_at/by` | Ada konteks internal. Jangan menambah BPJS/kasir/billing hanya untuk menyamai contoh metadata | Mapping administratif use case applicable; NA disertai alasan |
| M12 | General consent | `GeneralConsent`, `GeneralConsentService`, `Kunjungan.general_consent_id` | Record append-only berversi, keputusan/wakil/bukti/hash serta gate pengiriman pengkajian pada kunjungan baru diterapkan 19 November; legacy tetap unknown. Tidak menjadi izin pelepasan universal. Regresi dan status T15 pada tracker | Penyimpanan lokal dahulu; resource Consent hanya jika kontrak use case mendukung |
| M13 | Identitas/tanggal/status kunjungan | `Kunjungan.no_kunjungan/pasien_id/poliklinik_id/dokter_id/tanggal/status` | Ada; tanggal layanan bukan timestamp period penuh. Lengkapi mapping onset/end pelayanan, pembatalan, histori transisi dan timezone | Encounter subject/identifier/participant/status/period/location |
| M14 | Keluhan/anamnesis/RPS | `Screening.keluhan`, `Pemeriksaan.anamnesis/riwayat_penyakit_sekarang` | Ada per kunjungan; bedakan catatan perawat dan dokter, snapshot dan current confirmation | Condition/ClinicalImpression/Composition sesuai playbook, bukan mapping tunggal otomatis |
| M15 | Penyakit dahulu/keluarga/sosial | `Screening`, `Pemeriksaan`, `PatientClinicalHistory` | Ada sebagian; sumber kunjungan lalu masuk defaults, perlu provenance dan verifikasi; bagian sosial mengikuti form sebenarnya | FamilyMemberHistory/ClinicalImpression/Observation menurut elemen |
| M16 | Alergi dan obat yang sedang digunakan | `PatientAllergyHistory`, `Screening`, `Pemeriksaan`, master pasien | Ada narasi/status/reaksi; perlu provenance, konfirmasi kini, struktur zat/jenis/reaksi bila diketahui. Unknown tidak sama dengan no known allergy | AllergyIntolerance/MedicationStatement sesuai data nyata |
| M17 | Tekanan darah, nadi, suhu, respirasi, SpO2 | `Screening` dan waktu pencatatan TTV | Ada nilai per kunjungan; validasi rentang, unit, pengukuran tidak dapat dilakukan, measurer dan waktu; jangan ambil TTV lama | Observation, unit UCUM dan kode sesuai profil |
| M18 | Berat/tinggi/lingkar perut/gizi | `Screening` | Ada; berat/waktu/sumber belum masuk proyeksi penyerahan farmasi yang diperiksa. Nilai kosong tidak diganti nol | Observation dan proyeksi farmasi minimum |
| M19 | Triase/kesadaran/nyeri/risiko | `Screening`, `GeneralAssessment`, `DentalAssessment` | Sudah tersedia; pertahankan keputusan dan ketidakpastian; aturan klinis baru memerlukan PJ. Tidak membangun IGD hanya karena ada red flag | Observation/RiskAssessment sesuai use case applicable |
| M20 | Pemeriksaan fisik dan sistem tubuh | `Pemeriksaan.pemeriksaan_fisik/pemeriksaan_fisik_terstruktur/body_map`, review of systems | Ada narasi dan body map; bedakan tidak diperiksa/normal/abnormal; pakai versi sah dan atribusi pemeriksa | Observation/Composition/ClinicalImpression sesuai elemen |
| M21 | Dental/odontogram | `OdontogramFinding`, FDI, `Pemeriksaan.pemeriksaan_ekstraoral/ringkasan_odontogram` | Sudah ada temuan dan versi; diagnosis/prosedur per gigi dipertahankan, jangan menjadikan temuan sebagai diagnosis otomatis | Body site/Observation/Condition/Procedure sesuai profil dental yang diverifikasi |
| M22 | Diagnosis | `Diagnosa.kode_icd10/code_system/code_release/coding_status`, `ClinicalTerminology`, `Icd10` | Entri baru normal memakai ICD-10 WHO aktif dan release; canonical display/actor/waktu diverifikasi server. Manual/katalog kosong unresolved, final hanya lewat darurat atau downtime beralasan; arsip legacy tidak diklaim verified. Rekonsiliasi pascafinal mengikuti T26 | Condition coding ICD-10 WHO supported release; unresolved/legacy tidak otomatis siap integrasi |
| M23 | Tindakan/rencana/pelaksanaan | `TindakanKunjungan`, `Tindakan`, `ClinicalTerminology` | Review coding draf eksplisit: verified ICD-9-CM dengan snapshot display/rilis, not_applicable beralasan, atau unresolved yang menahan final normal. Legacy tidak diverifikasi otomatis; scope/gigi/BHP tetap. Rilis didukung SATUSEHAT 2010 harus diverifikasi pada readiness | ServiceRequest untuk rencana dan Procedure untuk kejadian nyata sesuai kontrak |
| M24 | Informed consent | `InformedConsent`, `ProcedureConsentService` | Workflow terikat tindakan/rencana/gigi, penjelasan/wakil/saksi, bukti private/hash, keputusan append-only, server timestamp dan gate dokter/perawat tersedia. T16 menunggu review klinis/UI | Arsip consent lokal dahulu, pemetaan FHIR sesuai dukungan |
| M25 | Katalog obat | `Obat.kode_kfa/nama/kandungan/satuan`, jenis obat/BHP | KFA masih string opsional. Butuh produk aktual/template/zat aktif sesuai konteks, sumber/status mapping dan snapshot | Medication dengan KFA sesuai resource/use case; bukan semua item harus kode tipe sama |
| M26 | Peresepan | `Resep`, `ResepObat` nama/jumlah/satuan/aturan_pakai/catatan | Ada e-resep dan resep luar; dosis/frekuensi/rute/durasi/kekuatan belum kontrak terstruktur lengkap. Perlu uji representasi dan snapshot | MedicationRequest; bentuk/strength/rute/dosis/duration UCUM sesuai data terverifikasi |
| M27 | Review dan penyerahan farmasi | `Farmasi`, `FarmasiItem`, `dispensed_at/by`, `FarmasiResepLuar` | Final sudah ada, review terstruktur/otoritas apoteker dan komunikasi belum lengkap; jangan menyamakan order dengan actual dispense | QuestionnaireResponse bila profil meminta, MedicationDispense untuk jumlah aktual |
| M28 | Obat langsung/retur/stok | `FarmasiMandiri`, `PersediaanRecorder`, batch/mutasi | Alur sudah ada; mapping indikasi/kategori obat langsung dan retur klinis perlu keputusan apoteker. Stok fisik berbeda dari kuantitas resep | MedicationDispense sesuai use case sah; jejak inventaris lokal bukan dikirim sebagai resep baru |
| M29 | Surat pengantar/hasil eksternal | `MedicalLetterData` jenis laboratorium/rontgen; `Kunjungan.lampiran` | Permintaan dan scan ada; belum hasil lab terstruktur/LOINC. Tambah sumber fasilitas, tanggal, tipe, hash/provenance; LOINC/value/unit hanya bila diketahui | ServiceRequest/DiagnosticReport/Observation/DocumentReference sesuai profil dan bukti |
| M30 | Edukasi/terapi nonfarmakologis/kontrol/kondisi akhir | `Pemeriksaan`, `VisitSchedulingService` | Ada; hapus narasi negatif otomatis, persist pilihan eksplisit dan versi; kode disposisi/rujukan tidak ditebak dari nama menu | CarePlan/Procedure/ServiceRequest/Encounter sesuai use case |
| M31 | Resume, surat, rujukan | `SuratMedis.document_data`, `RujukanInternal`, `MedicalLetterData`, persuratan | Jenis ringkasan dan nomor otomatis ada; perlu versi efektif, snapshot faskes lengkap, status pengganti/QR dan register pelepasan | Composition dan referensi terkait sesuai kontrak; dokumen cetak lokal tetap arsip |
| M32 | Author/waktu/integritas | `signed_at`, `ClinicalNoteVersion`, `ScreeningVersion`, audit | Ada sebagian; role snapshot, timestamp event/entry, correction approval dan storage append-only belum seragam | Provenance/AuditEvent hanya bila use case mendukung; ledger lokal tetap wajib berfungsi |

## 2. Struktur kamus lanjutan yang wajib dilengkapi T27

Setiap variabel sumber harus mempunyai baris atomik; jangan menutup T27 hanya dengan 32 kelompok di atas.

```text
variable_id dan judul regulasi/playbook + bagian/halaman/versi:
nama variabel sumber:
applicable / conditional / tidak applicable + alasan + reviewer:
model.table.column atau JSON path dan tipe database:
definisi klinis/administratif, format, unit dan timezone:
value set/code system/release dan sumber katalog:
required lokal, required saat final, required untuk outbound:
conditional rule dan keadaan absent/unknown/not-performed:
asal data/pencatat/verifier/waktu dan bukti:
validator request, constraint DB dan aturan UI:
izin role/objek/view/print/export:
retensi/enkripsi/masking/backup:
FHIR resource.path, cardinality, namespace dan dependency:
legacy availability/provenance, aturan backfill atau unresolved:
test case dan hasil verifikasi:
task penutup dan status:
```

Untuk tiap baris M01–M32: owner mengurai field, memeriksa kesesuaian nilai nyata yang diizinkan, lalu melengkapi kamus. Jangan membuat field data baru sebelum applicability ditetapkan. Contoh yang perlu klasifikasi: bayi baru lahir, IGD/rawat inap, pemeriksaan spesialistik di luar umum/gigi, skema penjamin dan pemeriksaan internal yang telah dipensiunkan. `Tidak applicable` memerlukan alasan tertulis dan bukan alasan menghapus data legacy.

## 3. Readiness per kunjungan — target validator offline

| Pemeriksaan | Kondisi lulus | Bila gagal |
| --- | --- | --- |
| Identitas | Identitas lokal sah, tidak konflik; IHS terverifikasi saat outbound memerlukannya | `identity_unresolved`; petugas memperbaiki lewat jalur sah, bukan dummy NIK |
| Profesi/faskes | Organization/Location/Practitioner mapping sah dan cocok penugasan | `provider_unresolved`; tidak substitusi akun admin |
| Versi final | Tanda tangan aplikasi, chain valid, versi efektif dan author terbukti | `note_unverified`; tidak ekspor payload yang tidak dipercaya |
| Kelengkapan klinis | Elemen applicable, waktu/unit, diagnosis dan prosedur standar terverifikasi | `clinical_incomplete`/`coding_unresolved`; narasi asli tetap disimpan |
| Obat | Identitas produk, unit/dosis dan order/actual dispense sesuai data nyata | `medication_unmapped`; tidak menebak KFA dari nama mirip |
| Lampiran/hasil | File ada/hash sesuai, sumber jelas; structured result punya kode/unit yang sah bila applicable | `attachment_unverified`; scan tidak diperlakukan sebagai hasil numerik |
| Koreksi | Versi/koreksi dan dokumen pengganti terhubung, downstream tidak ketinggalan | `amendment_pending`; tandai butuh rekonsiliasi |
| Kebijakan data | Scope yang sah, tidak memasukkan metadata audit rahasia atau consent opsional yang tidak relevan | `disclosure_review`; evaluasi dasar pemrosesan, bukan tombol setuju universal |

Validator menyimpan hasil dan versi aturan yang dipakai, bukan mengubah fakta klinis. Readiness hijau adalah prasyarat teknis pengiriman sesuai kontrak; belum berarti resource telah diterima SATUSEHAT. Pada fase integrasi, tambahkan state queued/sent/accepted/rejected/reconciled per resource dengan retry dan reconciliation yang jelas.

## 4. Profil data legacy yang direncanakan

Inventaris **agregat tanpa raw data pasien dalam dokumen/log**: pasien tanpa NIK/identitas alternatif, duplikasi identitas, mapping kode/release, obat tanpa KFA, catatan selesai tanpa signed version, chain tidak valid, consent belum terdokumentasi, metadata waktu/pelaku kosong, lampiran hilang/hash belum ada dan FK orphan.

Pengisian data lama hanya berdasarkan dokumen sumber yang sah dan reviewer berwenang. `migrated_at` berarti waktu migrasi, bukan waktu pelayanan/tanda tangan lama. Nilai baru dipisahkan dari fakta historis. Temuan unresolved tetap terbuka pada tracker dan membatasi kesiapan integrasi; tidak menghalangi membaca arsip sah oleh petugas yang berwenang.
