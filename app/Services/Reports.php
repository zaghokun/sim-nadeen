<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Reports
{
    public function orderTotals(): Builder
    {
        return DB::table('detail_pesanan')->select('pesanan_id')->groupBy('pesanan_id')->selectRaw('
            SUM(jumlah * harga_satuan) AS omzet,
            SUM(jumlah * (estimasi_bahan + estimasi_upah + estimasi_overhead)) AS estimasi,
            SUM(hpp_aktual) AS biaya_diketahui,
            CASE WHEN COUNT(hpp_aktual) = COUNT(*) THEN SUM(hpp_aktual) END AS hpp,
            GROUP_CONCAT(DISTINCT sumber ORDER BY sumber SEPARATOR ", ") AS sumber');
    }

    public function cash(string $from, string $to, ?int $category = null): array
    {
        $before = DB::table('transaksi_kas')->where('tanggal', '<', $from)
            ->selectRaw("COALESCE(SUM(CASE WHEN arah = 'masuk' THEN nominal ELSE -nominal END), 0) AS saldo")->value('saldo');
        $period = DB::table('transaksi_kas')->whereBetween('tanggal', [$from, $to]);
        $total = (clone $period)->selectRaw("
            COALESCE(SUM(CASE WHEN arah = 'masuk' THEN nominal ELSE 0 END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN arah = 'keluar' THEN nominal ELSE 0 END), 0) AS keluar,
            COALESCE(SUM(CASE WHEN NOT nonrutin AND kelompok <> 'saldo_awal' THEN IF(arah = 'masuk', nominal, -nominal) ELSE 0 END), 0) AS operasional,
            COALESCE(SUM(CASE WHEN nonrutin AND kelompok <> 'saldo_awal' THEN IF(arah = 'masuk', nominal, -nominal) ELSE 0 END), 0) AS nonrutin,
            COALESCE(SUM(CASE WHEN kelompok = 'saldo_awal' THEN IF(arah = 'masuk', nominal, -nominal) ELSE 0 END), 0) AS saldo_diposting")->first();
        $groups = $period->when($category, fn (Builder $q) => $q->where('kategori_id', $category))
            ->select('nama_kategori', 'kelompok', 'nonrutin')->groupBy('nama_kategori', 'kelompok', 'nonrutin')
            ->selectRaw("SUM(IF(arah = 'masuk', nominal, 0)) AS masuk, SUM(IF(arah = 'keluar', nominal, 0)) AS keluar")
            ->orderBy('nama_kategori')->get();

        return [
            'awal' => $before, 'total' => $total, 'kelompok' => $groups,
            'akhir' => bcsub(bcadd($before, $total->masuk, 2), $total->keluar, 2),
        ];
    }

    public function profit(string $from, string $to): array
    {
        $sales = DB::table('pesanan as p')->joinSub($this->orderTotals(), 't', 't.pesanan_id', '=', 'p.id')
            ->where('p.status', 'konfirmasi')->whereBetween('p.tanggal_selesai', [$from, $to])
            ->selectRaw('COALESCE(SUM(t.omzet), 0) AS omzet, COALESCE(SUM(t.biaya_diketahui), 0) AS hpp,
                COUNT(*) AS pesanan, COUNT(*) - COUNT(t.hpp) AS belum_lengkap')->first();
        $expenses = DB::table('transaksi_kas')->whereBetween('tanggal', [$from, $to])->where('kelompok', 'beban')
            ->where('masuk_hpp', false)->where('nonrutin', false)
            ->selectRaw("COALESCE(SUM(IF(arah = 'keluar', nominal, -nominal)), 0) AS total")->value('total');
        $adjustments = DB::table('transaksi_kas')->whereBetween('tanggal', [$from, $to])
            ->where('nonrutin', true)->where('masuk_hpp', false)
            ->where(fn (Builder $q) => $q->where('kelompok', 'beban')
                ->orWhere(fn (Builder $q) => $q->where('kelompok', 'penyesuaian')->whereNull('pembayaran_id')->whereNull('pembelian_id')))
            ->selectRaw("COALESCE(SUM(IF(arah = 'masuk', nominal, -nominal)), 0) AS total")->value('total');
        $result = bcadd(bcsub(bcsub($sales->omzet, $sales->hpp, 2), $expenses, 2), $adjustments, 2);

        return ['sales' => $sales, 'beban' => $expenses, 'penyesuaian' => $adjustments, 'hasil' => $result];
    }

    public function concentration(string $month, string $threshold): array
    {
        $monthPayments = DB::table('pembayaran')->select('pesanan_id')
            ->selectRaw("DATE_FORMAT(tanggal, '%Y-%m-01') AS bulan, SUM(IF(asal_id IS NULL, nominal, -nominal)) AS total")
            ->where('tanggal', '<', date('Y-m-d', strtotime($month.' +1 month UTC')))
            ->groupBy('pesanan_id', 'bulan');
        $running = DB::query()->fromSub($monthPayments, 'p')->select('pesanan_id', 'bulan')
            ->selectRaw('SUM(total) OVER (PARTITION BY pesanan_id ORDER BY bulan ROWS UNBOUNDED PRECEDING) AS terbayar');
        $firstPaid = DB::query()->fromSub($running, 'r')->joinSub($this->orderTotals(), 't', 't.pesanan_id', '=', 'r.pesanan_id')
            ->whereColumn('r.terbayar', '>=', 't.omzet')->select('r.pesanan_id')
            ->selectRaw('MIN(r.bulan) AS pertama')->groupBy('r.pesanan_id');
        $rows = DB::query()->fromSub($firstPaid, 'f')->join('pesanan as p', 'p.id', '=', 'f.pesanan_id')
            ->join('pelanggan as c', 'c.id', '=', 'p.pelanggan_id')->joinSub($this->orderTotals(), 't', 't.pesanan_id', '=', 'p.id')
            ->where('f.pertama', $month)->groupBy('c.id', 'c.nama')->select('c.id', 'c.nama')
            ->selectRaw('COUNT(*) AS pesanan, SUM(t.omzet) AS omzet, COUNT(t.hpp) AS hpp_lengkap, SUM(t.omzet - t.hpp) AS laba')
            ->orderByDesc('omzet')->orderBy('c.id')->get();
        $total = $rows->reduce(fn (string $sum, object $row): string => bcadd($sum, $row->omzet, 2), '0.00');
        $rows = $this->shares($rows, $total, $threshold);

        return ['rows' => $rows, 'omzet' => $total, 'pesanan' => $rows->sum('pesanan')];
    }

    private function shares(Collection $rows, string $total, string $threshold): Collection
    {
        return $rows->map(function (object $row) use ($total, $threshold): object {
            $numerator = bcmul($row->omzet, '100', 4);
            $row->persen = bcround(bcdiv($numerator, $total, 6), 2);
            $row->peringatan = bccomp($numerator, bcmul($total, $threshold, 4), 4) > 0;

            return $row;
        });
    }
}
