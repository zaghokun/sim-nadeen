<?php

namespace App\Services;

use App\Enums\StockKind;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Finance
{
    public function __construct(private Orders $orders, private Inventory $inventory) {}

    public function paid(int $order): string
    {
        return DB::table('pembayaran')->where('pesanan_id', $order)
            ->selectRaw('COALESCE(SUM(CASE WHEN asal_id IS NULL THEN nominal ELSE -nominal END), 0) AS total')->value('total');
    }

    public function payment(int $orderId, string $amount, string $method, int $actor, ?string $date = null): int
    {
        $order = $this->orders->find($orderId, true);
        $date ??= BusinessDate::today();
        $total = $this->orders->total($orderId);
        $paid = $this->paid($orderId);
        $after = bcadd($paid, $amount, 2);
        if ($order->status !== 'konfirmasi' || bccomp($amount, '0', 2) <= 0 || bccomp($after, $total, 2) > 0 || $date < $order->tanggal) {
            throw ValidationException::withMessages(['nominal' => 'Pembayaran memerlukan pesanan terkonfirmasi dan nominal positif yang tidak melebihi sisa tagihan. Periksa tanggal pembayaran.']);
        }

        $fullyPaid = bccomp($after, $total, 2) === 0;
        $type = $fullyPaid ? 'pelunasan' : (bccomp($paid, '0', 2) === 0 ? 'dp' : 'cicilan');
        $id = DB::table('pembayaran')->insertGetId([
            'pesanan_id' => $orderId, 'pembuat_id' => $actor, 'tanggal' => $date, 'nominal' => $amount,
            'jenis' => $type, 'metode' => $method, 'created_at' => now(),
        ]);
        $category = $this->category($order->nonrutin ? 'JUAL_STOCK_NON_RUTIN' : 'PENJUALAN');
        $this->writeCash($category, 'masuk', $amount, 'Pembayaran '.$order->kode, $actor, $date, ['pembayaran_id' => $id]);
        $this->orders->change($order, 'status_bayar', $fullyPaid ? 'lunas' : 'dp', $actor);

        return $id;
    }

    public function reversePayment(int $id, int $actor, string $reason): int
    {
        $payment = DB::table('pembayaran')->where('id', $id)->firstOrFail();
        $order = $this->orders->find($payment->pesanan_id, true);
        if ($payment->asal_id || $order->status !== 'konfirmasi' || DB::table('pembayaran')->where('asal_id', $id)->exists()) {
            throw ValidationException::withMessages(['alasan' => 'Pembayaran ini sudah dibalik atau tidak tersedia.']);
        }

        $date = BusinessDate::today();
        $result = DB::table('pembayaran')->insertGetId([
            'pesanan_id' => $order->id, 'pembuat_id' => $actor, 'asal_id' => $id, 'tanggal' => $date,
            'nominal' => $payment->nominal, 'jenis' => 'pembalik', 'metode' => $payment->metode,
            'alasan' => $reason, 'created_at' => now(),
        ]);
        $source = DB::table('transaksi_kas')->where('pembayaran_id', $id)->firstOrFail();
        $this->reverseCash($source, $reason, $actor, ['pembayaran_id' => $result]);
        $remainingPaid = $this->paid($order->id);
        $this->orders->change($order, 'status_bayar', bccomp($remainingPaid, '0', 2) === 0 ? 'menunggu' : 'dp', $actor, reason: $reason);

        return $result;
    }

    public function purchase(array $data, int $actor, ?string $date = null): int
    {
        $date ??= BusinessDate::today();
        $orderId = $data['pesanan_id'] ?? null;
        if ($orderId) {
            $order = $this->orders->find($orderId, true);
            if ($order->status !== 'konfirmasi' || $order->status_kirim !== 'belum') {
                throw ValidationException::withMessages(['pesanan_id' => 'Pilih pesanan terkonfirmasi yang belum dikirim.']);
            }
        }
        $supplier = DB::table('supplier')->where('id', $data['supplier_id'])->lockForUpdate()->firstOrFail();
        if (! $supplier->aktif) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier sudah nonaktif.']);
        }

        $id = DB::table('pembelian_bahan')->insertGetId([
            'supplier_id' => $supplier->id, 'bahan_id' => $data['bahan_id'], 'pesanan_id' => $orderId,
            'pembuat_id' => $actor, 'tanggal' => $date, 'referensi' => $data['referensi'],
            'metode' => $data['metode'], 'jumlah' => $data['jumlah'], 'nominal' => $data['nominal'], 'created_at' => now(),
        ]);
        $this->inventory->receive(StockKind::Material, $data['bahan_id'], $data['jumlah'], $data['nominal'],
            $date, 'layak', $actor, 'Pembelian '.$data['referensi'], $id);
        $this->writeCash($this->category('BAHAN_BAKU'), 'keluar', $data['nominal'], 'Belanja bahan '.$data['referensi'], $actor, $date, ['pembelian_id' => $id]);

        return $id;
    }

    public function reversePurchase(int $id, int $actor, string $reason): int
    {
        $purchase = DB::table('pembelian_bahan')->where('id', $id)->lockForUpdate()->firstOrFail();
        if ($purchase->asal_id || DB::table('pembelian_bahan')->where('asal_id', $id)->exists()) {
            throw ValidationException::withMessages(['alasan' => 'Pembelian ini tidak bisa dibalik lagi.']);
        }
        $lot = DB::table('lot_stok')->where('pembelian_id', $id)->firstOrFail();
        $this->inventory->undoPurchase($lot->id, $actor, $reason);
        $result = DB::table('pembelian_bahan')->insertGetId([
            'asal_id' => $id, 'supplier_id' => $purchase->supplier_id, 'bahan_id' => $purchase->bahan_id,
            'pesanan_id' => $purchase->pesanan_id, 'pembuat_id' => $actor, 'tanggal' => BusinessDate::today(),
            'referensi' => $purchase->referensi, 'metode' => $purchase->metode, 'jumlah' => $purchase->jumlah,
            'nominal' => $purchase->nominal, 'alasan' => $reason, 'created_at' => now(),
        ]);
        $source = DB::table('transaksi_kas')->where('pembelian_id', $id)->firstOrFail();
        $this->reverseCash($source, $reason, $actor, ['pembelian_id' => $result]);

        return $result;
    }

    public function manual(array $data, int $actor, ?string $date = null): int
    {
        $category = DB::table('kategori_kas')->where('id', $data['kategori_id'])->lockForUpdate()->firstOrFail();
        $date ??= BusinessDate::today();
        if ($category->kelompok === 'saldo_awal') {
            $exists = DB::table('transaksi_kas as kas')->where('kelompok', 'saldo_awal')->whereNull('asal_id')
                ->whereNotExists(fn ($query) => $query->from('transaksi_kas as pembalik')->whereColumn('pembalik.asal_id', 'kas.id'))
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['kategori_id' => 'Saldo awal aktif sudah dicatat.']);
            }
        }
        if (! empty($data['masuk_hpp']) && ($category->kelompok !== 'beban' || $data['arah'] !== 'keluar')) {
            throw ValidationException::withMessages(['masuk_hpp' => 'Penanda HPP berlaku untuk beban kas keluar.']);
        }

        return $this->writeCash($category, $data['arah'], $data['nominal'], $data['keterangan'], $actor, $date, [
            'masuk_hpp' => $data['masuk_hpp'] ?? false,
        ]);
    }

    public function reverseManual(int $id, int $actor, string $reason): int
    {
        $source = DB::table('transaksi_kas')->where('id', $id)->lockForUpdate()->firstOrFail();
        if ($source->pembayaran_id || $source->pembelian_id || $source->asal_id || DB::table('transaksi_kas')->where('asal_id', $id)->exists()) {
            throw ValidationException::withMessages(['alasan' => 'Balik jurnal otomatis dari dokumen sumbernya. Jurnal pembalik hanya dibuat satu kali.']);
        }

        return $this->reverseCash($source, $reason, $actor);
    }

    private function category(string $code): object
    {
        $category = DB::table('kategori_kas')->where('kode', $code)->first();
        if (! $category) {
            throw ValidationException::withMessages(['kategori_id' => "Kategori kas {$code} belum tersedia."]);
        }

        return $category;
    }

    private function writeCash(object $category, string $direction, string $amount, string $description, int $actor, string $date, array $source): int
    {
        if (! $category->aktif || ! in_array($category->arah, [$direction, 'keduanya'], true) || bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages(['kategori_id' => 'Gunakan kategori aktif yang sesuai arah kas dan nominal positif.']);
        }

        return DB::table('transaksi_kas')->insertGetId([
            'kategori_id' => $category->id, 'nama_kategori' => $category->nama, 'kelompok' => $category->kelompok,
            'nonrutin' => $category->nonrutin, 'tanggal' => $date, 'arah' => $direction, 'nominal' => $amount,
            'keterangan' => $description, 'pembuat_id' => $actor, 'created_at' => now(), ...$source,
        ]);
    }

    private function reverseCash(object $source, string $reason, int $actor, array $link = []): int
    {
        return DB::table('transaksi_kas')->insertGetId([
            'asal_id' => $source->id, 'kategori_id' => $source->kategori_id, 'nama_kategori' => $source->nama_kategori,
            'kelompok' => $source->kelompok, 'nonrutin' => $source->nonrutin, 'masuk_hpp' => $source->masuk_hpp,
            'tanggal' => BusinessDate::today(), 'arah' => $source->arah === 'masuk' ? 'keluar' : 'masuk',
            'nominal' => $source->nominal, 'keterangan' => $reason, 'pembuat_id' => $actor, 'created_at' => now(), ...$link,
        ]);
    }
}
