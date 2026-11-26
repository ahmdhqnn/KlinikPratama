<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemeriksaan', function (Blueprint $table): void {
            if (! Schema::hasColumn('pemeriksaan', 'td_sistole_ulang')) {
                $table->unsignedSmallInteger('td_sistole_ulang')->nullable()->after('pemeriksaan_fisik');
            }
            if (! Schema::hasColumn('pemeriksaan', 'td_diastole_ulang')) {
                $table->unsignedSmallInteger('td_diastole_ulang')->nullable()->after('td_sistole_ulang');
            }
            if (! Schema::hasColumn('pemeriksaan', 'nadi_ulang')) {
                $table->unsignedSmallInteger('nadi_ulang')->nullable()->after('td_diastole_ulang');
            }
            if (! Schema::hasColumn('pemeriksaan', 'suhu_ulang')) {
                $table->decimal('suhu_ulang', 4, 1)->nullable()->after('nadi_ulang');
            }
        });

        Schema::table('tindakan', function (Blueprint $table): void {
            if (! Schema::hasColumn('tindakan', 'can_delegate_to_nurse')) {
                $table->boolean('can_delegate_to_nurse')->default(true)->after('kategori');
            }
        });

        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            if (! Schema::hasColumn('tindakan_kunjungan', 'performed_by_nakes_id')) {
                $table->foreignId('performed_by_nakes_id')
                    ->nullable()
                    ->after('performed_by')
                    ->constrained('nakes')
                    ->nullOnDelete();
            }
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_tindakan_kunjungan_delete BEFORE DELETE ON tindakan_kunjungan BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END");
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_tindakan_kunjungan_voided_update BEFORE UPDATE ON tindakan_kunjungan WHEN OLD.voided_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Entri klinis yang dibatalkan dilindungi'); END");
        }

        Schema::table('screening', function (Blueprint $table): void {
            if (! Schema::hasColumn('screening', 'satusehat_observation_ids')) {
                $table->json('satusehat_observation_ids')->nullable()->after('signature_path_snapshot');
            }
        });

        Schema::table('odontogram_findings', function (Blueprint $table): void {
            if (! Schema::hasColumn('odontogram_findings', 'snomed_finding_code')) {
                $table->string('snomed_finding_code', 32)->nullable()->after('finding_code');
            }
            if (! Schema::hasColumn('odontogram_findings', 'snomed_bodysite_code')) {
                $table->string('snomed_bodysite_code', 32)->nullable()->after('snomed_finding_code');
            }
            if (! Schema::hasColumn('odontogram_findings', 'satusehat_observation_id')) {
                $table->string('satusehat_observation_id', 64)->nullable()->after('snomed_bodysite_code');
            }
        });

        Schema::table('rujukan_internal', function (Blueprint $table): void {
            if (! Schema::hasColumn('rujukan_internal', 'jawaban_konsultasi')) {
                $table->text('jawaban_konsultasi')->nullable()->after('catatan');
            }
            if (! Schema::hasColumn('rujukan_internal', 'dijawab_pada')) {
                $table->timestamp('dijawab_pada')->nullable()->after('jawaban_konsultasi');
            }
            if (! Schema::hasColumn('rujukan_internal', 'satusehat_service_request_id')) {
                $table->string('satusehat_service_request_id', 64)->nullable()->after('dijawab_pada');
            }
        });

        Schema::table('surat_medis', function (Blueprint $table): void {
            if (! Schema::hasColumn('surat_medis', 'satusehat_service_request_id')) {
                $table->string('satusehat_service_request_id', 64)->nullable()->after('document_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('surat_medis', function (Blueprint $table): void {
            if (Schema::hasColumn('surat_medis', 'satusehat_service_request_id')) {
                $table->dropColumn('satusehat_service_request_id');
            }
        });

        Schema::table('rujukan_internal', function (Blueprint $table): void {
            $drop = array_filter(['jawaban_konsultasi', 'dijawab_pada', 'satusehat_service_request_id'], fn (string $c): bool => Schema::hasColumn('rujukan_internal', $c));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('odontogram_findings', function (Blueprint $table): void {
            $drop = array_filter(['snomed_finding_code', 'snomed_bodysite_code', 'satusehat_observation_id'], fn (string $c): bool => Schema::hasColumn('odontogram_findings', $c));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('screening', function (Blueprint $table): void {
            if (Schema::hasColumn('screening', 'satusehat_observation_ids')) {
                $table->dropColumn('satusehat_observation_ids');
            }
        });

        if (Schema::hasColumn('tindakan_kunjungan', 'performed_by_nakes_id')) {
            Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('performed_by_nakes_id');
            });
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_tindakan_kunjungan_delete BEFORE DELETE ON tindakan_kunjungan BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END");
                DB::unprepared("CREATE TRIGGER IF NOT EXISTS protect_tindakan_kunjungan_voided_update BEFORE UPDATE ON tindakan_kunjungan WHEN OLD.voided_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Entri klinis yang dibatalkan dilindungi'); END");
            }
        }

        Schema::table('tindakan', function (Blueprint $table): void {
            if (Schema::hasColumn('tindakan', 'can_delegate_to_nurse')) {
                $table->dropColumn('can_delegate_to_nurse');
            }
        });

        Schema::table('pemeriksaan', function (Blueprint $table): void {
            $drop = array_filter(['td_sistole_ulang', 'td_diastole_ulang', 'nadi_ulang', 'suhu_ulang'], fn (string $c): bool => Schema::hasColumn('pemeriksaan', $c));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
