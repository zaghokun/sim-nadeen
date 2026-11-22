@php
    $title = $account ? 'Ubah akun' : 'Tambah akun';
    $values = $account ? [
        'nama' => $account->nama,
        'username' => $account->username,
        'peran' => $account->peran->value,
        'aktif' => $account->aktif,
    ] : ['aktif' => true];
    $passwordHint = $account ? 'Isi untuk mengganti kata sandi. Gunakan minimal 10 karakter.' : 'Gunakan minimal 10 karakter.';
    $roleOptions = collect(\App\Enums\UserRole::cases())->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all();
    $fields = [
        'nama' => ['label' => 'Nama lengkap', 'rules' => ['required'], 'max' => 120],
        'username' => ['label' => 'Nama pengguna', 'rules' => ['required'], 'max' => 40, 'autocomplete' => 'off', 'hint' => 'Gunakan 3 sampai 40 karakter. Contoh: staf.gudang.'],
        'peran' => ['label' => 'Peran', 'type' => 'select', 'rules' => ['required'], 'options' => $roleOptions],
        'aktif' => ['label' => 'Akun aktif', 'type' => 'checkbox', 'rules' => []],
        'password' => ['label' => 'Kata sandi', 'type' => 'password', 'rules' => $account ? [] : ['required'], 'autocomplete' => 'new-password', 'hint' => $passwordHint],
        'password_confirmation' => ['label' => 'Ulangi kata sandi', 'type' => 'password', 'rules' => $account ? [] : ['required'], 'autocomplete' => 'new-password'],
    ];
@endphp

<x-workspace :title="$title">
    <header class="page-header"><h1>{{ $title }}</h1></header>
    <form class="editor" method="post" action="{{ $account ? route('accounts.update', $account) : route('accounts.store') }}">
        @csrf
        @if ($account)
            @method('PATCH')
        @endif

        <div class="form-grid">
            @foreach ($fields as $name => $field)
                @include('shared.field')
            @endforeach
        </div>
        <footer class="form-actions">
            <button class="button button-primary" type="submit">Simpan</button>
            <a class="button button-secondary" href="{{ route('accounts.index') }}">Batal</a>
        </footer>
    </form>
</x-workspace>
