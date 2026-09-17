import { useEffect, useState } from 'react';

type Product = { id: string; name: string; code: string; qty: number; track_imei: boolean; reorder_policy?: { version: number; reorder_threshold: number; target_stock: number; active: boolean } | null };
type Supplier = { public_id: string; supplier_code: string; name: string; phone?: string | null; email?: string | null; is_active: boolean; version: number };
type OrderLine = { line_id: string; product_id: string; name: string; ordered_quantity: number; received_quantity: number; ordered_unit_cost: string; planned_landed_unit_cost: string };
type Order = { id: string; number: string; supplier_name: string; status: string; expected_at?: string | null; lines: OrderLine[] };
type StocktakeRow = { public_id: string; session_number: string; kind: string; status: string; version: number };
type TransferRow = { public_id: string; transfer_number: string; status: string; version: number; source_outlet_id: string; source_outlet_name: string; destination_outlet_id: string; destination_outlet_name: string };
type Recommendation = { product_id: string; name: string; stock_status: string; available: number; incoming: number; reorder_threshold: number; target_stock: number; recommended_quantity: number; action: string };
type Permissions = { canProcure: boolean; canStocktake: boolean; canApprove: boolean; canDispatch: boolean; canReceive: boolean; canBulk: boolean };
type Data = {
    outlet: { id: string; name: string }; permissions: Permissions; products: Product[];
    outlets: Array<{ id: string; name: string }>; products_by_outlet: Record<string, Product[]>;
    suppliers: Supplier[]; orders: Order[]; stocktakes: StocktakeRow[]; transfers: TransferRow[]; recommendations: Recommendation[];
};
type StocktakeSession = { stocktake_id: string; session_number: string; kind: string; status: string; version: number; lines: Array<{ line_id: string; product_id: string; product_name: string; tracked_serialized: boolean; baseline_quantity: number; iteration: number; expected_quantity: number | null; counted_quantity: number | null; variance: number | null; reason_code: string | null; line_version: number }> };
type TransferDetails = { transfer_id: string; transfer_number: string; source_outlet_id: string; destination_outlet_id: string; status: string; version: number; lines: Array<{ line_id: string; source_product_id: string; destination_product_id: string; tracked_serialized: boolean; quantity: number; received_quantity: number; rejected_quantity: number; version: number; units: Array<{ source_unit_id: string; status: string }> }> };

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

export default function PosStockControlWorkspace() {
    const [data, setData] = useState<Data | null>(null);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const load = async () => setData(await api<Data>('/internal/admin/pos/stock-control'));
    useEffect(() => { void load(); }, []);
    const run = async (task: () => Promise<void>) => {
        setBusy(true); setMessage('');
        try { await task(); await load(); } catch (e) { setMessage(e instanceof Error ? e.message : 'Request failed.'); } finally { setBusy(false); }
    };
    if (!data) return <section className="rounded-2xl border bg-white p-5 text-sm">Loading stock control…</section>;
    const p = data.permissions;
    return <section data-testid="stock-control" className="rounded-2xl border border-slate-300 bg-slate-50 p-4">
        <div className="mb-4"><p className="text-xs font-semibold uppercase tracking-wide text-slate-500">MT-4.5 Stock Control</p><h3 className="text-lg font-semibold">Procurement, counts, transfers & bulk tools</h3><p className="text-xs text-slate-500">{data.outlet.name} · backend validation remains authoritative</p></div>
        {message && <p role="alert" className="mb-4 rounded-lg border bg-white p-3 text-sm">{message}</p>}
        <div className="grid gap-5 xl:grid-cols-2">
            {p.canProcure && <Procurement data={data} busy={busy} run={run} />}
            {(p.canStocktake || p.canApprove) && <Stocktake data={data} busy={busy} run={run} />}
            {(p.canDispatch || p.canReceive) && <Transfers data={data} busy={busy} run={run} />}
            {p.canBulk && <Bulk busy={busy} run={run} />}
        </div>
    </section>;
}

