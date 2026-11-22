<form class="filters" method="get" action="{{ $filterUrl }}" aria-label="Filter daftar">
    <div class="field filter-search">
        <label for="q">{{ $searchLabel }}</label>
        <input id="q" name="q" value="{{ $filters['q'] }}" type="search" maxlength="120">
    </div>
    @if ($showStatus)
        <div class="field">
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">Semua status</option>
                <option value="aktif" @selected($filters['status'] === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected($filters['status'] === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
    @endif
    <button type="submit" class="button button-secondary">Cari</button>
    @if ($filters['q'] !== '' || $filters['status'] !== '')
        <a class="button button-link" href="{{ $filterUrl }}">Hapus filter</a>
    @endif
</form>
