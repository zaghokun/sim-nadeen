@use('App\Support\Decimal')
@use('App\Services\Orders')

<x-layout :title="$title">
    <main class="print-document">
        <nav class="button-row print-controls" aria-label="Kendali cetak">
            <a class="button button-secondary" href="{{ route('orders.show', $record->id) }}">Kembali ke pesanan</a>
            <button class="button button-primary" type="button" data-print hidden>Cetak atau simpan PDF</button>
        </nav>
        <header class="page-header">
            <div><p>Furniture Nadeen</p><h1>{{ $title }}</h1></div><strong>{{ $record->kode }}</strong>
        </header>
        <dl class="operation-summary">
            <div><dt>Pelanggan</dt><dd>{{ $customer->nama }}</dd></div>
            <div><dt>Tanggal</dt><dd>{{ $receipt->tanggal ?? $record->tanggal }}</dd></div>
            @if($receipt)
                <div><dt>Referensi pembayaran</dt><dd>{{ '#'.$receipt->id }}</dd></div>
                <div><dt>Jenis</dt><dd>{{ Orders::LABELS[$receipt->jenis] }}</dd></div>
                <div><dt>Metode</dt><dd>{{ $receipt->metode }}</dd></div>
                <div><dt>Nominal</dt><dd>{{ Decimal::rupiah($receipt->nominal) }}</dd></div>
            @else
                <div><dt>Total</dt><dd>{{ Decimal::rupiah($total) }}</dd></div>
            @endif
        </dl>
        @if (!$receipt)
            <x-ledger :headers="['Produk', 'Jumlah', 'Harga satuan', 'Subtotal']"
                :rows="$lines->map(fn ($line) => [$line->kode_produk.' · '.$line->nama_produk, $line->jumlah, Decimal::rupiah($line->harga_satuan), Decimal::rupiah(bcmul($line->harga_satuan, (string) $line->jumlah, 2))])"
                :label="$title" />
        @endif
    </main>
</x-layout>
