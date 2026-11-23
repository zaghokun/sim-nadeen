@use('App\Services\Orders')

<x-workspace title="Pesanan">
    <header class="page-header">
        <h1>Pesanan</h1>
        @if($admin)<a class="button button-primary" href="{{ route('orders.create') }}">Tambah pesanan</a>@endif
    </header>
    <form method="get" class="filters" aria-label="Filter pesanan">
        <div class="field filter-search"><label for="q">Kode pesanan atau pelanggan</label><input id="q" name="q" value="{{ $search }}" type="search"></div>
        @if ($admin)
            <div class="field"><label for="status">Status dokumen</label><select id="status" name="status"><option value="">Semua dokumen</option>
                @foreach (['draf', 'konfirmasi', 'batal'] as $state)<option value="{{ $state }}" @selected($status === $state)>{{ Orders::LABELS[$state] }}</option>@endforeach
            </select></div>
        @endif
        <button class="button button-secondary" type="submit">Cari</button>
        @if ($search || $status)<a class="button button-link" href="{{ route('orders.index') }}">Hapus filter</a>@endif
    </form>
    <x-ledger :headers="['Pesanan', 'Pelanggan', 'Tanggal', 'Dokumen', ...($admin ? ['Bayar'] : []), 'Produksi', 'Pengiriman']"
        :rows="$records->map(fn ($row) => [
            ['label'=>$row->kode, 'url'=>route('orders.show', $row->id)], $row->pelanggan, $row->tanggal, Orders::LABELS[$row->status],
            ...($admin ? [Orders::LABELS[$row->status_bayar]] : []), Orders::LABELS[$row->status_produksi], Orders::LABELS[$row->status_kirim],
        ])" label="Daftar pesanan">Tidak ada pesanan yang cocok.</x-ledger>
    @include('shared.page-navigation')
</x-workspace>
