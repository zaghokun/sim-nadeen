@use('App\Support\Decimal')
@use('App\Services\Orders')

@php($labels = ['arus-kas'=>'Arus kas', 'laba-pesanan'=>'Laba per pesanan', 'laba-rugi'=>'Laba rugi'])
<x-workspace :title="$labels[$report]">
    <header class="page-header"><h1>{{ $labels[$report] }}</h1></header>
    <nav class="section-links" aria-label="Jenis laporan">
        @foreach ($labels as $key => $label)
            <a href="{{ route('reports.index', ['report'=>$key, 'bulan'=>$month]) }}" @if($key === $report) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <a href="{{ route('inventory.index', 'bahan') }}">Inventori</a>
    </nav>
    <form class="filters" method="get" aria-label="Filter laporan">
        <div class="field"><label for="bulan">Bulan</label><input id="bulan" name="bulan" type="month" value="{{ $month }}"></div>
        @if($report === 'arus-kas' || $report === 'laba-pesanan')
            @php($field = $report === 'arus-kas' ? 'kategori' : 'pelanggan')
            <div class="field"><label for="{{ $field }}">{{ ucfirst($field) }}</label><select id="{{ $field }}" name="{{ $field }}">
                <option value="">Semua {{ $field }}</option>
                @foreach($options as $id => $name)<option value="{{ $id }}" @selected(($report === 'arus-kas' ? $category : $customer) === $id)>{{ $name }}</option>@endforeach
            </select></div>
        @endif
        @if($report === 'laba-pesanan')
            <div class="field"><label for="q">Kode pesanan</label><input id="q" name="q" type="search" value="{{ $search }}"></div>
            <div class="field"><label for="dari">Dari tanggal</label><input id="dari" name="dari" type="date" value="{{ request('dari') }}"></div>
            <div class="field"><label for="sampai">Sampai tanggal</label><input id="sampai" name="sampai" type="date" value="{{ request('sampai') }}"></div>
            <div class="field"><label for="hpp">Kesiapan HPP</label><select id="hpp" name="hpp">
                <option value="">Semua</option><option value="lengkap" @selected($ready === 'lengkap')>Lengkap</option><option value="tertunda" @selected($ready === 'tertunda')>Tertunda</option>
            </select></div>
        @endif
        <button class="button button-secondary" type="submit">Terapkan filter</button>
        <a class="button button-link" href="{{ route('reports.index', $report) }}">Reset periode dan filter</a>
    </form>
    @if ($report === 'arus-kas')
        <dl class="operation-summary">
            <div><dt>Saldo awal periode</dt><dd>{{ Decimal::rupiah($data['awal']) }}</dd></div>
            <div><dt>Kas masuk</dt><dd>{{ Decimal::rupiah($data['total']->masuk) }}</dd></div>
            <div><dt>Kas keluar</dt><dd>{{ Decimal::rupiah($data['total']->keluar) }}</dd></div>
            <div><dt>Saldo akhir</dt><dd>{{ Decimal::rupiah($data['akhir']) }}</dd></div>
            <div><dt>Arus operasional</dt><dd>{{ Decimal::rupiah($data['total']->operasional) }}</dd></div>
            <div><dt>Arus nonrutin</dt><dd>{{ Decimal::rupiah($data['total']->nonrutin) }}</dd></div>
            <div><dt>Saldo awal yang diposting</dt><dd>{{ Decimal::rupiah($data['total']->saldo_diposting) }}</dd></div>
        </dl>
        <section class="operation-section" aria-labelledby="categories"><h2 id="categories">Rincian kategori</h2>
            @if($category)<p class="muted">Ringkasan saldo mencakup semua kategori.</p>@endif
            <x-ledger :headers="['Kategori', 'Jenis', 'Masuk', 'Keluar']"
                :rows="$data['kelompok']->map(fn ($row) => [$row->nama_kategori, $row->kelompok === 'saldo_awal' ? 'Saldo awal' : ($row->nonrutin ? 'Nonrutin' : 'Rutin'),
                    ['label'=>Decimal::rupiah($row->masuk),'number'=>true], ['label'=>Decimal::rupiah($row->keluar),'number'=>true]])" label="Kas per kategori" />
            <a class="back-link" href="{{ route('cash.index', ['dari'=>$from, 'sampai'=>$to, 'kategori'=>$category]) }}">Lihat jurnal pada periode ini</a>
        </section>
    @elseif ($report === 'laba-pesanan')
        <x-ledger :headers="['Pesanan', 'Tanggal', 'Pelanggan', 'Sumber', 'Omzet', 'Estimasi HPP', 'Aktual HPP', 'Laba', 'Margin']"
            :rows="$records->map(fn ($row) => [
                ['label'=>$row->kode, 'url'=>route('orders.show', $row->id)], $row->tanggal, $row->pelanggan,
                implode(' + ', array_map(fn($source) => Orders::LABELS[$source], explode(', ', $row->sumber))),
                Decimal::rupiah($row->omzet), Decimal::rupiah($row->estimasi), $row->hpp === null ? 'Belum lengkap' : Decimal::rupiah($row->hpp),
                $row->hpp === null ? null : Decimal::rupiah(bcsub($row->omzet, $row->hpp, 2)),
                $row->hpp === null || bccomp($row->omzet, '0', 2) <= 0 ? null : Decimal::display(bcround(bcdiv(bcmul(bcsub($row->omzet, $row->hpp, 2), '100', 2), $row->omzet, 4), 2)).'%',
            ])" label="Laba per pesanan">Tidak ada pesanan yang cocok pada periode ini.</x-ledger>
        @include('shared.page-navigation')
    @else
        <dl class="operation-summary">
            <div><dt>Omzet pesanan selesai kirim</dt><dd>{{ Decimal::rupiah($data['sales']->omzet) }}</dd></div>
            <div><dt>HPP aktual tercatat</dt><dd>{{ Decimal::rupiah($data['sales']->hpp) }}</dd></div>
            <div><dt>Beban operasional</dt><dd>{{ Decimal::rupiah($data['beban']) }}</dd></div>
            <div><dt>Penyesuaian nonrutin</dt><dd>{{ Decimal::rupiah($data['penyesuaian']) }}</dd></div>
            <div><dt>{{ $data['sales']->belum_lengkap ? 'Hasil sementara' : 'Hasil manajemen' }}</dt><dd>{{ Decimal::rupiah($data['hasil']) }}</dd></div>
        </dl>
        @if($data['sales']->belum_lengkap)<p class="notice notice-error">{{ $data['sales']->belum_lengkap }} pesanan selesai kirim masih menunggu HPP lengkap.</p>@endif
        <p class="form-intro muted">{{ $data['sales']->pesanan }} pesanan selesai kirim. Beban yang telah masuk HPP dan belanja persediaan tidak dikurangkan lagi.</p>
    @endif
</x-workspace>
