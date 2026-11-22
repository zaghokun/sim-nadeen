<?php

namespace Database\Seeders;

use App\Models\CashCategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['PENJUALAN', 'Penjualan', 'masuk', 'pendapatan', false],
            ['JUAL_STOCK_NON_RUTIN', 'Jual Stock Non-Rutin', 'masuk', 'penyesuaian', true],
            ['BAHAN_BAKU', 'Bahan Baku', 'keluar', 'persediaan', false],
            ['KOMISI', 'Komisi', 'keluar', 'beban', false],
            ['OPERASIONAL', 'Operasional', 'keluar', 'beban', false],
            ['KONSUMSI', 'Konsumsi', 'keluar', 'beban', false],
            ['GAJI', 'Gaji', 'keluar', 'beban', false],
            ['SALDO_AWAL', 'Saldo Awal', 'keduanya', 'saldo_awal', true],
        ];

        foreach ($categories as [$code, $name, $direction, $group, $nonroutine]) {
            CashCategory::firstOrCreate(['kode' => $code], [
                'nama' => $name,
                'arah' => $direction,
                'kelompok' => $group,
                'nonrutin' => $nonroutine,
                'aktif' => true,
            ]);
        }
    }
}
