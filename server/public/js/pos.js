/* Zion POS till — Flutter sales_page parity.
   Server owns the transaction; browser owns the cart. Works offline via
   IndexedDB queue (offline.js) with idempotency keys. */
(function () {
    'use strict';

    // ---------- catalog ----------
    let PRODUCTS = [];
    let CATEGORIES = [];
    try {
        PRODUCTS = JSON.parse(decodeURIComponent(escape(atob(document.querySelector('meta[name="pos-products"]').content))));
        CATEGORIES = JSON.parse(decodeURIComponent(escape(atob(document.querySelector('meta[name="pos-categories"]').content))));
    } catch (e) { console.error('catalog decode failed', e); }

    const CUR = (window.POS_STORE && window.POS_STORE.currency) || 'GHS';
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    // ---------- state ----------
    const cart = [];          // {id, name, price, qty, stock, tax_rate}
    const payments = [];      // {method, amount, reference}
    let discount = 0;
    let selectedMethod = 'cash';
    let activeCategory = 'all';
    let offlineMode = !navigator.onLine;

    // ---------- helpers ----------
    const $ = (sel) => document.querySelector(sel);
    const money = (v) => `${CUR} ${Number(v || 0).toFixed(2)}`;
    const findProduct = (id) => PRODUCTS.find((p) => p.id === id);
    const flash = (msg, kind) => {
        if (window.flashInfo && kind !== 'error' && kind !== 'warn') return window.flashInfo(msg);
        if (window.flashError && kind === 'error') return window.flashError(msg);
        if (window.flashWarn && kind === 'warn') return window.flashWarn(msg);
        console.log(`[${kind || 'info'}] ${msg}`);
    };
    const setDisabled = (btn, on) => { btn.disabled = on; };

    // ---------- icons (feather-style line icons) ----------
    const ic = (body, size = 15) =>
        `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;flex:none;">${body}</svg>`;
    const IC = {
        grid: ic('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'),
        tag: ic('<path d="M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.6 8.6a2 2 0 0 0 2.8 0l7.2-7.2a2 2 0 0 0 0-2.8z"/><circle cx="7.5" cy="7.5" r="1" fill="currentColor" stroke="none"/>'),
        cash: ic('<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h-.01"/>'),
        phone: ic('<rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18.5h2"/>'),
        card: ic('<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'),
        box: ic('<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/>'),
        ticket: ic('<path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a3 3 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a3 3 0 0 0 0-6z"/><path d="M13 5v2M13 11v2M13 17v2"/>'),
    };

    // ---------- product list ----------
    function stockLabel(p) {
        return Number.isInteger(p.stock) ? `Stock: ${p.stock}` : `Stock: ${p.stock.toFixed(1)}`;
    }

    function matches(p, q) {
        const s = q.trim().toLowerCase();
        if (!s) return true;
        return [p.name, p.sku, p.barcode || ''].some((t) => (t || '').toLowerCase().includes(s));
    }

    function renderProducts() {
        const list = $('#pos-list');
        const q = $('#pos-search').value;
        let items = PRODUCTS.filter((p) => matches(p, q))
            .filter((p) => activeCategory === 'all' || (p.category || '') === activeCategory);

        if (!items.length) {
            list.innerHTML = PRODUCTS.length
                ? '<div class="text-center text-muted py-4">No products match your search.</div>'
                : `<div class="text-center text-muted py-4"><div class="mb-2 d-inline-block">${IC.box}</div>No products yet — the catalog loads automatically when online.</div>`;
            return;
        }
        list.innerHTML = items.map((p) => {
            const out = p.stock <= 0;
            const low = p.stock > 0 && p.stock <= 5;
            return `<button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2 pos-item py-2" data-id="${p.id}">
                <span class="p-tile ${p.serialized ? 'ser' : ''}">${p.serialized ? IC.ticket : IC.box}</span>
                <span class="flex-grow-1 min-w-0 text-start">
                    <span class="d-block fw-semibold text-wrap" style="line-height:1.2;">${escapeHtml(p.name)}</span>
                    <span class="d-inline-flex align-items-center gap-1" style="font-size:11px;color:var(--muted);">
                        ${escapeHtml(p.sku)} ·
                        <span class="stock-chip ${out ? 'out' : ''}">${stockLabel(p)}</span>
                        ${low ? '<span style="color:#F59E0B;font-weight:700;">!</span>' : ''}
                    </span>
                </span>
                <span class="price-tag">${money(p.price)}</span>
            </button>`;
        }).join('');
        list.querySelectorAll('.pos-item').forEach((el) => {
            el.addEventListener('click', () => addToCart(Number(el.dataset.id)));
        });
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function renderCategories() {
        const row = $('#pos-category-row');
        row.innerHTML = [['all', 'All Products']].concat(CATEGORIES.map((c) => [c.name, c.name]))
            .map(([id, label]) => `<button type="button" class="mode-chip ${activeCategory === id ? 'active' : ''}" data-cat="${escapeHtml(id)}">${id === 'all' ? IC.grid : IC.tag} ${escapeHtml(label)}</button>`)
            .join('');
        row.querySelectorAll('.mode-chip').forEach((chip) => chip.addEventListener('click', () => {
            activeCategory = chip.dataset.cat;
            renderCategories();
            renderProducts();
        }));
    }

    // ---------- cart ----------
    function addToCart(id, quiet) {
        const p = findProduct(id);
        if (!p) return;
        const line = cart.find((l) => l.id === id);
        const nextQty = (line ? line.qty : 0) + 1;
        if (nextQty > p.stock) {
            flash(`Only ${Math.floor(p.stock)} in stock for ${p.name}.`, 'warn');
            return;
        }
        if (line) line.qty = nextQty; else cart.push({ id, name: p.name, price: p.price, qty: 1, stock: p.stock, tax_rate: p.tax_rate });
        // optimistic local stock decrement
        p.stock = Math.max(0, p.stock - 1);
        renderProducts();
        renderCart();
        if (!quiet) flash(`Added ${p.name}`, 'info');
    }

    function setQty(id, qty) {
        const index = cart.findIndex((l) => l.id === id);
        if (index < 0) return;
        const current = cart[index];
        const p = findProduct(id);
        // Optimal local stock view: stock already reflects current cart qty.
        const target = Math.min(Math.max(0, qty), current.qty + Math.floor(p ? p.stock : 0));
        const delta = target - current.qty; // positive = add, negative = remove
        if (p && delta !== 0) p.stock = Math.max(0, p.stock - delta);
        if (target <= 0) cart.splice(index, 1);
        else current.qty = target;
        renderProducts();
        renderCart();
    }

    function cartTotals() {
        const gross = cart.reduce((s, l) => s + l.price * l.qty, 0);
        const taxGross = cart.reduce((s, l) => s + (l.tax_rate > 0 ? l.price * l.qty * l.tax_rate / (100 + l.tax_rate) : 0), 0);
        discount = Math.min(Math.max(discount, 0), gross || 0);
        const ratio = gross > 0 ? (gross - discount) / gross : 0;
        const tax = taxGross * ratio;
        return { gross, taxGross, tax, total: gross - discount, units: cart.reduce((s, l) => s + l.qty, 0) };
    }

    function renderCart() {
        const wrap = $('#pos-cart');
        if (!cart.length) {
            wrap.innerHTML = `<div class="cart-empty">
                ${IC.box}
                <div class="lead-t">No items yet</div>
                <div class="d">Tap a product or scan a barcode to ring it up.</div>
            </div>`;
        } else {
            wrap.innerHTML = cart.map((l) => {
                const p = findProduct(l.id);
                const tile = p && p.serialized ? IC.ticket : IC.box;
                return `<div class="cart-line d-flex align-items-start gap-2">
                    <span class="cart-tile">${tile}</span>
                    <div class="min-w-0 flex-grow-1">
                        <div class="line-name">${escapeHtml(l.name)}</div>
                        <div class="d-flex align-items-center mt-1" style="gap:.55rem;min-width:0;">
                            <div class="cart-qty d-inline-flex align-items-center flex-shrink-0">
                                <button type="button" data-act="dec" data-id="${l.id}">−</button>
                                <span>${trimNum(l.qty)}</span>
                                <button type="button" data-act="inc" data-id="${l.id}">+</button>
                            </div>
                            <span class="line-sub">${CUR} ${money(l.price).slice(4)} each${trimNum(l.qty) === 1 ? '' : ' · ' + trimNum(l.qty) + ' units'}</span>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end justify-content-between flex-shrink-0">
                        <div class="line-total">${money(l.price * l.qty)}</div>
                        <button type="button" class="cart-x" data-act="rm" data-id="${l.id}" title="Remove">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                </div>`;
            }).join('');
        }
        wrap.querySelectorAll('[data-act]').forEach((btn) => btn.addEventListener('click', () => {
            const id = Number(btn.dataset.id);
            const line = cart.find((l) => l.id === id);
            if (btn.dataset.act === 'inc') addToCart(id, true);
            if (btn.dataset.act === 'dec') setQty(id, line.qty - 1);
            if (btn.dataset.act === 'rm') setQty(id, 0);
        }));

        const t = cartTotals();
        const paid = payments.reduce((s, p) => s + p.amount, 0);
        $('#pos-item-count').textContent = `${t.units} ${t.units === 1 ? 'item' : 'items'}`;
        $('#pos-subtotal').textContent = money(t.gross);
        $('#pos-tax').textContent = money(t.tax);
        $('#pos-total').textContent = money(t.total);
        $('#pos-units').textContent = `${trimNum(t.units)} unit${t.units === 1 ? '' : 's'}`;
        const balance = t.total - paid;
        $('#pos-pay-row').classList.toggle('d-none', balance <= 0.005);
        if (balance > 0.005) $('#pos-remaining').textContent = money(balance);
        $('#pos-change-box').classList.toggle('d-none', balance > -0.005);
        if (balance <= -0.005) $('#pos-change').textContent = money(-balance);
        // Complete-line enablement: paid must cover the total (0.005 tolerance),
        // change due is allowed for cash.
        setDisabled($('#pos-checkout'), !(cart.length > 0 && t.total - paid <= 0.005));
        renderQuickChips(Math.max(0, balance));
    }

    function trimNum(v) {
        return Number.isInteger(v) ? String(v) : String(parseFloat(v.toFixed(2)));
    }

    function revalidateCheckout(balance) {
        const ok = cart.length > 0 && balance <= 0.005 && balance > -0.005 ? true : cart.length > 0 && balance <= 0.005;
        setDisabled($('#pos-checkout'), !ok);
    }

    // ---------- payments ----------
    const METHODS = [
        ['cash', 'Cash', IC.cash],
        ['mobile_money', 'Mobile Money', IC.phone],
    ];

    function renderMethodChips() {
        $('#pos-method-chips').innerHTML = METHODS
            .map(([id, label, icon]) => `<button type="button" class="mode-chip ${selectedMethod === id ? 'active' : ''}" data-m="${id}">${icon} ${label}</button>`)
            .join('');
        $('#pos-method-chips').querySelectorAll('[data-m]').forEach((b) => b.addEventListener('click', () => {
            selectedMethod = b.dataset.m;
            $('#pos-pay-ref').style.display = selectedMethod === 'mobile_money' ? '' : 'none';
            renderMethodChips();
        }));
        const ml = $('#pos-momo-label');
        if (ml) ml.style.display = selectedMethod === 'mobile_money' ? '' : 'none';
        $('#pos-pay-ref').style.display = selectedMethod === 'mobile_money' ? '' : 'none';
    }

    function renderPaymentsChips() {
        $('#pos-payments').innerHTML = payments.map((p, i) => {
            const m = METHODS.find(([id]) => id === p.method) || [p.method, p.method, IC.card];
            return `<span class="pill d-inline-flex align-items-center gap-1">${m[2]} ${m[1]} ${money(p.amount)}
                <a href="#" style="color:inherit;text-decoration:none;" data-rm="${i}">✕</a></span>`;
        }).join('');
        $('#pos-payments').querySelectorAll('[data-rm]').forEach((x) => x.addEventListener('click', (e) => {
            e.preventDefault();
            payments.splice(Number(x.dataset.rm), 1);
            renderCart();
        }));
    }

    function addPayment() {
        const t = cartTotals();
        const paid = payments.reduce((s, p) => s + p.amount, 0);
        const balance = t.total - paid;
        const amount = parseFloat($('#pos-pay-amount').value || '0');
        if (!(amount > 0) || !cart.length) {
            flash('Enter a positive payment amount.', 'warn');
            return;
        }
        if (selectedMethod !== 'cash' && amount > balance + 0.005) {
            flash(`${METHODS.find(([m]) => m === selectedMethod)[1]} overpayment is not allowed — use cash for change.`, 'error');
            return;
        }
        payments.push({ method: selectedMethod, amount: Math.min(amount, selectedMethod === 'cash' ? amount : balance), reference: $('#pos-pay-ref').value.trim() });
        $('#pos-pay-amount').value = '';
        $('#pos-pay-ref').value = '';
        renderPaymentsChips();
        renderCart();
    }

    function renderQuickChips(balance) {
        const row = $('#pos-method-chips');
        // quick cash suggestion chips sit under method chips
        let holder = $('#pos-quick-chips');
        if (!holder) {
            holder = document.createElement('div');
            holder.id = 'pos-quick-chips';
            holder.className = 'd-flex flex-wrap gap-1 mb-2';
            row.insertAdjacentElement('afterend', holder);
        }
        if (balance <= 0.005 || !cart.length) { holder.innerHTML = ''; return; }
        const notes = [10, 20, 50, 100, 200, 500, 1000];
        const chips = [`${CUR} ${money(balance).slice(4)} (exact)`];
        const firstNotes = notes.filter((n) => n >= balance).slice(0, 2);
        firstNotes.forEach((n, i) => { if (i < firstNotes.length && chips.length < 2) chips.push(`Pay ${CUR} ${n}`); });
        // tenable fallback chips
        if (!firstNotes.length) {
            const ceil = Math.ceil(balance / 50) * 50;
            chips.push(`Pay ${CUR} ${ceil}`);
            const ceil100 = Math.ceil(balance / 100) * 100;
            if (ceil100 > ceil && chips.length < 2) chips.push(`Pay ${CUR} ${ceil100}`);
        }
        holder.innerHTML = chips.map((c, i) => `<button type="button" class="mode-chip quick-chip" data-amt="${i === 0 ? balance : parseFloat(c.replace(/[^0-9.]+/g, ''))}">${c}</button>`).join('');
        holder.querySelectorAll('.quick-chip').forEach((chip) => chip.addEventListener('click', () => {
            $('#pos-pay-amount').value = Number(chip.dataset.amt).toFixed(2);
        }));
    }

    // ---------- search / scan ----------
    function searchSubmit() {
        const q = $('#pos-search').value.trim();
        if (!q) return;
        const lc = q.toLowerCase();
        const exact = PRODUCTS.find((p) =>
            [p.name, p.sku, p.barcode || ''].some((t) => (t || '').toLowerCase() === lc));
        if (exact) { addToCart(exact.id); $('#pos-search').value = ''; renderProducts(); return; }
        const hits = PRODUCTS.filter((p) => matches(p, q));
        if (hits.length === 1) { addToCart(hits[0].id); $('#pos-search').value = ''; renderProducts(); return; }
        flash(hits.length === 0
            ? `No product matches “${q}” — check the name or barcode.`
            : `${hits.length} products match “${q}” — pick from the list.`, 'warn');
        renderProducts();
    }

    function scanSubmit() {
        const code = $('#scan-input').value.trim();
        const err = $('#scan-error');
        err.classList.add('d-none');
        if (!code) return;
        const lc = code.toLowerCase();
        const hit = PRODUCTS.find((p) => [p.sku, p.barcode || '', p.name].some((t) => (t || '').toLowerCase() === lc))
            || PRODUCTS.find((p) => (p.barcode || '').toLowerCase() === lc);
        if (!hit) {
            $('#scan-visual').classList.remove('d-none');
            $('#scan-matched').classList.add('d-none');
            err.textContent = `No product found with barcode "${code}"`;
            err.classList.remove('d-none');
            return;
        }
        $('#scan-visual').classList.add('d-none');
        $('#scan-matched').classList.remove('d-none');
        $('#scan-matched').innerHTML = `
            <div class="p-2 rounded mb-2 d-flex align-items-center gap-2" style="background:color-mix(in srgb, var(--accent) 8%, transparent);border:1px solid color-mix(in srgb, var(--accent) 30%, transparent);">
                <span class="p-tile">✓</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">${escapeHtml(hit.name)}</div>
                    <div style="font-size:11px;color:var(--muted);">SKU: ${escapeHtml(hit.sku)} • Stock: ${trimNum(hit.stock)}</div>
                </div>
                <span class="fw-bold text-accent">${money(hit.price)}</span>
            </div>`;
        const find = $('#scan-find');
        find.textContent = 'Add to Cart';
        find.dataset.found = hit.id;
    }

    // ---------- checkout ----------
    async function checkout() {
        const btn = $('#pos-checkout');
        if (!cart.length || btn.disabled) return;
        setDisabled(btn, true);
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';

        const t = cartTotals();
        const payload = {
            items: cart.map((l) => ({ product_id: l.id, quantity: l.qty })),
            discount: Number(discount.toFixed(2)),
            payments: payments.map((p) => ({ method: p.method, amount: Number(p.amount.toFixed(2)), reference: p.reference || null })),
        };

        try {
            let sale;
            if (!offlineMode) {
                const res = await fetch('/pos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload),
                });
                if (!res.ok) {
                    const msg = await safeError(res);
                    throw new Error(msg);
                }
                const json = await res.json();
                sale = json.sale;
            } else {
                const idempotencyKey = (crypto.randomUUID && crypto.randomUUID()) || String(Date.now()) + Math.random().toString(16).slice(2);
                sale = {
                    receipt_number: 'PENDING-' + idempotencyKey.slice(0, 8),
                    created_at: new Date().toISOString().replace('T', ' ').slice(0, 16),
                    cashier_name: document.querySelector('meta[name="user-email"]')?.content || '',
                    items: payload.items.map((i) => {
                        const p = findProduct(i.product_id);
                        return { name: p ? p.name : 'Item', quantity: i.quantity, unit_price: p ? p.price : 0 };
                    }),
                    subtotal: t.gross, discount: payload.discount, tax: t.tax, total: t.total,
                    payments: payload.payments,
                    change: Math.max(0, payments.reduce((s, x) => s + x.amount, 0) - t.total),
                    offline: true,
                };
                if (window.PosOffline) {
                    window.PosOffline.queue('sale', payload, idempotencyKey);
                    flash('Offline — sale queued and will sync automatically.', 'warn');
                }
            }

            flash(`Sale ${sale.receipt_number} complete (${money(sale.total)})` + (sale.change_due || sale.change > 0 ? ` · Change due: ${money(sale.change || 0)}` : ''), 'info');
            printReceipt(sale);
            resetSale();
        } catch (e) {
            flash(`Could not complete the sale: ${e.message}`, 'error');
        } finally {
            btn.innerHTML = 'Complete sale';
            renderCart();
        }
    }

    async function safeError(res) {
        try {
            const j = await res.json();
            return j.message || j.error || `${res.status}`;
        } catch { return `${res.status}`; }
    }

    function resetSale() {
        cart.length = 0;
        payments.length = 0;
        discount = 0;
        $('#pos-discount').value = 0;
        $('#pos-payments').innerHTML = '';
        $('#pos-change-box').classList.add('d-none');
        $('#pos-pay-row').classList.remove('d-none');
        renderCart();
    }

    // ---------- receipt printing ----------
    async function printReceipt(sale) {
        // Preferred: real generated receipt page from the server.
        if (!sale.offline && sale.id) {
            try {
                const page = await fetch(`/sales/${sale.id}/receipt`);
                const doc = new DOMParser().parseFromString(await page.text(), 'text/html');
                const inner = doc.querySelector('#receipt-print')?.innerHTML || doc.querySelector('.receipt')?.outerHTML;
                if (inner) { printHtml(inner); return; }
            } catch { /* fall back */ }
        }
        // Offline / fallback: build the receipt locally (Flutter parity skeleton).
        const store = window.POS_STORE || {};
        let totalQty = 0;
        const rows = sale.items.map((i) => {
            const q = Number(i.quantity) || 1;
            totalQty += q;
            return `<div style="display:flex;justify-content:space-between;align-items:flex-start;font-size:12px;font-weight:700;margin:2px 0;">
                <span style="width:16%;">${trimNum(q)}x</span>
                <span style="flex-grow:1;padding:0 4px;word-break:break-word;">${escapeHtml(i.name)}</span>
                <span style="text-align:right;white-space:nowrap;">${CUR} ${(i.unit_price * q).toFixed(2)}</span>
            </div>`;
        }).join('');
        const pays = sale.payments.map((p) => {
            const m = (p.method || 'cash').replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
            return `<div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;"><span>Paid (${m})</span><span>${CUR} ${Number(p.amount).toFixed(2)}</span></div>`;
        }).join('');
        printHtml(`
            <div class="receipt">
                <div class="receipt-center">
                    ${store.show_store_name !== '0' ? `<div style="font-weight:900;font-size:16px;letter-spacing:.5px;text-transform:uppercase;line-height:1.2;">${escapeHtml(store.name || '').toUpperCase()}</div>` : ''}
                    ${store.show_store_address !== '0' && store.address ? `<div style="font-size:12px;font-weight:700;margin-top:2px;">${escapeHtml(store.address)}</div>` : ''}
                    ${store.show_store_phone !== '0' && store.phone ? `<div style="font-size:12px;font-weight:700;">Tel: ${escapeHtml(store.phone)}</div>` : ''}
                    ${store.header_custom_text ? `<div style="font-size:11.5px;font-weight:700;margin-top:2px;line-height:1.25;">${escapeHtml(store.header_custom_text)}</div>` : ''}
                </div>
                <div class="receipt-divider" style="border-top:1.5px dashed #000;margin:5px 0;width:100%;height:0;"></div>
                <div style="font-size:11.5px;font-weight:700;display:flex;justify-content:space-between;flex-wrap:wrap;">
                    <span>RECEIPT: ${escapeHtml(sale.receipt_number)}</span>
                    <span>${escapeHtml(sale.created_at || '')}</span>
                </div>
                <div class="receipt-divider" style="border-top:1.5px dashed #000;margin:5px 0;width:100%;height:0;"></div>
                <div style="display:flex;justify-content:space-between;font-weight:900;font-size:12px;">
                    <span style="width:16%;">QTY</span>
                    <span style="flex-grow:1;padding:0 4px;">ITEM DESCRIPTION</span>
                    <span style="text-align:right;">AMOUNT</span>
                </div>
                <div class="receipt-divider" style="border-top:1.5px dashed #000;margin:5px 0;width:100%;height:0;"></div>
                ${rows}
                <div style="margin-top:4px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;"><span>Subtotal</span><span>${CUR} ${Number(sale.subtotal).toFixed(2)}</span></div>
                    ${sale.discount > 0 ? `<div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;"><span>Discount</span><span>- ${CUR} ${Number(sale.discount).toFixed(2)}</span></div>` : ''}
                    ${store.show_tax !== '0' && sale.tax > 0 ? `<div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;"><span>VAT incl.</span><span>${CUR} ${Number(sale.tax).toFixed(2)}</span></div>` : ''}
                </div>
                <div class="receipt-divider" style="border-top:1.5px dashed #000;margin:5px 0;width:100%;height:0;"></div>
                <div style="display:flex;justify-content:space-between;font-weight:900;font-size:14px;letter-spacing:.3px;"><span>TOTAL DUE</span><span>${CUR} ${Number(sale.total).toFixed(2)}</span></div>
                <div class="receipt-divider" style="border-top:1.5px dashed #000;margin:5px 0;width:100%;height:0;"></div>
                ${pays}
                ${store.show_change_due !== '0' ? `<div style="display:flex;justify-content:space-between;font-weight:800;font-size:12px;"><span>Change</span><span>${CUR} ${Number(sale.change || 0).toFixed(2)}</span></div>` : ''}
                ${(sale.offline ? '<div style="text-align:center;font-weight:800;font-size:12px;">⚠ OFFLINE SALE — PENDING REVIEW</div>' : '')}
                <div class="receipt-divider" style="border-top:1.5px dashed #000;margin:5px 0;width:100%;height:0;"></div>
                <div class="receipt-center" style="font-size:12px;font-weight:700;margin:2px 0;">Total Items: ${trimNum(totalQty)}</div>
                <div class="receipt-center" style="white-space:pre-line;font-size:11.5px;font-weight:700;line-height:1.3;margin-top:2px;">${escapeHtml(store.footer_message || '')}</div>
                ${store.show_barcode !== '0' ? `<div class="receipt-divider" style="border-top:1.5px dashed #000;margin:6px 0;width:100%;height:0;"></div><div style="height:8mm;"></div>` : ''}
            </div>`);
    }

    function printHtml(inner) {
        let frame = document.getElementById('receipt-print-frame');
        if (!frame) {
            frame = document.createElement('iframe');
            frame.id = 'receipt-print-frame';
            frame.style.position = 'fixed';
            frame.style.right = '0';
            frame.style.bottom = '0';
            frame.style.width = '0';
            frame.style.height = '0';
            frame.style.border = '0';
            frame.style.opacity = '0';
            document.body.appendChild(frame);
        }
        const doc = frame.contentWindow.document;
        doc.open();
        doc.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
            <title></title>
            <style>
                @page { size: auto; margin: 0mm !important; }
                @page :header { display: none; }
                @page :footer { display: none; }
                html, body {
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #fff !important;
                    color: #000 !important;
                }
                .receipt {
                    font-family: 'Consolas', 'Courier New', monospace;
                    margin: 0 auto;
                    font-size: 12px;
                    line-height: 1.35;
                    color: #000 !important;
                    background: #fff;
                    padding: 4px 6px;
                    font-weight: 700;
                    -webkit-font-smoothing: antialiased;
                    text-rendering: geometricPrecision;
                }
                .receipt * { color: #000 !important; box-sizing: border-box; }
                .receipt .receipt-center { text-align: center; }
                .receipt hr, .receipt .receipt-divider {
                    border: none !important;
                    border-top: 1.5px dashed #000 !important;
                    margin: 5px 0 !important;
                    display: block !important;
                    width: 100% !important;
                    height: 0 !important;
                }
                .receipt .barcode-wrap { text-align: center; margin-top: 6px; }
                .receipt .barcode-code { font-size: 11.5px; font-weight: 800; letter-spacing: 1px; }
            </style>
        </head><body><div id="receipt-print">${inner}</div></body></html>`);
        doc.close();
        setTimeout(function () {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                console.error('Receipt print failed:', e);
            }
        }, 150);
    }

    // ---------- history mode ----------
    async function loadHistory() {
        const list = $('#pos-history-list');
        list.innerHTML = '<div class="text-muted small py-3">Loading…</div>';
        try {
            const json = await (await fetch('/pos/history')).json();
            if (!json.sales.length) {
                list.innerHTML = '<div class="text-muted small py-3">No sales yet. Complete a sale to see it here.</div>';
                return;
            }
            list.innerHTML = json.sales.map((s) => `
                <button type="button" class="btn w-100 text-start d-flex align-items-center gap-2 py-2 px-2 mb-1 feature-card" data-detail="${s.id}">
                    <span class="p-tile">🧾</span>
                    <span class="flex-grow-1 min-w-0">
                        <span class="d-block fw-semibold">${escapeHtml(s.receipt_number)} · ${money(s.total)}</span>
                        <span class="text-muted" style="font-size:.72rem;">${s.created_at}</span>
                    </span>
                    <span>›</span>
                </button>`).join('');
            list.querySelectorAll('[data-detail]').forEach((b) => b.addEventListener('click', () => openDetail(Number(b.dataset.detail))));
        } catch { list.innerHTML = '<div class="text-muted small py-3">Could not load sales. Retry when online.</div>'; }
    }

    async function openDetail(id) {
        const json = await (await fetch(`/pos/history/${id}`)).json();
        const rows = json.items.map((i) => `<div class="d-flex justify-content-between"><span>${escapeHtml(i.name)} × ${trimNum(i.quantity)}</span><span>${money(i.amount)}</span></div>`).join('');
        const pays = json.payments.map((p) => `<div class="d-flex justify-content-between text-muted"><span>${(p.method || '').replace('_', ' ').toUpperCase()}</span><span>${money(p.amount)}</span></div>`).join('');
        openDialog(`Sale ${json.receipt_number}`, `
            ${rows}
            <div class="divider my-2"></div>
            <div class="d-flex justify-content-between fw-bold"><span>Total</span><span>${money(json.total)}</span></div>
            ${pays}`, [
            { label: 'View receipt', href: `/sales/${json.id}/receipt`, btn: 'btn-outline-accent' },
            { label: 'Print', onclick: `window.location='/sales/${json.id}/receipt'`, btn: 'btn-accent' },
            { label: 'Close', dismiss: true, btn: 'btn-outline-secondary' },
        ]);
    }

    function openDialog(title, bodyHtml, actions) {
        let modalEl = document.getElementById('pos-dialog');
        if (!modalEl) {
            document.body.insertAdjacentHTML('beforeend',
                `<div class="modal fade" id="pos-dialog" tabindex="-1"><div class="modal-dialog" style="max-width:480px;">
                    <div class="modal-content feature-card">
                        <div class="modal-header"><h6 class="modal-title" id="pos-dialog-title"></h6></div>
                        <div class="modal-body" id="pos-dialog-body"></div>
                        <div class="modal-footer" id="pos-dialog-actions"></div>
                    </div></div></div>`);
            modalEl = document.getElementById('pos-dialog');
        }
        document.getElementById('pos-dialog-title').textContent = title;
        document.getElementById('pos-dialog-body').innerHTML = bodyHtml;
        const footer = document.getElementById('pos-dialog-actions');
        footer.innerHTML = '';
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        (actions || []).forEach((a) => {
            if (a.href) {
                footer.insertAdjacentHTML('beforeend', `<a class="btn btn-sm ${a.btn}" href="${a.href}" data-bs-dismiss="modal">${a.label}</a>`);
            } else if (a.onclick) {
                footer.insertAdjacentHTML('beforeend', `<button class="btn btn-sm ${a.btn}" onclick="${a.onclick}">${a.label}</button>`);
            } else {
                const b = document.createElement('button');
                b.className = `btn btn-sm ${a.btn}`; b.textContent = a.label;
                b.addEventListener('click', () => modal.hide());
                footer.appendChild(b);
            }
        });
        modal.show();
    }

    // ---------- mode switch ----------
    function bindMode() {
        $('#mode-new').addEventListener('change', () => {
            $('#pos-register').classList.remove('d-none');
            $('#pos-history').classList.add('d-none');
        });
        $('#mode-history').addEventListener('change', () => {
            $('#pos-register').classList.add('d-none');
            $('#pos-history').classList.remove('d-none');
            loadHistory();
        });
    }

    // ---------- boot ----------
    function bind() {
        renderCategories();
        renderProducts();
        renderCart();
        renderMethodChips();
        renderPaymentsChips();
        loadHistoryReset();

        $('#pos-search').addEventListener('input', renderProducts);
        $('#pos-search').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); searchSubmit(); } });
        $('#pos-scan-btn').addEventListener('click', () => {
            const find = $('#scan-find');
            find.textContent = 'Find Product'; delete find.dataset.found;
            $('#scan-input').value = '';
            $('#scan-matched').classList.add('d-none');
            $('#scan-visual').classList.remove('d-none');
            $('#scan-error').classList.add('d-none');
        });
        const scanModalEl = document.getElementById('scanModal');
        if (scanModalEl) {
            scanModalEl.addEventListener('shown.bs.modal', () => {
                const inp = $('#scan-input');
                if (inp) { inp.value = ''; inp.focus(); }
            });
        }

        $('#scan-clear').addEventListener('click', () => $('#scan-input').value = '');
        $('#scan-find').addEventListener('click', () => {
            const find = $('#scan-find');
            if (find.dataset.found) {
                addToCart(Number(find.dataset.found));
                const modal = bootstrap.Modal.getOrCreateInstance($('#scanModal'));
                modal.hide();
                $('#scan-input').value = '';
                $('#scan-matched').classList.add('d-none');
                $('#scan-visual').classList.remove('d-none');
                find.textContent = 'Find Product'; delete find.dataset.found;
            } else {
                scanSubmit();
            }
        });
        $('#scan-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('#scan-find').click(); } });

        // Global hardware scanner listener: automatically adds scanned product to cart even if search is not focused
        let posScanBuf = '';
        let posScanTime = 0;
        window.addEventListener('keydown', (e) => {
            const act = document.activeElement;
            if (act && (act.id === 'pos-pay-amount' || act.id === 'pos-discount' || act.id === 'scan-input')) return;
            const now = Date.now();
            const diff = now - posScanTime;
            posScanTime = now;

            if (e.key === 'Enter') {
                if (posScanBuf.length >= 2 && diff < 90) {
                    e.preventDefault();
                    e.stopPropagation();
                    const code = posScanBuf.trim();
                    posScanBuf = '';
                    const searchBox = $('#pos-search');
                    if (searchBox) {
                        searchBox.value = code;
                        searchSubmit();
                    }
                }
                posScanBuf = '';
                return;
            }

            if (e.key.length === 1) {
                if (diff < 60) {
                    posScanBuf += e.key;
                } else {
                    posScanBuf = e.key;
                }
            }
        }, true);

        $('#pos-discount').addEventListener('input', (e) => { discount = parseFloat(e.target.value || '0'); renderCart(); });
        $('#pos-pay-add').addEventListener('click', addPayment);
        $('#pos-pay-amount').addEventListener('keydown', (e) => { if (e.key === 'Enter') addPayment(); });
        $('#pos-checkout').addEventListener('click', checkout);

        bindMode();

        window.addEventListener('online', () => { offlineMode = false; flash('Back online.', 'info'); });
        window.addEventListener('offline', () => { offlineMode = true; flash('Offline — sales will be queued.', 'warn'); });
    }

    function loadHistoryReset() {
        // pre-warm history when leaving register once
        document.getElementById('mode-history').addEventListener('change', loadHistory);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
    else bind();
})();
