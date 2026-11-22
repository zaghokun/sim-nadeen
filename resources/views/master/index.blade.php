@php($numericColumns = ['harga_standar', 'total_hpp', 'markup'])

<x-workspace :title="$section->label()">
    <header class="page-header">
        <h1>{{ $section->label() }}</h1>
        @if ($admin)
            <a class="button button-primary" href="{{ route('master.create', $section->value) }}">
                Tambah {{ \Illuminate\Support\Str::lower($section->label()) }}
            </a>
        @endif
    </header>

    @include('shared.filters', [
        'filterUrl' => route('master.index', $section->value),
        'searchLabel' => 'Cari '.\Illuminate\Support\Str::lower($section->label()),
        'showStatus' => $admin,
    ])

    @if ($records->isEmpty())
        <p class="empty-state">{{ $filters['q'] !== '' || $filters['status'] !== '' ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data untuk ditampilkan.' }}</p>
    @else
        <div class="table-wrap">
            <table class="ledger" role="table" aria-label="Daftar {{ $section->label() }}">
                <thead role="rowgroup">
                    <tr role="row">
                        @foreach ($columns as $key => $label)
                            <th role="columnheader" scope="col" @class(['numeric' => in_array($key, $numericColumns, true)])>{{ $label }}</th>
                        @endforeach
                        @if ($admin)
                            <th role="columnheader" scope="col">Status</th>
                            <th role="columnheader" scope="col">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach ($records as $record)
                        <tr role="row">
                            @foreach ($columns as $key => $label)
                                <td role="cell" @class(['numeric' => in_array($key, $numericColumns, true)])>
                                    <span class="cell-label" aria-hidden="true">{{ $label }}</span>
                                    <span>@include('master.value', ['key' => $key, 'value' => data_get($record, $key)])</span>
                                </td>
                            @endforeach
                            @if ($admin)
                                <td role="cell">
                                    <span class="cell-label" aria-hidden="true">Status</span>
                                    <x-record-status :active="$record->aktif" />
                                </td>
                                <td role="cell">
                                    <a class="table-action" href="{{ route('master.edit', [$section->value, $record->id]) }}">
                                        Ubah<span class="sr-only"> {{ $record->nama ?? $record->produk?->nama }}</span>
                                    </a>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @include('shared.page-navigation')
</x-workspace>
