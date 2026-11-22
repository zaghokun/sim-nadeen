<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountInput
{
    public static function username(string $username): string
    {
        return Str::lower(trim($username));
    }

    public static function rules(?User $existing = null): array
    {
        $username = Rule::unique(User::class, 'username');

        return [
            'nama' => ['required', 'string', 'max:120'],
            'username' => [
                'bail', 'required', 'string', 'regex:/\A[a-z0-9][a-z0-9._-]{2,39}\z/',
                $existing ? $username->ignore($existing) : $username,
            ],
            'password' => [
                'bail', $existing ? 'nullable' : 'required', 'string', 'min:10', 'confirmed',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (strlen($value) > 72 || str_contains($value, "\0")) {
                        $fail('Kata sandi paling panjang 72 byte dan tidak boleh memuat karakter null.');
                    }
                },
            ],
        ];
    }
}
