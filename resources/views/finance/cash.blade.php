@use('App\Support\Decimal')

<x-workspace title="Buku Kas">
    <header class="page-header"><div><h1>Buku Kas</h1><p class="muted">Saldo total {{ Decimal::rupiah($balance) }}</p></div>
        <a class="button button-primary" href="{{ route('cash.create') }}">Catat transaksi</a>
    </header>
    <form class="filters" method="get" aria-label="Filter buku kas">
        <div class="field"><label for="dari">Tanggal dari</label><input id="dari" name="dari" type="date" value="{{ $filters['dari'] ?? '' }}"></div>
        <div class="field"><label for="sampai">Tanggal sampai</label><input id="sampai" name="sampai" type="date" value="{{ $filters['sampai'] ?? '' }}"></div>
        <div class="field"><label for="kategori">Kategori</label><select id="kategori" name="kategori"><option value="">Semua kategori</option>
            @foreach($categories as $id => $name)<option value="{{ $id }}" @selected($category === $id)>{{ $name }}</option>@endforeach
        </select></div>
        <button class="button button-secondary" type="submit">Terapkan filter</button>
        @if($category || !empty($filters['dari']) || !empty($filters['sampai']))<a class="button button-link" href="{{ route('cash.index') }}">Hapus filter</a>@endif
    </form>
    <x-ledger :headers="['Tanggal', 'Kategori', 'Keterangan', 'Nominal', 'Saldo berjalan', 'Sumber atau koreksi']"
        :rows="$records->map(fn ($row) => [
            $row->tanggal, $row->nama_kategori, $row->keterangan,
            ['label'=>($row->arah === 'masuk' ? '+' : '−').Decimal::rupiah($row->nominal), 'number'=>true],
            ['label'=>Decimal::rupiah($row->saldo), 'number'=>true],
            $row->pembayaran_id ? ['label'=>'Bukti bayar #'.$row->pembayaran_id, 'url'=>route('orders.receipt', [$row->pesanan_id, $row->pembayaran_id])]
                : ($row->pembelian_id ? ['label'=>'Pembelian #'.$row->pembelian_id, 'url'=>route('purchases.index', ['q'=>$row->referensi])]
                : ($row->asal_id ? 'Pembalik #'.$row->asal_id : ($row->pembalik_id ? 'Sudah dibalik' : ['label'=>'Balik jurnal', 'url'=>route('finance.reverse.form', ['kas', $row->id])]))),
        ])" label="Jurnal kas">Tidak ada transaksi pada filter ini.</x-ledger>
    <p class="form-intro muted">Saldo berjalan mencakup semua kategori dan dihitung sesuai urutan tanggal efektif serta waktu pencatatan.</p>
    @include('shared.page-navigation')
</x-workspace>
