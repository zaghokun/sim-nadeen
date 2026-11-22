<x-workspace title="Akun">
    <header class="page-header">
        <h1>Akun</h1>
        <a class="button button-primary" href="{{ route('accounts.create') }}">Tambah akun</a>
    </header>

    @include('shared.filters', ['filterUrl' => route('accounts.index'), 'searchLabel' => 'Cari akun', 'showStatus' => true])

    @if ($records->isEmpty())
        <p class="empty-state">Tidak ada akun yang cocok dengan filter.</p>
    @else
        <div class="table-wrap">
            <table class="ledger" role="table" aria-label="Daftar akun">
                <thead role="rowgroup">
                    <tr role="row">
                        <th role="columnheader" scope="col">Nama lengkap</th>
                        <th role="columnheader" scope="col">Nama pengguna</th>
                        <th role="columnheader" scope="col">Peran</th>
                        <th role="columnheader" scope="col">Status</th>
                        <th role="columnheader" scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach ($records as $account)
                        <tr role="row">
                            <td role="cell"><span class="cell-label" aria-hidden="true">Nama lengkap</span><span>{{ $account->nama }}</span></td>
                            <td role="cell"><span class="cell-label" aria-hidden="true">Nama pengguna</span><span>{{ $account->username }}</span></td>
                            <td role="cell"><span class="cell-label" aria-hidden="true">Peran</span><span>{{ $account->peran->label() }}</span></td>
                            <td role="cell"><span class="cell-label" aria-hidden="true">Status</span><x-record-status :active="$account->aktif" /></td>
                            <td role="cell"><a class="table-action" href="{{ route('accounts.edit', $account) }}">Ubah<span class="sr-only"> {{ $account->nama }}</span></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @include('shared.page-navigation')
</x-workspace>
