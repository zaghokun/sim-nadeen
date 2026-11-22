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
                @foreach (\App\Enums\MasterSection::cases() as $menuSection)
                    @can('view-master', $menuSection)
                        @php($current = request()->route('section') === $menuSection || request()->route('section') === $menuSection->value)
                        <a href="{{ route('master.index', $menuSection->value) }}" @if ($current) aria-current="page" @endif>
                            {{ $menuSection->label() }}
                        </a>
                    @endcan
                @endforeach
                @can('manage-accounts')
                    <a href="{{ route('accounts.index') }}" @if (request()->routeIs('accounts.*')) aria-current="page" @endif>Akun</a>
                @endcan
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
            {{ $slot }}
        </main>
    </div>
</x-layout>
