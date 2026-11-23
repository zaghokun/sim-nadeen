@use('App\Support\Decimal')

@php
    $headers = ['Item', 'Stok layak', 'Siap dialokasikan', 'Minimum', 'Lot tertua', 'Peringatan'];
    if ($admin) $headers[] = 'Valuasi stok layak';
    $rows = $items->map(function ($row) use ($kind, $dateLimit, $admin) {
        $warnings = array_filter([
            bccomp($row->fisik, (string) $row->stok_minimum, 3) <= 0 ? 'Stok minimum' : null,
            $row->tertua && $row->tertua < $dateLimit ? 'Lebih dari 180 hari' : null,
            $row->tanggal_tidak_diketahui ? 'Tanggal sebagian lot belum diketahui' : null,
            bccomp($row->rusak, '0', 3) > 0 ? 'Ada lot rusak' : null,
            bccomp($row->fisik, $row->siap, 3) > 0 ? 'Nilai belum disahkan' : null,
        ]);
        $unit = $row->satuan ?? 'unit';
        return [
            ['label' => $row->kode.' · '.$row->nama, 'url' => route('inventory.show', [$kind->value, $row->id])],
            Decimal::display($row->fisik, 3).' '.$unit,
            Decimal::display($row->siap, 3).' '.$unit,
            Decimal::display($row->stok_minimum, 3).' '.$unit,
            $row->tertua,
            implode(' · ', $warnings) ?: 'Aman',
            ...($admin ? [Decimal::rupiah($row->nilai)] : []),
        ];
    });
@endphp

<x-workspace title="Inventori">
    <header class="page-header">
        <h1>Inventori</h1>
        @if ($admin && $kind->value === 'bahan')
            <a class="button button-primary" href="{{ route('master.create', 'bahan') }}">Tambah bahan</a>
        @endif
    </header>
    <nav class="section-links" aria-label="Jenis inventori">
        @foreach (App\Enums\StockKind::cases() as $tab)
            <a href="{{ route('inventory.index', $tab->value) }}" @if($tab === $kind) aria-current="page" @endif>{{ $tab->label() }}</a>
        @endforeach
    </nav>
    <form method="get" class="filters" aria-label="Filter inventori">
        <div class="field filter-search"><label for="q">Nama atau kode item</label><input id="q" name="q" value="{{ $search }}" type="search"></div>
        <div class="field"><label for="kategori">Kategori</label><select id="kategori" name="kategori"><option value="">Semua kategori</option>
            @foreach ($categories as $option)<option @selected($category === $option)>{{ $option }}</option>@endforeach
        </select></div>
        <div class="field"><label for="filter">Kondisi stok</label><select id="filter" name="filter"><option value="">Semua kondisi</option>
            @foreach (['minimum'=>'Stok minimum', 'lama'=>'Lot lama', 'rusak'=>'Ada lot rusak', 'menunggu'=>'Menunggu valuasi'] as $key => $label)
                <option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>
            @endforeach
        </select></div>
        <button class="button button-secondary" type="submit">Cari</button>
        @if ($search || $filter || $category)<a class="button button-link" href="{{ route('inventory.index', $kind->value) }}">Hapus filter</a>@endif
    </form>
    <x-ledger :headers="$headers" :rows="$rows" label="Ringkasan inventori">Tidak ada item yang cocok.</x-ledger>
    @include('shared.page-navigation', ['records' => $items])
</x-workspace>
