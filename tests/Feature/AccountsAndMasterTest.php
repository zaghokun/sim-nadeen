<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CashCategory;
use App\Models\CostStandard;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class AccountsAndMasterTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Test-password-2026';

    public function test_admin_login_and_logout_use_the_session(): void
    {
        $admin = $this->account('t02-admin', UserRole::Admin);
        $this->get('/master/produk')->assertRedirect(route('login'));
        $this->from('/')->post('/masuk', ['username' => $admin->username, 'password' => 'salah'])
            ->assertSessionHasErrors('username');

        $this->post('/masuk', ['username' => 'T02-ADMIN', 'password' => self::PASSWORD])
            ->assertRedirect('/master/produk');
        $this->assertAuthenticatedAs($admin);
        $this->get('/master/produk')->assertOk()->assertSee('Tambah produk');
        $this->post('/keluar')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_and_inactive_accounts_are_rejected(): void
    {
        $staff = $this->account('t02-nonaktif', UserRole::Production);
        $staff->update(['aktif' => false]);

        foreach (range(1, 5) as $attempt) {
            $this->from('/')->post('/masuk', ['username' => $staff->username, 'password' => self::PASSWORD])
                ->assertSessionHasErrors(['username' => 'Nama pengguna atau kata sandi tidak sesuai.']);
        }

        $this->from('/')->post('/masuk', ['username' => $staff->username, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('username');
        $this->assertStringStartsWith('Terlalu banyak percobaan', session('errors')->first('username'));
        $this->assertGuest();
    }

    public function test_production_can_read_active_products_and_suppliers_only(): void
    {
        $staff = $this->account('t02-produksi', UserRole::Production);
        Product::create($this->product('T02-PUBLIK'));
        Product::create([...$this->product('T02-ARSIP'), 'aktif' => false]);
        Supplier::create(['nama' => 'Supplier T02', 'jenis_bahan' => 'Kayu', 'kontak' => 'Kontak privat T02', 'aktif' => true]);

        $this->post('/masuk', ['username' => $staff->username, 'password' => self::PASSWORD])
            ->assertRedirect('/master/produk');
        $this->get('/master/produk')->assertOk()->assertSee('T02-PUBLIK')->assertDontSee('T02-ARSIP')
            ->assertDontSee('Harga standar')->assertDontSee('Tambah produk');
        $this->get('/master/supplier')->assertOk()->assertSee('Supplier T02')->assertDontSee('Kontak privat T02');
        $this->get('/master/hpp')->assertForbidden();
        $this->get('/master/pelanggan')->assertForbidden();
        $this->get('/master/kategori-kas')->assertForbidden();
        $this->get('/akun')->assertForbidden();
        $this->post('/master/produk', $this->product('T02-DITOLAK'))->assertForbidden();
        $this->assertDatabaseMissing('produk', ['kode' => 'T02-DITOLAK']);
    }

    public function test_admin_can_create_an_account_and_revoke_its_old_session(): void
    {
        $admin = $this->account('t02-admin-akun', UserRole::Admin);
        $this->actingAs($admin)->withSession(['account_version' => 0]);
        $this->get('/akun/tambah')->assertOk();

        $form = [
            'nama' => 'Staf T02',
            'username' => 'T02-Staf-Baru',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'peran' => UserRole::Production->value,
            'aktif' => '1',
        ];
        $this->post('/akun', $form)->assertRedirect('/akun');
        $staff = User::where('username', 't02-staf-baru')->firstOrFail();
        $this->assertTrue(Hash::check(self::PASSWORD, $staff->password_hash));

        $this->get('/akun?q=t02-staf')->assertOk()->assertSee('Staf T02')->assertDontSee(self::PASSWORD);
        $this->patch('/akun/'.$admin->id, [
            'nama' => $admin->nama, 'username' => $admin->username, 'peran' => 'produksi', 'aktif' => '0',
        ])->assertSessionHasErrors('peran');
        $this->assertTrue($admin->fresh()->isAdmin());

        $this->patch('/akun/'.$staff->id, [
            ...$form, 'password' => 'Test-new-password-2026', 'password_confirmation' => 'Test-new-password-2026',
        ])->assertRedirect('/akun');
        $staff = $staff->fresh();
        $this->assertSame(1, $staff->versi_sesi);
        $this->assertTrue(Hash::check('Test-new-password-2026', $staff->password_hash));
        $this->actingAs($staff)->withSession(['account_version' => 0]);
        $this->get('/master/produk')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_can_manage_master_records_and_product_standard_versions(): void
    {
        $admin = $this->account('t02-admin-master', UserRole::Admin);
        $this->actingAs($admin)->withSession(['account_version' => 0]);

        $data = $this->product('t02_kursi');
        $this->get('/master/produk/tambah')->assertOk();
        $this->post('/master/produk', $data)->assertRedirect('/master/produk');
        $product = Product::where('kode', 'T02_KURSI')->firstOrFail();
        $this->post('/master/produk', $data)->assertSessionHasErrors('kode');
        Product::create($this->product('T02XKURSI'));
        $this->get('/master/produk?q=T02_KURSI')->assertOk()->assertSee('T02_KURSI')->assertDontSee('T02XKURSI');
        $this->get('/master/produk/'.$product->id.'/ubah')->assertOk()->assertSee('T02_KURSI');
        $this->patch('/master/produk/'.$product->id, [...$data, 'aktif' => '0'])->assertRedirect('/master/produk');
        $this->get('/master/produk?status=nonaktif')->assertOk()->assertSee('T02_KURSI')->assertDontSee('T02XKURSI');

        $this->post('/master/pelanggan', ['nama' => 'Pelanggan T02', 'aktif' => '1'])->assertRedirect('/master/pelanggan');
        $this->get('/master/pelanggan')->assertOk()->assertSee('Pelanggan T02');
        $this->post('/master/supplier', ['nama' => 'Supplier T02', 'aktif' => '1'])->assertRedirect('/master/supplier');
        $this->get('/master/supplier')->assertOk()->assertSee('Supplier T02');

        $cost = [
            'produk_id' => $product->id, 'berlaku_mulai' => '2026-11-23', 'biaya_bahan' => '100.10',
            'upah' => '20.20', 'overhead' => '1.30', 'markup' => '12.500', 'aktif' => '1',
        ];
        $this->get('/master/hpp/tambah')->assertOk()->assertSee('T02_KURSI');
        $this->post('/master/hpp', $cost)->assertRedirect('/master/hpp');
        $this->post('/master/hpp', $cost)->assertSessionHasErrors('berlaku_mulai');
        $this->post('/master/hpp', [...$cost, 'berlaku_mulai' => '2026-12-01'])->assertRedirect('/master/hpp');
        $this->assertSame('121.60', CostStandard::where('produk_id', $product->id)->firstOrFail()->total_hpp);
        $this->get('/master/hpp?q=T02_KURSI')->assertOk()->assertSee('Rp 121,60')->assertSee('12,5%');
    }

    public function test_cash_categories_can_be_seeded_again_without_replacing_edits(): void
    {
        $admin = $this->account('t02-admin-kas', UserRole::Admin);
        $this->actingAs($admin)->withSession(['account_version' => 0]);
        $this->assertSame(0, Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]));
        $sale = CashCategory::where('kode', 'PENJUALAN')->firstOrFail();
        $this->assertDatabaseHas('kategori_kas', ['kode' => 'JUAL_STOCK_NON_RUTIN', 'nonrutin' => true]);
        $this->get('/master/kategori-kas/'.$sale->id.'/ubah')->assertOk()->assertSee('readonly');
        $this->patch('/master/kategori-kas/'.$sale->id, [
            'kode' => 'KODE-BARU', 'nama' => 'Penjualan T02', 'arah' => 'masuk', 'kelompok' => 'pendapatan',
            'nonrutin' => '0', 'aktif' => '1',
        ])->assertRedirect('/master/kategori-kas');
        $this->assertSame(0, Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]));
        $this->assertDatabaseHas('kategori_kas', ['id' => $sale->id, 'kode' => 'PENJUALAN', 'nama' => 'Penjualan T02']);
    }

    public function test_interactive_command_creates_admin_with_a_hashed_password(): void
    {
        $command = new CommandTester(Artisan::all()['nadeen:buat-admin']);
        $command->setInputs(['Admin CLI T02', 'T02-admin-CLI', self::PASSWORD, self::PASSWORD]);
        $command->execute([]);
        $command->assertCommandIsSuccessful();

        $admin = User::where('username', 't02-admin-cli')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password_hash));
    }

    private function account(string $username, UserRole $role): User
    {
        return User::create([
            'username' => $username, 'nama' => $username, 'peran' => $role, 'aktif' => true,
            'password_hash' => self::PASSWORD,
        ]);
    }

    private function product(string $code): array
    {
        return [
            'kode' => $code, 'nama' => 'Kursi T02', 'kategori' => 'Kursi', 'varian' => 'Kayu',
            'harga_standar' => '840000.00', 'stok_minimum' => '3', 'aktif' => '1',
        ];
    }
}
