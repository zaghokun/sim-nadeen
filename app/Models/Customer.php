<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'pelanggan';

    protected $fillable = ['nama', 'kontak', 'alamat', 'wilayah', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];
}
