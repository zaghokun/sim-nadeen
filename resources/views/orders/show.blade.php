@use('App\Services\Orders')
@use('App\Support\Decimal')

@php
    $confirmed = $record->status === 'konfirmasi';
@endphp
<x-workspace :title="$record->kode">
    <a class="back-link" href="{{ route('orders.index') }}">Kembali ke pesanan</a>
    <header class="page-header">
        <div><h1>{{ $record->kode }}</h1><p class="muted">{{ $customer->nama }} · {{ $record->tanggal }}</p></div>
        @if($admin)
            <div class="button-row">
                @if($record->status === 'draf')
                    <a class="button button-secondary" href="{{ route('orders.edit', $record->id) }}">Ubah draf</a>
                    <form method="post" action="{{ route('orders.confirm', $record->id) }}">
                        <x-post-token /><button type="submit" class="button button-primary">Konfirmasi pesanan</button>
                    </form>
                @elseif($confirmed && bccomp($due, '0', 2) > 0)
                    <a class="button button-primary" href="{{ route('payments.create', $record->id) }}">Catat pembayaran</a>
                @endif
                @if($record->status !== 'batal')
                    <a class="button button-secondary" href="{{ route('orders.print', $record->id) }}">{{ $confirmed ? 'Nota tagihan' : 'Nota estimasi' }}</a>
                @endif
            </div>
        @endif
    </header>

    <dl class="operation-summary">
        <div><dt>Dokumen</dt><dd>{{ Orders::LABELS[$record->status] }}</dd></div>
        @if($admin)
            <div><dt>Pembayaran</dt><dd>{{ Orders::LABELS[$record->status_bayar] }}</dd></div>
            <div><dt>Total tagihan</dt><dd>{{ Decimal::rupiah($total) }}</dd></div>
            <div><dt>Sisa tagihan</dt><dd>{{ Decimal::rupiah($due) }}</dd></div>
        @endif
        <div><dt>Produksi</dt><dd>{{ Orders::LABELS[$record->status_produksi] }}</dd></div>
        <div><dt>Pengiriman</dt><dd>{{ Orders::LABELS[$record->status_kirim] }}</dd></div>
    </dl>
    @if($customer->alamat)<p class="form-intro">Alamat pengiriman {{ $customer->alamat }}</p>@endif
    @if($admin && $record->catatan)<p class="form-intro">{{ $record->catatan }}</p>@endif

    <section class="operation-section" aria-labelledby="items">
        <h2 id="items">Rincian barang</h2>
        @foreach ($lines as $line)
            @php
                $moves = $materials->get($line->id, collect());
                $estimate = $line->estimasi_bahan !== null
                    ? bcadd(bcadd($line->estimasi_bahan, $line->estimasi_upah, 2), $line->estimasi_overhead, 2) : null;
                $materialCost = $admin && $moves->isNotEmpty()
                    ? $moves->reduce(fn ($sum, $row) => bcsub($sum, $row->perubahan_nilai, 2), '0.00') : '0.00';
            @endphp
            <article class="line-panel">
                <div class="section-heading">
                    <div><h3>{{ $line->nama_produk }}</h3><p class="muted">{{ $line->kode_produk }} · {{ $line->jumlah }} unit · {{ Orders::LABELS[$line->sumber] }}</p></div>
                    <span class="record-status">{{ Orders::LABELS[$line->kesiapan] }}</span>
                </div>
                @if($admin)
                    <dl class="operation-summary compact-summary">
                        <div><dt>Harga per unit</dt><dd>{{ Decimal::rupiah($line->harga_satuan) }}</dd></div>
                        <div><dt>HPP estimasi per unit</dt><dd>{{ $estimate === null ? 'HPP belum lengkap' : Decimal::rupiah($estimate) }}</dd></div>
                        <div><dt>HPP aktual seluruh baris</dt><dd>{{ $line->hpp_aktual === null ? 'Menunggu pengesahan' : Decimal::rupiah($line->hpp_aktual) }}</dd></div>
                        @if($line->sumber === 'khusus')<div><dt>Pemakaian bahan bersih</dt><dd>{{ Decimal::rupiah($materialCost) }}</dd></div>@endif
                        @if($line->aktual_upah !== null)<div><dt>Upah aktual</dt><dd>{{ Decimal::rupiah($line->aktual_upah) }}</dd></div>@endif
                        @if($line->aktual_overhead !== null)<div><dt>Overhead aktual</dt><dd>{{ Decimal::rupiah($line->aktual_overhead) }}</dd></div>@endif
                    </dl>
                @endif
                @if ($line->sumber === 'khusus' && $confirmed)
                    @if ($moves->isNotEmpty())
                        <ul class="transaction-list">
                            @foreach ($moves as $move)
                                <li>
                                    <span>{{ $move->tanggal }} · {{ $move->nama }} · {{ Decimal::display($move->perubahan_jumlah, 3) }} {{ $move->satuan }}</span>
                                    @if ($admin && $move->jenis === 'keluar' && !$move->pembalik_id && !$line->hpp_disahkan_pada && $record->status_kirim === 'belum')
                                        <a class="table-action" href="{{ route('orders.material.reverse.form', [$record->id, $line->id, $move->id]) }}">Balik pemakaian</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="button-row">
                        @if($record->status_kirim === 'belum' && in_array($line->kesiapan, ['antri', 'proses'], true))
                            <form method="post" action="{{ route('orders.production', [$record->id, $line->id]) }}">
                                <x-post-token />
                                <input type="hidden" name="status" value="{{ $line->kesiapan === 'antri' ? 'proses' : 'siap' }}">
                                <button class="button button-primary" type="submit">{{ $line->kesiapan === 'antri' ? 'Mulai pengerjaan' : 'Tandai siap' }}</button>
                            </form>
                            @if($line->kesiapan === 'proses')
                                <a class="button button-secondary" href="{{ route('orders.work.form', [$record->id, $line->id, 'pakai']) }}">Catat pemakaian</a>
                                <a class="button button-secondary" href="{{ route('purchases.create', ['pesanan'=>$record->id]) }}">Belanja bahan</a>
                            @endif
                        @endif
                        @if($admin && $line->kesiapan === 'siap' && !$line->hpp_disahkan_pada)
                            <a class="button button-secondary" href="{{ route('orders.work.form', [$record->id, $line->id, 'hpp']) }}">Sahkan HPP aktual</a>
                        @endif
                    </div>
                @endif
            </article>
        @endforeach
    </section>

    @if($admin && $confirmed)
        <section class="operation-section" aria-labelledby="pembayaran"><h2 id="pembayaran">Riwayat pembayaran</h2>
            @if($payments->isEmpty())<p class="empty-state">Belum ada pembayaran.</p>@endif
            <ul class="transaction-list">
                @foreach ($payments as $payment)
                    <li><span>{{ $payment->tanggal }} · {{ Orders::LABELS[$payment->jenis] }} · {{ Decimal::rupiah($payment->nominal) }} · {{ $payment->metode }} @if($payment->alasan)· {{ $payment->alasan }}@endif</span>
                        <span class="button-row"><a class="table-action" href="{{ route('orders.receipt', [$record->id, $payment->id]) }}">Bukti</a>
                            @if(!$payment->asal_id && !$payment->pembalik_id)
                                <a class="table-action" href="{{ route('finance.reverse.form', ['pembayaran', $payment->id]) }}">Balik pembayaran</a>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($confirmed && $record->status_kirim !== 'selesai')
        <section class="operation-section" aria-labelledby="delivery"><h2 id="delivery">Pengiriman</h2>
            @if($record->status_produksi === 'selesai')
                <form method="post" action="{{ route('orders.shipping', $record->id) }}">
                    <x-post-token />
                    <input type="hidden" name="status" value="{{ $record->status_kirim === 'belum' ? 'dikirim' : 'selesai' }}">
                    <button class="button button-primary" type="submit">{{ $record->status_kirim === 'belum' ? 'Tandai dikirim' : 'Selesaikan pengiriman' }}</button>
                </form>
            @else
                <p class="muted">Pengiriman tersedia setelah semua barang siap.</p>
            @endif
        </section>
    @endif

    <section class="operation-section" aria-labelledby="history"><h2 id="history">Riwayat status</h2>
        <x-ledger :headers="['Waktu (WIB)', 'Dimensi', 'Perubahan', 'Oleh', 'Alasan']"
            :rows="$history->map(fn ($h) => [
                Carbon\CarbonImmutable::parse($h->created_at, 'UTC')->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
                ['status'=>'Dokumen','status_kirim'=>'Pengiriman','status_bayar'=>'Pembayaran','status_produksi'=>'Produksi','kesiapan'=>'Kesiapan barang','hpp'=>'HPP'][$h->dimensi] ?? $h->dimensi,
                ($h->sebelum ? (Orders::LABELS[$h->sebelum] ?? $h->sebelum).' → ' : '').(Orders::LABELS[$h->sesudah] ?? ucfirst($h->sesudah)),
                $h->aktor, $h->alasan,
            ])" label="Riwayat status pesanan" />
    </section>
    @if($admin && $record->status !== 'batal' && $record->status_kirim === 'belum')
        <div class="operation-section"><a class="button button-link" href="{{ route('orders.cancel.form', $record->id) }}">Batalkan pesanan</a></div>
    @endif
</x-workspace>
