<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->whereIn('role', ['admin', 'pendaftaran'])->where('is_active', true)
            ->groupBy('role')->havingRaw('COUNT(*) > 1')->select('role')->get()->isNotEmpty()) {
            throw new RuntimeException('Tentukan satu akun aktif untuk setiap peran Admin dan Pendaftaran sebelum migrasi.');
        }

        if (DB::table('nakes')->whereNotNull('user_id')->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')->select('user_id')->get()->isNotEmpty()) {
            throw new RuntimeException('Tinjau akun yang tertaut ke lebih dari satu profil tenaga kerja sebelum migrasi.');
        }

        if (DB::table('users')->selectRaw('LOWER(email) AS login_email')->groupByRaw('LOWER(email)')
            ->havingRaw('COUNT(*) > 1')->get()->isNotEmpty()) {
            throw new RuntimeException('Tinjau akun yang memakai email sama sebelum migrasi.');
        }

        if (DB::table('nakes')->whereNotNull('no_str')->selectRaw('UPPER(TRIM(no_str)) AS staff_str')
            ->groupByRaw('UPPER(TRIM(no_str))')->havingRaw('COUNT(*) > 1')->get()->isNotEmpty()) {
            throw new RuntimeException('Tinjau nomor STR yang tercatat pada lebih dari satu profil sebelum migrasi.');
        }

        match (DB::getDriverName()) {
            'sqlite', 'sqlsrv' => DB::statement("CREATE UNIQUE INDEX users_active_single_role_unique ON users (role) WHERE is_active = 1 AND role IN ('admin', 'pendaftaran')"),
            'pgsql' => DB::statement("CREATE UNIQUE INDEX users_active_single_role_unique ON users (role) WHERE is_active AND role IN ('admin', 'pendaftaran')"),
            'mysql', 'mariadb' => $this->addGeneratedRoleSlot(),
        };

        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('CREATE UNIQUE INDEX users_login_email_unique ON users (LOWER(email))');
            DB::statement('CREATE UNIQUE INDEX nakes_no_str_unique ON nakes (UPPER(TRIM(no_str)))');
        }

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX nakes_user_id_unique ON nakes (user_id) WHERE user_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX nakes_no_str_unique ON nakes (no_str) WHERE no_str IS NOT NULL');
        } else {
            Schema::table('nakes', function (Blueprint $table): void {
                $table->unique('user_id');
                if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                    $table->unique('no_str');
                }
            });
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_active_single_role_unique');
                $table->dropColumn('active_single_role');
            });
        } else {
            DB::statement('DROP INDEX users_active_single_role_unique'.(DB::getDriverName() === 'sqlsrv' ? ' ON users' : ''));
        }

        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX users_login_email_unique');
            DB::statement('DROP INDEX nakes_no_str_unique');
        }

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('DROP INDEX nakes_user_id_unique ON nakes');
            DB::statement('DROP INDEX nakes_no_str_unique ON nakes');
        } else {
            Schema::table('nakes', function (Blueprint $table): void {
                $table->dropUnique('nakes_user_id_unique');
                if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                    $table->dropUnique('nakes_no_str_unique');
                }
            });
        }
    }

    private function addGeneratedRoleSlot(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('active_single_role', 20)
                ->virtualAs("CASE WHEN is_active = 1 AND role IN ('admin', 'pendaftaran') THEN role ELSE NULL END");
            $table->unique('active_single_role', 'users_active_single_role_unique');
        });
    }
};
