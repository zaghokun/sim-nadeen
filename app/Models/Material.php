<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $table = 'bahan_baku';

    protected $fillable = ['kode', 'nama', 'kategori', 'satuan', 'stok_minimum', 'aktif'];

    protected $casts = ['stok_minimum' => 'decimal:3', 'aktif' => 'boolean'];
}
