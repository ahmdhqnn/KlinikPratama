<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening', function (Blueprint $table): void {
            if (! Schema::hasColumn('screening', 'status_kehamilan')) {
                $table->string('status_kehamilan', 24)->nullable()->after('skrining_gizi');
            }
            if (! Schema::hasColumn('screening', 'usia_kehamilan_minggu')) {
                $table->unsignedTinyInteger('usia_kehamilan_minggu')->nullable()->after('status_kehamilan');
            }
            if (! Schema::hasColumn('screening', 'hpht')) {
                $table->date('hpht')->nullable()->after('usia_kehamilan_minggu');
            }
            if (! Schema::hasColumn('screening', 'status_menyusui')) {
                $table->string('status_menyusui', 24)->nullable()->after('hpht');
            }
            if (! Schema::hasColumn('screening', 'gcs_eye')) {
                $table->unsignedTinyInteger('gcs_eye')->nullable()->after('kesadaran');
            }
            if (! Schema::hasColumn('screening', 'gcs_verbal')) {
                $table->unsignedTinyInteger('gcs_verbal')->nullable()->after('gcs_eye');
            }
            if (! Schema::hasColumn('screening', 'gcs_motor')) {
                $table->unsignedTinyInteger('gcs_motor')->nullable()->after('gcs_verbal');
            }
        });

        if (! Schema::hasTable('patient_allergies')) {
            Schema::create('patient_allergies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pasien_id')->constrained('pasien')->restrictOnDelete();
                $table->foreignId('kunjungan_id')->nullable()->constrained('kunjungan')->nullOnDelete();
                $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('category', 24)->default('medication');
                $table->string('allergen_code_system', 64)->nullable();
                $table->string('allergen_code', 64)->nullable();
                $table->string('allergen_display', 255);
                $table->string('criticality', 24)->nullable();
                $table->text('reaction_text')->nullable();
                $table->string('reaction_snomed', 32)->nullable();
                $table->string('clinical_status', 24)->default('active');
                $table->string('verification_status', 24)->default('confirmed');
                $table->string('satusehat_allergy_id', 64)->nullable();
                $table->timestamps();

                $table->index(['pasien_id', 'clinical_status'], 'patient_allergies_pasien_status_idx');
            });

            $driver = Schema::getConnection()->getDriverName();
            DB::unprepared(match ($driver) {
                'sqlite' => "CREATE TRIGGER IF NOT EXISTS protect_patient_allergies_delete BEFORE DELETE ON patient_allergies BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END",
                'mysql', 'mariadb' => "CREATE TRIGGER protect_patient_allergies_delete BEFORE DELETE ON patient_allergies FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Catatan klinis dilindungi'",
                'pgsql' => 'CREATE TRIGGER protect_patient_allergies_delete BEFORE DELETE ON patient_allergies FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()',
            });
        }

        Schema::table('obat', function (Blueprint $table): void {
            if (! Schema::hasColumn('obat', 'is_high_alert')) {
                $table->boolean('is_high_alert')->default(false)->after('boleh_diberikan_langsung');
            }
            if (! Schema::hasColumn('obat', 'is_lasa')) {
                $table->boolean('is_lasa')->default(false)->after('is_high_alert');
            }
            if (! Schema::hasColumn('obat', 'rute_default')) {
                $table->string('rute_default', 32)->nullable()->after('is_lasa');
            }
            if (! Schema::hasColumn('obat', 'kode_kfa_poa')) {
                $table->string('kode_kfa_poa', 32)->nullable()->after('kode_kfa');
            }
            if (! Schema::hasColumn('obat', 'kode_kfa_pov')) {
                $table->string('kode_kfa_pov', 32)->nullable()->after('kode_kfa_poa');
            }
            if (! Schema::hasColumn('obat', 'nama_kfa')) {
                $table->string('nama_kfa', 255)->nullable()->after('kode_kfa_pov');
            }
            if (! Schema::hasColumn('obat', 'kode_bentuk_sediaan')) {
                $table->string('kode_bentuk_sediaan', 32)->nullable()->after('nama_kfa');
            }
            if (! Schema::hasColumn('obat', 'nama_bentuk_sediaan')) {
                $table->string('nama_bentuk_sediaan', 100)->nullable()->after('kode_bentuk_sediaan');
            }
            if (! Schema::hasColumn('obat', 'satusehat_medication_id')) {
                $table->string('satusehat_medication_id', 64)->nullable()->after('nama_bentuk_sediaan');
            }
        });

        Schema::table('resep_obat', function (Blueprint $table): void {
            if (! Schema::hasColumn('resep_obat', 'allergy_override_reason')) {
                $table->text('allergy_override_reason')->nullable()->after('catatan');
            }
            if (! Schema::hasColumn('resep_obat', 'allergy_matched_term')) {
                $table->string('allergy_matched_term', 255)->nullable()->after('allergy_override_reason');
            }
            if (! Schema::hasColumn('resep_obat', 'frekuensi')) {
                $table->unsignedSmallInteger('frekuensi')->nullable()->after('aturan_pakai');
            }
            if (! Schema::hasColumn('resep_obat', 'periode')) {
                $table->unsignedSmallInteger('periode')->nullable()->after('frekuensi');
            }
            if (! Schema::hasColumn('resep_obat', 'periode_unit')) {
                $table->string('periode_unit', 8)->nullable()->after('periode');
            }
            if (! Schema::hasColumn('resep_obat', 'dosis_sekali_pakai')) {
                $table->decimal('dosis_sekali_pakai', 8, 2)->nullable()->after('periode_unit');
            }
            if (! Schema::hasColumn('resep_obat', 'dosis_unit')) {
                $table->string('dosis_unit', 32)->nullable()->after('dosis_sekali_pakai');
            }
            if (! Schema::hasColumn('resep_obat', 'rute_pemberian')) {
                $table->string('rute_pemberian', 32)->nullable()->after('dosis_unit');
            }
            if (! Schema::hasColumn('resep_obat', 'waktu_pakai')) {
                $table->string('waktu_pakai', 32)->nullable()->after('rute_pemberian');
            }
            if (! Schema::hasColumn('resep_obat', 'durasi_hari')) {
                $table->unsignedSmallInteger('durasi_hari')->nullable()->after('waktu_pakai');
            }
            if (! Schema::hasColumn('resep_obat', 'satusehat_medication_request_id')) {
                $table->string('satusehat_medication_request_id', 64)->nullable()->after('durasi_hari');
            }
        });

        Schema::table('klinik_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('klinik_settings', 'kode_fasyankes')) {
                $table->string('kode_fasyankes', 32)->nullable()->after('nama_klinik');
            }
            if (! Schema::hasColumn('klinik_settings', 'izin_operasional')) {
                $table->string('izin_operasional', 100)->nullable()->after('kode_fasyankes');
            }
            if (! Schema::hasColumn('klinik_settings', 'satusehat_org_id')) {
                $table->string('satusehat_org_id', 64)->nullable()->after('izin_operasional');
            }
            if (! Schema::hasColumn('klinik_settings', 'kode_provinsi')) {
                $table->string('kode_provinsi', 2)->nullable()->after('alamat');
            }
            if (! Schema::hasColumn('klinik_settings', 'kode_kabupaten')) {
                $table->string('kode_kabupaten', 4)->nullable()->after('kode_provinsi');
            }
            if (! Schema::hasColumn('klinik_settings', 'kode_kecamatan')) {
                $table->string('kode_kecamatan', 6)->nullable()->after('kode_kabupaten');
            }
            if (! Schema::hasColumn('klinik_settings', 'kode_kelurahan')) {
                $table->string('kode_kelurahan', 10)->nullable()->after('kode_kecamatan');
            }
            if (! Schema::hasColumn('klinik_settings', 'kode_pos')) {
                $table->string('kode_pos', 5)->nullable()->after('kode_kelurahan');
            }
        });

        Schema::table('poliklinik', function (Blueprint $table): void {
            if (! Schema::hasColumn('poliklinik', 'satusehat_org_id')) {
                $table->string('satusehat_org_id', 64)->nullable()->after('jenis');
            }
        });

        Schema::table('ruang_poli', function (Blueprint $table): void {
            if (! Schema::hasColumn('ruang_poli', 'kode')) {
                $table->string('kode', 32)->nullable()->after('poliklinik_id');
            }
            if (! Schema::hasColumn('ruang_poli', 'satusehat_location_id')) {
                $table->string('satusehat_location_id', 64)->nullable()->after('nama');
            }
            if (! Schema::hasColumn('ruang_poli', 'physical_type_code')) {
                $table->string('physical_type_code', 16)->default('ro')->after('satusehat_location_id');
            }
        });

        Schema::table('nakes', function (Blueprint $table): void {
            if (! Schema::hasColumn('nakes', 'nik')) {
                $table->string('nik', 16)->nullable()->after('nama');
                $table->index('nik', 'nakes_nik_lookup_index');
            }
            if (! Schema::hasColumn('nakes', 'ihs_number')) {
                $table->string('ihs_number', 32)->nullable()->after('nik');
            }
            if (! Schema::hasColumn('nakes', 'tanggal_lahir')) {
                $table->date('tanggal_lahir')->nullable()->after('jenis_kelamin');
            }
            if (! Schema::hasColumn('nakes', 'sip_berlaku_sampai')) {
                $table->date('sip_berlaku_sampai')->nullable()->after('no_sip');
            }
            if (! Schema::hasColumn('nakes', 'str_berlaku_sampai')) {
                $table->date('str_berlaku_sampai')->nullable()->after('no_str');
            }
        });

        Schema::table('pasien', function (Blueprint $table): void {
            if (! Schema::hasColumn('pasien', 'ihs_number')) {
                $table->string('ihs_number', 32)->nullable()->after('nik');
            }
            if (! Schema::hasColumn('pasien', 'kewarganegaraan')) {
                $table->string('kewarganegaraan', 8)->default('WNI')->after('agama');
            }
            if (! Schema::hasColumn('pasien', 'no_paspor')) {
                $table->string('no_paspor', 32)->nullable()->after('kewarganegaraan');
            }
            if (! Schema::hasColumn('pasien', 'alasan_tanpa_nik')) {
                $table->string('alasan_tanpa_nik', 255)->nullable()->after('no_paspor');
            }
            if (! Schema::hasColumn('pasien', 'pendidikan')) {
                $table->string('pendidikan', 50)->nullable()->after('pekerjaan');
            }
            if (! Schema::hasColumn('pasien', 'kode_provinsi')) {
                $table->string('kode_provinsi', 2)->nullable()->after('kecamatan');
            }
            if (! Schema::hasColumn('pasien', 'kode_kabupaten')) {
                $table->string('kode_kabupaten', 4)->nullable()->after('kode_provinsi');
            }
            if (! Schema::hasColumn('pasien', 'kode_kecamatan')) {
                $table->string('kode_kecamatan', 6)->nullable()->after('kode_kabupaten');
            }
            if (! Schema::hasColumn('pasien', 'kode_kelurahan')) {
                $table->string('kode_kelurahan', 10)->nullable()->after('kode_kecamatan');
            }
            if (! Schema::hasColumn('pasien', 'kode_pos')) {
                $table->string('kode_pos', 5)->nullable()->after('kode_kelurahan');
            }
        });

        Schema::table('general_consents', function (Blueprint $table): void {
            if (! Schema::hasColumn('general_consents', 'satusehat_sharing_decision')) {
                $table->string('satusehat_sharing_decision', 16)->default('undocumented');
            }
            if (! Schema::hasColumn('general_consents', 'satusehat_consent_id')) {
                $table->string('satusehat_consent_id', 64)->nullable();
            }
        });

        Schema::table('kunjungan', function (Blueprint $table): void {
            if (! Schema::hasColumn('kunjungan', 'ruang_poli_id')) {
                $table->foreignId('ruang_poli_id')->nullable()->after('poliklinik_id')->constrained('ruang_poli')->nullOnDelete();
            }
            if (! Schema::hasColumn('kunjungan', 'satusehat_encounter_id')) {
                $table->string('satusehat_encounter_id', 64)->nullable()->after('no_antrian');
            }
            if (! Schema::hasColumn('kunjungan', 'arrived_at')) {
                $table->timestamp('arrived_at')->nullable()->after('tanggal');
            }
            if (! Schema::hasColumn('kunjungan', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('tindakan_started_at');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_kunjungan_delete BEFORE DELETE ON kunjungan BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END");
        }

        Schema::table('pemeriksaan', function (Blueprint $table): void {
            if (! Schema::hasColumn('pemeriksaan', 'discharge_disposition_code')) {
                $table->string('discharge_disposition_code', 32)->nullable()->after('disposisi');
            }
            if (! Schema::hasColumn('pemeriksaan', 'kondisi_akhir_snomed')) {
                $table->string('kondisi_akhir_snomed', 32)->nullable()->after('kondisi_akhir');
            }
            if (! Schema::hasColumn('pemeriksaan', 'prognosis_code')) {
                $table->string('prognosis_code', 32)->nullable()->after('diagnosis_banding');
            }
            if (! Schema::hasColumn('pemeriksaan', 'satusehat_clinical_impression_id')) {
                $table->string('satusehat_clinical_impression_id', 64)->nullable();
            }
            if (! Schema::hasColumn('pemeriksaan', 'satusehat_composition_id')) {
                $table->string('satusehat_composition_id', 64)->nullable();
            }
        });

        Schema::table('diagnosa', function (Blueprint $table): void {
            if (! Schema::hasColumn('diagnosa', 'clinical_status')) {
                $table->string('clinical_status', 24)->default('active')->after('jenis');
            }
            if (! Schema::hasColumn('diagnosa', 'verification_status')) {
                $table->string('verification_status', 24)->default('confirmed')->after('clinical_status');
            }
            if (! Schema::hasColumn('diagnosa', 'onset_at')) {
                $table->timestamp('onset_at')->nullable()->after('verification_status');
            }
            if (! Schema::hasColumn('diagnosa', 'satusehat_condition_id')) {
                $table->string('satusehat_condition_id', 64)->nullable()->after('onset_at');
            }
        });

        Schema::table('tindakan', function (Blueprint $table): void {
            if (! Schema::hasColumn('tindakan', 'snomed_code')) {
                $table->string('snomed_code', 32)->nullable()->after('nama');
            }
            if (! Schema::hasColumn('tindakan', 'snomed_display')) {
                $table->string('snomed_display', 255)->nullable()->after('snomed_code');
            }
        });

        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            if (! Schema::hasColumn('tindakan_kunjungan', 'snomed_code')) {
                $table->string('snomed_code', 32)->nullable()->after('kode_icd9cm');
            }
            if (! Schema::hasColumn('tindakan_kunjungan', 'snomed_display')) {
                $table->string('snomed_display', 255)->nullable()->after('snomed_code');
            }
            if (! Schema::hasColumn('tindakan_kunjungan', 'surfaces')) {
                $table->text('surfaces')->nullable()->after('tooth_fdi');
            }
            if (! Schema::hasColumn('tindakan_kunjungan', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('doctor_started_at');
            }
            if (! Schema::hasColumn('tindakan_kunjungan', 'satusehat_procedure_id')) {
                $table->string('satusehat_procedure_id', 64)->nullable();
            }
        });

        Schema::table('farmasi', function (Blueprint $table): void {
            if (! Schema::hasColumn('farmasi', 'prepared_at')) {
                $table->timestamp('prepared_at')->nullable()->after('status');
            }
        });

        Schema::table('farmasi_item', function (Blueprint $table): void {
            if (! Schema::hasColumn('farmasi_item', 'days_supply')) {
                $table->unsignedSmallInteger('days_supply')->nullable()->after('aturan_pakai');
            }
            if (! Schema::hasColumn('farmasi_item', 'satusehat_medication_dispense_id')) {
                $table->string('satusehat_medication_dispense_id', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_allergies');
    }
};
