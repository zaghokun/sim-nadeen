<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostStandard extends Model
{
    protected $table = 'komponen_hpp';

    protected $fillable = ['produk_id', 'berlaku_mulai', 'biaya_bahan', 'upah', 'overhead', 'markup', 'aktif'];

    protected $casts = [
        'aktif' => 'boolean',
        'berlaku_mulai' => 'immutable_date',
        'biaya_bahan' => 'decimal:2',
        'upah' => 'decimal:2',
        'overhead' => 'decimal:2',
        'markup' => 'decimal:3',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'produk_id');
    }

    protected function totalHpp(): Attribute
    {
        return Attribute::get(fn (): string => bcadd(bcadd($this->biaya_bahan, $this->upah, 2), $this->overhead, 2));
    }
}
