<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CostStandard;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance;
use App\Services\Orders;
use App\Services\Reports;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $staff;

    private Product $product;

    private Customer $customer;

    private Material $material;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        (new DatabaseSeeder)->run();
        $suffix = Str::lower(Str::random(8));
        $this->admin = $this->user('admin-'.$suffix, UserRole::Admin);
        $this->staff = $this->user('staff-'.$suffix, UserRole::Production);
        $this->product = Product::create(['kode' => 'UJI-'.$suffix, 'nama' => 'Kursi Uji', 'kategori' => 'Kursi', 'harga_standar' => '250000.00', 'stok_minimum' => 2, 'aktif' => true]);
        $this->customer = Customer::create(['nama' => 'Pelanggan Uji', 'aktif' => true]);
        $this->supplier = Supplier::create(['nama' => 'Supplier Uji', 'aktif' => true]);
        $this->material = Material::create(['kode' => 'BHN-'.$suffix, 'nama' => 'Bahan Uji', 'kategori' => 'Kayu', 'satuan' => 'm', 'stok_minimum' => '2.500', 'aktif' => true]);
        CostStandard::create([
            'produk_id' => $this->product->id, 'berlaku_mulai' => '2020-01-01', 'biaya_bahan' => '100000.00',
            'upah' => '20000.00', 'overhead' => '10000.00', 'markup' => '20.000', 'aktif' => true,
        ]);
        $this->as($this->admin);
    }

    public function test_fifo_order_payment_retries_delivery_and_reports(): void
    {
        $this->stock('barang', $this->product->id, '2', '240000', '2020-01-01');
        $this->stock('barang', $this->product->id, '3', '450000', '2020-02-01');
        $this->get('/inventori/barang?filter=lama')->assertOk()->assertSee('Lebih dari 180 hari');
        $this->get('/pesanan/tambah')->assertOk()->assertSee('Simpan draf');
        $this->getJson('/pesanan/estimasi?produk_id='.$this->product->id)->assertOk()->assertJsonPath('estimasi.saran', '156000.00');
        $order = $this->draft('stok', 3, '250000.00');
        $this->get("/pesanan/$order/nota")->assertOk()->assertSee('Nota estimasi');
        $confirmation = $this->postData([]);
        $this->post("/pesanan/$order/konfirmasi", $confirmation)->assertRedirect("/pesanan/$order");
        $this->post("/pesanan/$order/konfirmasi", $confirmation)->assertRedirect("/pesanan/$order");
        $this->assertDatabaseHas('detail_pesanan', ['pesanan_id' => $order, 'hpp_aktual' => '390000.00', 'kesiapan' => 'siap']);
        $this->assertSame(['0.000', '2.000'], DB::table('lot_stok')->where('produk_id', $this->product->id)->orderBy('id')->pluck('jumlah_sisa')->all());

        $first = $this->postData(['nominal' => '250000.00', 'metode' => 'Transfer']);
        $this->post("/pesanan/$order/bayar", $first)->assertRedirect("/pesanan/$order");
        $this->post("/pesanan/$order/bayar", $first)->assertRedirect("/pesanan/$order");
        $this->assertDatabaseHas('pesanan', ['id' => $order, 'status_bayar' => 'dp']);
        $this->post("/pesanan/$order/bayar", $this->postData(['nominal' => '500001.00', 'metode' => 'Tunai']))->assertSessionHasErrors('nominal');
        $this->post("/pesanan/$order/bayar", $this->postData(['nominal' => '500000.00', 'metode' => 'Tunai']))->assertRedirect();
        $this->assertDatabaseHas('pesanan', ['id' => $order, 'status_bayar' => 'lunas', 'status_produksi' => 'selesai']);
        $payment = DB::table('pembayaran')->where('pesanan_id', $order)->orderBy('id')->value('id');
        $this->get("/pesanan/$order/bukti/$payment")->assertOk()->assertSee('Bukti pembayaran');
        $this->post("/koreksi/pembayaran/$payment", $this->postData(['alasan' => 'Pembayaran uji dibalik']))->assertRedirect();
        $this->assertDatabaseHas('pesanan', ['id' => $order, 'status_bayar' => 'dp']);
        $this->post("/pesanan/$order/bayar", $this->postData(['nominal' => '250000.00', 'metode' => 'Tunai']))->assertRedirect();
        $this->assertSame(4, DB::table('transaksi_kas')->whereIn('pembayaran_id', DB::table('pembayaran')->where('pesanan_id', $order)->select('id'))->count());
        $this->get('/buku-kas')->assertOk()->assertSee('Saldo berjalan');
        $this->get("/pesanan/$order")->assertOk()->assertSee('Riwayat pembayaran');
        $this->get("/pesanan/$order/nota")->assertOk()->assertSee('Nota tagihan');

        $this->as($this->staff);
        $this->get('/buku-kas')->assertForbidden();
        $this->get('/dashboard')->assertForbidden();
        $this->get("/pesanan/$order/nota")->assertForbidden();
        $this->get("/pesanan/$order")->assertOk()->assertDontSee('Harga per unit');
        $this->post("/pesanan/$order/kirim", $this->postData(['status' => 'dikirim']))->assertRedirect();
        $this->post("/pesanan/$order/kirim", $this->postData(['status' => 'selesai']))->assertRedirect();

        $this->as($this->admin);
        $this->get('/laporan/laba-pesanan?q='.DB::table('pesanan')->where('id', $order)->value('kode'))->assertOk()->assertSee('Rp 360.000');
        $this->get('/laporan/arus-kas')->assertOk()->assertSee('Saldo akhir');
        $this->get('/laporan/laba-rugi')->assertOk()->assertSee('Omzet pesanan selesai kirim');
        $this->get('/dashboard')->assertOk()->assertSee('Kontribusi pelanggan');
        $summary = app(Reports::class)->concentration(substr(BusinessDate::today(), 0, 7).'-01', '80');
        $this->assertTrue($summary['rows']->firstWhere('id', $this->customer->id)->peringatan);
    }

    public function test_pending_valuation_rollback_and_stock_cancellation(): void
    {
        $this->as($this->staff);
        $lot = $this->stock('barang', $this->product->id, '2', null, null);
        $this->get("/inventori/barang/{$this->product->id}")->assertOk()->assertDontSee('Nilai sisa');
        $this->get("/inventori/barang/{$this->product->id}/lot/$lot/nilai")->assertForbidden();
        $this->as($this->admin);
        $order = $this->draft('stok', 3, '250000.00');
        $this->from("/pesanan/$order")->post("/pesanan/$order/konfirmasi", $this->postData([]))->assertSessionHasErrors('jumlah');
        $this->get("/inventori/barang/{$this->product->id}/lot/$lot/nilai")->assertOk()->assertSee('Sahkan nilai lot');
        $this->post("/inventori/barang/{$this->product->id}/lot/$lot/nilai", $this->postData(['nilai' => '30000', 'alasan' => 'Nilai awal uji']))
            ->assertRedirect("/inventori/barang/{$this->product->id}");
        $this->post("/pesanan/$order/konfirmasi", $this->postData([]))->assertSessionHasErrors('jumlah');
        $this->assertDatabaseHas('lot_stok', ['id' => $lot, 'jumlah_sisa' => '2.000', 'nilai_sisa' => '30000.00']);
        $this->assertDatabaseHas('pesanan', ['id' => $order, 'status' => 'draf']);
        $this->stock('barang', $this->product->id, '1', '20000', null);
        $this->post("/pesanan/$order/konfirmasi", $this->postData([]))->assertRedirect("/pesanan/$order");
        $this->post("/pesanan/$order/batal", $this->postData(['alasan' => 'Koreksi stok', 'barang_kembali' => '0']))->assertSessionHasErrors('barang_kembali');
        $this->post("/pesanan/$order/batal", $this->postData(['alasan' => 'Barang kembali', 'barang_kembali' => '1']))->assertRedirect("/pesanan/$order");
        $this->assertDatabaseHas('lot_stok', ['id' => $lot, 'jumlah_sisa' => '2.000', 'nilai_sisa' => '30000.00']);
        $this->assertDatabaseHas('pesanan', ['id' => $order, 'status' => 'batal']);
    }

    public function test_custom_order_purchase_fractional_usage_cost_and_controls(): void
    {
        $order = $this->draft('khusus', 1, '350000.00');
        $this->post("/pesanan/$order/konfirmasi", $this->postData([]))->assertRedirect();
        $line = DB::table('detail_pesanan')->where('pesanan_id', $order)->value('id');
        $this->as($this->staff);
        $this->post("/pesanan/$order/kirim", $this->postData(['status' => 'dikirim']))->assertSessionHasErrors('status');
        $this->post("/pesanan/$order/detail/$line/progres", $this->postData(['status' => 'proses']))->assertRedirect();
        $buy = $this->postData([
            'supplier_id' => $this->supplier->id, 'bahan_id' => $this->material->id, 'pesanan_id' => $order,
            'referensi' => 'Kwitansi bahan uji', 'jumlah' => '5.000', 'nominal' => '100000.00', 'metode' => 'Tunai',
        ]);
        $this->get('/pembelian-bahan/tambah')->assertOk()->assertSee('Referensi kuitansi');
        $this->post('/pembelian-bahan', $buy)->assertRedirect('/pembelian-bahan');
        $this->post('/pembelian-bahan', $buy)->assertRedirect('/pembelian-bahan');
        $this->assertSame(1, DB::table('pembelian_bahan')->where('bahan_id', $this->material->id)->count());
        $this->get('/pembelian-bahan')->assertOk()->assertSee('Kwitansi bahan uji')->assertDontSee('Rp 100.000');
        $this->post("/pesanan/$order/detail/$line/pakai", $this->postData(['bahan_id' => $this->material->id, 'jumlah' => '2.500']))->assertRedirect();
        $this->assertDatabaseHas('lot_stok', ['bahan_id' => $this->material->id, 'jumlah_sisa' => '2.500', 'nilai_sisa' => '50000.00']);
        $this->get('/inventori/bahan?filter=minimum')->assertOk()->assertSee('Stok minimum');
        $this->post("/pesanan/$order/detail/$line/progres", $this->postData(['status' => 'siap']))->assertRedirect();
        $this->post("/pesanan/$order/detail/$line/hpp", $this->postData(['upah' => '60000', 'overhead' => '15000']))->assertForbidden();
        $this->as($this->admin);
        $purchase = DB::table('pembelian_bahan')->where('bahan_id', $this->material->id)->value('id');
        $this->post("/koreksi/pembelian/$purchase", $this->postData(['alasan' => 'Bahan sudah dipakai']))->assertSessionHasErrors('alasan');
        $this->get("/pesanan/$order/detail/$line/hpp")->assertOk()->assertSee('Sahkan HPP aktual');
        $this->post("/pesanan/$order/detail/$line/hpp", $this->postData(['upah' => '60000', 'overhead' => '15000']))->assertRedirect();
        $this->assertDatabaseHas('detail_pesanan', ['id' => $line, 'aktual_bahan' => '50000.00', 'hpp_aktual' => '125000.00']);
        config(['operations.syarat_bayar_kirim' => 'lunas']);
        $this->post("/pesanan/$order/kirim", $this->postData(['status' => 'dikirim']))->assertSessionHasErrors('status');
        $this->post("/pesanan/$order/bayar", $this->postData(['nominal' => '350000.00', 'metode' => 'Transfer']))->assertRedirect();
        $this->post("/pesanan/$order/kirim", $this->postData(['status' => 'dikirim']))->assertRedirect();
    }

    public function test_manual_cash_validation_reversal_and_category_balance(): void
    {
        $category = DB::table('kategori_kas')->where('kode', 'OPERASIONAL')->value('id');
        $data = ['kategori_id' => $category, 'arah' => 'masuk', 'nominal' => '40000.00', 'keterangan' => 'Beban uji', 'masuk_hpp' => '0'];
        $this->get('/buku-kas/tambah')->assertOk()->assertSee('Catat transaksi kas');
        $this->post('/buku-kas', $this->postData($data))->assertSessionHasErrors('kategori_id');
        $token = $this->postData([...$data, 'arah' => 'keluar']);
        $this->post('/buku-kas', $token)->assertRedirect('/buku-kas');
        $this->post('/buku-kas', $token)->assertRedirect('/buku-kas');
        $this->post('/buku-kas', [...$token, 'nominal' => '50000'])->assertSessionHasErrors('token');
        $id = DB::table('transaksi_kas')->where('pembuat_id', $this->admin->id)->value('id');
        $this->get('/buku-kas?kategori='.$category)->assertOk()->assertSee('Beban uji');
        $this->post("/koreksi/kas/$id", $this->postData(['alasan' => 'Koreksi uji']))->assertRedirect();
        $this->assertDatabaseHas('transaksi_kas', ['asal_id' => $id, 'arah' => 'masuk', 'nominal' => '40000.00']);
    }

    public function test_concentration_counts_first_settled_month_once_and_uses_strict_threshold(): void
    {
        $now = CarbonImmutable::now('UTC');
        $jan = $now->startOfMonth()->subMonths(2)->toDateString();
        $feb = $now->startOfMonth()->subMonth()->toDateString();
        $orders = app(Orders::class);
        $finance = app(Finance::class);
        $ids = [];
        foreach (['800', '200'] as $amount) {
            $customer = Customer::create(['nama' => 'Pelanggan '.$amount, 'aktif' => true]);
            $id = $orders->saveDraft([
                'pelanggan_id' => $customer->id, 'tanggal' => $jan, 'nonrutin' => 0,
                'detail' => [['produk_id' => $this->product->id, 'jumlah' => 1, 'sumber' => 'khusus', 'harga_satuan' => $amount]],
            ], $this->admin->id);
            $orders->confirm($id, $this->admin->id);
            $ids[] = [$customer->id, $id, $finance->payment($id, $amount, 'Tunai', $this->admin->id, $jan)];
        }
        $result = app(Reports::class)->concentration($jan, '80')['rows']->firstWhere('id', $ids[0][0]);
        $this->assertSame('80.00', $result->persen);
        $this->assertFalse($result->peringatan);
        $reversal = $finance->reversePayment($ids[0][2], $this->admin->id, 'Uji lintas bulan');
        DB::table('pembayaran')->where('id', $reversal)->update(['tanggal' => $feb]);
        $finance->payment($ids[0][1], '800', 'Tunai', $this->admin->id, $feb);
        $this->assertNull(app(Reports::class)->concentration($feb, '80')['rows']->firstWhere('id', $ids[0][0]));
    }

    private function as(User $user): void
    {
        $this->actingAs($user)->withSession(['account_version' => $user->versi_sesi]);
    }

    private function user(string $username, UserRole $role): User
    {
        return User::create(['nama' => $username, 'username' => $username, 'peran' => $role, 'password_hash' => Str::random(30)]);
    }

    private function stock(string $kind, int $item, string $count, ?string $value, ?string $date): int
    {
        $this->post("/inventori/$kind/$item/masuk", $this->postData([
            'jumlah' => $count, 'tanggal_masuk' => $date, 'nilai' => $value, 'kondisi' => 'layak', 'alasan' => 'Saldo awal uji',
        ]))->assertRedirect("/inventori/$kind/$item");

        return DB::table('lot_stok')->where($kind === 'barang' ? 'produk_id' : 'bahan_id', $item)->max('id');
    }

    private function draft(string $source, int $quantity, string $price): int
    {
        $this->post('/pesanan', $this->postData([
            'pelanggan_id' => $this->customer->id, 'nonrutin' => '0',
            'detail' => [['produk_id' => $this->product->id, 'jumlah' => $quantity, 'sumber' => $source, 'harga_satuan' => $price]],
        ]))->assertRedirectContains('/pesanan/');

        return DB::table('pesanan')->where('pelanggan_id', $this->customer->id)->max('id');
    }

    private function postData(array $data): array
    {
        return ['token' => (string) Str::uuid(), ...$data];
    }
}
