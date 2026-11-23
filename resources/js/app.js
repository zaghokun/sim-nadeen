const rupiah = (cents) => {
    const absolute = cents < 0n ? -cents : cents;
    const fraction = absolute % 100n;
    return (cents < 0n ? '−' : '') + 'Rp ' + (absolute / 100n).toLocaleString('id-ID')
        + (fraction ? ',' + fraction.toString().padStart(2, '0') : '');
};

function toCents(value) {
    if (!/^[0-9]{1,24}(?:\.[0-9]{1,2})?$/.test(value)) return null;
    const [whole, fraction = ''] = value.split('.');
    return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
}

function configureOrder(form) {
    const list = form.querySelector('[data-order-lines]');
    const template = form.querySelector('[data-line-template]');
    const add = form.querySelector('[data-add-line]');
    const summary = form.querySelector('[data-estimate-total]');
    const rows = () => [...list.querySelectorAll('[data-order-line]')];
    const state = new WeakMap();
    let index = rows().length;

    function paint() {
        let sales = 0n;
        let costs = 0n;
        let counted = 0;
        let incomplete = false;
        for (const [position, row] of rows().entries()) {
            const current = state.get(row);
            const quantityInput = row.querySelector('[data-quantity]').value;
            const quantity = /^[1-9][0-9]{0,6}$/.test(quantityInput) ? BigInt(quantityInput) : 0n;
            const price = toCents(row.querySelector('[data-price]').value);
            const status = row.querySelector('[data-line-estimate]');
            const choice = row.querySelector('[data-product]').value;
            const estimate = current?.estimate;

            row.querySelector('[data-line-legend]').textContent = 'Barang ' + (position + 1);
            row.querySelector('[data-remove-line]').hidden = rows().length === 1;
            row.querySelector('[data-apply-price]').hidden = !estimate || toCents(estimate.saran) === null;
            status.hidden = !choice;
            if (choice && !estimate) status.textContent = current?.text ?? 'Standar HPP belum lengkap.';
            if (estimate) {
                status.textContent = 'Bahan ' + rupiah(toCents(estimate.estimasi_bahan))
                    + ' · Upah ' + rupiah(toCents(estimate.estimasi_upah))
                    + ' · Overhead ' + rupiah(toCents(estimate.estimasi_overhead))
                    + ' · HPP per unit ' + rupiah(toCents(estimate.total))
                    + ' · Markup ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(Number(estimate.markup)) + '%'
                    + ' · Harga saran ' + rupiah(toCents(estimate.saran));
            }
            if (choice && quantity && price !== null) {
                sales += price * quantity;
                counted += 1;
                if (estimate) costs += toCents(estimate.total) * quantity;
                else incomplete = true;
            } else {
                incomplete = true;
            }
        }
        add.hidden = rows().length >= 20;
        summary.hidden = counted === 0;
        if (counted > 0) {
            const profit = sales - costs;
            const margin = sales > 0n ? ((profit * 1000n) / sales).toString() : null;
            summary.textContent = 'Nilai jual ' + rupiah(sales)
                + (incomplete ? ' · Ringkasan estimasi belum lengkap.' : ' · HPP ' + rupiah(costs)
                    + ' · Estimasi laba ' + rupiah(profit)
                    + (margin === null ? '' : ' · Margin ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(Number(margin) / 10) + '%'));
        }
    }

    async function load(row) {
        state.get(row)?.controller?.abort();
        const product = row.querySelector('[data-product]').value;
        if (!product) {
            state.delete(row);
            paint();
            return;
        }
        const controller = new AbortController();
        state.set(row, { controller, text: 'Memuat standar HPP…' });
        paint();
        const url = new URL(form.dataset.estimate);
        url.searchParams.set('produk_id', product);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) throw new Error('Estimation request failed');
            const { estimasi } = await response.json();
            if (controller.signal.aborted) return;
            state.set(row, { estimate: estimasi });
        } catch {
            if (controller.signal.aborted) return;
            state.set(row, { text: 'Gagal memuat estimasi. Pilih ulang produk untuk mencoba lagi.' });
        }
        paint();
    }

    add.addEventListener('click', () => {
        if (rows().length >= 20) return;
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__BARIS__', String(index++)));
        paint();
        rows().at(-1).querySelector('[data-product]').focus();
    });
    list.addEventListener('input', paint);
    list.addEventListener('change', (event) => {
        if (event.target.matches('[data-product]')) void load(event.target.closest('[data-order-line]'));
    });
    list.addEventListener('click', (event) => {
        const row = event.target.closest('[data-order-line]');
        if (!row) return;
        if (event.target.matches('[data-remove-line]') && rows().length > 1) {
            state.get(row)?.controller?.abort();
            row.remove();
            paint();
            const destination = add.hidden ? rows().at(-1).querySelector('[data-product]') : add;
            destination.focus();
        }
        if (event.target.matches('[data-apply-price]') && state.get(row)?.estimate) {
            row.querySelector('[data-price]').value = state.get(row).estimate.saran;
            paint();
        }
    });
    paint();
    rows().forEach((row) => { if (row.querySelector('[data-product]').value) void load(row); });
}

document.querySelectorAll('[data-order-editor]').forEach(configureOrder);
document.querySelectorAll('[data-print]').forEach((button) => {
    button.hidden = false;
    button.addEventListener('click', () => window.print());
});
