@php($title = ($record ? 'Ubah ' : 'Tambah ').\Illuminate\Support\Str::lower($section->label()))

<x-workspace :title="$title">
    <header class="page-header"><h1>{{ $title }}</h1></header>

    @if ($productRequired)
        <div class="empty-state">
            <p>Tambahkan produk sebelum menetapkan standar HPP.</p>
            <a class="button button-primary" href="{{ route('master.create', 'produk') }}">Tambah produk</a>
        </div>
    @else
        @if ($section === \App\Enums\MasterSection::Cost || $section === \App\Enums\MasterSection::Product)
            <p class="muted form-intro">Masukkan nominal tanpa pemisah ribuan. Gunakan titik untuk desimal.</p>
        @endif

        <form class="editor" method="post" action="{{ $record ? route('master.update', [$section->value, $record->id]) : route('master.store', $section->value) }}">
            @csrf
            @if ($record)
                @method('PATCH')
            @endif

            <div class="form-grid">
                @foreach ($fields as $name => $field)
                    @include('shared.field')
                @endforeach
            </div>
            <footer class="form-actions">
                <button class="button button-primary" type="submit">Simpan</button>
                <a class="button button-secondary" href="{{ route('master.index', $section->value) }}">Batal</a>
            </footer>
        </form>
    @endif
</x-workspace>
