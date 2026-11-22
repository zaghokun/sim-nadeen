<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna_sistem', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 40)->unique();
            $table->string('nama', 120);
            $table->string('password_hash');
            $table->enum('peran', ['admin', 'produksi']);
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('versi_sesi')->default(0);
            $table->timestamps();
        });

        Schema::create('pelanggan', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 120)->index();
            $table->string('kontak', 80)->nullable();
            $table->text('alamat')->nullable();
            $table->string('wilayah', 100)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 120)->index();
            $table->string('kontak', 80)->nullable();
            $table->text('alamat')->nullable();
            $table->string('jenis_bahan', 100)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('produk', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 40)->unique();
            $table->string('nama', 120)->index();
            $table->string('kategori', 100);
            $table->string('varian', 100)->nullable();
            $table->decimal('harga_standar', 18, 2);
            $table->unsignedInteger('stok_minimum')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('komponen_hpp', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->restrictOnDelete();
            $table->date('berlaku_mulai');
            $table->decimal('biaya_bahan', 18, 2);
            $table->decimal('upah', 18, 2);
            $table->decimal('overhead', 18, 2);
            $table->decimal('markup', 7, 3);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->unique(['produk_id', 'berlaku_mulai']);
        });

        Schema::create('kategori_kas', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 40)->unique();
            $table->string('nama', 120)->index();
            $table->enum('arah', ['masuk', 'keluar', 'keduanya']);
            $table->enum('kelompok', ['pendapatan', 'persediaan', 'beban', 'penyesuaian', 'saldo_awal']);
            $table->boolean('nonrutin')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_kas');
        Schema::dropIfExists('komponen_hpp');
        Schema::dropIfExists('produk');
        Schema::dropIfExists('supplier');
        Schema::dropIfExists('pelanggan');
        Schema::dropIfExists('pengguna_sistem');
    }
};
