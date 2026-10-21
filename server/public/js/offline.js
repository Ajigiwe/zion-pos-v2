/*
 * Zion POS offline fallback (PHP-PLAN §3).
 *
 * IndexedDB layout:
 *   store "snapshot": one record keyed "catalog" — products, settings, store
 *     info, user, fetched whenever we are online (login + every 5 minutes).
 *   store "outbox": {id (uuid), key (idempotency uuid), kind ("sale"|"refund"),
 *     payload, status, retries, error, createdAt} — append-only queue.
 *
 * Flush policy: on load, on `online` event, and every 5s while online.
 * Every payload already carries a server-side idempotency key, so retries
 * are always safe.
 */
(() => {
    'use strict';

    const DB_NAME = 'zion-pos';
    const SNAP = 'snapshot';
    const OUT = 'outbox';
    const SYNC_URL = '/api/sales';
    const SNAPSHOT_URL = '/api/snapshot';
    const FLUSH_MS = 5000;

    let db = null;
    let flushing = false;

    function openDb() {
        return new Promise((resolve, reject) => {
            if (db) return resolve(db);
            const req = indexedDB.open(DB_NAME, 1);
            req.onupgradeneeded = () => {
                const d = req.result;
                if (!d.objectStoreNames.contains(SNAP)) d.createObjectStore(SNAP);
                if (!d.objectStoreNames.contains(OUT)) d.createObjectStore(OUT, { keyPath: 'id' });
            };
            req.onsuccess = () => { db = req.result; resolve(db); };
            req.onerror = () => reject(req.error);
        });
    }

    const tx = (store, mode) => db.transaction(store, mode).objectStore(store);

    const req = (r) => new Promise((resolve, reject) => {
        r.onsuccess = () => resolve(r.result);
        r.onerror = () => reject(r.error);
    });

    async function online() {
        // Record the ping result so the indicator reflects reality. flush()
        // calls this every 5s, so the badge self-corrects continuously.
        pingOk = navigator.onLine && (await ping());
        return pingOk;
    }

    async function ping() {
        try {
            const res = await fetch('/api/ping?_=' + Date.now(), { credentials: 'same-origin' });
            return res.ok;
        } catch { return false; }
    }

    function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content; }

    // ── Toasts (shared by offline.js and pos.js) ───────────────────────────
    window.flashWarn ??= function (message) { ink('warning', message); };
    window.flashInfo ??= function (message) { ink('primary', message); };
    window.flashError ??= function (message) { ink('danger', message); };

    function ink(color, message) {
        let stack = document.getElementById('flash-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'flash-stack';
            stack.style.cssText = 'position:fixed;top:.8rem;left:50%;transform:translateX(-50%);z-index:2000;display:flex;flex-direction:column;align-items:center;gap:.4rem;max-width:420px;width:max-content;text-align:center';
            document.body.appendChild(stack);
        }
        const toast = document.createElement('div');
        toast.className = 'alert alert-' + color + ' py-2 px-3 shadow-sm mb-0';
        toast.textContent = message;
        stack.appendChild(toast);
        setTimeout(() => toast.remove(), 3500);
    }

    // ── UI ────────────────────────────────────────────────────────────────
    function setIndicator(state, label) {
        // Dot-only badge: green = online, red = offline. The label becomes
        // the hover tooltip instead of cluttering the topbar.
        const dot = document.getElementById('sync-dot');
        if (dot) {
            dot.className = 'sync-dot ' + state;
            dot.title = label;
        }
    }

    async function updatePendingBadge() {
        const db2 = await openDb();
        const all = await req(tx(OUT, 'readonly').getAll());
        const badge = document.getElementById('pending-count');
        if (badge) {
            badge.textContent = all.length + ' queued';
            badge.classList.toggle('d-none', all.length === 0);
        }
        updateIndicator();
        return all.length;
    }

    let pingOk = false;

    async function updateIndicator() {
        const dot = document.getElementById('sync-dot');
        if (!dot) return;
        if (!navigator.onLine || !pingOk) {
            setIndicator('sync-offline', 'Offline — sales queued on this page will sync automatically when reconnected');
            document.getElementById('offline-banner')?.classList.remove('d-none');
        } else {
            const pending = await countPending();
            setIndicator('sync-online', pending > 0 ? ('Online — ' + pending + ' queued sale(s) syncing') : 'Online');
            document.getElementById('offline-banner')?.classList.add('d-none');
        }
    }

    async function countPending() {
        const d = await openDb();
        return (await req(tx(OUT, 'readonly').count())) || 0;
    }

    // ── Snapshot cache ─────────────────────────────────────────────────────
    async function refreshSnapshot() {
        if (!document.querySelector('meta[name="authed"][content="1"]')) return null;
        if (!(await online())) return loadSnapshot();
        const res = await fetch(SNAPSHOT_URL, { credentials: 'same-origin' });
        if (!res.ok) return loadSnapshot();
        const data = await res.json();
        const d = await openDb();
        await new Promise((resolve) => fetchKeepalive(d, data, resolve));
        window.dispatchEvent(new CustomEvent('pos:snapshot', { detail: data }));
        return data;
    }

    function fetchKeepalive(database, data, resolve) {
        database.transaction(SNAP, 'readwrite').objectStore(SNAP).put(data, 'catalog').onsuccess = resolve;
    }
    async function loadSnapshot() {
        const d = await openDb();
        const data = await req(tx(SNAP, 'readonly').get('catalog'));
        if (data) window.dispatchEvent(new CustomEvent('pos:snapshot', { detail: data }));
        return data || null;
    }

    // ── Outbox ─────────────────────────────────────────────────────────────
    async function queue(kind, payload) {
        const d = await openDb();
        const entry = {
            id: crypto.randomUUID(),
            key: crypto.randomUUID(),      // server idempotency key
            kind,
            payload,
            status: 'PENDING',
            retries: 0,
            error: null,
            createdAt: new Date().toISOString(),
        };
        await req(tx(OUT, 'readwrite').add(entry));
        await updatePendingBadge();
        flush();
        return entry;
    }

    async function flush() {
        if (flushing) return;
        flushing = true;
        try {
            const d = await openDb();
            if (!(await online())) { await updateIndicator(); return; }
            const entries = await req(tx(OUT, 'readonly').getAll());
            for (const entry of entries) {
                try {
                    const res = await fetch(SYNC_URL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf(),
                            'X-Idempotency-Key': entry.key,
                            'X-POS-Kind': entry.kind,
                        },
                        body: JSON.stringify(entry.payload),
                    });
                    // 409 = duplicate idempotency key (already synced): the
                    // server returned the canonical sale, so delete + resolve.
                    if (res.ok || res.status === 409) {
                        await req(tx(OUT, 'readwrite').delete(entry.id));
                        window.dispatchEvent(new CustomEvent('pos:synced', {
                            detail: { entryId: entry.id, sale: res.status === 409 ? null : await res.json() },
                        }));
                    } else if (res.status === 422 || res.status === 403) {
                        // Permanent failure — keep it visible, do not retry.
                        // Permanent failure — keep it visible, do not retry.
                        entry.status = 'REJECTED';
                        entry.error = (await res.json().catch(() => ({})))?.message || 'Rejected by server';
                        await req(tx(OUT, 'readwrite').put(entry));
                        window.dispatchEvent(new CustomEvent('pos:rejected', { detail: entry }));
                    } else {
                        entry.retries++;
                        throw new Error('Status ' + res.status);
                    }
                } catch (e) {
                    entry.error = e.message;
                    if (entry.retries > 100) entry.status = 'FAILED_MAX';
                    await req(tx(OUT, 'readwrite').put(entry));
                }
            }
        } finally {
            flushing = false;
            await updatePendingBadge();
        }
    }

    // ── Wiring ─────────────────────────────────────────────────────────────
    window.addEventListener('online', () => { probeAndFlush(); });
    window.addEventListener('offline', () => { updateIndicator(); });
    document.addEventListener('DOMContentLoaded', async () => {
        await openDb();
        if (document.querySelector('meta[name="authed"][content="1"]')) {
            setInterval(() => { if (navigator.onLine && !flushing) refreshSnapshot().catch(() => {}); }, 5 * 60 * 1000);
        }
        setInterval(flush, FLUSH_MS);
        // Probe once on load so the badge shows Synced right away instead of
        // waiting for an `online` event (which never fires while online).
        await probeAndFlush();
    });

    async function probeAndFlush() {
        pingOk = await ping();
        updateIndicator();
        if (pingOk) flush();
        refreshSnapshot().catch(() => {});
    }

    // Public API
    window.PosOffline = {
        queue,
        loadSnapshot,
        refreshSnapshot,
        pending: async () => {
            const d = await openDb();
            return req(tx(OUT, 'readonly').getAll());
        },
        exportQueue: async () => {
            const entries = await PosOffline.pending();
            return new Blob([JSON.stringify(entries, null, 2)], { type: 'application/json' });
        },
    };
})();
