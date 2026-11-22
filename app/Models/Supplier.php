<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'supplier';

    protected $fillable = ['nama', 'kontak', 'alamat', 'jenis_bahan', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];
}
