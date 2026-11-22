@if ($records->total() > 0)
    <div class="page-navigation">
        <p>{{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} dari {{ $records->total() }} data</p>
        @if ($records->hasPages())
            <nav aria-label="Halaman daftar">
                @if ($records->previousPageUrl())
                    <a class="button button-secondary" rel="prev" href="{{ $records->previousPageUrl() }}">Sebelumnya</a>
                @endif
                @if ($records->nextPageUrl())
                    <a class="button button-secondary" rel="next" href="{{ $records->nextPageUrl() }}">Berikutnya</a>
                @endif
            </nav>
        @endif
    </div>
@endif
