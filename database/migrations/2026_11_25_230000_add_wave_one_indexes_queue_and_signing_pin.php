<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'signing_pin_hash')) {
                $table->string('signing_pin_hash')->nullable();
            }
            if (! Schema::hasColumn('users', 'signing_pin_set_at')) {
                $table->timestamp('signing_pin_set_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'must_setup_signing_pin')) {
                $table->boolean('must_setup_signing_pin')->default(false);
            }
        });

        Schema::table('pemeriksaan', function (Blueprint $table): void {
            if (! Schema::hasColumn('pemeriksaan', 'signature_path_snapshot')) {
                $table->string('signature_path_snapshot')->nullable();
            }
        });

        Schema::table('screening', function (Blueprint $table): void {
            if (! Schema::hasColumn('screening', 'signature_path_snapshot')) {
                $table->string('signature_path_snapshot')->nullable();
            }
        });

        Schema::table('procedure_signature_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('procedure_signature_sessions', 'failed_attempts')) {
                $table->unsignedSmallInteger('failed_attempts')->default(0);
            }
        });

        Schema::table('kunjungan', function (Blueprint $table): void {
            if (! Schema::hasColumn('kunjungan', 'no_antrian')) {
                $table->unsignedInteger('no_antrian')->nullable();
            }
        });

        $this->backfillQueueNumbers();

        $this->addIndexIfMissing('kunjungan', ['poliklinik_id', 'tanggal', 'no_antrian'], 'kunjungan_poli_tanggal_antrian_unique', unique: true);
        $this->addIndexIfMissing('kunjungan', ['tanggal', 'status', 'poliklinik_id'], 'kunjungan_tanggal_status_poli_index');
        $this->addIndexIfMissing('kunjungan', ['status', 'poliklinik_id'], 'kunjungan_status_poli_index');
        $this->addIndexIfMissing('kunjungan', ['dokter_id', 'tanggal', 'status'], 'kunjungan_dokter_tanggal_status_index');
        $this->addIndexIfMissing('kunjungan', ['pasien_id', 'tanggal', 'id'], 'kunjungan_pasien_tanggal_id_index');
        $this->addIndexIfMissing('kunjungan', ['rujukan_internal_id', 'status'], 'kunjungan_rujukan_status_index');

        $driver = DB::connection()->getDriverName();

        $this->addIndexIfMissing('pasien', ['nik'], 'pasien_nik_lookup_index');
        $this->addIndexIfMissing('pasien', ['deleted_at', 'nama'], 'pasien_deleted_at_nama_index');

        $this->addIndexIfMissing('clinical_audit_events', ['occurred_at', 'id'], 'clinical_audit_events_occurred_at_id_index');
        $this->addIndexIfMissing('clinical_audit_events', ['action', 'occurred_at', 'id'], 'clinical_audit_events_action_occurred_id_index');
        $this->addIndexIfMissing('clinical_audit_events', ['kunjungan_id', 'action', 'id'], 'clinical_audit_events_kunjungan_action_id_index');

        $this->addIndexIfMissing('stok_mutasi', ['obat_id', 'id'], 'stok_mutasi_obat_id_id_index');
        $this->addIndexIfMissing('stok_mutasi', ['referensi_type', 'referensi_id'], 'stok_mutasi_referensi_lookup_index');
        if ($driver !== 'sqlite') {
            $this->addIndexIfMissing('stok_mutasi', ['batch_id', 'id'], 'stok_mutasi_batch_id_id_index');
            $this->addIndexIfMissing('stok_mutasi', ['kunjungan_id'], 'stok_mutasi_kunjungan_id_index');
            $this->addIndexIfMissing('farmasi', ['status', 'dispensed_at'], 'farmasi_status_dispensed_at_index');
            $this->addIndexIfMissing('farmasi', ['dispensed_by', 'dispensed_at'], 'farmasi_dispensed_by_at_index');
        } else {
            $this->addIndexIfMissing('farmasi', ['status'], 'farmasi_status_lookup_index');
        }

        $this->addIndexIfMissing('surat_medis', ['kunjungan_id', 'id'], 'surat_medis_kunjungan_id_id_index');
        $this->addIndexIfMissing('surat_medis', ['jenis', 'tanggal'], 'surat_medis_jenis_tanggal_index');

        $this->addIndexIfMissing('rujukan_internal', ['status', 'ke_poli_id'], 'rujukan_internal_status_ke_poli_index');
        $this->addIndexIfMissing('rujukan_internal', ['kunjungan_id'], 'rujukan_internal_kunjungan_id_index');

        $this->addIndexIfMissing('jadwal_dokter', ['poliklinik_id', 'hari', 'is_active'], 'jadwal_dokter_poli_hari_active_index');
        $this->addIndexIfMissing('jadwal_khusus_dokter', ['poliklinik_id', 'tanggal'], 'jadwal_khusus_poli_tanggal_index');
        $this->addIndexIfMissing('jadwal_khusus_dokter', ['tanggal'], 'jadwal_khusus_tanggal_index');

        $this->addIndexIfMissing('pharmacy_clinical_reviews', ['kunjungan_id', 'id'], 'pharmacy_clinical_reviews_kunjungan_id_index');
        $this->addIndexIfMissing('pharmacy_staff_credentials', ['nakes_id', 'id'], 'pharmacy_staff_credentials_nakes_id_index');
        $this->addIndexIfMissing('medical_document_verification_events', ['verification_id', 'action'], 'med_doc_verif_events_verif_action_index');
        $this->addIndexIfMissing('medical_information_release_events', ['kunjungan_id', 'id'], 'med_info_release_kunjungan_id_index');
        $this->addIndexIfMissing('procedure_signature_sessions', ['procedure_id', 'cancelled_at', 'consent_id'], 'proc_sig_sessions_procedure_pending_index');
    }

    public function down(): void
    {
        $indexes = [
            'kunjungan' => [
                'kunjungan_poli_tanggal_antrian_unique',
                'kunjungan_tanggal_status_poli_index',
                'kunjungan_status_poli_index',
                'kunjungan_dokter_tanggal_status_index',
                'kunjungan_pasien_tanggal_id_index',
                'kunjungan_rujukan_status_index',
            ],
            'pasien' => ['pasien_nik_unique', 'pasien_nik_lookup_index', 'pasien_deleted_at_nama_index'],
            'clinical_audit_events' => [
                'clinical_audit_events_occurred_at_id_index',
                'clinical_audit_events_action_occurred_id_index',
                'clinical_audit_events_kunjungan_action_id_index',
            ],
            'stok_mutasi' => [
                'stok_mutasi_obat_id_id_index',
                'stok_mutasi_batch_id_id_index',
                'stok_mutasi_kunjungan_id_index',
                'stok_mutasi_referensi_lookup_index',
            ],
            'farmasi' => ['farmasi_status_dispensed_at_index', 'farmasi_dispensed_by_at_index'],
            'surat_medis' => ['surat_medis_kunjungan_id_id_index', 'surat_medis_jenis_tanggal_index'],
            'rujukan_internal' => ['rujukan_internal_status_ke_poli_index', 'rujukan_internal_kunjungan_id_index'],
            'jadwal_dokter' => ['jadwal_dokter_poli_hari_active_index'],
            'jadwal_khusus_dokter' => ['jadwal_khusus_poli_tanggal_index', 'jadwal_khusus_tanggal_index'],
            'pharmacy_clinical_reviews' => ['pharmacy_clinical_reviews_kunjungan_id_index'],
            'pharmacy_staff_credentials' => ['pharmacy_staff_credentials_nakes_id_index'],
            'medical_document_verification_events' => ['med_doc_verif_events_verif_action_index'],
            'medical_information_release_events' => ['med_info_release_kunjungan_id_index'],
            'procedure_signature_sessions' => ['proc_sig_sessions_procedure_pending_index'],
        ];

        foreach ($indexes as $table => $names) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $names): void {
                foreach ($names as $name) {
                    if (Schema::hasIndex($table, $name)) {
                        $blueprint->dropIndex($name);
                    }
                }
            });
        }
    }

    private function addIndexIfMissing(string $table, array $columns, string $name, bool $unique = false): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name, $unique): void {
            if ($unique) {
                $blueprint->unique($columns, $name);
            } else {
                $blueprint->index($columns, $name);
            }
        });
    }

    private function hasDuplicatePatientNik(): bool
    {
        return DB::table('pasien')
            ->whereNotNull('nik')
            ->where('nik', '!=', '')
            ->groupBy('nik')
            ->havingRaw('COUNT(*) > 1')
            ->select('nik')
            ->limit(1)
            ->get()
            ->isNotEmpty();
    }

    private function backfillQueueNumbers(): void
    {
        $groups = DB::table('kunjungan')
            ->where('status', '!=', 'batal')
            ->where('kontrol_pending', false)
            ->whereNull('no_antrian')
            ->select('poliklinik_id', 'tanggal')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $dateString = substr((string) $group->tanggal, 0, 10);
            $currentMax = (int) (DB::table('kunjungan')
                ->where('poliklinik_id', $group->poliklinik_id)
                ->where('tanggal', $dateString)
                ->max('no_antrian') ?? 0);

            $visits = DB::table('kunjungan')
                ->where('poliklinik_id', $group->poliklinik_id)
                ->where('tanggal', $dateString)
                ->where('status', '!=', 'batal')
                ->where('kontrol_pending', false)
                ->whereNull('no_antrian')
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($visits as $visitId) {
                $currentMax++;
                DB::table('kunjungan')->where('id', $visitId)->update(['no_antrian' => $currentMax]);
            }

            $scope = 'antrian-'.$group->poliklinik_id.'-'.str_replace('-', '', $dateString);
            DB::table('clinic_document_sequences')->upsert(
                [['scope' => $scope, 'last_number' => $currentMax]],
                ['scope'],
                ['last_number']
            );
        }
    }
};
