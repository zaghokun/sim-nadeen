<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Production = 'produksi';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Pemilik / Admin',
            self::Production => 'Produksi & Gudang',
        };
    }
}
