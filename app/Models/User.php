<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'pengguna_sistem';

    protected $authPasswordName = 'password_hash';

    protected $fillable = ['username', 'nama', 'password_hash', 'peran', 'aktif'];

    protected $attributes = ['aktif' => true, 'versi_sesi' => 0];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'peran' => UserRole::class,
            'password_hash' => 'hashed',
            'aktif' => 'boolean',
            'versi_sesi' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->aktif && $this->peran === UserRole::Admin;
    }
}
