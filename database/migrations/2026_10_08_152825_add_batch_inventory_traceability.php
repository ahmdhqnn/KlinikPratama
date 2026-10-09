<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obat_batch', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('obat_id')->constrained('obat')->restrictOnDelete();
            $table->foreignId('depo_id')->nullable()->constrained('depo_obat')->restrictOnDelete();
            $table->string('nomor_batch', 100);
            $table->date('expired_at')->nullable();
            $table->decimal('stok', 12, 2)->default(0);
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->string('status', 30)->default('tersedia');
            $table->string('sumber', 30);
            $table->string('referensi', 255);
            $table->timestamps();
            $table->index(['obat_id', 'status', 'expired_at']);
        });
        Schema::table('stok_mutasi', function (Blueprint $table): void {
            $table->foreignId('batch_id')->nullable()->constrained('obat_batch')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('kunjungan_id')->nullable()->constrained('kunjungan')->restrictOnDelete();
            $table->foreignId('farmasi_item_id')->nullable()->constrained('farmasi_item')->restrictOnDelete();
            $table->unsignedBigInteger('mutasi_asal_id')->nullable()->index();
            $table->string('cost_center', 100)->nullable();
            $table->decimal('batch_stok_sebelum', 12, 2)->nullable();
            $table->decimal('batch_stok_sesudah', 12, 2)->nullable();
        });
        Schema::table('farmasi', function (Blueprint $table): void {
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispensed_at')->nullable();
        });
        Schema::table('tindakan_kunjungan', fn (Blueprint $table) => $table->timestamp('bhp_consumed_at')->nullable());

        DB::table('resep_obat')->join('resep', 'resep.id', '=', 'resep_obat.resep_id')
            ->where('resep.status', '!=', 'selesai')->where('resep_obat.stok_dikurangi', true)
            ->select('resep_obat.*')->orderBy('resep_obat.id')->get()->each(function (object $item): void {
                if ($item->obat_id) {
                    $medicine = DB::table('obat')->where('id', $item->obat_id)->first();
                    DB::table('obat')->where('id', $item->obat_id)->increment('stok', $item->jumlah);
                    DB::table('stok_mutasi')->insert([
                        'obat_id' => $item->obat_id, 'jenis' => 'masuk', 'referensi_type' => 'RekonsiliasiReservasi',
                        'referensi_id' => $item->id, 'jumlah' => $item->jumlah, 'harga' => $medicine->harga_beli,
                        'stok_sebelum' => $medicine->stok, 'stok_sesudah' => $medicine->stok + $item->jumlah,
                        'keterangan' => 'Reservasi lama dikembalikan; stok keluar saat penyerahan obat.',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('resep_obat')->where('id', $item->id)->update(['stok_dikurangi' => false]);
            });
        DB::table('obat')->where('stok', '>', 0)->orderBy('id')->get()->each(function (object $medicine): void {
            DB::table('obat_batch')->insert([
                'obat_id' => $medicine->id, 'nomor_batch' => 'LEGACY-'.$medicine->id,
                'stok' => $medicine->stok, 'harga_beli' => $medicine->harga_beli, 'status' => 'karantina',
                'sumber' => 'saldo_awal', 'referensi' => 'Migrasi saldo lama; verifikasi fisik batch dan kedaluwarsa diperlukan.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tindakan_kunjungan', fn (Blueprint $table) => $table->dropColumn('bhp_consumed_at'));
        Schema::table('farmasi', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dispensed_by');
            $table->dropColumn('dispensed_at');
        });
        Schema::table('stok_mutasi', function (Blueprint $table): void {
            $table->dropIndex(['mutasi_asal_id']);
            foreach (['batch_id', 'actor_id', 'kunjungan_id', 'farmasi_item_id'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn(['mutasi_asal_id', 'cost_center', 'batch_stok_sebelum', 'batch_stok_sesudah']);
        });
        Schema::dropIfExists('obat_batch');
    }
};
