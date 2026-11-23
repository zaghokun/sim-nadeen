@use('App\Support\Decimal')

<x-workspace title="Pembelian bahan">
    <header class="page-header"><h1>Pembelian bahan</h1><a class="button button-primary" href="{{ route('purchases.create') }}">Catat pembelian</a></header>
    @include('shared.filters', ['filterUrl'=>route('purchases.index'), 'searchLabel'=>'Cari kuitansi, bahan, atau supplier', 'showStatus'=>false, 'filters'=>['q'=>$search, 'status'=>'']])
    <section aria-label="Daftar pembelian">
        @forelse ($records as $row)
            <article class="line-panel">
                <div class="section-heading">
                    <div><h2>{{ $row->referensi }}</h2><p class="muted">{{ $row->tanggal }} · {{ $row->supplier }} @if($row->pesanan)· {{ $row->pesanan }}@endif</p></div>
                    <span class="record-status">{{ $row->asal_id ? 'Pembalik #'.$row->asal_id : ($row->pembalik_id ? 'Sudah dibalik' : 'Tercatat') }}</span>
                </div>
                <dl class="operation-summary compact-summary">
                    <div><dt>Bahan</dt><dd>{{ $row->bahan }}</dd></div>
                    <div><dt>Jumlah</dt><dd>{{ Decimal::display($row->jumlah, 3) }} {{ $row->satuan }}</dd></div>
                    @if($admin)<div><dt>Nominal</dt><dd>{{ Decimal::rupiah($row->nominal) }}</dd></div><div><dt>Metode</dt><dd>{{ $row->metode }}</dd></div>@endif
                </dl>
                @if($row->alasan)<p>{{ $row->alasan }}</p>@endif
                <div class="button-row">
                    @if($row->lot_id)<a class="table-action" href="{{ route('inventory.show', ['bahan', $row->bahan_id]) }}">Lihat lot #{{ $row->lot_id }}</a>@endif
                    @if($admin && !$row->asal_id && !$row->pembalik_id)<a class="table-action" href="{{ route('finance.reverse.form', ['pembelian', $row->id]) }}">Balik pembelian</a>@endif
                </div>
            </article>
        @empty
            <p class="empty-state">Tidak ada pembelian yang cocok.</p>
        @endforelse
    </section>
    @include('shared.page-navigation')
</x-workspace>
