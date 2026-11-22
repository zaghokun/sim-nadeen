<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'produk';

    protected $fillable = ['kode', 'nama', 'kategori', 'varian', 'harga_standar', 'stok_minimum', 'aktif'];

    protected $casts = ['aktif' => 'boolean', 'harga_standar' => 'decimal:2', 'stok_minimum' => 'integer'];
}
