<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patient_documents')) {
            Schema::create('patient_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('pasien_id')->constrained('pasien')->restrictOnDelete();
                $table->foreignId('kunjungan_id')->nullable()->constrained('kunjungan')->nullOnDelete();
                $table->foreignId('poliklinik_id')->nullable()->constrained('poliklinik')->nullOnDelete();
                $table->string('category', 40);
                $table->string('sensitivity', 20)->default('clinical');
                $table->string('title', 200);
                $table->date('document_date');
                $table->string('issuer_facility', 150)->nullable();
                $table->string('tooth_fdi', 10)->nullable();
                $table->string('file_path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('file_size');
                $table->char('sha256', 64);
                $table->text('notes')->nullable();
                $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('archived_at')->nullable();
                $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('archive_reason')->nullable();
                $table->string('loinc_document_type', 32)->nullable();
                $table->string('satusehat_document_reference_id', 64)->nullable();
                $table->timestamps();

                $table->index(['pasien_id', 'archived_at', 'document_date'], 'patient_documents_patient_idx');
                $table->index(['pasien_id', 'category'], 'patient_documents_category_idx');
                $table->index(['kunjungan_id'], 'patient_documents_visit_idx');
            });

            $driver = Schema::getConnection()->getDriverName();
            DB::unprepared(match ($driver) {
                'sqlite' => "CREATE TRIGGER IF NOT EXISTS protect_patient_documents_delete BEFORE DELETE ON patient_documents BEGIN SELECT RAISE(ABORT, 'Catatan klinis dilindungi'); END",
                'mysql', 'mariadb' => "CREATE TRIGGER protect_patient_documents_delete BEFORE DELETE ON patient_documents FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Catatan klinis dilindungi'",
                'pgsql' => 'CREATE TRIGGER protect_patient_documents_delete BEFORE DELETE ON patient_documents FOR EACH ROW EXECUTE FUNCTION reject_clinical_mutation()',
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (Schema::hasTable('patient_documents')) {
            match ($driver) {
                'sqlite' => DB::unprepared('DROP TRIGGER IF EXISTS protect_patient_documents_delete'),
                'mysql', 'mariadb' => DB::unprepared('DROP TRIGGER IF EXISTS protect_patient_documents_delete'),
                'pgsql' => DB::unprepared('DROP TRIGGER IF EXISTS protect_patient_documents_delete ON patient_documents'),
                default => null,
            };
        }

        Schema::dropIfExists('patient_documents');
    }
};