function Procurement({ data, busy, run }: { data: Data; busy: boolean; run: (task: () => Promise<void>) => Promise<void> }) {
    const [code, setCode] = useState(''); const [name, setName] = useState('');
    const [supplier, setSupplier] = useState(''); const [product, setProduct] = useState('');
    const [qty, setQty] = useState('1'); const [cost, setCost] = useState('0.00');
    const [orderId, setOrderId] = useState(''); const [lineId, setLineId] = useState('');
    const [receiveQty, setReceiveQty] = useState('1');
    const [threshold, setThreshold] = useState('1'); const [target, setTarget] = useState('5');
    const order = data.orders.find((o) => o.id === orderId);
    return <div className="grid gap-4 rounded-xl border bg-white p-4">
        <h4 className="font-semibold">Suppliers & purchase orders</h4>
        <div className="grid grid-cols-2 gap-2"><input value={code} onChange={(e) => setCode(e.target.value)} placeholder="Supplier code" className="rounded border p-2 text-sm" /><input value={name} onChange={(e) => setName(e.target.value)} placeholder="Supplier name" className="rounded border p-2 text-sm" /></div>
        <button data-testid="supplier-create" disabled={busy || !code || !name} onClick={() => void run(async () => { await api('/internal/admin/pos/stock-control/suppliers', { method: 'POST', body: JSON.stringify({ supplier_code: code, name }) }); setCode(''); setName(''); })} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Add supplier</button>
        <div className="grid gap-2 sm:grid-cols-2"><select value={supplier} onChange={(e) => setSupplier(e.target.value)} className="rounded border p-2 text-sm"><option value="">Supplier</option>{data.suppliers.filter((s) => s.is_active).map((s) => <option key={s.public_id} value={s.public_id}>{s.supplier_code} · {s.name}</option>)}</select><select value={product} onChange={(e) => setProduct(e.target.value)} className="rounded border p-2 text-sm"><option value="">Product</option>{data.products.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select><input value={qty} onChange={(e) => setQty(e.target.value)} placeholder="Order qty" className="rounded border p-2 text-sm" /><input value={cost} onChange={(e) => setCost(e.target.value)} placeholder="Unit / landed cost" className="rounded border p-2 text-sm" /></div>
        <button data-testid="po-create" disabled={busy || !supplier || !product} onClick={() => void run(async () => { await api('/internal/admin/pos/stock-control/orders', { method: 'POST', body: JSON.stringify({ supplier_id: supplier, lines: [{ product_id: product, quantity: Number(qty), unit_cost: cost, landed_unit_cost: cost }] }) }); })} className="rounded border px-3 py-2 text-sm font-semibold">Create purchase order</button>
        <div className="grid gap-2 sm:grid-cols-3"><select value={orderId} onChange={(e) => { setOrderId(e.target.value); setLineId(''); }} className="rounded border p-2 text-sm"><option value="">Open PO</option>{data.orders.filter((o) => ['ordered','partially_received'].includes(o.status)).map((o) => <option key={o.id} value={o.id}>{o.number} · {o.supplier_name}</option>)}</select><select value={lineId} onChange={(e) => setLineId(e.target.value)} className="rounded border p-2 text-sm"><option value="">PO line</option>{order?.lines.map((l) => <option key={l.line_id} value={l.line_id}>{l.name} · {l.received_quantity}/{l.ordered_quantity}</option>)}</select><input value={receiveQty} onChange={(e) => setReceiveQty(e.target.value)} placeholder="Receive qty" className="rounded border p-2 text-sm" /></div>
        <button data-testid="po-receive" disabled={busy || !order || !lineId} onClick={() => void run(async () => { const l = order?.lines.find((x) => x.line_id === lineId); if (!l) return; await api('/internal/admin/pos/stock-control/orders/' + orderId + '/receive', { method: 'POST', body: JSON.stringify({ lines: [{ line_id: lineId, quantity: Number(receiveQty), unit_cost: l.ordered_unit_cost, landed_unit_cost: l.planned_landed_unit_cost }] }) }); })} className="rounded border px-3 py-2 text-sm font-semibold">Receive partial / complete</button>
        <div className="grid gap-2 sm:grid-cols-3"><select value={product} onChange={(e) => setProduct(e.target.value)} className="rounded border p-2 text-sm"><option value="">Reorder product</option>{data.products.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select><input value={threshold} onChange={(e) => setThreshold(e.target.value)} placeholder="Threshold" className="rounded border p-2 text-sm" /><input value={target} onChange={(e) => setTarget(e.target.value)} placeholder="Target stock" className="rounded border p-2 text-sm" /></div>
        <button disabled={busy || !product} onClick={() => void run(async () => { const selected = data.products.find((x) => x.id === product); await api('/internal/admin/pos/stock-control/reorder/' + product, { method: 'POST', body: JSON.stringify({ version: selected?.reorder_policy?.version, reorder_threshold: Number(threshold), target_stock: Number(target), active: true }) }); })} className="rounded border px-3 py-2 text-sm">Set reorder policy</button>
        {data.recommendations.length > 0 && <div className="rounded bg-amber-50 p-3 text-xs">{data.recommendations.map((r) => <p key={r.product_id}>{r.name}: {r.stock_status}, available {r.available}, incoming {r.incoming}, recommended {r.recommended_quantity}</p>)}</div>}
    </div>;
}

function Stocktake({ data, busy, run }: { data: Data; busy: boolean; run: (task: () => Promise<void>) => Promise<void> }) {
    const [kind, setKind] = useState('cycle'); const [product, setProduct] = useState('');
    const [id, setId] = useState(''); const [session, setSession] = useState<StocktakeSession | null>(null);
    const [count, setCount] = useState('0'); const [units, setUnits] = useState(''); const [reason, setReason] = useState('count_error');
    const loadSession = async (value: string) => { setId(value); setSession(value ? await api<StocktakeSession>('/internal/admin/pos/stock-control/stocktakes/' + value) : null); };
    const line = session?.lines.find((l) => l.counted_quantity === null) ?? session?.lines[0];
    return <div className="grid gap-3 rounded-xl border bg-white p-4">
        <h4 className="font-semibold">Stocktake / recount / approval</h4>
        <div className="grid grid-cols-2 gap-2"><select value={kind} onChange={(e) => setKind(e.target.value)} className="rounded border p-2 text-sm"><option value="cycle">Cycle count</option><option value="full">Full count</option></select><select disabled={kind === 'full'} value={product} onChange={(e) => setProduct(e.target.value)} className="rounded border p-2 text-sm"><option value="">Cycle product</option>{data.products.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select></div>
        <button data-testid="stocktake-start" disabled={busy || (kind === 'cycle' && !product)} onClick={() => void run(async () => { const result = await api<{ stocktake_id: string }>('/internal/admin/pos/stock-control/stocktakes', { method: 'POST', body: JSON.stringify({ kind, product_ids: kind === 'cycle' ? [product] : [] }) }); await loadSession(result.stocktake_id); })} className="rounded border px-3 py-2 text-sm font-semibold">Start stocktake</button>
        <select value={id} onChange={(e) => void loadSession(e.target.value)} className="rounded border p-2 text-sm"><option value="">Existing stocktake</option>{data.stocktakes.map((s) => <option key={s.public_id} value={s.public_id}>{s.session_number} · {s.status}</option>)}</select>
        {line && session && <div className="grid gap-2 rounded bg-slate-50 p-3 text-sm"><p><strong>{line.product_name}</strong> · baseline {line.baseline_quantity} · iteration {line.iteration}</p>{line.tracked_serialized ? <input value={units} onChange={(e) => setUnits(e.target.value)} placeholder="Scan/paste unit IDs, comma-separated" className="rounded border bg-white p-2" /> : <input value={count} onChange={(e) => setCount(e.target.value)} placeholder="Counted quantity" className="rounded border bg-white p-2" />}<select value={reason} onChange={(e) => setReason(e.target.value)} className="rounded border bg-white p-2"><option value="count_error">Count error</option><option value="damage">Damage</option><option value="loss">Loss</option><option value="found_stock">Found stock</option><option value="misplacement">Misplacement</option><option value="other">Other</option></select><button disabled={busy || session.status !== 'counting'} onClick={() => void run(async () => { await api('/internal/admin/pos/stock-control/stocktakes/' + id + '/lines/' + line.line_id, { method: 'POST', body: JSON.stringify({ line_version: line.line_version, counted_quantity: line.tracked_serialized ? null : Number(count), unit_ids: line.tracked_serialized ? units.split(',').map((x) => x.trim()).filter(Boolean) : null, reason_code: reason }) }); await loadSession(id); })} className="rounded bg-slate-950 px-3 py-2 font-semibold text-white">Submit count</button></div>}
        {session?.status === 'submitted' && <div className="flex gap-2"><button disabled={!data.permissions.canApprove || busy} onClick={() => void run(async () => { await api('/internal/admin/pos/stock-control/stocktakes/' + id + '/approve', { method: 'POST', body: JSON.stringify({ session_version: session.version }) }); await loadSession(id); })} className="rounded border px-3 py-2 text-sm">Approve</button>{session.lines[0] && <button disabled={!data.permissions.canApprove || busy} onClick={() => void run(async () => { await api('/internal/admin/pos/stock-control/stocktakes/' + id + '/recount', { method: 'POST', body: JSON.stringify({ session_version: session.version, line_id: session.lines[0].line_id, reason: 'POS recount requested' }) }); await loadSession(id); })} className="rounded border px-3 py-2 text-sm">Request recount</button>}</div>}
    </div>;
}

function Transfers({ data, busy, run }: { data: Data; busy: boolean; run: (task: () => Promise<void>) => Promise<void> }) {
    const [destination, setDestination] = useState(''); const [sourceProduct, setSourceProduct] = useState(''); const [destProduct, setDestProduct] = useState('');
    const [qty, setQty] = useState('1'); const [unitIds, setUnitIds] = useState('');
    const [id, setId] = useState(''); const [details, setDetails] = useState<TransferDetails | null>(null);
    const loadTransfer = async (value: string) => { setId(value); setDetails(value ? await api<TransferDetails>('/internal/admin/pos/stock-control/transfers/' + value) : null); };
    const src = data.products.find((x) => x.id === sourceProduct);
    const destProducts = destination ? (data.products_by_outlet[destination] ?? []) : [];
    return <div className="grid gap-3 rounded-xl border bg-white p-4">
        <h4 className="font-semibold">Inter-outlet transfers</h4>
        {data.permissions.canDispatch && <><select value={destination} onChange={(e) => { setDestination(e.target.value); setDestProduct(''); }} className="rounded border p-2 text-sm"><option value="">Destination outlet</option>{data.outlets.filter((o) => o.id !== data.outlet.id).map((o) => <option key={o.id} value={o.id}>{o.name}</option>)}</select><div className="grid grid-cols-2 gap-2"><select value={sourceProduct} onChange={(e) => setSourceProduct(e.target.value)} className="rounded border p-2 text-sm"><option value="">Source product</option>{data.products.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select><select value={destProduct} onChange={(e) => setDestProduct(e.target.value)} className="rounded border p-2 text-sm"><option value="">Destination product</option>{destProducts.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select></div>{src?.track_imei ? <input value={unitIds} onChange={(e) => setUnitIds(e.target.value)} placeholder="Scan/paste source unit IDs" className="rounded border p-2 text-sm" /> : <input value={qty} onChange={(e) => setQty(e.target.value)} placeholder="Transfer quantity" className="rounded border p-2 text-sm" />}<button data-testid="transfer-create" disabled={busy || !destination || !sourceProduct || !destProduct} onClick={() => void run(async () => { const result = await api<{ transfer_id: string }>('/internal/admin/pos/stock-control/transfers', { method: 'POST', body: JSON.stringify({ destination_outlet_id: destination, lines: [{ source_product_id: sourceProduct, destination_product_id: destProduct, quantity: src?.track_imei ? null : Number(qty), unit_ids: src?.track_imei ? unitIds.split(',').map((x) => x.trim()).filter(Boolean) : [] }] }) }); await loadTransfer(result.transfer_id); })} className="rounded border px-3 py-2 text-sm font-semibold">Create draft transfer</button></>}
        <select value={id} onChange={(e) => void loadTransfer(e.target.value)} className="rounded border p-2 text-sm"><option value="">Existing transfer</option>{data.transfers.map((t) => <option key={t.public_id} value={t.public_id}>{t.transfer_number} · {t.status} · {t.source_outlet_name} → {t.destination_outlet_name}</option>)}</select>
        {details && <div className="grid gap-2 rounded bg-slate-50 p-3 text-sm"><p>{details.transfer_number} · {details.status} · version {details.version}</p>{details.status === 'draft' && data.permissions.canDispatch && <button onClick={() => void run(async () => { await api('/internal/admin/pos/stock-control/transfers/' + id + '/dispatch', { method: 'POST', body: JSON.stringify({ transfer_version: details.version }) }); await loadTransfer(id); })} className="rounded border px-3 py-2">Dispatch</button>}{['in_transit','partially_received'].includes(details.status) && data.permissions.canReceive && details.lines[0] && <div className="grid gap-2"><input value={qty} onChange={(e) => setQty(e.target.value)} placeholder="Receive qty" className="rounded border bg-white p-2" /><button onClick={() => void run(async () => { const l = details.lines[0]; await api('/internal/admin/pos/stock-control/transfers/' + id + '/receive', { method: 'POST', body: JSON.stringify({ transfer_version: details.version, lines: [{ line_id: l.line_id, receive_quantity: l.tracked_serialized ? null : Number(qty), reject_quantity: 0, receive_unit_ids: l.tracked_serialized ? l.units.filter((u) => u.status === 'in_transit').map((u) => u.source_unit_id) : null, reject_unit_ids: l.tracked_serialized ? [] : null }] }) }); await loadTransfer(id); })} className="rounded border px-3 py-2">Receive selected balance</button></div>}</div>}
    </div>;
}

function Bulk({ busy, run }: { busy: boolean; run: (task: () => Promise<void>) => Promise<void> }) {
    const [dataset, setDataset] = useState('inventory'); const [document, setDocument] = useState(''); const [preview, setPreview] = useState<Record<string, unknown> | null>(null); const [recovery, setRecovery] = useState('whole_batch');
    const exportFile = async () => { const result = await api<{ document: string }>('/internal/admin/pos/stock-control/bulk/export', { method: 'POST', body: JSON.stringify({ dataset, format: 'csv' }) }); const blob = new Blob([String(result.document)], { type: 'text/csv' }); const url = URL.createObjectURL(blob); const a = window.document.createElement('a'); a.href = url; a.download = dataset + '-export.csv'; a.click(); URL.revokeObjectURL(url); };
    return <div className="grid gap-3 rounded-xl border bg-white p-4">
        <h4 className="font-semibold">Validated bulk operations</h4><div className="grid grid-cols-2 gap-2"><select value={dataset} onChange={(e) => setDataset(e.target.value)} className="rounded border p-2 text-sm"><option value="catalogue">Catalogue</option><option value="price">Price</option><option value="inventory">Inventory</option></select><select value={recovery} onChange={(e) => setRecovery(e.target.value)} className="rounded border p-2 text-sm"><option value="whole_batch">Whole batch</option><option value="row">Row recovery</option></select></div><textarea value={document} onChange={(e) => setDocument(e.target.value)} placeholder="Paste CSV including headers. Preview is mandatory before import." rows={7} className="rounded border p-2 font-mono text-xs" /><div className="flex flex-wrap gap-2"><button data-testid="bulk-preview" disabled={busy || !document} onClick={() => void run(async () => setPreview(await api('/internal/admin/pos/stock-control/bulk/preview', { method: 'POST', body: JSON.stringify({ dataset, format: 'csv', document }) })))} className="rounded border px-3 py-2 text-sm">Preview</button><button disabled={busy || !preview} onClick={() => void run(async () => setPreview(await api('/internal/admin/pos/stock-control/bulk/import', { method: 'POST', body: JSON.stringify({ dataset, format: 'csv', document, recovery }) })))} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Import validated rows</button><button disabled={busy} onClick={() => void run(exportFile)} className="rounded border px-3 py-2 text-sm">Export CSV</button></div>{preview && <pre data-testid="bulk-result" className="max-h-56 overflow-auto rounded bg-slate-950 p-3 text-xs text-white">{JSON.stringify(preview, null, 2)}</pre>}
    </div>;
}
