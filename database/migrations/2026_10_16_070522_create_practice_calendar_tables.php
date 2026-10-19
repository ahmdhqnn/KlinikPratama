<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hari_libur_praktik', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal')->unique();
            $table->string('nama');
            $table->string('jenis');
            $table->string('sumber')->nullable();
            $table->timestamps();
        });

        Schema::create('jadwal_khusus_dokter', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dokter_id')->constrained('nakes')->cascadeOnDelete();
            $table->foreignId('poliklinik_id')->constrained('poliklinik')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->boolean('is_active');
            $table->string('alasan');
            $table->timestamps();
            $table->unique(['dokter_id', 'poliklinik_id', 'tanggal']);
        });

        DB::table('jadwal_dokter')->where('berlaku_mulai', '1970-01-01')
            ->whereIn('jam_mulai', ['00:00', '00:00:00'])->whereIn('jam_selesai', ['23:59', '23:59:00'])
            ->whereIn('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat'])
            ->update(['jam_mulai' => '08:00', 'jam_selesai' => '16:00']);
        DB::table('jadwal_dokter')->where('berlaku_mulai', '1970-01-01')
            ->whereIn('jam_mulai', ['00:00', '00:00:00'])->whereIn('jam_selesai', ['23:59', '23:59:00'])
            ->whereIn('hari', ['sabtu', 'minggu'])->update(['is_active' => false]);

        $holidays = [
            ['2026-01-01', 'Tahun Baru', 'nasional'], ['2026-01-16', 'Isra Mikraj', 'nasional'],
            ['2026-02-17', 'Tahun Baru Imlek', 'nasional'], ['2026-03-19', 'Nyepi', 'nasional'],
            ['2026-03-21', 'Idulfitri', 'nasional'], ['2026-03-22', 'Idulfitri', 'nasional'],
            ['2026-04-03', 'Wafat Yesus Kristus', 'nasional'], ['2026-04-05', 'Paskah', 'nasional'],
            ['2026-05-01', 'Hari Buruh', 'nasional'], ['2026-05-14', 'Kenaikan Yesus Kristus', 'nasional'],
            ['2026-05-27', 'Iduladha', 'nasional'], ['2026-05-31', 'Waisak', 'nasional'],
            ['2026-06-01', 'Hari Lahir Pancasila', 'nasional'], ['2026-06-16', 'Tahun Baru Islam', 'nasional'],
            ['2026-08-17', 'Kemerdekaan RI', 'nasional'], ['2026-08-25', 'Maulid Nabi Muhammad', 'nasional'],
            ['2026-12-25', 'Natal', 'nasional'],
            ['2026-02-16', 'Cuti bersama Imlek', 'cuti_bersama'], ['2026-03-18', 'Cuti bersama Nyepi', 'cuti_bersama'],
            ['2026-03-20', 'Cuti bersama Idulfitri', 'cuti_bersama'], ['2026-03-23', 'Cuti bersama Idulfitri', 'cuti_bersama'],
            ['2026-03-24', 'Cuti bersama Idulfitri', 'cuti_bersama'], ['2026-05-15', 'Cuti bersama Kenaikan Yesus Kristus', 'cuti_bersama'],
            ['2026-05-28', 'Cuti bersama Iduladha', 'cuti_bersama'], ['2026-12-24', 'Cuti bersama Natal', 'cuti_bersama'],
            ['2027-01-01', 'Tahun Baru', 'nasional'], ['2027-01-05', 'Isra Mikraj', 'nasional'],
            ['2027-02-06', 'Tahun Baru Imlek', 'nasional'], ['2027-03-08', 'Nyepi', 'nasional'],
            ['2027-03-10', 'Idulfitri', 'nasional'], ['2027-03-11', 'Idulfitri', 'nasional'],
            ['2027-03-26', 'Wafat Yesus Kristus', 'nasional'], ['2027-03-28', 'Paskah', 'nasional'],
            ['2027-05-01', 'Hari Buruh', 'nasional'], ['2027-05-06', 'Kenaikan Yesus Kristus', 'nasional'],
            ['2027-05-17', 'Iduladha', 'nasional'], ['2027-05-20', 'Waisak', 'nasional'],
            ['2027-06-01', 'Hari Lahir Pancasila', 'nasional'], ['2027-06-06', 'Tahun Baru Islam', 'nasional'],
            ['2027-08-15', 'Maulid Nabi Muhammad', 'nasional'], ['2027-08-17', 'Kemerdekaan RI', 'nasional'],
            ['2027-12-25', 'Natal', 'nasional'], ['2027-12-26', 'Isra Mikraj', 'nasional'],
            ['2027-02-05', 'Cuti bersama Imlek', 'cuti_bersama'], ['2027-03-09', 'Cuti bersama Idulfitri', 'cuti_bersama'],
            ['2027-03-12', 'Cuti bersama Idulfitri', 'cuti_bersama'], ['2027-03-15', 'Cuti bersama Idulfitri', 'cuti_bersama'],
            ['2027-03-25', 'Cuti bersama Wafat Yesus Kristus', 'cuti_bersama'], ['2027-05-18', 'Cuti bersama Iduladha', 'cuti_bersama'],
            ['2027-05-19', 'Cuti bersama Waisak', 'cuti_bersama'], ['2027-12-24', 'Cuti bersama Natal', 'cuti_bersama'],
        ];
        DB::table('hari_libur_praktik')->insert(array_map(fn (array $holiday): array => [
            'tanggal' => $holiday[0], 'nama' => $holiday[1], 'jenis' => $holiday[2],
            'sumber' => str_starts_with($holiday[0], '2026') ? 'SKB 1497/2025, 2/2025, 5/2025' : 'SKB 1205/2026, 3/2026, 2/2026',
            'created_at' => now(), 'updated_at' => now(),
        ], $holidays));
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_khusus_dokter');
        Schema::dropIfExists('hari_libur_praktik');
    }
};
