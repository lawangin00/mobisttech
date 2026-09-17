import { useEffect, useMemo, useState } from 'react';

type Unit = { id: string; code: string; status: string; version: number; imeis: string[] };
type Product = { id: string; code: string; name: string; purchase_price: string; sale_price: string; qty: number; track_imei: boolean; units: Unit[] };
type Destination = { public_id: string; method: string; display_name: string };
type Master = { id: number; list_key: string; code: string; label: string; metadata: Record<string, unknown> };
type Catalogue = { products: Product[]; payment_destinations: Destination[]; master_data: Master[] };
type Payment = { method: string; destination_id: string; amount: string; transaction_reference?: string; cash_tendered?: string };
type Quote = { payable: string; payments_total: string; remaining: string; cash_change: string };

async function csrf() {
    const response = await fetch('/internal/admin/auth/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const body = await response.json() as { data?: { csrf_token?: string } };
    if (!response.ok || !body.data?.csrf_token) throw new Error('Secure request token is unavailable.');
    return body.data.csrf_token;
}

async function api<T>(url: string, init?: RequestInit): Promise<T> {
    const method = init?.method ?? 'GET';
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = await csrf();
        headers['Idempotency-Key'] = crypto.randomUUID();
    }
    const response = await fetch(url, { ...init, credentials: 'same-origin', headers });
    const body = await response.json().catch(() => ({})) as { data?: T; message?: string; errors?: Record<string, string[]> };
    if (!response.ok) throw new Error(body.errors ? Object.values(body.errors).flat()[0] : (body.message ?? 'Request failed.'));
    return body.data as T;
}

export default function PosTransactionWorkspace({ area }: { area: 'inventory' | 'sales' }) {
    const [catalogue, setCatalogue] = useState<Catalogue | null>(null);
    const [query, setQuery] = useState('');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const load = async (value = '') => setCatalogue(await api<Catalogue>('/internal/admin/pos/catalogue?q=' + encodeURIComponent(value)));
    useEffect(() => { void load(); }, []);
    const run = async (task: () => Promise<void>) => {
        setBusy(true); setMessage('');
        try { await task(); } catch (error) { setMessage(error instanceof Error ? error.message : 'Request failed.'); } finally { setBusy(false); }
    };
    const lookup = () => run(async () => {
        const found = await api<{ product: Product; unit: Unit | null }>('/internal/admin/pos/lookup?q=' + encodeURIComponent(query));
        setCatalogue((old) => old ? { ...old, products: [found.product] } : old);
        setMessage(found.unit ? 'Matched unit ' + found.unit.code : 'Matched product ' + found.product.code);
    });
    return <div className="mt-6 grid gap-5">
        <section className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="flex flex-col gap-2 sm:flex-row">
                <input data-testid="pos-lookup" value={query} onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter') void lookup(); }}
                    placeholder="Scan barcode / QR / IMEI, or search product" className="min-w-0 flex-1 rounded-lg border px-3 py-2 text-sm" />
                <button disabled={busy} onClick={() => void lookup()} className="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Lookup</button>
                <button disabled={busy} onClick={() => void run(() => load(query))} className="rounded-lg border px-4 py-2 text-sm font-semibold">Search</button>
            </div>
            {message && <p role="alert" className="mt-3 text-sm">{message}</p>}
        </section>
        {area === 'inventory'
            ? <Inventory catalogue={catalogue} busy={busy} run={run} reload={() => load(query)} />
            : <Sales catalogue={catalogue} busy={busy} run={run} />}
    </div>;
}

