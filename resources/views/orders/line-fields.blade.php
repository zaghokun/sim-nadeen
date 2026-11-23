<fieldset class="order-line" data-order-line>
    <legend data-line-legend>Barang {{ is_int($index) ? $index + 1 : '' }}</legend>
    <div class="form-grid">
        <div class="field">
            <label for="produk-{{ $index }}">Produk</label>
            <select id="produk-{{ $index }}" name="detail[{{ $index }}][produk_id]" data-product required>
                <option value="">Pilih produk</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected((string) ($entry['produk_id'] ?? '') === (string) $product->id)>{{ $product->kode }} · {{ $product->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="sumber-{{ $index }}">Sumber pemenuhan</label>
            <select id="sumber-{{ $index }}" name="detail[{{ $index }}][sumber]" required>
                <option value="khusus" @selected(($entry['sumber'] ?? '') === 'khusus')>Pesanan khusus</option>
                <option value="stok" @selected(($entry['sumber'] ?? '') === 'stok')>Stok</option>
            </select>
        </div>
        <div class="field">
            <label for="jumlah-{{ $index }}">Jumlah</label>
            <input id="jumlah-{{ $index }}" name="detail[{{ $index }}][jumlah]" value="{{ is_scalar($entry['jumlah'] ?? null) ? $entry['jumlah'] : 1 }}" inputmode="numeric" data-quantity required>
        </div>
        <div class="field">
            <label for="harga-{{ $index }}">Harga satuan disepakati (Rp)</label>
            <input id="harga-{{ $index }}" name="detail[{{ $index }}][harga_satuan]" value="{{ is_scalar($entry['harga_satuan'] ?? null) ? $entry['harga_satuan'] : '' }}" inputmode="decimal" data-price>
        </div>
    </div>
    <p class="estimate-line" data-line-estimate aria-live="polite" hidden></p>
    <div class="button-row">
        <button class="button button-secondary" type="button" data-apply-price hidden>Pakai harga saran</button>
        <button class="button button-link" type="button" data-remove-line hidden>Hapus baris</button>
    </div>
</fieldset>
