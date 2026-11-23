<?php

namespace App\Enums;

enum StockKind: string
{
    case Material = 'bahan';
    case Product = 'barang';

    public function column(): string
    {
        return $this === self::Material ? 'bahan_id' : 'produk_id';
    }

    public function table(): string
    {
        return $this === self::Material ? 'bahan_baku' : 'produk';
    }

    public function label(): string
    {
        return $this === self::Material ? 'Bahan baku' : 'Barang jadi';
    }
}
