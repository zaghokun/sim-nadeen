<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bahan_baku', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 40)->unique();
            $table->string('nama', 120)->index();
            $table->string('kategori', 100);
            $table->string('satuan', 20);
            $table->decimal('stok_minimum', 14, 3)->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('pesanan', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 32)->nullable()->unique();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->restrictOnDelete();
            $table->foreignId('pembuat_id')->constrained('pengguna_sistem')->restrictOnDelete();
            $table->date('tanggal')->index();
            $table->string('status', 16)->default('draf')->index();
            $table->string('status_bayar', 16)->default('menunggu');
            $table->string('status_produksi', 16)->default('antri');
            $table->string('status_kirim', 16)->default('belum');
            $table->boolean('nonrutin')->default(false);
            $table->text('catatan')->nullable();
            $table->dateTime('dikonfirmasi_pada')->nullable();
            $table->date('tanggal_selesai')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('detail_pesanan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->restrictOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->restrictOnDelete();
            $table->string('kode_produk', 40);
            $table->string('nama_produk', 120);
            $table->unsignedInteger('jumlah');
            $table->string('sumber', 12);
            $table->string('kesiapan', 12)->default('antri');
            $table->decimal('harga_satuan', 18, 2);
            $table->decimal('estimasi_bahan', 18, 2)->nullable();
            $table->decimal('estimasi_upah', 18, 2)->nullable();
            $table->decimal('estimasi_overhead', 18, 2)->nullable();
            $table->decimal('markup', 7, 3)->nullable();
            $table->decimal('aktual_bahan', 18, 2)->nullable();
            $table->decimal('aktual_upah', 18, 2)->nullable();
            $table->decimal('aktual_overhead', 18, 2)->nullable();
            $table->decimal('hpp_aktual', 18, 2)->nullable();
            $table->dateTime('hpp_disahkan_pada')->nullable();
        });

        Schema::create('riwayat_status_pesanan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->restrictOnDelete();
            $table->foreignId('detail_id')->nullable()->constrained('detail_pesanan')->restrictOnDelete();
            $table->foreignId('aktor_id')->constrained('pengguna_sistem')->restrictOnDelete();
            $table->string('dimensi', 24);
            $table->string('sebelum', 24)->nullable();
            $table->string('sesudah', 24);
            $table->string('alasan', 250)->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('pembelian_bahan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('supplier')->restrictOnDelete();
            $table->foreignId('bahan_id')->constrained('bahan_baku')->restrictOnDelete();
            $table->foreignId('pesanan_id')->nullable()->constrained('pesanan')->restrictOnDelete();
            $table->foreignId('asal_id')->nullable()->unique()->constrained('pembelian_bahan')->restrictOnDelete();
            $table->foreignId('pembuat_id')->constrained('pengguna_sistem')->restrictOnDelete();
            $table->date('tanggal')->index();
            $table->string('referensi', 100);
            $table->string('metode', 40);
            $table->decimal('jumlah', 14, 3);
            $table->decimal('nominal', 18, 2);
            $table->string('alasan', 250)->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('lot_stok', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bahan_id')->nullable()->constrained('bahan_baku')->restrictOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produk')->restrictOnDelete();
            $table->foreignId('pembelian_id')->nullable()->unique()->constrained('pembelian_bahan')->restrictOnDelete();
            $table->date('tanggal_masuk')->nullable();
            $table->string('kondisi', 12);
            $table->decimal('jumlah_awal', 14, 3);
            $table->decimal('jumlah_sisa', 14, 3);
            $table->decimal('nilai_awal', 18, 2)->nullable();
            $table->decimal('nilai_sisa', 18, 2)->nullable();
            $table->dateTime('nilai_disahkan_pada')->nullable();
            $table->dateTime('created_at');
            $table->index(['bahan_id', 'tanggal_masuk']);
            $table->index(['produk_id', 'tanggal_masuk']);
        });

        Schema::create('mutasi_stok', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained('lot_stok')->restrictOnDelete();
            $table->foreignId('detail_id')->nullable()->constrained('detail_pesanan')->restrictOnDelete();
            $table->foreignId('asal_id')->nullable()->unique()->constrained('mutasi_stok')->restrictOnDelete();
            $table->foreignId('aktor_id')->constrained('pengguna_sistem')->restrictOnDelete();
            $table->string('jenis', 24);
            $table->decimal('perubahan_jumlah', 14, 3);
            $table->decimal('perubahan_nilai', 18, 2)->nullable();
            $table->string('alasan', 250);
            $table->date('tanggal');
            $table->dateTime('created_at');
        });

        Schema::create('pembayaran', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->restrictOnDelete();
            $table->foreignId('pembuat_id')->constrained('pengguna_sistem')->restrictOnDelete();
            $table->foreignId('asal_id')->nullable()->unique()->constrained('pembayaran')->restrictOnDelete();
            $table->date('tanggal');
            $table->decimal('nominal', 18, 2);
            $table->string('jenis', 16);
            $table->string('metode', 40);
            $table->string('alasan', 250)->nullable();
            $table->dateTime('created_at');
            $table->index(['pesanan_id', 'tanggal']);
        });

        Schema::create('transaksi_kas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kategori_id')->constrained('kategori_kas')->restrictOnDelete();
            $table->foreignId('pembayaran_id')->nullable()->unique()->constrained('pembayaran')->restrictOnDelete();
            $table->foreignId('pembelian_id')->nullable()->unique()->constrained('pembelian_bahan')->restrictOnDelete();
            $table->foreignId('asal_id')->nullable()->unique()->constrained('transaksi_kas')->restrictOnDelete();
            $table->foreignId('pembuat_id')->constrained('pengguna_sistem')->restrictOnDelete();
            $table->date('tanggal')->index();
            $table->string('nama_kategori', 120);
            $table->string('kelompok', 30);
            $table->boolean('nonrutin');
            $table->boolean('masuk_hpp')->default(false);
            $table->string('arah', 8);
            $table->decimal('nominal', 18, 2);
            $table->string('keterangan', 250);
            $table->dateTime('created_at');
        });

        Schema::create('permintaan_tulis', function (Blueprint $table): void {
            $table->uuid('token')->primary();
            $table->foreignId('aktor_id')->constrained('pengguna_sistem')->restrictOnDelete();
            $table->string('operasi', 48);
            $table->char('sidik_data', 64);
            $table->unsignedBigInteger('hasil_id')->nullable();
            $table->dateTime('created_at');
        });

        DB::statement('ALTER TABLE lot_stok ADD CONSTRAINT lot_satu_item CHECK ((bahan_id IS NULL) <> (produk_id IS NULL)), ADD CONSTRAINT lot_sisa_valid CHECK (jumlah_sisa >= 0 AND jumlah_sisa <= jumlah_awal AND (nilai_sisa IS NULL OR nilai_sisa >= 0))');
        DB::statement('ALTER TABLE transaksi_kas ADD CONSTRAINT kas_satu_sumber CHECK (pembayaran_id IS NULL OR pembelian_id IS NULL), ADD CONSTRAINT kas_nominal_positif CHECK (nominal > 0)');
        DB::statement('ALTER TABLE pembayaran ADD CONSTRAINT bayar_nominal_positif CHECK (nominal > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_tulis');
        Schema::dropIfExists('transaksi_kas');
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('mutasi_stok');
        Schema::dropIfExists('lot_stok');
        Schema::dropIfExists('pembelian_bahan');
        Schema::dropIfExists('riwayat_status_pesanan');
        Schema::dropIfExists('detail_pesanan');
        Schema::dropIfExists('pesanan');
        Schema::dropIfExists('bahan_baku');
    }
};
