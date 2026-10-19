<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pasien', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asuransi_id');
            $table->dropColumn('no_asuransi');
        });

        Schema::table('kunjungan', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asuransi_id');
            $table->dropIndex(['cost_center']);
            $table->dropColumn(['jenis_bayar', 'cost_center']);
        });

        Schema::table('kepesertaan', function (Blueprint $table): void {
            $table->dropColumn('cost_center');
        });

        Schema::table('obat', function (Blueprint $table): void {
            $table->dropColumn(['harga_beli', 'harga_jual']);
        });

        Schema::table('alkes', function (Blueprint $table): void {
            $table->dropColumn(['harga_beli', 'harga_jual']);
        });

        Schema::table('obat_batch', function (Blueprint $table): void {
            $table->dropColumn('harga_beli');
        });

        Schema::table('stok_mutasi', function (Blueprint $table): void {
            $table->dropColumn(['harga', 'cost_center']);
        });

        Schema::table('tindakan', function (Blueprint $table): void {
            $table->dropColumn(['tarif', 'tarif_dokter', 'tarif_asisten', 'tarif_klinik']);
        });

        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            $table->dropColumn(['tarif', 'tarif_dokter']);
        });

        Schema::table('laboratorium', function (Blueprint $table): void {
            $table->dropColumn('tarif');
        });

        foreach ([
            'tagihan_item',
            'tagihan',
            'asuransi_harga',
            'paket_tindakan_items',
            'paket_tindakan',
            'biaya_pendaftaran',
            'biaya_admin',
            'purchase_order_item',
            'purchase_order',
            'penjualan_langsung_item',
            'penjualan_langsung',
            'asuransi',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('asuransi', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('jenis')->default('umum');
            $table->text('alamat')->nullable();
            $table->string('telepon')->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('pasien', function (Blueprint $table): void {
            $table->foreignId('asuransi_id')->nullable()->after('kepesertaan_id')->constrained('asuransi')->nullOnDelete();
            $table->string('no_asuransi')->nullable();
        });

        Schema::table('kunjungan', function (Blueprint $table): void {
            $table->foreignId('asuransi_id')->nullable()->constrained('asuransi')->nullOnDelete();
            $table->string('jenis_bayar')->default('umum');
            $table->string('cost_center', 100)->nullable()->index();
        });

        Schema::table('kepesertaan', function (Blueprint $table): void {
            $table->string('cost_center', 100)->nullable();
        });

        Schema::table('obat', function (Blueprint $table): void {
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->decimal('harga_jual', 15, 2)->default(0);
        });

        Schema::table('alkes', function (Blueprint $table): void {
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->decimal('harga_jual', 15, 2)->default(0);
        });

        Schema::table('obat_batch', function (Blueprint $table): void {
            $table->decimal('harga_beli', 15, 2)->default(0);
        });

        Schema::table('stok_mutasi', function (Blueprint $table): void {
            $table->decimal('harga', 15, 2)->default(0);
            $table->string('cost_center', 100)->nullable();
        });

        Schema::table('tindakan', function (Blueprint $table): void {
            $table->decimal('tarif', 15, 2)->default(0);
            $table->decimal('tarif_dokter', 15, 2)->default(0);
            $table->decimal('tarif_asisten', 15, 2)->default(0);
            $table->decimal('tarif_klinik', 15, 2)->default(0);
        });

        Schema::table('tindakan_kunjungan', function (Blueprint $table): void {
            $table->decimal('tarif', 15, 2)->default(0);
            $table->decimal('tarif_dokter', 15, 2)->default(0);
        });

        Schema::table('laboratorium', function (Blueprint $table): void {
            $table->decimal('tarif', 15, 2)->default(0);
        });

        Schema::create('biaya_admin', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->decimal('tarif', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('biaya_pendaftaran', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('poliklinik_id')->nullable()->constrained('poliklinik')->nullOnDelete();
            $table->foreignId('dokter_id')->nullable()->constrained('nakes')->nullOnDelete();
            $table->string('jenis_pasien')->default('baru');
            $table->decimal('tarif', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('paket_tindakan', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->decimal('tarif', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('paket_tindakan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paket_tindakan_id')->constrained('paket_tindakan')->cascadeOnDelete();
            $table->foreignId('tindakan_id')->nullable()->constrained('tindakan')->nullOnDelete();
            $table->foreignId('laboratorium_id')->nullable()->constrained('laboratorium')->nullOnDelete();
            $table->string('jenis');
            $table->timestamps();
        });

        Schema::create('asuransi_harga', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asuransi_id')->constrained('asuransi')->cascadeOnDelete();
            $table->foreignId('obat_id')->constrained('obat')->cascadeOnDelete();
            $table->decimal('harga_khusus', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['asuransi_id', 'obat_id']);
        });

        Schema::create('tagihan', function (Blueprint $table): void {
            $table->id();
            $table->string('no_tagihan')->unique();
            $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('kasir_id')->nullable()->constrained('nakes')->nullOnDelete();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('bayar', 15, 2)->default(0);
            $table->decimal('kembalian', 15, 2)->default(0);
            $table->string('metode_bayar')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('tagihan_item', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->string('jenis');
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->string('nama');
            $table->integer('jumlah')->default(1);
            $table->decimal('tarif', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_order', function (Blueprint $table): void {
            $table->id();
            $table->string('no_po')->unique();
            $table->foreignId('depo_id')->constrained('depo_obat');
            $table->string('supplier')->nullable();
            $table->date('tanggal');
            $table->date('tanggal_kirim')->nullable();
            $table->string('status')->default('draft');
            $table->text('catatan')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_order_item', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_order')->cascadeOnDelete();
            $table->foreignId('obat_id')->constrained('obat');
            $table->decimal('jumlah', 10, 2)->default(1);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('jumlah_terima', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('penjualan_langsung', function (Blueprint $table): void {
            $table->id();
            $table->string('no_transaksi')->unique();
            $table->date('tanggal');
            $table->foreignId('kasir_id')->nullable()->constrained('nakes')->nullOnDelete();
            $table->string('nama_pembeli')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('bayar', 15, 2)->default(0);
            $table->decimal('kembalian', 15, 2)->default(0);
            $table->string('metode_bayar')->default('tunai');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('penjualan_langsung_item', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('penjualan_langsung_id')->constrained('penjualan_langsung')->cascadeOnDelete();
            $table->foreignId('obat_id')->constrained('obat');
            $table->decimal('jumlah', 10, 2)->default(1);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
    }
};
