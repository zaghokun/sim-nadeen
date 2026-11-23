@use('App\Support\Decimal')

<x-workspace title="Dasbor">
    <header class="page-header"><h1>Dasbor</h1></header>
    <form method="get" class="filters" aria-label="Periode dan ambang konsentrasi">
        <div class="field"><label for="bulan">Bulan</label><input id="bulan" name="bulan" type="month" value="{{ $month }}"></div>
        <div class="field"><label for="ambang">Ambang kontribusi (%)</label><input id="ambang" name="ambang" type="number" min="0" max="100" step="0.01" value="{{ $threshold }}"></div>
        <button class="button button-secondary" type="submit">Terapkan filter</button>
    </form>
    <section class="operation-section" aria-labelledby="contributors">
        <h2 id="contributors">Kontribusi pelanggan</h2>
        <p class="muted">{{ $data['pesanan'] }} pesanan pertama kali lunas pada periode ini · Total {{ Decimal::rupiah($data['omzet']) }}</p>
        @forelse ($data['rows'] as $row)
            <article class="line-panel">
                <div class="section-heading"><h3>{{ $row->nama }}</h3><strong>{{ Decimal::display($row->persen) }}%</strong></div>
                <meter min="0" max="100" value="{{ $row->persen }}" aria-label="Kontribusi {{ $row->nama }}">{{ $row->persen }}%</meter>
                <p>{{ Decimal::rupiah($row->omzet) }} · {{ $row->pesanan }} pesanan · HPP lengkap {{ $row->hpp_lengkap }} dari {{ $row->pesanan }}</p>
                @if($row->laba !== null)<p>Laba dari pesanan ber-HPP lengkap {{ Decimal::rupiah($row->laba) }}</p>@endif
                @if($row->peringatan)<p class="notice-inline">Kontribusi melebihi {{ Decimal::display($threshold) }}%</p>@endif
            </article>
        @empty
            <p class="empty-state">Belum ada omzet dari pesanan yang pertama kali lunas pada bulan ini.</p>
        @endforelse
    </section>
    <section class="operation-section" aria-labelledby="more">
        <h2 id="more">Ringkasan operasional</h2>
        <dl class="operation-summary">
            <div><dt>Saldo akhir</dt><dd>{{ Decimal::rupiah($cash['akhir']) }}</dd></div>
            <div><dt>Hasil manajemen</dt><dd>{{ Decimal::rupiah($profit['hasil']) }} @if($profit['sales']->belum_lengkap)<span class="muted">(sementara)</span>@endif</dd></div>
        </dl>
        <div class="button-row">
            <a class="button button-secondary" href="{{ route('reports.index', ['report'=>'arus-kas', 'bulan'=>$month]) }}">Lihat arus kas</a>
            <a class="button button-secondary" href="{{ route('reports.index', ['report'=>'laba-rugi', 'bulan'=>$month]) }}">Lihat laba rugi</a>
            <a class="button button-secondary" href="{{ route('inventory.index', ['kind'=>'bahan','filter'=>'minimum']) }}">Stok bahan minimum</a>
        </div>
    </section>
</x-workspace>
