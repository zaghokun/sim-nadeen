@use('App\Support\BusinessDate')

@php
    $title = $record ? 'Ubah '.$record->kode : 'Tambah pesanan';
    $entries = old('detail', $lines);
    $entries = is_array($entries) ? array_values(array_filter($entries, 'is_array')) : [];
@endphp

<x-workspace :title="$title">
    <header class="page-header"><h1>{{ $title }}</h1></header>
    @if ($customers->isEmpty() || $products->isEmpty())
        <div class="empty-state">
            <p>Pesanan memerlukan pelanggan dan produk aktif.</p>
            @if ($customers->isEmpty())<a class="button button-primary" href="{{ route('master.create', 'pelanggan') }}">Tambah pelanggan</a>@endif
            @if ($products->isEmpty())<a class="button button-secondary" href="{{ route('master.create', 'produk') }}">Tambah produk</a>@endif
        </div>
    @else
        <form class="editor wide-editor" method="post" data-order-editor
            data-estimate="{{ route('orders.estimate', ['tanggal' => $record->tanggal ?? BusinessDate::today()]) }}"
            action="{{ $record ? route('orders.update', $record->id) : route('orders.store') }}">
            <x-post-token />
            <div class="form-grid">
                @include('shared.field', ['name'=>'pelanggan_id', 'field'=>['label'=>'Pelanggan','type'=>'select','rules'=>['required'],'options'=>$customers], 'values'=>['pelanggan_id'=>$record->pelanggan_id ?? null]])
                @include('shared.field', ['name'=>'nonrutin', 'field'=>['label'=>'Klasifikasikan sebagai penjualan nonrutin','type'=>'checkbox'], 'values'=>['nonrutin'=>$record->nonrutin ?? false]])
                @include('shared.field', ['name'=>'catatan', 'field'=>['label'=>'Catatan pesanan','type'=>'textarea','hint'=>'Catat alasan jika standar HPP memakai komponen bernilai nol.'], 'values'=>['catatan'=>$record->catatan ?? null]])
            </div>
            <section class="operation-section" aria-labelledby="rincian">
                <div class="section-heading"><h2 id="rincian">Rincian barang</h2><button class="button button-secondary" type="button" data-add-line hidden>Tambah baris</button></div>
                <p class="muted">Masukkan harga tanpa pemisah ribuan. Gunakan titik untuk desimal.</p>
                <div data-order-lines>
                    @foreach ($entries ?: [[]] as $index => $entry)
                        @include('orders.line-fields')
                    @endforeach
                </div>
                <template data-line-template>@include('orders.line-fields', ['index'=>'__BARIS__','entry'=>[]])</template>
                <p class="order-estimate-total" data-estimate-total aria-live="polite" hidden></p>
            </section>
            <footer class="form-actions">
                <button class="button button-primary" type="submit">Simpan draf</button>
                <a class="button button-secondary" href="{{ $record ? route('orders.show', $record->id) : route('orders.index') }}">Batal</a>
            </footer>
        </form>
    @endif
</x-workspace>
