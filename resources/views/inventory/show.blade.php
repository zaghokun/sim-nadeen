@use('App\Support\Decimal')

@php($unit = $record->satuan ?? 'unit')
<x-workspace :title="$record->nama">
    <a class="back-link" href="{{ route('inventory.index', $kind->value) }}">Kembali ke inventori</a>
    <header class="page-header">
        <div><h1>{{ $record->nama }}</h1><p class="muted">{{ $record->kode }} · {{ $kind->label() }} · {{ $unit }}</p></div>
        @if ($record->aktif)
            <div class="button-row">
                <a class="button button-primary" href="{{ route('inventory.receive.form', [$kind->value, $record->id]) }}">Catat stok masuk</a>
                <a class="button button-secondary" href="{{ route('inventory.issue.form', [$kind->value, $record->id]) }}">Penyesuaian keluar</a>
            </div>
        @endif
    </header>
    <section class="operation-section" aria-labelledby="lot">
        <h2 id="lot">Lot stok</h2>
        <div class="table-wrap">
            <table class="ledger" role="table" aria-label="Lot stok">
                <thead role="rowgroup"><tr role="row">
                    <th role="columnheader" scope="col">Lot</th><th role="columnheader" scope="col">Tanggal masuk</th>
                    <th role="columnheader" scope="col">Kondisi</th><th role="columnheader" scope="col">Sisa</th>
                    <th role="columnheader" scope="col">Pengesahan</th>
                    @if($admin)<th role="columnheader" scope="col">Nilai sisa</th>@endif
                    <th role="columnheader" scope="col">Aksi</th>
                </tr></thead>
                <tbody role="rowgroup">
                    @forelse ($lots as $lot)
                        <tr role="row">
                            <td role="cell"><span class="cell-label" aria-hidden="true">Lot</span><span>{{ '#'.$lot->id }}</span></td>
                            <td role="cell"><span class="cell-label" aria-hidden="true">Tanggal masuk</span><span>{{ $lot->tanggal_masuk ?? 'Belum diketahui' }}</span></td>
                            <td role="cell"><span class="cell-label" aria-hidden="true">Kondisi</span><span>{{ $lot->kondisi === 'layak' ? 'Layak' : 'Rusak' }}</span></td>
                            <td role="cell"><span class="cell-label" aria-hidden="true">Sisa</span><span>{{ Decimal::display($lot->jumlah_sisa, 3) }} {{ $unit }}</span></td>
                            <td role="cell"><span class="cell-label" aria-hidden="true">Pengesahan</span><span>{{ $lot->nilai_disahkan_pada ? 'Disahkan' : 'Menunggu' }}</span></td>
                            @if($admin)<td role="cell"><span class="cell-label" aria-hidden="true">Nilai</span><span>{{ Decimal::rupiah($lot->nilai_sisa) }}</span></td>@endif
                            <td role="cell"><span class="cell-label" aria-hidden="true">Aksi</span><span>
                                @if (bccomp($lot->jumlah_sisa, '0', 3) > 0)
                                    <a class="table-action" href="{{ route('inventory.condition.form', [$kind->value, $record->id, $lot->id]) }}">Ubah kondisi</a>
                                    @if($admin && !$lot->nilai_disahkan_pada)
                                        <a class="table-action" href="{{ route('inventory.value.form', [$kind->value, $record->id, $lot->id]) }}">Sahkan nilai</a>
                                    @endif
                                @endif
                            </span></td>
                        </tr>
                    @empty
                        <tr role="row"><td role="cell" colspan="{{ $admin ? 7 : 6 }}">Belum ada lot untuk item ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('shared.page-navigation', ['records' => $lots])
    </section>
    <section class="operation-section" aria-labelledby="mutasi">
        <h2 id="mutasi">Riwayat mutasi</h2>
        <x-ledger
            :headers="['Tanggal', 'Lot', 'Jenis', 'Perubahan jumlah', ...($admin ? ['Perubahan nilai'] : []), 'Alasan']"
            :rows="$movements->map(fn ($m) => [$m->tanggal, '#'.$m->lot_id, str_replace('_', ' ', ucfirst($m->jenis)), Decimal::display($m->perubahan_jumlah, 3), ...($admin ? [Decimal::rupiah($m->perubahan_nilai)] : []), $m->alasan])"
            label="Mutasi stok"
        />
        @include('shared.page-navigation', ['records' => $movements])
    </section>
</x-workspace>
