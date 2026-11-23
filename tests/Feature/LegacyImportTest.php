<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ImportWorkbook;
use App\Services\LegacyImport;
use App\Support\BusinessDate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_excel_preview_apply_sources_and_retry(): void
    {
        (new DatabaseSeeder)->run();
        $actor = $this->actor();
        $today = BusinessDate::today();
        $file = $this->workbook([
            'pelanggan' => [['c1', '', 'Pelanggan Impor Uji', '', '', '']],
            'supplier' => [['s1', '', 'Supplier Impor Uji', '', '', 'Kayu']],
            'produk' => [['p1', 'IMPOR-TEST-P1', 'Kursi Impor Uji', 'Kursi', '', '300000', '1']],
            'bahan' => [['b1', 'IMPOR-TEST-B1', 'Kayu Impor Uji', 'Kayu', 'm', '1.5']],
            'hpp' => [['h1', 'p1', $today, '100000', '50000', '25000', '20']],
            'lot' => [['l1', 'barang', 'p1', $today, '2', '220000', 'layak', 'Saldo awal barang']],
            'pesanan' => [['o1', 'c1', $today, 'konfirmasi', '0', '']],
            'rincian' => [['d1', 'o1', 'p1', '1', 'stok', '300000']],
            'pembelian' => [['pb1', 's1', 'b1', '', $today, 'IMPOR-KUITANSI-UJI', '5', '50000', 'Tunai']],
            'pembayaran' => [['pm1', 'o1', $today, '300000', 'Transfer']],
            'kas' => [
                ['k1', 'PENJUALAN', $today, 'masuk', '300000', 'Kas pembayaran lama', '0', 'pembayaran', 'pm1'],
                ['k2', 'BAHAN_BAKU', $today, 'keluar', '50000', 'Kas pembelian lama', '0', 'pembelian', 'pb1'],
                ['k3', 'OPERASIONAL', $today, 'keluar', '3000', 'Pengeluaran manual uji', '0', '', ''],
            ],
        ]);

        try {
            $import = app(LegacyImport::class);
            $plainPreview = $import->run($file, 't08-uji', $actor->id, false);
            $this->assertSame([], $plainPreview['errors']);
            $this->assertFalse($plainPreview['saved']);
            $this->assertDatabaseMissing('bahan_baku', ['kode' => 'IMPOR-TEST-B1']);
            $this->assertSame(0, DB::table('batch_impor')->where('dataset', 't08-uji')->count());

            $result = $import->run($file, 't08-uji', $actor->id, true);
            $this->assertSame([], $result['errors']);
            $this->assertTrue($result['saved']);
            $this->assertSame(3, DB::table('transaksi_kas')->where('pembuat_id', $actor->id)->count());
            $this->assertDatabaseHas('pesanan', ['pembuat_id' => $actor->id, 'status_bayar' => 'lunas']);
            $this->assertDatabaseHas('detail_pesanan', ['kode_produk' => 'IMPOR-TEST-P1', 'hpp_aktual' => '110000.00']);
            $this->assertDatabaseHas('baris_impor', ['dataset' => 't08-uji', 'lembar' => 'kas', 'kunci_sumber' => 'k1']);

            $retry = $import->run($file, 't08-uji', $actor->id, true);
            $this->assertSame([], $retry['errors']);
            $this->assertSame(['baru' => 0, 'sudah' => 3], $retry['counts']['kas']);
            $this->assertSame(3, DB::table('transaksi_kas')->where('pembuat_id', $actor->id)->count());
        } finally {
            unlink($file);
        }
    }

    public function test_ambiguous_historical_row_holds_the_entire_batch(): void
    {
        (new DatabaseSeeder)->run();
        $actor = $this->actor();
        $file = $this->workbook([
            'pelanggan' => [['c1', '', 'Pelanggan Implikasi Ditahan', '', '', '']],
            'kas' => [['k1', 'OPERASIONAL', '', 'keluar', '15000', 'Tanggal belum diketahui', '0', '', '']],
        ]);

        try {
            $result = app(LegacyImport::class)->run($file, 't08-tahan', $actor->id, true);
            $this->assertFalse($result['saved']);
            $this->assertStringContainsString('kas baris 2', $result['errors'][0]);
            $this->assertDatabaseMissing('pelanggan', ['nama' => 'Pelanggan Implikasi Ditahan']);
            $this->assertDatabaseHas('batch_impor', ['dataset' => 't08-tahan', 'status' => 'ditahan']);
        } finally {
            unlink($file);
        }
    }

    private function actor(): User
    {
        return User::create([
            'nama' => 'Admin impor uji', 'username' => 'impor-'.Str::lower(Str::random(8)),
            'password_hash' => Str::random(30), 'peran' => UserRole::Admin,
        ]);
    }

    private function workbook(array $rows): string
    {
        $path = storage_path('framework/testing/'.Str::uuid().'.xlsx');
        $this->assertSame(0, Artisan::call('nadeen:template-impor', ['berkas' => $path]));
        $book = IOFactory::load($path);
        $this->assertSame(array_keys(ImportWorkbook::COLUMNS), $book->getSheetNames());
        foreach ($rows as $name => $data) {
            $book->getSheetByName($name)->fromArray($data, null, 'A2', true);
        }
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
