<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ListFilters
{
    public static function read(Request $request, bool $allowStatus): array
    {
        $query = $request->query('q');
        $status = $request->query('status');

        return [
            'q' => is_string($query) ? Str::substr(trim($query), 0, 120) : '',
            'status' => $allowStatus && in_array($status, ['aktif', 'nonaktif'], true) ? $status : '',
        ];
    }

    public static function like(string $query): string
    {
        return '%'.addcslashes($query, '\\%_').'%';
    }
}
