<?php

namespace App\Http\Controllers;

use App\Services\Reports;
use App\Support\BusinessDate;
use App\Support\ListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private Reports $reports) {}

    public function dashboard(Request $request): View
    {
        [$month, $from, $to] = $this->period($request);
        $input = $request->validate(['ambang' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2']]);
        $threshold = (string) ($input['ambang'] ?? '80');
        $data = $this->reports->concentration($from, $threshold);
        $cash = $this->reports->cash($from, $to);
        $profit = $this->reports->profit($from, $to);

        return view('reports.dashboard', compact('month', 'threshold', 'data', 'cash', 'profit'));
    }

    public function index(Request $request, string $report = 'arus-kas'): View
    {
        [$month, $from, $to] = $this->period($request);
        $category = $request->integer('kategori') ?: null;
        $customer = $request->integer('pelanggan') ?: null;
        $ready = $request->query('hpp');
        $ready = in_array($ready, ['lengkap', 'tertunda'], true) ? $ready : '';
        $search = ListFilters::read($request, false)['q'];
        $data = [];
        $records = null;
        $options = [];

        if ($report === 'arus-kas') {
            $data = $this->reports->cash($from, $to, $category);
            $options = DB::table('kategori_kas')->orderBy('nama')->pluck('nama', 'id');
        } elseif ($report === 'laba-rugi') {
            $data = $this->reports->profit($from, $to);
        } elseif ($report === 'laba-pesanan') {
            $dates = $request->validate(['dari' => ['nullable', 'date_format:Y-m-d'], 'sampai' => ['nullable', 'date_format:Y-m-d']]);
            $from = $dates['dari'] ?? $from;
            $to = $dates['sampai'] ?? $to;
            if ($from > $to) {
                throw ValidationException::withMessages(['sampai' => 'Tanggal akhir harus pada atau setelah tanggal awal.']);
            }
            $options = DB::table('pelanggan')->orderBy('nama')->pluck('nama', 'id');
            $query = DB::table('pesanan as p')->join('pelanggan as c', 'c.id', '=', 'p.pelanggan_id')
                ->joinSub($this->reports->orderTotals(), 't', 't.pesanan_id', '=', 'p.id')->where('p.status', 'konfirmasi')
                ->whereBetween('p.tanggal', [$from, $to])->select('p.id', 'p.kode', 'p.tanggal', 'c.nama as pelanggan', 't.*')
                ->orderByDesc('p.tanggal')->orderByDesc('p.id');
            if ($customer) {
                $query->where('p.pelanggan_id', $customer);
            }
            if ($search !== '') {
                $query->whereLike('p.kode', ListFilters::like($search));
            }
            if ($ready === 'lengkap') {
                $query->whereNotNull('t.hpp');
            } elseif ($ready === 'tertunda') {
                $query->whereNull('t.hpp');
            }
            $records = $query->paginate(15)->withQueryString();
        }

        return view('reports.index', compact('report', 'month', 'from', 'to', 'data', 'records', 'options', 'category', 'customer', 'ready', 'search'));
    }

    private function period(Request $request): array
    {
        $values = $request->validate(['bulan' => ['nullable', 'date_format:Y-m', 'regex:/\A[1-9]\d{3}-\d{2}\z/']]);
        $month = $values['bulan'] ?? substr(BusinessDate::today(), 0, 7);
        $first = CarbonImmutable::parse($month.'-01');

        return [$month, $first->toDateString(), $first->endOfMonth()->toDateString()];
    }
}
