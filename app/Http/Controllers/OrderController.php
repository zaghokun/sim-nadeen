<?php

namespace App\Http\Controllers;

use App\Services\Finance;
use App\Services\Orders;
use App\Support\BusinessDate;
use App\Support\Decimal;
use App\Support\ListFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends OperationController
{
    public function __construct(private Orders $orders, private Finance $finance) {}

    public function index(Request $request): View
    {
        $admin = $request->user()->isAdmin();
        $search = ListFilters::read($request, false)['q'];
        $status = $request->query('status');
        $status = $admin && in_array($status, ['draf', 'konfirmasi', 'batal'], true) ? $status : '';
        $query = DB::table('pesanan as p')->join('pelanggan as c', 'c.id', '=', 'p.pelanggan_id')
            ->select('p.*', 'c.nama as pelanggan')->orderByDesc('p.tanggal')->orderByDesc('p.id');
        if (! $admin || $status !== '') {
            $query->where('p.status', $admin ? $status : 'konfirmasi');
        }
        if ($search !== '') {
            $term = ListFilters::like($search);
            $query->where(fn ($builder) => $builder->whereLike('p.kode', $term)->orWhereLike('c.nama', $term));
        }
        $records = $query->paginate(15)->withQueryString();

        return view('orders.index', compact('records', 'admin', 'search', 'status'));
    }

    public function show(Request $request, int $order): View
    {
        $record = $this->visibleOrder($request, $order);
        $customer = DB::table('pelanggan')->where('id', $record->pelanggan_id)->firstOrFail();
        $lines = $this->orders->lines($order)->orderBy('id')->get();
        $history = DB::table('riwayat_status_pesanan as h')->join('pengguna_sistem as u', 'u.id', '=', 'h.aktor_id')
            ->where('h.pesanan_id', $order)->select('h.*', 'u.nama as aktor')->orderByDesc('h.id')->get();
        $admin = $request->user()->isAdmin();
        if (! $admin) {
            $history = $history->reject(fn ($row) => in_array($row->dimensi, ['status_bayar', 'hpp'], true));
        }
        $payments = $admin ? DB::table('pembayaran as p')->leftJoin('pembayaran as r', 'r.asal_id', '=', 'p.id')
            ->where('p.pesanan_id', $order)->select('p.*', 'r.id as pembalik_id')->orderBy('p.id')->get() : collect();
        $materials = DB::table('mutasi_stok as m')->join('lot_stok as l', 'l.id', '=', 'm.lot_id')
            ->join('bahan_baku as b', 'b.id', '=', 'l.bahan_id')->leftJoin('mutasi_stok as r', 'r.asal_id', '=', 'm.id')
            ->whereIn('m.detail_id', $lines->pluck('id'))->select('m.*', 'b.nama', 'b.satuan', 'r.id as pembalik_id')->orderBy('m.id')->get()->groupBy('detail_id');
        $total = $admin ? $this->orders->total($order) : null;
        $due = $admin ? bcsub($total, $this->finance->paid($order), 2) : null;

        return view('orders.show', compact('record', 'customer', 'lines', 'history', 'payments', 'materials', 'admin', 'total', 'due'));
    }

    public function create(): View
    {
        return $this->draft();
    }

    public function edit(int $order): View
    {
        $record = $this->orders->find($order);
        abort_unless($record->status === 'draf', 404);

        return $this->draft($record);
    }

    public function save(Request $request, ?int $order = null): RedirectResponse
    {
        $id = $this->submit($request, $order ? 'pesanan.ubah' : 'pesanan.draf', [
            'pelanggan_id' => ['required', 'integer', 'exists:pelanggan,id'],
            'nonrutin' => ['required', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'detail' => ['required', 'array', 'max:20'],
            'detail.*' => ['required', 'array:produk_id,jumlah,sumber,harga_satuan'],
            'detail.*.produk_id' => ['required', 'integer', 'exists:produk,id'],
            'detail.*.jumlah' => ['required', 'integer', 'between:1,1000000'],
            'detail.*.sumber' => ['required', Rule::in(['stok', 'khusus'])],
            'detail.*.harga_satuan' => ['nullable', 'string', Decimal::MONEY],
        ], fn (array $d, int $actor) => $this->orders->saveDraft($d, $actor, $order), [$order]);

        return $this->backToOrder($id, 'Draf pesanan disimpan.');
    }

    public function estimate(Request $request): JsonResponse
    {
        $input = $request->validate([
            'produk_id' => ['required', 'integer', 'exists:produk,id'],
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.BusinessDate::today()],
        ]);

        return response()->json(['estimasi' => $this->orders->estimate($input['produk_id'], $input['tanggal'] ?? BusinessDate::today())]);
    }

    public function confirm(Request $request, int $order): RedirectResponse
    {
        $this->submit($request, 'pesanan.konfirmasi', [], fn (array $d, int $actor) => $this->orders->confirm($order, $actor), [$order]);

        return $this->backToOrder($order, 'Pesanan terkonfirmasi.');
    }

    public function cancelForm(int $order): View
    {
        $this->orders->find($order);

        return $this->editor('Batalkan pesanan', route('orders.cancel', $order), route('orders.show', $order), [
            'alasan' => $this->field('Alasan pembatalan'),
            'barang_kembali' => $this->field('Barang jadi yang teralokasi sudah kembali ke gudang', 'checkbox', false),
        ], hint: 'Balik pembayaran terlebih dahulu. Pemakaian bahan produksi tetap tercatat.');
    }

    public function cancel(Request $request, int $order): RedirectResponse
    {
        $this->submit($request, 'pesanan.batal', [
            'alasan' => ['required', 'string', 'max:220'], 'barang_kembali' => ['required', 'boolean'],
        ], fn (array $d, int $actor) => $this->orders->cancel($order, $actor, $d['alasan'], (bool) $d['barang_kembali']), [$order]);

        return $this->backToOrder($order, 'Pesanan dibatalkan.');
    }

    public function transition(Request $request, int $order, ?int $line = null): RedirectResponse
    {
        $this->visibleOrder($request, $order);
        $states = $line ? ['proses', 'siap'] : ['dikirim', 'selesai'];
        $this->submit($request, $line ? 'pesanan.produksi' : 'pesanan.kirim', [
            'status' => ['required', Rule::in($states)],
        ], fn (array $d, int $actor) => $line
            ? $this->orders->setPreparation($order, $line, $d['status'], $actor)
            : $this->orders->ship($order, $d['status'], $actor), [$order, $line]);

        return $this->backToOrder($order, 'Progres diperbarui.');
    }

    public function workForm(Request $request, int $order, int $line, string $action): View
    {
        $this->visibleOrder($request, $order);
        $this->orders->lines($order)->where('id', $line)->where('sumber', 'khusus')->firstOrFail();
        if ($action === 'hpp') {
            Gate::authorize('operate-admin');
            $fields = [
                'upah' => $this->field('Upah aktual seluruh baris (Rp)', 'decimal'),
                'overhead' => $this->field('Overhead aktual seluruh baris (Rp)', 'decimal'),
            ];
            $title = 'Sahkan HPP aktual';
        } else {
            $fields = [
                'bahan_id' => $this->field('Bahan baku', 'select', options: $this->materialOptions()),
                'jumlah' => $this->field('Jumlah dalam satuan bahan', 'decimal'),
            ];
            $title = 'Catat pemakaian bahan';
        }

        return $this->editor($title, route('orders.work', [$order, $line, $action]), route('orders.show', $order), $fields);
    }

    public function work(Request $request, int $order, int $line, string $action): RedirectResponse
    {
        $this->visibleOrder($request, $order);
        if ($action === 'hpp') {
            Gate::authorize('operate-admin');
            $this->submit($request, 'pesanan.hpp', [
                'upah' => ['required', 'string', Decimal::MONEY], 'overhead' => ['required', 'string', Decimal::MONEY],
            ], fn (array $d, int $actor) => $this->orders->approveCost($order, $line, $d['upah'], $d['overhead'], $actor), [$order, $line]);
        } else {
            $this->submit($request, 'pesanan.pakai', [
                'bahan_id' => ['required', 'integer', 'exists:bahan_baku,id'], 'jumlah' => ['required', 'string', Decimal::QUANTITY],
            ], fn (array $d, int $actor) => $this->orders->consume($order, $line, $d['bahan_id'], $d['jumlah'], $actor), [$order, $line]);
        }

        return $this->backToOrder($order, 'Data produksi disimpan.');
    }

    public function materialCorrection(int $order, int $line, int $movement): View
    {
        $this->orders->lines($order)->where('id', $line)->where('sumber', 'khusus')->firstOrFail();
        DB::table('mutasi_stok')->where('id', $movement)->where('detail_id', $line)->firstOrFail();

        return $this->editor('Balik pemakaian bahan', route('orders.material.reverse', [$order, $line, $movement]),
            route('orders.show', $order), ['alasan' => $this->field('Alasan koreksi')]);
    }

    public function reverseMaterial(Request $request, int $order, int $line, int $movement): RedirectResponse
    {
        $this->submit($request, 'pesanan.pakai.balik', ['alasan' => ['required', 'string', 'max:220']],
            fn (array $d, int $actor) => $this->orders->reverseMaterial($order, $line, $movement, $actor, $d['alasan']), [$order, $line, $movement]);

        return $this->backToOrder($order, 'Pemakaian bahan dibalik.');
    }

    public function print(int $order, ?int $payment = null): View
    {
        $record = $this->orders->find($order);
        $customer = DB::table('pelanggan')->where('id', $record->pelanggan_id)->firstOrFail();
        $lines = $this->orders->lines($order)->orderBy('id')->get();
        $receipt = $payment ? DB::table('pembayaran')->where('id', $payment)->where('pesanan_id', $order)->firstOrFail() : null;
        $title = $receipt ? 'Bukti pembayaran' : ($record->status === 'draf' ? 'Nota estimasi' : 'Nota tagihan');
        $total = $this->orders->total($order);

        return view('orders.print', compact('record', 'customer', 'lines', 'receipt', 'title', 'total'));
    }

    private function draft(?object $record = null): View
    {
        $customers = DB::table('pelanggan')->where('aktif', true)->orderBy('nama')->pluck('nama', 'id');
        $products = DB::table('produk')->where('aktif', true)->orderBy('kode')->get(['id', 'kode', 'nama']);
        $lines = $record ? $this->orders->lines($record->id)->orderBy('id')->get()->map(fn ($line) => (array) $line)->all() : [[]];

        return view('orders.form', compact('record', 'customers', 'products', 'lines'));
    }

    private function visibleOrder(Request $request, int $id): object
    {
        $record = $this->orders->find($id);
        abort_unless($request->user()->isAdmin() || $record->status === 'konfirmasi', 403);

        return $record;
    }

    private function materialOptions(): array
    {
        $available = DB::table('lot_stok')->where('kondisi', 'layak')->whereNotNull('nilai_disahkan_pada')
            ->groupBy('bahan_id')->select('bahan_id')->selectRaw('SUM(jumlah_sisa) AS siap');

        return DB::table('bahan_baku as b')->leftJoinSub($available, 's', 's.bahan_id', '=', 'b.id')
            ->where('b.aktif', true)->orderBy('b.kode')->get(['b.*', 's.siap'])
            ->mapWithKeys(fn ($row) => [$row->id => $row->kode.' · '.$row->nama.' (siap '.Decimal::display($row->siap ?? '0', 3).' '.$row->satuan.')'])->all();
    }

    private function backToOrder(int $id, string $message): RedirectResponse
    {
        return redirect()->route('orders.show', $id)->with('status', $message);
    }
}
