<x-layout title="Masuk">
    <main class="login">
        <section class="login-identity" aria-labelledby="app-name">
            <h1 id="app-name">Furniture<br>Nadeen</h1>
            <p>Sistem informasi bengkel</p>
        </section>

        <section class="login-panel" aria-labelledby="login-title">
            <h2 id="login-title">Masuk</h2>
            <p class="muted">Gunakan akun yang disiapkan oleh admin.</p>

            @if (session('status'))
                <p class="notice" role="status">{{ session('status') }}</p>
            @endif

            <form method="post" action="{{ route('login.store') }}" class="login-form">
                @csrf
                @include('shared.field', [
                    'name' => 'username', 'field' => [
                        'label' => 'Nama pengguna', 'rules' => ['required'], 'autocomplete' => 'username', 'max' => 40, 'autofocus' => true,
                    ],
                    'values' => [],
                ])
                @include('shared.field', [
                    'name' => 'password', 'field' => [
                        'label' => 'Kata sandi', 'rules' => ['required'], 'type' => 'password', 'autocomplete' => 'current-password',
                    ],
                    'values' => [],
                ])
                <button class="button button-primary" type="submit">Masuk</button>
            </form>
        </section>
    </main>
</x-layout>
