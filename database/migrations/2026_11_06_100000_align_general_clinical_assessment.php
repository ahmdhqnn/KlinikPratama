<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some local databases contain the earlier dental rollout schema. Keep it intact,
        // and allow retry after a partially completed SQLite schema migration.
        foreach ([
            'screening' => ['general_assessment' => 'json', 'vitals_unmeasurable' => 'boolean', 'vitals_measured_at' => 'dateTime', 'triaged_at' => 'dateTime', 'submitted_by_user_id' => 'unsignedBigInteger', 'submitted_petugas_id' => 'unsignedBigInteger', 'last_submitted_at' => 'dateTime'],
            'pemeriksaan' => ['review_of_systems' => 'json', 'supporting_exams' => 'json', 'riwayat_sosial' => 'text', 'kondisi_akhir' => 'text', 'disposisi' => 'string'],
        ] as $name => $columns) {
            foreach ($columns as $column => $type) {
                if (! Schema::hasColumn($name, $column)) {
                    Schema::table($name, function (Blueprint $table) use ($column, $type): void {
                        $definition = $table->{$type}($column);
                        $type === 'boolean' ? $definition->default(false) : $definition->nullable();
                    });
                }
            }
        }
        if (! Schema::hasTable('screening_versions')) {
            Schema::create('screening_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('screening_id')->constrained('screening');
                $table->unsignedInteger('version');
                $table->string('kind');
                $table->foreignId('petugas_id')->nullable()->constrained('nakes');
                $table->foreignId('actor_id')->nullable()->constrained('users');
                $table->text('reason')->nullable();
                $table->uuid('request_id')->nullable();
                $table->string('request_hash', 64)->nullable();
                $table->unique(['screening_id', 'request_id']);
                $table->json('payload');
                $table->dateTime('recorded_at');
                $table->unique(['screening_id', 'version']);
            });
        } else {
            if (! Schema::hasColumn('screening_versions', 'request_id')) {
                Schema::table('screening_versions', function (Blueprint $table): void {
                    $table->uuid('request_id')->nullable();
                    $table->unique(['screening_id', 'request_id']);
                });
            }
            if (! Schema::hasColumn('screening_versions', 'request_hash')) {
                Schema::table('screening_versions', fn (Blueprint $table) => $table->string('request_hash', 64)->nullable());
            }
        }
    }

    public function down(): void
    {
        // Audit history and original submission metadata survive a feature rollback.
        Schema::table('pemeriksaan', fn (Blueprint $table) => $table->dropColumn(['supporting_exams', 'review_of_systems', 'riwayat_sosial', 'kondisi_akhir', 'disposisi']));
        Schema::table('screening', fn (Blueprint $table) => $table->dropColumn(['general_assessment', 'vitals_unmeasurable', 'vitals_measured_at', 'triaged_at']));
    }
};
