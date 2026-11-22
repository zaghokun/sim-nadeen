<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashCategory extends Model
{
    public const DIRECTIONS = ['masuk' => 'Masuk', 'keluar' => 'Keluar', 'keduanya' => 'Masuk dan keluar'];

    public const GROUPS = [
        'pendapatan' => 'Pendapatan',
        'persediaan' => 'Persediaan',
        'beban' => 'Beban operasional',
        'penyesuaian' => 'Penyesuaian',
        'saldo_awal' => 'Saldo awal',
    ];

    protected $table = 'kategori_kas';

    protected $fillable = ['kode', 'nama', 'arah', 'kelompok', 'nonrutin', 'aktif'];

    protected $casts = ['nonrutin' => 'boolean', 'aktif' => 'boolean'];
}
