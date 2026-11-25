<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CheckClinicalSecurity extends Command
{
    protected $signature = 'clinic:security-check {--production : Wajibkan konfigurasi produksi} {--json}';

    protected $description = 'Periksa kontrol konfigurasi RME tanpa membaca isi pasien atau menampilkan secret';

    public function handle(): int
    {
        $checks = [
            'audit_context_schema' => Schema::hasColumns('clinical_audit_events', ['actor_role', 'user_agent', 'request_id', 'outcome']),
            'password_reset_schema' => Schema::hasColumns('users', ['must_change_password', 'session_version']),
            'single_active_role_constraint' => Schema::hasIndex('users', 'users_active_single_role_unique', 'unique'),
            'unique_staff_account_link' => Schema::hasIndex('nakes', 'nakes_user_id_unique', 'unique'),
            'idle_limit_15_minutes' => config('clinical_security.idle_minutes') === 15,
        ];
        if (DB::connection()->getDriverName() === 'sqlite') {
            $checks['sqlite_audit_append_only_triggers'] = DB::table('sqlite_master')->where('type', 'trigger')->whereIn('name', ['protect_clinical_audit_events_update', 'protect_clinical_audit_events_delete'])->count() === 2;
            $checks['sqlite_visit_retention_trigger'] = DB::table('sqlite_master')->where('type', 'trigger')->where('name', 'protect_kunjungan_delete')->exists();
            $retained = ['pasien', 'kunjungan', 'screening', 'pemeriksaan', 'resep', 'farmasi', 'farmasi_mandiris', 'surat_medis', 'informed_consent', 'diagnosa', 'resep_obat', 'tindakan_kunjungan', 'farmasi_item', 'odontogram_findings'];
            $expected = array_map(fn (string $name): string => 'protect_'.$name.'_delete', $retained);
            $checks['sqlite_clinical_retention_triggers'] = DB::table('sqlite_master')->where('type', 'trigger')->whereIn('name', $expected)->count() === count($expected);
            $voided = array_map(fn (string $name): string => 'protect_'.$name.'_voided_update', ['diagnosa', 'resep_obat', 'tindakan_kunjungan', 'farmasi_item', 'odontogram_findings']);
            $checks['sqlite_voided_entries_immutable'] = DB::table('sqlite_master')->where('type', 'trigger')->whereIn('name', $voided)->count() === count($voided);
            $checks['sqlite_consent_append_only'] = DB::table('sqlite_master')->where('type', 'trigger')->whereIn('name', ['protect_general_consents_update', 'protect_general_consents_delete'])->count() === 2;
            $checks['sqlite_information_release_append_only'] = DB::table('sqlite_master')->where('type', 'trigger')->whereIn('name', ['protect_information_release_update', 'protect_information_release_delete'])->count() === 2;
            $checks['sqlite_pharmacy_review_append_only'] = DB::table('sqlite_master')->where('type', 'trigger')->whereIn('name', [
                'protect_pharmacy_credentials_update', 'protect_pharmacy_credentials_delete',
                'protect_pharmacy_reviews_update', 'protect_pharmacy_reviews_delete',
                'protect_pharmacy_direct_reviews_update', 'protect_pharmacy_direct_reviews_delete',
            ])->count() === 6;
            $checks['sqlite_document_proof_append_only'] = DB::table('sqlite_master')->where('type', 'trigger')->whereIn('name', [
                'protect_medical_document_verifications_update', 'protect_medical_document_verifications_delete',
                'protect_medical_document_verification_events_update', 'protect_medical_document_verification_events_delete',
            ])->count() === 4;
        }
        if ($this->option('production')) {
            $checks['debug_disabled'] = config('app.debug') === false;
            $checks['https_configured_tls_still_requires_endpoint_test'] = str_starts_with((string) config('app.url'), 'https://');
            $checks['secure_session_cookie'] = config('session.secure') === true;
            $checks['backup_scheduled'] = config('clinical_backup.enabled') === true;
            $checks['offsite_operator_confirmation'] = config('clinical_backup.offsite_confirmed') === true;
            try {
                $status = json_decode(Storage::disk(config('clinical_backup.disk'))->get('last-success.json'), true, flags: JSON_THROW_ON_ERROR);
                $checks['verified_backup_within_26_hours'] = empty($status['local_only']) && Carbon::parse($status['at'])->gte(now()->subHours(config('clinical_backup.maximum_age_hours')));
            } catch (\Throwable) {
                $checks['verified_backup_within_26_hours'] = false;
            }
        }
        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Kontrol', 'Hasil'], collect($checks)->map(fn ($passed, $name) => [$name, $passed ? 'LULUS' : 'BELUM TERPENUHI'])->all());
            $this->line('Pemeriksaan konfigurasi ini tidak menggantikan uji TLS, restore operasional, atau pengesahan SOP.');
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
