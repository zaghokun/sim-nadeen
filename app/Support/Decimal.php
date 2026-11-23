<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class Decimal
{
    public const MONEY = 'regex:/\A\d{1,16}(?:\.\d{1,2})?\z/';

    public const QUANTITY = 'regex:/\A\d{1,11}(?:\.\d{1,3})?\z/';

    public static function storedMoney(string $value, string $field = 'nominal'): string
    {
        $value = bcround($value, 2);

        if (bccomp($value, '9999999999999999.99', 2) > 0) {
            throw ValidationException::withMessages([$field => 'Nilai melebihi batas nominal transaksi.']);
        }

        return $value;
    }

    public static function display(string|int|null $value, int $scale = 2): string
    {
        if ($value === null) {
            return '–';
        }

        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');
        $fraction = rtrim(substr($fraction, 0, $scale), '0');

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).($fraction === '' ? '' : ','.$fraction);
    }

    public static function rupiah(string|int|null $value): string
    {
        return $value === null ? '–' : 'Rp '.self::display($value);
    }
}
