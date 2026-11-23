<?php

namespace App\Http\Controllers;

use App\Enums\StockKind;
use App\Services\Inventory;
use App\Support\BusinessDate;
use App\Support\Decimal;
use App\Support\ListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends OperationController
{
    public function __construct(private Inventory $inventory) {}

    public function index(Request $request, StockKind $kind): View
    {
        $search = ListFilters::read($request, false)['q'];
        $filter = $request->query('filter');
        $category = $request->query('kategori');
        $category = is_string($category) ? $category : '';
        $categories = DB::table($kind->table())->where('aktif', true)->distinct()->orderBy('kategori')->pluck('kategori');
        $summary = DB::table('lot_stok')->where('jumlah_sisa', '>', 0)->groupBy($kind->column())
            ->select($kind->column())
            ->selectRaw("SUM(CASE WHEN kondisi = 'layak' THEN jumlah_sisa ELSE 0 END) AS fisik")
            ->selectRaw("SUM(CASE WHEN kondisi = 'layak' AND nilai_disahkan_pada IS NOT NULL THEN jumlah_sisa ELSE 0 END) AS siap")
            ->selectRaw("SUM(CASE WHEN kondisi = 'rusak' THEN jumlah_sisa ELSE 0 END) AS rusak")
            ->selectRaw("SUM(CASE WHEN kondisi = 'layak' AND nilai_disahkan_pada IS NOT NULL THEN nilai_sisa ELSE 0 END) AS nilai")
            ->selectRaw("MIN(CASE WHEN kondisi = 'layak' THEN tanggal_masuk END) AS tertua")
            ->selectRaw("MAX(CASE WHEN kondisi = 'layak' AND tanggal_masuk IS NULL THEN 1 ELSE 0 END) AS tanggal_tidak_diketahui");
        $dateLimit = CarbonImmutable::parse(BusinessDate::today())->subDays(180)->toDateString();
        $query = DB::table($kind->table().' as i')->leftJoinSub($summary, 's', 's.'.$kind->column(), '=', 'i.id')
            ->where('i.aktif', true)->select('i.*', 's.tertua', 's.tanggal_tidak_diketahui')
            ->selectRaw('COALESCE(s.fisik, 0) AS fisik, COALESCE(s.siap, 0) AS siap, COALESCE(s.rusak, 0) AS rusak, COALESCE(s.nilai, 0) AS nilai')
            ->orderByRaw('COALESCE(s.fisik, 0) > i.stok_minimum')->orderBy('i.kode');

        if ($search !== '') {
            $term = ListFilters::like($search);
            $query->where(fn ($q) => $q->whereLike('i.kode', $term)->orWhereLike('i.nama', $term));
        }
        if ($category !== '') {
            $query->where('i.kategori', $category);
        }
        match ($filter) {
            'minimum' => $query->whereRaw('COALESCE(s.fisik, 0) <= i.stok_minimum'),
            'lama' => $query->where('s.tertua', '<', $dateLimit),
            'rusak' => $query->where('s.rusak', '>', 0),
            'menunggu' => $query->whereColumn('s.fisik', '>', 's.siap'),
            default => null,
        };
        $items = $query->paginate(15)->withQueryString();
        $admin = $request->user()->isAdmin();

        return view('inventory.index', compact('kind', 'items', 'admin', 'search', 'filter', 'dateLimit', 'category', 'categories'));
    }

    public function show(Request $request, StockKind $kind, int $item): View
    {
        $record = $this->item($kind, $item);
        $lots = $this->inventory->lots($kind, $item)->orderByRaw('tanggal_masuk IS NULL')->orderBy('tanggal_masuk')->orderBy('id')->paginate(15);
        $movements = DB::table('mutasi_stok as m')->join('lot_stok as l', 'l.id', '=', 'm.lot_id')
            ->leftJoin('mutasi_stok as r', 'r.asal_id', '=', 'm.id')->where('l.'.$kind->column(), $item)
            ->select('m.*', 'r.id as pembalik_id')->orderByDesc('m.id')->paginate(15, pageName: 'mutasi')->withQueryString();
        $admin = $request->user()->isAdmin();

        return view('inventory.show', compact('kind', 'record', 'lots', 'movements', 'admin'));
    }

    public function receiveForm(Request $request, StockKind $kind, int $item): View
    {
        $this->item($kind, $item);
        $fields = [
            'jumlah' => $this->field('Jumlah masuk', 'decimal'),
            'tanggal_masuk' => $this->field('Tanggal fisik masuk', 'date', false),
            'kondisi' => $this->conditionField(),
            'alasan' => $this->field('Sumber atau alasan'),
        ];
        if ($request->user()->isAdmin()) {
            $fields['nilai'] = $this->field('Nilai seluruh lot (Rp)', 'decimal', false);
        }

        return $this->editor('Catat stok masuk', route('inventory.receive', [$kind->value, $item]),
            route('inventory.show', [$kind->value, $item]), $fields, ['kondisi' => 'layak'],
            'Kosongkan tanggal bila belum diketahui. Nilai kosong menunggu pengesahan admin.');
    }

    public function receive(Request $request, StockKind $kind, int $item): RedirectResponse
    {
        $this->submit($request, 'stok.masuk', [
            'jumlah' => ['required', 'string', Decimal::QUANTITY],
            'tanggal_masuk' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:'.BusinessDate::today()],
            'nilai' => $request->user()->isAdmin() ? ['nullable', 'string', Decimal::MONEY] : ['prohibited'],
            'kondisi' => ['required', Rule::in(['layak', 'rusak'])],
            'alasan' => ['required', 'string', 'max:220'],
        ], fn (array $d, int $actor) => $this->inventory->receive($kind, $item, $d['jumlah'], $d['nilai'] ?? null,
            $d['tanggal_masuk'] ?? null, $d['kondisi'], $actor, $d['alasan']), [$kind->value, $item]);

        return $this->backToItem($kind, $item, 'Lot berhasil dicatat.');
    }

    public function issueForm(StockKind $kind, int $item): View
    {
        $this->item($kind, $item);

        return $this->editor('Catat penyesuaian stok keluar', route('inventory.issue', [$kind->value, $item]),
            route('inventory.show', [$kind->value, $item]), [
                'jumlah' => $this->field('Jumlah keluar', 'decimal'), 'alasan' => $this->field('Alasan'),
            ], hint: 'Pakai untuk selisih fisik. Pengeluaran pesanan dan pemakaian produksi dicatat dari detail pesanan.');
    }

    public function issue(Request $request, StockKind $kind, int $item): RedirectResponse
    {
        $this->submit($request, 'stok.penyesuaian', [
            'jumlah' => ['required', 'string', Decimal::QUANTITY], 'alasan' => ['required', 'string', 'max:220'],
        ], function (array $data, int $actor) use ($kind, $item): int {
            $this->inventory->issue($kind, $item, $data['jumlah'], $actor, $data['alasan']);

            return $item;
        }, [$kind->value, $item]);

        return $this->backToItem($kind, $item, 'Penyesuaian dicatat.');
    }

    public function lotForm(StockKind $kind, int $item, int $lot, string $action): View
    {
        $record = $this->inventory->lots($kind, $item)->where('id', $lot)->firstOrFail();
        $fields = $action === 'nilai'
            ? ['nilai' => $this->field('Nilai seluruh lot (Rp)', 'decimal')]
            : ['kondisi' => $this->conditionField()];

        return $this->editor($action === 'nilai' ? 'Sahkan nilai lot' : 'Ubah kondisi lot',
            route($action === 'nilai' ? 'inventory.value' : 'inventory.condition', [$kind->value, $item, $lot]), route('inventory.show', [$kind->value, $item]),
            [...$fields, 'alasan' => $this->field('Alasan')], ['kondisi' => $record->kondisi]);
    }

    public function updateLot(Request $request, StockKind $kind, int $item, int $lot, string $action): RedirectResponse
    {
        $this->inventory->lots($kind, $item)->where('id', $lot)->firstOrFail();
        $rules = $action === 'nilai'
            ? ['nilai' => ['required', 'string', Decimal::MONEY]]
            : ['kondisi' => ['required', Rule::in(['layak', 'rusak'])]];
        $this->submit($request, 'stok.'.$action, [...$rules, 'alasan' => ['required', 'string', 'max:220']],
            fn (array $d, int $actor) => $action === 'nilai'
                ? $this->inventory->approve($lot, $d['nilai'], $actor, $d['alasan'])
                : $this->inventory->condition($lot, $d['kondisi'], $actor, $d['alasan']), [$lot]);

        return $this->backToItem($kind, $item, 'Lot berhasil diperbarui.');
    }

    private function item(StockKind $kind, int $id): object
    {
        return DB::table($kind->table())->where('id', $id)->firstOrFail();
    }

    private function conditionField(): array
    {
        return $this->field('Kondisi', 'select', true, ['layak' => 'Layak', 'rusak' => 'Rusak']);
    }

    private function backToItem(StockKind $kind, int $item, string $status): RedirectResponse
    {
        return redirect()->route('inventory.show', [$kind->value, $item])->with('status', $status);
    }
}
