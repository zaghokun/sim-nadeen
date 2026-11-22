<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AccountInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

Artisan::command('nadeen:buat-admin', function (): int {
    $data = Validator::make([
        'nama' => trim((string) $this->ask('Nama lengkap')),
        'username' => AccountInput::username((string) $this->ask('Nama pengguna')),
        'password' => $this->secret('Kata sandi'),
        'password_confirmation' => $this->secret('Ulangi kata sandi'),
    ], AccountInput::rules())->validate();

    try {
        User::create([
            'nama' => $data['nama'],
            'username' => $data['username'],
            'password_hash' => Hash::make($data['password']),
            'peran' => UserRole::Admin,
            'aktif' => true,
        ]);
    } catch (UniqueConstraintViolationException) {
        $this->error('Nama pengguna sudah dipakai.');

        return 1;
    }

    $this->info('Akun admin berhasil dibuat.');

    return 0;
})->purpose('Buat akun admin internal secara interaktif');
