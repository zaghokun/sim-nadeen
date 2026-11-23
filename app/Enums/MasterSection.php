<?php

namespace App\Enums;

use App\Models\CashCategory;
use App\Models\CostStandard;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Decimal;
use Illuminate\Validation\Rule;

enum MasterSection: string
{
    case Product = 'produk';
    case Customer = 'pelanggan';
    case Supplier = 'supplier';
    case Cost = 'hpp';
    case Category = 'kategori-kas';
    case Material = 'bahan';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Produk',
            self::Customer => 'Pelanggan',
            self::Supplier => 'Supplier',
            self::Cost => 'Standar HPP',
            self::Category => 'Kategori Kas',
            self::Material => 'Bahan Baku',
        };
    }

    public function model(): string
    {
        return match ($this) {
            self::Product => Product::class,
            self::Customer => Customer::class,
            self::Supplier => Supplier::class,
            self::Cost => CostStandard::class,
            self::Category => CashCategory::class,
            self::Material => Material::class,
        };
    }

    public function isStaffVisible(): bool
    {
        return in_array($this, [self::Product, self::Supplier], true);
    }

    public function columns(bool $admin): array
    {
        return match ($this) {
            self::Product => [
                'kode' => 'Kode produk',
                'nama' => 'Nama produk',
                'kategori' => 'Kategori',
                'varian' => 'Varian',
                ...($admin ? ['harga_standar' => 'Harga standar'] : []),
            ],
            self::Customer => ['nama' => 'Nama pelanggan', 'kontak' => 'Kontak', 'wilayah' => 'Wilayah'],
            self::Material => ['kode' => 'Kode bahan', 'nama' => 'Nama bahan', 'kategori' => 'Kategori', 'satuan' => 'Satuan', 'stok_minimum' => 'Stok minimum'],
            self::Supplier => [
                'nama' => 'Nama supplier',
                'jenis_bahan' => 'Jenis bahan',
                ...($admin ? ['kontak' => 'Kontak'] : []),
            ],
            self::Cost => [
                'produk.kode' => 'Kode produk',
                'produk.nama' => 'Nama produk',
                'berlaku_mulai' => 'Berlaku mulai',
                'total_hpp' => 'Total HPP',
                'markup' => 'Markup',
            ],
            self::Category => [
                'kode' => 'Kode',
                'nama' => 'Nama kategori',
                'arah' => 'Arah kas',
                'kelompok' => 'Kelompok laporan',
                'nonrutin' => 'Jenis',
            ],
        };
    }

    public function fields(): array
    {
        $name = ['label' => 'Nama', 'rules' => ['required', 'string', 'max:120']];
        $code = ['label' => 'Kode', 'rules' => ['bail', 'required', 'string', 'regex:/\A[A-Z0-9][A-Z0-9_-]{0,39}\z/']];
        $contact = ['label' => 'Kontak', 'rules' => ['nullable', 'string', 'max:80']];
        $address = ['label' => 'Alamat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:1000']];
        $money = ['type' => 'decimal', 'rules' => ['required', 'string', 'regex:/\A\d{1,16}(?:\.\d{1,2})?\z/']];

        $fields = match ($this) {
            self::Product => [
                'kode' => [...$code, 'label' => 'Kode produk', 'hint' => 'Gunakan huruf, angka, tanda hubung, atau garis bawah.'],
                'nama' => [...$name, 'label' => 'Nama produk'],
                'kategori' => ['label' => 'Kategori produk', 'rules' => ['required', 'string', 'max:100']],
                'varian' => ['label' => 'Varian', 'rules' => ['nullable', 'string', 'max:100']],
                'harga_standar' => [...$money, 'label' => 'Harga standar (Rp)'],
                'stok_minimum' => [
                    'label' => 'Stok minimum',
                    'type' => 'integer',
                    'rules' => ['required', 'integer', 'between:0,4294967295'],
                ],
            ],
            self::Customer => [
                'nama' => [...$name, 'label' => 'Nama pelanggan'],
                'kontak' => $contact,
                'wilayah' => ['label' => 'Wilayah', 'rules' => ['nullable', 'string', 'max:100']],
                'alamat' => $address,
            ],
            self::Material => [
                'kode' => [...$code, 'label' => 'Kode bahan'],
                'nama' => [...$name, 'label' => 'Nama bahan'],
                'kategori' => ['label' => 'Kategori bahan', 'rules' => ['required', 'string', 'max:100']],
                'satuan' => ['label' => 'Satuan dasar', 'rules' => ['required', 'string', 'max:20']],
                'stok_minimum' => ['label' => 'Stok minimum', 'type' => 'decimal', 'rules' => ['required', 'string', Decimal::QUANTITY]],
            ],
            self::Supplier => [
                'nama' => [...$name, 'label' => 'Nama supplier'],
                'jenis_bahan' => ['label' => 'Jenis bahan', 'rules' => ['nullable', 'string', 'max:100']],
                'kontak' => $contact,
                'alamat' => $address,
            ],
            self::Cost => [
                'produk_id' => ['label' => 'Produk', 'type' => 'select', 'rules' => ['required', 'integer', Rule::exists(Product::class, 'id')]],
                'berlaku_mulai' => ['label' => 'Berlaku mulai', 'type' => 'date', 'rules' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31']],
                'biaya_bahan' => [...$money, 'label' => 'Biaya bahan (Rp)'],
                'upah' => [...$money, 'label' => 'Upah tukang (Rp)'],
                'overhead' => [...$money, 'label' => 'Overhead (Rp)'],
                'markup' => [
                    'label' => 'Markup (%)',
                    'type' => 'decimal',
                    'rules' => ['required', 'string', 'regex:/\A\d{1,4}(?:\.\d{1,3})?\z/'],
                ],
            ],
            self::Category => [
                'kode' => [...$code, 'hint' => 'Kode dipakai sebagai referensi dan tidak dapat diubah setelah disimpan.'],
                'nama' => [...$name, 'label' => 'Nama kategori'],
                'arah' => [
                    'label' => 'Arah kas', 'type' => 'select', 'options' => CashCategory::DIRECTIONS,
                    'rules' => ['required', Rule::in(array_keys(CashCategory::DIRECTIONS))],
                ],
                'kelompok' => [
                    'label' => 'Kelompok laporan', 'type' => 'select', 'options' => CashCategory::GROUPS,
                    'rules' => ['required', Rule::in(array_keys(CashCategory::GROUPS))],
                ],
                'nonrutin' => ['label' => 'Transaksi nonrutin', 'type' => 'checkbox', 'rules' => ['required', 'boolean']],
            ],
        };

        $fields['aktif'] = ['label' => 'Aktif', 'type' => 'checkbox', 'rules' => ['required', 'boolean']];

        return $fields;
    }
}
