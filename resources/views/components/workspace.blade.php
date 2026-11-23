@props(['title'])

<x-layout :title="$title">
    <a class="skip-link" href="#konten">Langsung ke konten</a>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="wordmark" href="{{ route('master.index', 'produk') }}">
                <strong>Furniture Nadeen</strong>
                <span>Sistem informasi</span>
            </a>

            <nav class="sidebar-nav" aria-label="Navigasi utama">
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('reports.dashboard') }}" @if (request()->routeIs('reports.dashboard')) aria-current="page" @endif>Dasbor</a>
                @endif
                <a href="{{ route('orders.index') }}" @if (request()->routeIs('orders.*')) aria-current="page" @endif>Pesanan</a>
                <a href="{{ route('inventory.index', 'bahan') }}" @if (request()->routeIs('inventory.*')) aria-current="page" @endif>Inventori</a>
                <a href="{{ route('purchases.index') }}" @if (request()->routeIs('purchases.*')) aria-current="page" @endif>Pembelian</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('cash.index') }}" @if (request()->routeIs('cash.*')) aria-current="page" @endif>Buku Kas</a>
                    <a href="{{ route('reports.index') }}" @if (request()->routeIs('reports.*') && !request()->routeIs('reports.dashboard')) aria-current="page" @endif>Laporan</a>
                @endif
                <details @if(request()->routeIs('master.*', 'accounts.*')) open @endif>
                    <summary>Master data</summary>
                    <div class="sidebar-subnav">
                        @foreach (\App\Enums\MasterSection::cases() as $menuSection)
                            @can('view-master', $menuSection)
                                @php($current = request()->route('section') === $menuSection || request()->route('section') === $menuSection->value)
                                <a href="{{ route('master.index', $menuSection->value) }}" @if ($current) aria-current="page" @endif>{{ $menuSection->label() }}</a>
                            @endcan
                        @endforeach
                        @can('manage-accounts')
                            <a href="{{ route('accounts.index') }}" @if (request()->routeIs('accounts.*')) aria-current="page" @endif>Akun</a>
                        @endcan
                    </div>
                </details>
            </nav>

            <div class="sidebar-account">
                <div class="account-info">
                    <strong>{{ auth()->user()->nama }}</strong>
                    <span>{{ auth()->user()->peran->label() }}</span>
                </div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="button button-quiet">Keluar</button>
                </form>
            </div>
        </aside>

        <main id="konten" class="workspace" tabindex="-1">
            @if (session('status'))
                <p role="status" class="notice">{{ session('status') }}</p>
            @endif
            @if ($errors->any() && !request()->routeIs('master.*', 'accounts.*'))
                <div class="notice notice-error" role="alert">
                    <p>Periksa isian berikut.</p>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</x-layout>
