<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Posting
{
    public function run(string $token, int $actor, string $operation, array $data, Closure $action): int
    {
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($token, $actor, $operation, $fingerprint, $action): int {
            $requests = DB::table('permintaan_tulis');
            $requests->insertOrIgnore([
                'token' => $token, 'aktor_id' => $actor, 'operasi' => $operation,
                'sidik_data' => $fingerprint, 'created_at' => now(),
            ]);
            $saved = $requests->where('token', $token)->lockForUpdate()->first();

            if ($saved->aktor_id !== $actor || $saved->operasi !== $operation || $saved->sidik_data !== $fingerprint) {
                throw ValidationException::withMessages(['token' => 'Formulir ini sudah dipakai. Buka ulang halaman untuk transaksi baru.']);
            }

            if ($saved->hasil_id !== null) {
                return (int) $saved->hasil_id;
            }

            $id = $action();
            $requests->where('token', $token)->update(['hasil_id' => $id]);

            return $id;
        }, 3);
    }
}
