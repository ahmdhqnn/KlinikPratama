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
        $driver = DB::connection()->getDriverName();
        $retained = ['pasien', 'kunjungan', 'screening', 'pemeriksaan', 'resep', 'farmasi', 'farmasi_mandiris', 'surat_medis', 'informed_consent', 'diagnosa', 'resep_obat', 'tindakan_kunjungan', 'farmasi_item', 'odontogram_findings', 'patient_allergies', 'patient_documents'];
        $expectedRetention = array_map(fn (string $name): string => 'protect_'.$name.'_delete', $retained);
        $expectedVoided = array_map(fn (string $name): string => 'protect_'.$name.'_voided_update', ['diagnosa', 'resep_obat', 'tindakan_kunjungan', 'farmasi_item', 'odontogram_findings']);

        $checks = [
            'audit_context_schema' => Schema::hasColumns('clinical_audit_events', ['actor_role', 'user_agent', 'request_id', 'outcome']),
            'password_reset_schema' => Schema::hasColumns('users', ['must_change_password', 'session_version', 'signing_pin_hash', 'must_setup_signing_pin']),
            'single_active_role_constraint' => Schema::hasIndex('users', 'users_active_single_role_unique', 'unique'),
            'unique_staff_account_link' => Schema::hasIndex('nakes', 'nakes_user_id_unique', 'unique'),
            'idle_limit_15_minutes' => config('clinical_security.idle_minutes') === 15,
            'audit_append_only_triggers' => $this->hasTriggers($driver, ['protect_clinical_audit_events_update', 'protect_clinical_audit_events_delete']),
            'visit_retention_trigger' => $this->hasTriggers($driver, ['protect_kunjungan_delete']),
            'clinical_retention_triggers' => $this->hasTriggers($driver, $expectedRetention),
            'voided_entries_immutable' => $this->hasTriggers($driver, $expectedVoided),
            'consent_append_only' => $this->hasTriggers($driver, ['protect_general_consents_update', 'protect_general_consents_delete']),
            'information_release_append_only' => $this->hasTriggers($driver, ['protect_information_release_update', 'protect_information_release_delete']),
            'pharmacy_review_append_only' => $this->hasTriggers($driver, [
                'protect_pharmacy_credentials_update', 'protect_pharmacy_credentials_delete',
                'protect_pharmacy_reviews_update', 'protect_pharmacy_reviews_delete',
                'protect_pharmacy_direct_reviews_update', 'protect_pharmacy_direct_reviews_delete',
            ]),
            'document_proof_append_only' => $this->hasTriggers($driver, [
                'protect_medical_document_verifications_update', 'protect_medical_document_verifications_delete',
                'protect_medical_document_verification_events_update', 'protect_medical_document_verification_events_delete',
            ]),
        ];

        if ($this->option('production')) {
            $checks['debug_disabled'] = config('app.debug') === false;
            $checks['https_configured_tls_still_requires_endpoint_test'] = str_starts_with((string) config('app.url'), 'https://');
            $checks['secure_session_cookie'] = config('session.secure') === true;
            $checks['session_encrypted'] = config('session.encrypt') === true;
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

    /**
     * @param  list<string>  $names
     */
    private function hasTriggers(string $driver, array $names): bool
    {
        $expected = array_values(array_unique($names));
        if ($expected === []) {
            return true;
        }

        $found = match ($driver) {
            'sqlite' => DB::table('sqlite_master')
                ->where('type', 'trigger')
                ->whereIn('name', $expected)
                ->distinct()
                ->count('name'),
            'pgsql' => DB::table('pg_trigger as t')
                ->join('pg_class as c', 'c.oid', '=', 't.tgrelid')
                ->join('pg_namespace as n', 'n.oid', '=', 'c.relnamespace')
                ->where('t.tgisinternal', false)
                ->whereRaw('n.nspname = current_schema()')
                ->whereIn('t.tgname', $expected)
                ->distinct()
                ->count('t.tgname'),
            'mysql', 'mariadb' => DB::table('information_schema.triggers')
                ->where('trigger_schema', DB::connection()->getDatabaseName())
                ->whereIn('trigger_name', $expected)
                ->distinct()
                ->count('trigger_name'),
            default => 0,
        };

        return $found === count($expected);
    }
}