function Inventory({ catalogue, busy, run, reload }: { catalogue: Catalogue | null; busy: boolean; run: (task: () => Promise<void>) => Promise<void>; reload: () => Promise<void> }) {
    const [productId, setProductId] = useState('');
    const [quantity, setQuantity] = useState('1');
    const [cost, setCost] = useState('');
    const [source, setSource] = useState('');
    const [party, setParty] = useState('');
    const [phone, setPhone] = useState('03');
    const [cnic, setCnic] = useState('');
    const [address, setAddress] = useState('');
    const [unitId, setUnitId] = useState('');
    const [imei1, setImei1] = useState('');
    const [imei2, setImei2] = useState('');
    const product = catalogue?.products.find((p) => p.id === productId);
    const unit = product?.units.find((u) => u.id === unitId);
    const sources = catalogue?.master_data.filter((m) => m.list_key === 'acquisition_source_type') ?? [];
    const printLabel = async (kind: string, id: string) => {
        const label = await api<Record<string, unknown>>('/internal/admin/pos/labels/' + kind + '/' + id);
        const popup = window.open('', '_blank', 'width=520,height=420');
        if (!popup) throw new Error('Print window was blocked.');
        popup.document.write('<html><body style="font-family:Arial;padding:28px"><h2>' + String(label.name ?? 'mobiST') + '</h2><pre>' + JSON.stringify(label, null, 2) + '</pre><script>window.print()<\/script></body></html>');
        popup.document.close();
    };
    return <div className="grid gap-5 xl:grid-cols-2">
        <section className="rounded-2xl border bg-white p-5"><h3 className="font-semibold">Inventory</h3>
            <div className="mt-3 grid gap-2">{catalogue?.products.map((p) => <div key={p.id} className="rounded-xl border p-3">
                <button className="w-full text-left" onClick={() => { setProductId(p.id); setCost(p.purchase_price); setUnitId(p.units[0]?.id ?? ''); }}><strong>{p.name}</strong><span className="block text-xs text-slate-500">{p.code} · Qty {p.qty} · PKR {p.sale_price}</span></button>
                <button onClick={() => void run(() => printLabel('product', p.id))} className="mt-2 rounded border px-2 py-1 text-xs">Print product label</button>
                {p.units.length > 0 && <p className="mt-2 text-xs text-slate-500">{p.units.map((u) => u.code + (u.imeis.length ? ' (' + u.imeis.join(', ') + ')' : '')).join(' · ')}</p>}
            </div>)}</div>
        </section>
        <div className="grid gap-5">
            <section className="rounded-2xl border bg-white p-5"><h3 className="font-semibold">Receive stock</h3>
                <div className="mt-3 grid gap-2">
                    <select value={productId} onChange={(e) => setProductId(e.target.value)} className="rounded border p-2 text-sm"><option value="">Product</option>{catalogue?.products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</select>
                    <div className="grid grid-cols-2 gap-2"><input value={quantity} onChange={(e) => setQuantity(e.target.value)} placeholder="Qty" className="rounded border p-2 text-sm" /><input value={cost} onChange={(e) => setCost(e.target.value)} placeholder="Unit cost" className="rounded border p-2 text-sm" /></div>
                    <select value={source} onChange={(e) => setSource(e.target.value)} className="rounded border p-2 text-sm"><option value="">Source type</option>{sources.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}</select>
                    <input value={party} onChange={(e) => setParty(e.target.value)} placeholder="Business / seller name" className="rounded border p-2 text-sm" />
                    <div className="grid grid-cols-2 gap-2"><input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="03XXXXXXXXX" className="rounded border p-2 text-sm" /><input value={cnic} onChange={(e) => setCnic(e.target.value)} placeholder="CNIC if individual" className="rounded border p-2 text-sm" /></div>
                    <input value={address} onChange={(e) => setAddress(e.target.value)} placeholder="Address" className="rounded border p-2 text-sm" />
                    <button data-testid="acquire-submit" disabled={busy || !productId || !source} onClick={() => void run(async () => {
                        const sourceRow = sources.find((s) => String(s.id) === source); const business = sourceRow?.metadata?.party_kind === 'business';
                        await api('/internal/admin/pos/inventory/products/' + productId + '/acquire', { method: 'POST', body: JSON.stringify({
                            quantity: Number(quantity), unit_purchase_price: cost, source_type_master_data_id: Number(source),
                            business_name: business ? party : null, seller_name: business ? null : party, seller_cnic: business ? null : cnic,
                            seller_phone: phone, seller_address: address, reason: 'POS acquisition',
                        }) }); await reload();
                    })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Receive</button>
                </div>
            </section>
            {product?.track_imei && <section className="rounded-2xl border bg-white p-5"><h3 className="font-semibold">Unit / IMEI</h3>
                <select value={unitId} onChange={(e) => setUnitId(e.target.value)} className="mt-3 w-full rounded border p-2 text-sm"><option value="">Unit</option>{product.units.map((u) => <option key={u.id} value={u.id}>{u.code}</option>)}</select>
                <div className="mt-2 grid grid-cols-2 gap-2"><input value={imei1} onChange={(e) => setImei1(e.target.value)} placeholder="IMEI 1" className="rounded border p-2 text-sm" /><input value={imei2} onChange={(e) => setImei2(e.target.value)} placeholder="IMEI 2" className="rounded border p-2 text-sm" /></div>
                <div className="mt-3 flex gap-2"><button disabled={!unit} onClick={() => void run(async () => {
                    const values = [imei1, imei2].filter(Boolean); const imeis = Object.fromEntries(values.map((v, i) => [String(i + 1), v]));
                    await api('/internal/admin/pos/inventory/products/' + productId + '/imeis', { method: 'POST', body: JSON.stringify({ unit_id: unitId, version: unit?.version, imeis }) }); await reload();
                })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Save IMEI</button>{unit && <button onClick={() => void run(() => printLabel('unit', unit.id))} className="rounded border px-3 py-2 text-sm">Print unit label</button>}</div>
            </section>}
        </div>
    </div>;
}

function Sales({ catalogue, busy, run }: { catalogue: Catalogue | null; busy: boolean; run: (task: () => Promise<void>) => Promise<void> }) {
    const [cart, setCart] = useState<Array<{ product_id: string; name: string; quantity: number }>>([]);
    const [discount, setDiscount] = useState('0.00');
    const [reason, setReason] = useState('');
    const [promotion, setPromotion] = useState('');
    const [loyalty, setLoyalty] = useState('0');
    const [payments, setPayments] = useState<Payment[]>([]);
    const [quote, setQuote] = useState<Quote | null>(null);
    const [result, setResult] = useState<Record<string, unknown> | null>(null);
    const destinations = catalogue?.payment_destinations ?? [];
    const sale = useMemo(() => ({ discount, discount_reason: reason || null, promotion_codes: promotion ? [promotion] : [], loyalty_points: Number(loyalty || 0), lines: cart.map((x) => ({ product_id: x.product_id, quantity: x.quantity })) }), [cart, discount, reason, promotion, loyalty]);
    const add = (p: Product) => setCart((old) => old.some((x) => x.product_id === p.id) ? old.map((x) => x.product_id === p.id ? { ...x, quantity: x.quantity + 1 } : x) : [...old, { product_id: p.id, name: p.name, quantity: 1 }]);
    const addPayment = () => { const d = destinations[0]; if (d) setPayments((old) => [...old, { method: d.method, destination_id: d.public_id, amount: '' }]); };
    const changePayment = (i: number, patch: Partial<Payment>) => setPayments((old) => old.map((p, index) => index === i ? { ...p, ...patch } : p));
    return <div className="grid gap-5 xl:grid-cols-2">
        <section className="rounded-2xl border bg-white p-5"><h3 className="font-semibold">Products</h3><div className="mt-3 grid gap-2">{catalogue?.products.map((p) => <button key={p.id} onClick={() => add(p)} className="rounded-xl border p-3 text-left"><strong>{p.name}</strong><span className="block text-xs text-slate-500">{p.code} · Available {p.qty} · PKR {p.sale_price}</span></button>)}</div></section>
        <section className="rounded-2xl border bg-white p-5"><h3 className="font-semibold">Sale & payment</h3>
            <div className="mt-3 grid gap-2">{cart.map((line, i) => <div key={line.product_id} className="flex items-center gap-2 rounded bg-slate-50 p-2"><span className="flex-1 text-sm">{line.name}</span><input aria-label={'Quantity ' + line.name} value={line.quantity} onChange={(e) => setCart((old) => old.map((x, index) => index === i ? { ...x, quantity: Math.max(1, Number(e.target.value) || 1) } : x))} className="w-20 rounded border p-1 text-sm" /></div>)}</div>
            <div className="mt-3 grid grid-cols-2 gap-2"><input value={discount} onChange={(e) => setDiscount(e.target.value)} placeholder="Manual discount" className="rounded border p-2 text-sm" /><input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Discount reason" className="rounded border p-2 text-sm" /><input value={promotion} onChange={(e) => setPromotion(e.target.value)} placeholder="Promotion code" className="rounded border p-2 text-sm" /><input value={loyalty} onChange={(e) => setLoyalty(e.target.value)} placeholder="Loyalty points" className="rounded border p-2 text-sm" /></div>
            <div className="mt-4 flex justify-between"><h4 className="text-sm font-semibold">Payment editor</h4><button onClick={addPayment} className="rounded border px-2 py-1 text-xs">Add Payment</button></div>
            <div className="mt-2 grid gap-2">{payments.map((payment, i) => <div key={i} className="grid gap-2 rounded-xl border p-3 sm:grid-cols-2">
                <select value={payment.destination_id} onChange={(e) => { const d = destinations.find((x) => x.public_id === e.target.value); changePayment(i, { destination_id: e.target.value, method: d?.method ?? payment.method }); }} className="rounded border p-2 text-sm">{destinations.map((d) => <option key={d.public_id} value={d.public_id}>{d.display_name} · {d.method}</option>)}</select>
                <input value={payment.amount} onChange={(e) => changePayment(i, { amount: e.target.value })} placeholder="Amount" className="rounded border p-2 text-sm" /><input value={payment.transaction_reference ?? ''} onChange={(e) => changePayment(i, { transaction_reference: e.target.value })} placeholder="Safe reference" className="rounded border p-2 text-sm" />{payment.method === 'cash' && <input value={payment.cash_tendered ?? ''} onChange={(e) => changePayment(i, { cash_tendered: e.target.value })} placeholder="Cash tendered" className="rounded border p-2 text-sm" />}
            </div>)}</div>
            <div className="mt-4 flex gap-2"><button data-testid="server-totals" disabled={busy || cart.length === 0} onClick={() => void run(async () => setQuote(await api<Quote>('/internal/admin/pos/sales/quote', { method: 'POST', body: JSON.stringify({ ...sale, payments }) })))} className="rounded border px-3 py-2 text-sm font-semibold">Server totals</button><button data-testid="finalize-sale" disabled={busy || !quote || quote.remaining !== '0.00'} onClick={() => void run(async () => { const data = await api<Record<string, unknown>>('/internal/admin/pos/sales', { method: 'POST', body: JSON.stringify({ sale, payments }) }); setResult(data); setCart([]); setPayments([]); setQuote(null); })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Finalize sale</button></div>
            {quote && <div data-testid="authoritative-totals" className="mt-4 grid grid-cols-2 gap-2 rounded bg-slate-50 p-3 text-sm"><span>Invoice Total</span><strong>PKR {quote.payable}</strong><span>Payments</span><strong>PKR {quote.payments_total}</strong><span>Remaining</span><strong>PKR {quote.remaining}</strong><span>Cash change</span><strong>PKR {quote.cash_change}</strong></div>}
            {result && <div data-testid="sale-result" className="mt-4 rounded border border-emerald-200 bg-emerald-50 p-3 text-sm"><strong>Sale complete: {String(result.invoice_number ?? '')}</strong><p>Final PKR {String(result.final_bill ?? '')} · Discount PKR {String(result.discount ?? '0.00')} · Change PKR {String(result.cash_change_total ?? '0.00')}</p></div>}
        </section>
        <ReturnPanel busy={busy} run={run} />
    </div>;
}

function ReturnPanel({ busy, run }: { busy: boolean; run: (task: () => Promise<void>) => Promise<void> }) {
    const [invoiceId, setInvoiceId] = useState('');
    const [invoice, setInvoice] = useState<Record<string, unknown> | null>(null);
    const [saleId, setSaleId] = useState('');
    const [unitId, setUnitId] = useState('');
    const [result, setResult] = useState<Record<string, unknown> | null>(null);
    const lines = (invoice?.lines as Array<Record<string, unknown>> | undefined) ?? [];
    return <section className="rounded-2xl border bg-white p-5 xl:col-span-2"><h3 className="font-semibold">Return / refund</h3>
        <div className="mt-3 flex gap-2"><input value={invoiceId} onChange={(e) => setInvoiceId(e.target.value)} placeholder="Invoice public ID" className="flex-1 rounded border p-2 text-sm" /><button disabled={!invoiceId || busy} onClick={() => void run(async () => setInvoice(await api('/internal/admin/pos/invoices/' + invoiceId)))} className="rounded border px-3 py-2 text-sm">Load invoice</button></div>
        {lines.length > 0 && <div className="mt-3 grid gap-2 sm:grid-cols-4"><select value={saleId} onChange={(e) => { setSaleId(e.target.value); const line = lines.find((x) => x.sale_id === e.target.value); setUnitId(String(((line?.units as Array<Record<string, unknown>> | undefined) ?? [])[0]?.public_id ?? '')); }} className="rounded border p-2 text-sm"><option value="">Sale line</option>{lines.map((line) => <option key={String(line.sale_id)} value={String(line.sale_id)}>{String(line.name)}</option>)}</select><input value={unitId} onChange={(e) => setUnitId(e.target.value)} placeholder="Sold unit ID if serialized" className="rounded border p-2 text-sm" /><button disabled={!saleId || busy} onClick={() => void run(async () => setResult(await api('/internal/admin/pos/returns', { method: 'POST', body: JSON.stringify({ invoice_id: invoiceId, reason: 'POS accepted return', lines: [{ sale_id: saleId, quantity: 1, stock_unit_id: unitId || null, condition: 'returned', disposition: 'sellable' }] }) })))} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Accept return</button></div>}
        {result && <p className="mt-3 rounded bg-slate-50 p-3 text-sm">Accepted return {String(result.return_id ?? '')}. Refund remains bound to MT-2.20 original-tender rules.</p>}
    </section>;
}
