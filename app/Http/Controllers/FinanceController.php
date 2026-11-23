<?php

namespace App\Http\Controllers;

use App\Services\Finance;
use App\Services\Orders;
use App\Support\BusinessDate;
use App\Support\Decimal;
use App\Support\ListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceController extends OperationController
{
    public function __construct(private Finance $finance, private Orders $orders) {}

    public function purchases(Request $request): View
    {
        $search = ListFilters::read($request, false)['q'];
        $admin = $request->user()->isAdmin();
        $query = DB::table('pembelian_bahan as p')->join('supplier as s', 's.id', '=', 'p.supplier_id')
            ->join('bahan_baku as b', 'b.id', '=', 'p.bahan_id')->leftJoin('pesanan as o', 'o.id', '=', 'p.pesanan_id')
            ->leftJoin('pembelian_bahan as r', 'r.asal_id', '=', 'p.id')
            ->leftJoin('lot_stok as l', 'l.pembelian_id', '=', 'p.id')
            ->select('p.id', 'p.tanggal', 'p.referensi', 'p.metode', 'p.asal_id', 'p.jumlah', 'p.alasan',
                'b.nama as bahan', 'b.satuan', 's.nama as supplier', 'o.kode as pesanan', 'r.id as pembalik_id', 'l.id as lot_id', 'b.id as bahan_id')
            ->orderByDesc('p.tanggal')->orderByDesc('p.id');
        if ($admin) {
            $query->addSelect('p.nominal');
        }
        if ($search !== '') {
            $pattern = ListFilters::like($search);
            $query->where(fn ($q) => $q->whereLike('p.referensi', $pattern)->orWhereLike('b.nama', $pattern)->orWhereLike('s.nama', $pattern));
        }
        $records = $query->paginate(15)->withQueryString();

        return view('finance.purchases', compact('records', 'admin', 'search'));
    }

    public function purchaseForm(Request $request): View
    {
        $supplier = DB::table('supplier')->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')->all();
        $materials = DB::table('bahan_baku')->where('aktif', true)->orderBy('kode')->get()
            ->mapWithKeys(fn ($row) => [$row->id => $row->kode.' · '.$row->nama.' ('.$row->satuan.')'])->all();
        $orders = DB::table('pesanan')->where('status', 'konfirmasi')->where('status_kirim', 'belum')->orderByDesc('id')->pluck('kode', 'id')->all();

        return $this->editor('Catat pembelian bahan', route('purchases.store'), route('purchases.index'), [
            'supplier_id' => $this->field('Supplier', 'select', options: $supplier),
            'bahan_id' => $this->field('Bahan baku', 'select', options: $materials),
            'pesanan_id' => $this->field('Pesanan terkait', 'select', false, $orders),
            'referensi' => $this->field('Referensi kuitansi'),
            'jumlah' => $this->field('Jumlah dalam satuan bahan', 'decimal'),
            'nominal' => $this->field('Total dibayar (Rp)', 'decimal'),
            'metode' => $this->methodField(),
        ], ['pesanan_id' => $request->integer('pesanan') ?: null], 'Satu baris per bahan. Pakai referensi yang sama untuk isi dari satu kuitansi.');
    }

    public function purchase(Request $request): RedirectResponse
    {
        $this->submit($request, 'pembelian.bahan', [
            'supplier_id' => ['required', 'integer', 'exists:supplier,id'],
            'bahan_id' => ['required', 'integer', 'exists:bahan_baku,id'],
            'pesanan_id' => ['nullable', 'integer', 'exists:pesanan,id'],
            'referensi' => ['required', 'string', 'max:100'],
            'jumlah' => ['required', 'string', Decimal::QUANTITY],
            'nominal' => ['required', 'string', Decimal::MONEY],
            'metode' => $this->methodRules(),
        ], fn (array $d, int $actor) => $this->finance->purchase($d, $actor));

        return redirect()->route('purchases.index')->with('status', 'Pembelian, lot bahan, dan kas keluar dicatat.');
    }

    public function paymentForm(int $order): View
    {
        $record = $this->orders->find($order);
        abort_unless($record->status === 'konfirmasi', 404);
        $due = bcsub($this->orders->total($order), $this->finance->paid($order), 2);

        return $this->editor('Catat pembayaran '.$record->kode, route('payments.store', $order), route('orders.show', $order), [
            'nominal' => $this->field('Nominal diterima (Rp)', 'decimal'),
            'metode' => $this->methodField(),
        ], hint: 'Sisa tagihan saat ini '.Decimal::rupiah($due).'.');
    }

    public function payment(Request $request, int $order): RedirectResponse
    {
        $this->submit($request, 'pesanan.bayar', [
            'nominal' => ['required', 'string', Decimal::MONEY], 'metode' => $this->methodRules(),
        ], fn (array $d, int $actor) => $this->finance->payment($order, $d['nominal'], $d['metode'], $actor), [$order]);

        return redirect()->route('orders.show', $order)->with('status', 'Pembayaran dan kas masuk dicatat.');
    }

    public function cash(Request $request): View
    {
        $category = $request->integer('kategori') ?: null;
        $filters = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'], 'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ]);
        $ledger = DB::table('transaksi_kas')->select('*')->selectRaw("
            SUM(CASE WHEN arah = 'masuk' THEN nominal ELSE -nominal END)
                OVER (ORDER BY tanggal, created_at, id ROWS UNBOUNDED PRECEDING) AS saldo");
        $query = DB::query()->fromSub($ledger, 'kas')->leftJoin('pembayaran as byr', 'byr.id', '=', 'kas.pembayaran_id')
            ->leftJoin('transaksi_kas as r', 'r.asal_id', '=', 'kas.id')
            ->leftJoin('pembelian_bahan as b', 'b.id', '=', 'kas.pembelian_id')->select('kas.*', 'byr.pesanan_id', 'r.id as pembalik_id', 'b.referensi');
        if ($category) {
            $query->where('kas.kategori_id', $category);
        }
        if (! empty($filters['dari'])) {
            $query->where('kas.tanggal', '>=', $filters['dari']);
        }
        if (! empty($filters['sampai'])) {
            $query->where('kas.tanggal', '<=', $filters['sampai']);
        }
        $records = $query->orderByDesc('kas.tanggal')->orderByDesc('kas.created_at')->orderByDesc('kas.id')->paginate(15)->withQueryString();
        $balanceQuery = DB::table('transaksi_kas');
        if (! empty($filters['sampai'])) {
            $balanceQuery->where('tanggal', '<=', $filters['sampai']);
        }
        $balance = $balanceQuery->selectRaw("COALESCE(SUM(CASE WHEN arah = 'masuk' THEN nominal ELSE -nominal END), 0) AS saldo")->value('saldo');
        $categories = DB::table('kategori_kas')->orderBy('nama')->pluck('nama', 'id');

        return view('finance.cash', compact('records', 'category', 'categories', 'filters', 'balance'));
    }

    public function cashForm(): View
    {
        $categories = DB::table('kategori_kas')->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')->all();

        return $this->editor('Catat transaksi kas', route('cash.store'), route('cash.index'), [
            'kategori_id' => $this->field('Kategori kas', 'select', options: $categories),
            'arah' => $this->field('Arah kas', 'select', options: ['masuk' => 'Masuk', 'keluar' => 'Keluar']),
            'nominal' => $this->field('Nominal (Rp)', 'decimal'),
            'keterangan' => $this->field('Keterangan dan referensi', 'textarea'),
            'masuk_hpp' => $this->field('Beban ini sudah termasuk HPP', 'checkbox', false),
        ], hint: 'Tanggal efektif '.BusinessDate::today().' (WIB).');
    }

    public function storeCash(Request $request): RedirectResponse
    {
        $this->submit($request, 'kas.manual', [
            'kategori_id' => ['required', 'integer', 'exists:kategori_kas,id'],
            'arah' => ['required', Rule::in(['masuk', 'keluar'])],
            'nominal' => ['required', 'string', Decimal::MONEY],
            'keterangan' => ['required', 'string', 'max:250'],
            'masuk_hpp' => ['required', 'boolean'],
        ], fn (array $d, int $actor) => $this->finance->manual($d, $actor));

        return redirect()->route('cash.index')->with('status', 'Transaksi kas dicatat.');
    }

    public function reverseForm(string $source, int $record): View
    {
        return $this->editor('Balik '.$source, route('finance.reverse', [$source, $record]), $this->returnUrl($source, $record), [
            'alasan' => $this->field('Alasan pembalikan', 'textarea'),
        ], hint: 'Entri asal tetap tersedia di riwayat.');
    }

    public function reverse(Request $request, string $source, int $record): RedirectResponse
    {
        $back = $this->returnUrl($source, $record);
        $this->submit($request, $source.'.balik', ['alasan' => ['required', 'string', 'max:220']], fn (array $d, int $actor) => match ($source) {
            'pembayaran' => $this->finance->reversePayment($record, $actor, $d['alasan']),
            'pembelian' => $this->finance->reversePurchase($record, $actor, $d['alasan']),
            'kas' => $this->finance->reverseManual($record, $actor, $d['alasan']),
        }, [$record]);

        return redirect($back)->with('status', 'Entri pembalik dibuat.');
    }

    private function returnUrl(string $source, int $id): string
    {
        return match ($source) {
            'pembayaran' => route('orders.show', DB::table('pembayaran')->where('id', $id)->firstOrFail()->pesanan_id),
            'pembelian' => route('purchases.index'),
            'kas' => route('cash.index'),
        };
    }

    private function methodField(): array
    {
        return $this->field('Metode pembayaran', 'select', options: ['Tunai' => 'Tunai', 'Transfer' => 'Transfer', 'Lainnya' => 'Lainnya']);
    }

    private function methodRules(): array
    {
        return ['required', Rule::in(['Tunai', 'Transfer', 'Lainnya'])];
    }
}
