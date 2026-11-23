<x-workspace :title="$title">
    <header class="page-header"><h1>{{ $title }}</h1></header>
    @if ($hint)<p class="form-intro muted">{{ $hint }}</p>@endif
    <form class="editor" method="post" action="{{ $action }}">
        <x-post-token />
        <div class="form-grid">
            @foreach ($fields as $name => $field)
                @include('shared.field')
            @endforeach
        </div>
        <footer class="form-actions">
            <button class="button button-primary" type="submit">Simpan</button>
            <a class="button button-secondary" href="{{ $back }}">Batal</a>
        </footer>
    </form>
</x-workspace>
