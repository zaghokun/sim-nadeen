<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_impor', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aktor_id')->constrained('pengguna_sistem')->restrictOnDelete();
            $table->string('dataset', 60)->index();
            $table->char('sidik_berkas', 64);
            $table->string('status', 16);
            $table->json('ringkasan')->nullable();
            $table->dateTime('created_at');
        });
        Schema::create('baris_impor', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('batch_impor')->restrictOnDelete();
            $table->string('dataset', 60);
            $table->string('lembar', 32);
            $table->string('kunci_sumber', 100);
            $table->char('sidik_data', 64);
            $table->unsignedBigInteger('entitas_id');
            $table->unique(['dataset', 'lembar', 'kunci_sumber']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baris_impor');
        Schema::dropIfExists('batch_impor');
    }
};
