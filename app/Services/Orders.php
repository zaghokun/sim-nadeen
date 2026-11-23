<?php

namespace App\Services;

use App\Enums\StockKind;
use App\Support\BusinessDate;
use App\Support\Decimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Orders
{
    public const LABELS = [
        'draf' => 'Draf', 'konfirmasi' => 'Terkonfirmasi', 'batal' => 'Dibatalkan',
        'menunggu' => 'Menunggu pembayaran', 'dp' => 'DP', 'lunas' => 'Lunas',
        'cicilan' => 'Cicilan', 'pelunasan' => 'Pelunasan', 'pembalik' => 'Pembalik',
        'antri' => 'Antri', 'proses' => 'Dalam proses', 'siap' => 'Siap', 'selesai' => 'Selesai',
        'belum' => 'Belum diantar', 'dikirim' => 'Dikirim', 'stok' => 'Stok', 'khusus' => 'Pesanan khusus',
    ];

    public function __construct(private Inventory $inventory) {}

    public function lines(int $order): Builder
    {
        return DB::table('detail_pesanan')->where('pesanan_id', $order);
    }

    public function find(int $id, bool $lock = false): object
    {
        $query = DB::table('pesanan')->where('id', $id);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public function estimate(int $product, string $date): ?array
    {
        $cost = DB::table('komponen_hpp')->where('produk_id', $product)->where('aktif', true)
            ->where('berlaku_mulai', '<=', $date)->orderByDesc('berlaku_mulai')->first();
        if (! $cost) {
            return null;
        }

        $total = bcadd(bcadd($cost->biaya_bahan, $cost->upah, 2), $cost->overhead, 2);
        $suggested = bcround(bcmul($total, bcadd('1', bcdiv($cost->markup, '100', 5), 5), 7), 2);

        return [
            'estimasi_bahan' => $cost->biaya_bahan, 'estimasi_upah' => $cost->upah,
            'estimasi_overhead' => $cost->overhead, 'markup' => $cost->markup,
            'total' => $total, 'saran' => $suggested,
        ];
    }

    public function saveDraft(array $data, int $actor, ?int $order = null): int
    {
        $date = $data['tanggal'] ?? BusinessDate::today();
        if ($order !== null) {
            $existing = $this->find($order, true);
            $this->requireStatus($existing, 'draf');
            $date = $existing->tanggal;
        }
        $customer = DB::table('pelanggan')->where('id', $data['pelanggan_id'])->lockForUpdate()->firstOrFail();
        if (! $customer->aktif) {
            throw ValidationException::withMessages(['pelanggan_id' => 'Pelanggan sudah nonaktif.']);
        }

        $products = DB::table('produk')->whereIn('id', array_column($data['detail'], 'produk_id'))
            ->where('aktif', true)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $header = [
            'pelanggan_id' => $customer->id, 'tanggal' => $date, 'nonrutin' => $data['nonrutin'],
            'catatan' => $data['catatan'] ?? null, 'updated_at' => now(),
        ];
        if ($order === null) {
            $order = DB::table('pesanan')->insertGetId([...$header, 'pembuat_id' => $actor, 'created_at' => now()]);
            DB::table('pesanan')->where('id', $order)->update(['kode' => 'ORD-'.substr($date, 0, 4).'-'.str_pad((string) $order, 6, '0', STR_PAD_LEFT)]);
            $this->history($order, $actor, 'status', null, 'draf');
        } else {
            DB::table('pesanan')->where('id', $order)->update($header);
            $this->lines($order)->delete();
        }

        $total = '0.00';
        foreach ($data['detail'] as $input) {
            $product = $products->get($input['produk_id']);
            if (! $product) {
                throw ValidationException::withMessages(['detail' => 'Pilih produk yang masih aktif.']);
            }

            $cost = $this->estimate($product->id, $date);
            $price = $input['harga_satuan'] ?? '0.00';
            $total = bcadd($total, bcmul($price, (string) $input['jumlah'], 2), 2);
            DB::table('detail_pesanan')->insert([
                'pesanan_id' => $order, 'produk_id' => $product->id, 'kode_produk' => $product->kode,
                'nama_produk' => $product->nama, 'jumlah' => $input['jumlah'], 'sumber' => $input['sumber'],
                'harga_satuan' => $price, ...$this->snapshot($cost),
            ]);
        }
        Decimal::storedMoney($total, 'detail');

        return $order;
    }

    public function confirm(int $id, int $actor, ?string $stockAsOf = null): int
    {
        $order = $this->find($id, true);
        $this->requireStatus($order, 'draf');
        $customer = DB::table('pelanggan')->where('id', $order->pelanggan_id)->lockForUpdate()->firstOrFail();
        if (! $customer->aktif) {
            throw ValidationException::withMessages(['pesanan' => 'Pelanggan sudah nonaktif.']);
        }

        $lines = $this->lines($id)->orderBy('produk_id')->orderBy('id')->get();
        foreach ($lines as $line) {
            $product = DB::table('produk')->where('id', $line->produk_id)->lockForUpdate()->firstOrFail();
            $cost = $this->estimate($line->produk_id, $order->tanggal);
            if (! $product->aktif || bccomp($line->harga_satuan, '0', 2) <= 0 || ! $cost) {
                throw ValidationException::withMessages(['pesanan' => "Periksa produk aktif, harga positif, dan standar HPP untuk {$line->kode_produk}."]);
            }
            if (! $order->catatan && in_array('0.00', [$cost['estimasi_bahan'], $cost['estimasi_upah'], $cost['estimasi_overhead']], true)) {
                throw ValidationException::withMessages(['pesanan' => "Jelaskan komponen HPP bernilai nol untuk {$line->kode_produk} pada catatan draf."]);
            }
            $changes = [...$this->snapshot($cost), 'kode_produk' => $product->kode, 'nama_produk' => $product->nama];
            if ($line->sumber === 'stok') {
                $actual = $this->inventory->issue(StockKind::Product, $line->produk_id, (string) $line->jumlah, $actor, 'Alokasi '.$order->kode, $line->id, $stockAsOf);
                $changes += [
                    'hpp_aktual' => Decimal::storedMoney($actual, 'pesanan'), 'hpp_disahkan_pada' => now(),
                    'kesiapan' => 'siap',
                ];
                $this->history($id, $actor, 'kesiapan', 'antri', 'siap', $line->id, 'Alokasi barang jadi');
            }
            $this->lines($id)->where('id', $line->id)->update($changes);
        }

        $this->change($order, 'status', 'konfirmasi', $actor, ['dikonfirmasi_pada' => now()]);
        $this->refreshProduction($id, $actor);

        return $id;
    }

    public function total(int $id): string
    {
        return $this->lines($id)->selectRaw('COALESCE(SUM(jumlah * harga_satuan), 0) AS total')->value('total');
    }

    public function setPreparation(int $orderId, int $lineId, string $next, int $actor): int
    {
        [$order, $line] = $this->customLine($orderId, $lineId);
        $allowed = ['antri' => 'proses', 'proses' => 'siap'];
        if ($order->status_kirim !== 'belum' || ($allowed[$line->kesiapan] ?? null) !== $next) {
            throw ValidationException::withMessages(['status' => 'Perubahan progres ini tidak tersedia.']);
        }

        $this->lines($orderId)->where('id', $lineId)->update(['kesiapan' => $next]);
        $this->history($orderId, $actor, 'kesiapan', $line->kesiapan, $next, $lineId);
        $this->refreshProduction($orderId, $actor);

        return $orderId;
    }

    public function consume(int $orderId, int $lineId, int $material, string $quantity, int $actor): int
    {
        [$order, $line] = $this->customLine($orderId, $lineId);
        if ($order->status_kirim !== 'belum' || $line->kesiapan !== 'proses' || $line->hpp_disahkan_pada !== null) {
            throw ValidationException::withMessages(['jumlah' => 'Pemakaian hanya bisa dicatat ketika pengerjaan sedang berlangsung.']);
        }

        $this->inventory->issue(StockKind::Material, $material, $quantity, $actor, 'Pemakaian '.$order->kode, $lineId);

        return $orderId;
    }

    public function approveCost(int $orderId, int $lineId, string $labor, string $overhead, int $actor): int
    {
        [, $line] = $this->customLine($orderId, $lineId);
        if ($line->kesiapan !== 'siap' || $line->hpp_disahkan_pada !== null) {
            throw ValidationException::withMessages(['upah' => 'HPP hanya bisa disahkan satu kali setelah barang siap.']);
        }
        $materials = DB::table('mutasi_stok')->where('detail_id', $lineId)
            ->selectRaw('COALESCE(-SUM(perubahan_nilai), 0) AS total')->value('total');
        $total = Decimal::storedMoney(bcadd(bcadd($materials, $labor, 2), $overhead, 2), 'upah');

        $this->lines($orderId)->where('id', $lineId)->update([
            'aktual_bahan' => $materials, 'aktual_upah' => $labor, 'aktual_overhead' => $overhead,
            'hpp_aktual' => $total, 'hpp_disahkan_pada' => now(),
        ]);
        $this->history($orderId, $actor, 'hpp', null, 'disahkan', $lineId);

        return $orderId;
    }

    public function reverseMaterial(int $orderId, int $lineId, int $moveId, int $actor, string $reason): int
    {
        [$order, $line] = $this->customLine($orderId, $lineId);
        DB::table('mutasi_stok')->where('id', $moveId)->where('detail_id', $lineId)->firstOrFail();
        if ($order->status_kirim !== 'belum' || $line->hpp_disahkan_pada !== null) {
            throw ValidationException::withMessages(['alasan' => 'Koreksi pemakaian dilakukan sebelum pengiriman dan pengesahan HPP.']);
        }

        $this->inventory->undoIssue($moveId, $actor, $reason);

        return $orderId;
    }

    public function ship(int $id, string $next, int $actor): int
    {
        $order = $this->find($id, true);
        $this->requireStatus($order, 'konfirmasi');
        if (($order->status_kirim === 'belum' && $next !== 'dikirim') || ($order->status_kirim === 'dikirim' && $next !== 'selesai') || $order->status_kirim === 'selesai') {
            throw ValidationException::withMessages(['status' => 'Ikuti urutan status pengiriman.']);
        }
        if ($next === 'dikirim') {
            $policy = config('operations.syarat_bayar_kirim');
            $paymentReady = match ($policy) {
                'dp' => in_array($order->status_bayar, ['dp', 'lunas'], true),
                'lunas' => $order->status_bayar === 'lunas',
                'bebas' => true,
                default => false,
            };
            if ($order->status_produksi !== 'selesai' || ! $paymentReady) {
                throw ValidationException::withMessages(['status' => 'Pengiriman memerlukan barang siap dan syarat pembayaran terpenuhi.']);
            }
        }
        $changes = $next === 'selesai' ? ['tanggal_selesai' => BusinessDate::today()] : [];
        $this->change($order, 'status_kirim', $next, $actor, $changes);

        return $id;
    }

    public function cancel(int $id, int $actor, string $reason, bool $returned): int
    {
        $order = $this->find($id, true);
        if ($order->status === 'batal' || $order->status_kirim !== 'belum' || $order->status_bayar !== 'menunggu') {
            throw ValidationException::withMessages(['alasan' => 'Batalkan sebelum dikirim dan setelah pembayaran dibalik.']);
        }
        $movements = DB::table('mutasi_stok as m')->join('detail_pesanan as d', 'd.id', '=', 'm.detail_id')
            ->where('d.pesanan_id', $id)->where('d.sumber', 'stok')->where('m.jenis', 'keluar')->orderBy('m.lot_id')->pluck('m.id');
        if ($movements->isNotEmpty() && ! $returned) {
            throw ValidationException::withMessages(['barang_kembali' => 'Konfirmasi barang siap jual sudah kembali ke gudang.']);
        }
        foreach ($movements as $movement) {
            $this->inventory->undoIssue($movement, $actor, $reason);
        }

        $this->change($order, 'status', 'batal', $actor, reason: $reason);

        return $id;
    }

    public function change(object $order, string $field, string $next, int $actor, array $changes = [], ?string $reason = null): void
    {
        if ($order->{$field} !== $next) {
            DB::table('pesanan')->where('id', $order->id)->update([$field => $next, 'updated_at' => now(), ...$changes]);
            $this->history($order->id, $actor, $field, $order->{$field}, $next, reason: $reason);
        }
    }

    private function history(int $order, int $actor, string $dimension, ?string $before, string $after, ?int $line = null, ?string $reason = null): void
    {
        DB::table('riwayat_status_pesanan')->insert([
            'pesanan_id' => $order, 'detail_id' => $line, 'aktor_id' => $actor, 'dimensi' => $dimension,
            'sebelum' => $before, 'sesudah' => $after, 'alasan' => $reason, 'created_at' => now(),
        ]);
    }

    private function refreshProduction(int $id, int $actor): void
    {
        $states = $this->lines($id)->pluck('kesiapan');
        $next = $states->every(fn (string $state) => $state === 'siap')
            ? 'selesai'
            : ($states->contains('proses') || $states->contains('siap') ? 'proses' : 'antri');
        $this->change($this->find($id, true), 'status_produksi', $next, $actor);
    }

    private function customLine(int $orderId, int $lineId): array
    {
        $order = $this->find($orderId, true);
        $this->requireStatus($order, 'konfirmasi');
        $line = $this->lines($orderId)->where('id', $lineId)->where('sumber', 'khusus')->lockForUpdate()->firstOrFail();

        return [$order, $line];
    }

    private function requireStatus(object $order, string $status): void
    {
        if ($order->status !== $status) {
            throw ValidationException::withMessages(['pesanan' => 'Status pesanan sudah berubah. Buka kembali halaman untuk melanjutkan.']);
        }
    }

    private function snapshot(?array $cost): array
    {
        return array_intersect_key($cost ?? [], array_flip(['estimasi_bahan', 'estimasi_upah', 'estimasi_overhead', 'markup']));
    }
}
